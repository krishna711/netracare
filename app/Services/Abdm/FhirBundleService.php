<?php

namespace App\Services\Abdm;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\OptometristWorksheet;
use App\Models\Patient;
use App\Models\Setting;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class FhirBundleService
{
    protected AbdmClient $client;

    public function __construct(AbdmClient $client)
    {
        $this->client = $client;
    }

    /**
     * Build an NRCES compliant ABDM FHIR R4 OP Consultation Document Bundle.
     * Profile: https://nrces.in/ndhm/fhir/r4/StructureDefinition/OPConsultRecord
     */
    public function buildOpConsultationBundle(Appointment $appointment): array
    {
        $patient = $appointment->patient;
        if (!$patient) {
            throw new Exception("Appointment #{$appointment->id} has no associated patient.");
        }

        $consultation = $appointment->consultation;
        if (!$consultation) {
            try {
                $consultation = Consultation::where('appointment_id', $appointment->id)->first();
            } catch (\Throwable $e) {}
        }
        $doctor = $appointment->doctor;
        $optometrist = null;
        try {
            $optometrist = OptometristWorksheet::where('appointment_id', $appointment->id)->first();
        } catch (\Throwable $e) {}

        $bundleId = (string) Str::uuid();
        $now = now()->toISOString();
        $hipId = $this->client->getHipId() ?: 'NETRA_CARE_HIP_01';
        $orgName = 'Netrika Netralaya';
        try {
            $val = Setting::where('key', 'hospital_name')->value('value');
            if (!empty($val)) $orgName = $val;
        } catch (\Throwable $e) {}

        // Resource UUIDs
        $compositionId = (string) Str::uuid();
        $patientId = (string) Str::uuid();
        $practitionerId = (string) Str::uuid();
        $organizationId = (string) Str::uuid();
        $encounterId = (string) Str::uuid();

        // 1. Patient Resource
        $patientResource = $this->buildPatientResource($patientId, $patient);

        // 2. Practitioner Resource
        $practitionerResource = $this->buildPractitionerResource($practitionerId, $doctor);

        // 3. Organization Resource
        $organizationResource = $this->buildOrganizationResource($organizationId, $orgName, $hipId);

        // 4. Encounter Resource
        $encounterResource = $this->buildEncounterResource($encounterId, $patientId, $appointment);

        // 5. Conditions (Diagnoses)
        $conditionEntries = [];
        $conditionReferences = [];
        $diagnoses = $this->parseList($consultation?->diagnosis);
        if (empty($diagnoses)) {
            $diagnoses = ['Ophthalmic Evaluation'];
        }
        foreach ($diagnoses as $diag) {
            $condId = (string) Str::uuid();
            $conditionEntries[] = [
                'fullUrl' => "urn:uuid:{$condId}",
                'resource' => $this->buildConditionResource($condId, $patientId, $encounterId, $diag),
            ];
            $conditionReferences[] = ['reference' => "urn:uuid:{$condId}", 'display' => $diag];
        }

        // 6. Medication Requests (Prescriptions)
        $medicationEntries = [];
        $medicationReferences = [];
        $medicines = $this->parsePrescriptions($consultation?->prescription);
        foreach ($medicines as $med) {
            $medId = (string) Str::uuid();
            $medicationEntries[] = [
                'fullUrl' => "urn:uuid:{$medId}",
                'resource' => $this->buildMedicationRequestResource(
                    $medId,
                    $patientId,
                    $practitionerId,
                    $med,
                    $appointment->appointment_time ? Carbon::parse($appointment->appointment_time)->toISOString() : $now
                ),
            ];
            $medicationReferences[] = [
                'reference' => "urn:uuid:{$medId}",
                'display' => $med['medicine'] . (!empty($med['type']) ? " ({$med['type']})" : ''),
            ];
        }

        // 7. Eye Examination Observation
        $observationId = (string) Str::uuid();
        $observationResource = $this->buildEyeExaminationObservation(
            $observationId,
            $patientId,
            $encounterId,
            $consultation,
            $optometrist
        );
        $observationEntry = [
            'fullUrl' => "urn:uuid:{$observationId}",
            'resource' => $observationResource,
        ];

        // 8. Care Plan (Advice & Followup)
        $carePlanId = (string) Str::uuid();
        $carePlanResource = $this->buildCarePlanResource(
            $carePlanId,
            $patientId,
            $consultation?->advice,
            $consultation?->followup_date
        );
        $carePlanEntry = [
            'fullUrl' => "urn:uuid:{$carePlanId}",
            'resource' => $carePlanResource,
        ];

        // 9. Sections for Composition
        $sections = [];

        // Chief complaints section
        $complaints = $this->parseList($consultation?->complaint);
        if (!empty($complaints)) {
            $compDiv = "<ul>" . implode('', array_map(fn($c) => "<li>" . htmlspecialchars($c) . "</li>", $complaints)) . "</ul>";
            $sections[] = [
                'title' => 'Chief Complaints',
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://snomed.info/sct',
                            'code' => '422843007',
                            'display' => 'Chief complaint section',
                        ],
                    ],
                ],
                'text' => [
                    'status' => 'generated',
                    'div' => "<div xmlns=\"http://www.w3.org/1999/xhtml\">{$compDiv}</div>",
                ],
            ];
        }

        // Medical history section
        $history = $this->parseList($consultation?->previous_history);
        if (!empty($history)) {
            $histDiv = "<ul>" . implode('', array_map(fn($h) => "<li>" . htmlspecialchars($h) . "</li>", $history)) . "</ul>";
            $sections[] = [
                'title' => 'Medical History',
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://snomed.info/sct',
                            'code' => '371529009',
                            'display' => 'History and physical report',
                        ],
                    ],
                ],
                'text' => [
                    'status' => 'generated',
                    'div' => "<div xmlns=\"http://www.w3.org/1999/xhtml\">{$histDiv}</div>",
                ],
            ];
        }

        // Examination / Findings section
        $examDiv = "<p><strong>Ophthalmic Examination:</strong><br/>";
        $examDiv .= "RE-A/S: " . htmlspecialchars($consultation?->prescription_text_re ?? 'NAD') . "<br/>";
        $examDiv .= "LE-A/S: " . htmlspecialchars($consultation?->tests_le ?? 'NAD') . "<br/>";
        $examDiv .= "RE-Fundus: " . htmlspecialchars($consultation?->prescription_text ?? 'Wnl') . "<br/>";
        $examDiv .= "LE-Fundus: " . htmlspecialchars($consultation?->tests ?? 'Wnl') . "</p>";
        $sections[] = [
            'title' => 'Physical Examination / Eye Findings',
            'code' => [
                'coding' => [
                    [
                        'system' => 'http://snomed.info/sct',
                        'code' => '425044008',
                        'display' => 'Physical exam section',
                    ],
                ],
            ],
            'text' => [
                'status' => 'generated',
                'div' => "<div xmlns=\"http://www.w3.org/1999/xhtml\">{$examDiv}</div>",
            ],
            'entry' => [
                ['reference' => "urn:uuid:{$observationId}"],
            ],
        ];

        // Diagnosis section
        $diagDiv = "<ul>" . implode('', array_map(fn($d) => "<li>" . htmlspecialchars($d) . "</li>", $diagnoses)) . "</ul>";
        $sections[] = [
            'title' => 'Diagnosis',
            'code' => [
                'coding' => [
                    [
                        'system' => 'http://snomed.info/sct',
                        'code' => '4241000179101',
                        'display' => 'Diagnosis section',
                    ],
                ],
            ],
            'text' => [
                'status' => 'generated',
                'div' => "<div xmlns=\"http://www.w3.org/1999/xhtml\">{$diagDiv}</div>",
            ],
            'entry' => $conditionReferences,
        ];

        // Medications section
        if (!empty($medicationReferences)) {
            $medsList = [];
            foreach ($medicines as $m) {
                $medsList[] = "<li><strong>" . htmlspecialchars($m['medicine']) . "</strong> (" . htmlspecialchars($m['type']) . ") - " . htmlspecialchars($m['frequency']) . " [" . htmlspecialchars($m['time']) . "] for " . htmlspecialchars($m['duration']) . "</li>";
            }
            $medDiv = "<ul>" . implode('', $medsList) . "</ul>";
            $sections[] = [
                'title' => 'Medications / Treatment',
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://snomed.info/sct',
                            'code' => '721912009',
                            'display' => 'Medication summary document',
                        ],
                    ],
                ],
                'text' => [
                    'status' => 'generated',
                    'div' => "<div xmlns=\"http://www.w3.org/1999/xhtml\">{$medDiv}</div>",
                ],
                'entry' => $medicationReferences,
            ];
        }

        // Follow up & Advice section
        $adviceText = $consultation?->advice ?? 'General eye care';
        $followupText = $consultation?->followup_date ? Carbon::parse($consultation->followup_date)->format('d-M-Y') : 'As needed';
        $sections[] = [
            'title' => 'Follow up & Advice',
            'code' => [
                'coding' => [
                    [
                        'system' => 'http://snomed.info/sct',
                        'code' => '736271009',
                        'display' => 'Outpatient care plan',
                    ],
                ],
            ],
            'text' => [
                'status' => 'generated',
                'div' => "<div xmlns=\"http://www.w3.org/1999/xhtml\"><p>Advice: " . htmlspecialchars($adviceText) . "</p><p>Next Follow-up: " . htmlspecialchars($followupText) . "</p></div>",
            ],
            'entry' => [
                ['reference' => "urn:uuid:{$carePlanId}"],
            ],
        ];

        // 10. Composition Resource (MUST be the FIRST entry)
        $compositionResource = [
            'resourceType' => 'Composition',
            'id' => $compositionId,
            'meta' => [
                'versionId' => '1',
                'lastUpdated' => $now,
                'profile' => [
                    'https://nrces.in/ndhm/fhir/r4/StructureDefinition/Composition',
                ],
            ],
            'language' => 'en',
            'identifier' => [
                'system' => 'https://netrika.in/composition',
                'value' => "COMP-OPD-{$appointment->id}",
            ],
            'status' => 'final',
            'type' => [
                'coding' => [
                    [
                        'system' => 'http://snomed.info/sct',
                        'code' => '371530004',
                        'display' => 'Clinical consultation report',
                    ],
                ],
                'text' => 'Outpatient Consultation Note',
            ],
            'subject' => [
                'reference' => "urn:uuid:{$patientId}",
                'display' => $patient->name,
            ],
            'encounter' => [
                'reference' => "urn:uuid:{$encounterId}",
            ],
            'date' => $now,
            'author' => [
                [
                    'reference' => "urn:uuid:{$practitionerId}",
                    'display' => $doctor?->name ?? 'Specialist Doctor',
                ],
            ],
            'title' => 'Consultation Record',
            'custodian' => [
                'reference' => "urn:uuid:{$organizationId}",
                'display' => $orgName,
            ],
            'section' => $sections,
        ];

        // Assemble Complete FHIR Bundle
        $entries = [
            ['fullUrl' => "urn:uuid:{$compositionId}", 'resource' => $compositionResource],
            ['fullUrl' => "urn:uuid:{$patientId}", 'resource' => $patientResource],
            ['fullUrl' => "urn:uuid:{$practitionerId}", 'resource' => $practitionerResource],
            ['fullUrl' => "urn:uuid:{$organizationId}", 'resource' => $organizationResource],
            ['fullUrl' => "urn:uuid:{$encounterId}", 'resource' => $encounterResource],
        ];

        // Merge condition, medication, observation, care plan entries
        foreach ($conditionEntries as $c) {
            $entries[] = $c;
        }
        foreach ($medicationEntries as $m) {
            $entries[] = $m;
        }
        $entries[] = $observationEntry;
        $entries[] = $carePlanEntry;

        return [
            'resourceType' => 'Bundle',
            'id' => $bundleId,
            'meta' => [
                'versionId' => '1',
                'lastUpdated' => $now,
                'profile' => [
                    'https://nrces.in/ndhm/fhir/r4/StructureDefinition/OPConsultRecord',
                ],
            ],
            'identifier' => [
                'system' => 'http://hip.in',
                'value' => "BUNDLE-{$bundleId}",
            ],
            'type' => 'document',
            'timestamp' => $now,
            'entry' => $entries,
        ];
    }

    /**
     * Build an NRCES compliant ABDM FHIR R4 Prescription Document Bundle.
     * Profile: https://nrces.in/ndhm/fhir/r4/StructureDefinition/PrescriptionRecord
     */
    public function buildPrescriptionBundle(Appointment $appointment): array
    {
        $patient = $appointment->patient;
        if (!$patient) {
            throw new Exception("Appointment #{$appointment->id} has no associated patient.");
        }

        $consultation = $appointment->consultation;
        if (!$consultation) {
            try {
                $consultation = Consultation::where('appointment_id', $appointment->id)->first();
            } catch (\Throwable $e) {}
        }
        $doctor = $appointment->doctor;

        $bundleId = (string) Str::uuid();
        $now = now()->toISOString();
        $hipId = $this->client->getHipId() ?: 'NETRA_CARE_HIP_01';
        $orgName = 'Netrika Netralaya';
        try {
            $val = Setting::where('key', 'hospital_name')->value('value');
            if (!empty($val)) $orgName = $val;
        } catch (\Throwable $e) {}

        $compositionId = (string) Str::uuid();
        $patientId = (string) Str::uuid();
        $practitionerId = (string) Str::uuid();
        $organizationId = (string) Str::uuid();

        $patientResource = $this->buildPatientResource($patientId, $patient);
        $practitionerResource = $this->buildPractitionerResource($practitionerId, $doctor);
        $organizationResource = $this->buildOrganizationResource($organizationId, $orgName, $hipId);

        $medicationEntries = [];
        $medicationReferences = [];
        $medicines = $this->parsePrescriptions($consultation?->prescription);
        foreach ($medicines as $med) {
            $medId = (string) Str::uuid();
            $medicationEntries[] = [
                'fullUrl' => "urn:uuid:{$medId}",
                'resource' => $this->buildMedicationRequestResource(
                    $medId,
                    $patientId,
                    $practitionerId,
                    $med,
                    $appointment->appointment_time ? Carbon::parse($appointment->appointment_time)->toISOString() : $now
                ),
            ];
            $medicationReferences[] = [
                'reference' => "urn:uuid:{$medId}",
                'display' => $med['medicine'],
            ];
        }

        $compositionResource = [
            'resourceType' => 'Composition',
            'id' => $compositionId,
            'meta' => [
                'versionId' => '1',
                'lastUpdated' => $now,
                'profile' => [
                    'https://nrces.in/ndhm/fhir/r4/StructureDefinition/Composition',
                ],
            ],
            'language' => 'en',
            'identifier' => [
                'system' => 'https://netrika.in/composition',
                'value' => "PRES-OPD-{$appointment->id}",
            ],
            'status' => 'final',
            'type' => [
                'coding' => [
                    [
                        'system' => 'http://snomed.info/sct',
                        'code' => '721912009',
                        'display' => 'Medication summary document',
                    ],
                ],
                'text' => 'Prescription Record',
            ],
            'subject' => [
                'reference' => "urn:uuid:{$patientId}",
                'display' => $patient->name,
            ],
            'date' => $now,
            'author' => [
                [
                    'reference' => "urn:uuid:{$practitionerId}",
                    'display' => $doctor?->name ?? 'Specialist Doctor',
                ],
            ],
            'title' => 'Prescription',
            'custodian' => [
                'reference' => "urn:uuid:{$organizationId}",
                'display' => $orgName,
            ],
            'section' => [
                [
                    'title' => 'Prescribed Medications',
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://snomed.info/sct',
                                'code' => '721912009',
                                'display' => 'Medication summary document',
                            ],
                        ],
                    ],
                    'entry' => $medicationReferences,
                ],
            ],
        ];

        $entries = [
            ['fullUrl' => "urn:uuid:{$compositionId}", 'resource' => $compositionResource],
            ['fullUrl' => "urn:uuid:{$patientId}", 'resource' => $patientResource],
            ['fullUrl' => "urn:uuid:{$practitionerId}", 'resource' => $practitionerResource],
            ['fullUrl' => "urn:uuid:{$organizationId}", 'resource' => $organizationResource],
        ];
        foreach ($medicationEntries as $m) {
            $entries[] = $m;
        }

        return [
            'resourceType' => 'Bundle',
            'id' => $bundleId,
            'meta' => [
                'versionId' => '1',
                'lastUpdated' => $now,
                'profile' => [
                    'https://nrces.in/ndhm/fhir/r4/StructureDefinition/PrescriptionRecord',
                ],
            ],
            'identifier' => [
                'system' => 'http://hip.in',
                'value' => "PRES-BUNDLE-{$bundleId}",
            ],
            'type' => 'document',
            'timestamp' => $now,
            'entry' => $entries,
        ];
    }

    /**
     * Build Patient Resource.
     */
    protected function buildPatientResource(string $id, Patient $patient): array
    {
        $identifiers = [
            [
                'type' => [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                            'code' => 'MR',
                            'display' => 'Medical record number',
                        ],
                    ],
                ],
                'system' => 'https://netrika.in/patient-id',
                'value' => "P-{$patient->id}",
            ],
        ];

        if (!empty($patient->abha_number)) {
            $identifiers[] = [
                'type' => [
                    'coding' => [
                        [
                            'system' => 'https://nrces.in/ndhm/fhir/r4/StructureDefinition/identifier-type-code',
                            'code' => 'ABHA',
                            'display' => 'ABHA Number',
                        ],
                    ],
                ],
                'system' => 'https://healthid.ndhm.gov.in',
                'value' => $patient->formatted_abha_number ?: $patient->abha_number,
            ];
        }

        if (!empty($patient->abha_address)) {
            $identifiers[] = [
                'type' => [
                    'coding' => [
                        [
                            'system' => 'https://nrces.in/ndhm/fhir/r4/StructureDefinition/identifier-type-code',
                            'code' => 'ABHA-ADDRESS',
                            'display' => 'ABHA Address',
                        ],
                    ],
                ],
                'system' => 'https://ndhm.in/phr',
                'value' => $patient->abha_address,
            ];
        }

        $gender = strtolower(trim((string) $patient->sex));
        if (!in_array($gender, ['male', 'female', 'other'])) {
            $gender = 'unknown';
        }

        $res = [
            'resourceType' => 'Patient',
            'id' => $id,
            'meta' => [
                'profile' => [
                    'https://nrces.in/ndhm/fhir/r4/StructureDefinition/Patient',
                ],
            ],
            'identifier' => $identifiers,
            'name' => [
                [
                    'text' => $patient->name,
                ],
            ],
            'gender' => $gender,
        ];

        if (!empty($patient->mobile)) {
            $res['telecom'] = [
                [
                    'system' => 'phone',
                    'value' => $patient->mobile,
                    'use' => 'mobile',
                ],
            ];
        }

        if (!empty($patient->address)) {
            $res['address'] = [
                [
                    'text' => $patient->address,
                ],
            ];
        }

        return $res;
    }

    /**
     * Build Practitioner Resource.
     */
    protected function buildPractitionerResource(string $id, ?Doctor $doctor): array
    {
        return [
            'resourceType' => 'Practitioner',
            'id' => $id,
            'meta' => [
                'profile' => [
                    'https://nrces.in/ndhm/fhir/r4/StructureDefinition/Practitioner',
                ],
            ],
            'identifier' => [
                [
                    'type' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                                'code' => 'MD',
                                'display' => 'Medical License number',
                            ],
                        ],
                    ],
                    'system' => 'https://doctor.ndhm.gov.in',
                    'value' => "DOC-" . ($doctor?->id ?? '01'),
                ],
            ],
            'name' => [
                [
                    'text' => $doctor ? "Dr. {$doctor->name}" : 'Attending Ophthalmologist',
                ],
            ],
        ];
    }

    /**
     * Build Organization Resource.
     */
    protected function buildOrganizationResource(string $id, string $name, string $hipId): array
    {
        return [
            'resourceType' => 'Organization',
            'id' => $id,
            'meta' => [
                'profile' => [
                    'https://nrces.in/ndhm/fhir/r4/StructureDefinition/Organization',
                ],
            ],
            'identifier' => [
                [
                    'type' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                                'code' => 'PRN',
                                'display' => 'Provider number',
                            ],
                        ],
                    ],
                    'system' => 'https://facility.ndhm.gov.in',
                    'value' => $hipId,
                ],
            ],
            'name' => $name,
        ];
    }

    /**
     * Build Encounter Resource.
     */
    protected function buildEncounterResource(string $id, string $patientId, Appointment $appointment): array
    {
        $startTime = $appointment->appointment_time 
            ? Carbon::parse($appointment->appointment_time)->toISOString() 
            : now()->toISOString();

        return [
            'resourceType' => 'Encounter',
            'id' => $id,
            'meta' => [
                'profile' => [
                    'https://nrces.in/ndhm/fhir/r4/StructureDefinition/Encounter',
                ],
            ],
            'identifier' => [
                [
                    'system' => 'https://netrika.in/encounters',
                    'value' => "OPD-APP-{$appointment->id}",
                ],
            ],
            'status' => 'finished',
            'class' => [
                'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code' => 'AMB',
                'display' => 'ambulatory',
            ],
            'subject' => [
                'reference' => "urn:uuid:{$patientId}",
            ],
            'period' => [
                'start' => $startTime,
            ],
        ];
    }

    /**
     * Build Condition (Diagnosis) Resource.
     */
    protected function buildConditionResource(string $id, string $patientId, string $encounterId, string $diagnosis): array
    {
        $snomedMap = [
            'refractive error' => ['code' => '279039007', 'display' => 'Disorder of refraction (disorder)'],
            'myopia'           => ['code' => '57190000', 'display' => 'Myopia (disorder)'],
            'hypermetropia'    => ['code' => '38101003', 'display' => 'Hypermetropia (disorder)'],
            'astigmatism'      => ['code' => '82649003', 'display' => 'Astigmatism (disorder)'],
            'presbyopia'       => ['code' => '44069004', 'display' => 'Presbyopia (disorder)'],
            'cataract'         => ['code' => '193570009', 'display' => 'Cataract (disorder)'],
            'glaucoma'         => ['code' => '23986001', 'display' => 'Glaucoma (disorder)'],
            'conjunctivitis'   => ['code' => '9826008', 'display' => 'Conjunctivitis (disorder)'],
            'dry eye'          => ['code' => '39420005', 'display' => 'Dry eyes (finding)'],
        ];

        $lower = strtolower(trim($diagnosis));
        $snomed = $snomedMap[$lower] ?? null;

        $coding = [];
        if ($snomed) {
            $coding[] = [
                'system' => 'http://snomed.info/sct',
                'code' => $snomed['code'],
                'display' => $snomed['display'],
            ];
        }

        return [
            'resourceType' => 'Condition',
            'id' => $id,
            'meta' => [
                'profile' => [
                    'https://nrces.in/ndhm/fhir/r4/StructureDefinition/Condition',
                ],
            ],
            'clinicalStatus' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                        'code' => 'active',
                        'display' => 'Active',
                    ],
                ],
            ],
            'code' => [
                'coding' => $coding,
                'text' => $diagnosis,
            ],
            'subject' => [
                'reference' => "urn:uuid:{$patientId}",
            ],
            'encounter' => [
                'reference' => "urn:uuid:{$encounterId}",
            ],
        ];
    }

    /**
     * Build MedicationRequest Resource.
     */
    protected function buildMedicationRequestResource(
        string $id,
        string $patientId,
        string $practitionerId,
        array $med,
        string $authoredOn
    ): array {
        $dosageText = trim("{$med['type']}: {$med['frequency']} - {$med['time']} for {$med['duration']}", ": -");

        return [
            'resourceType' => 'MedicationRequest',
            'id' => $id,
            'meta' => [
                'profile' => [
                    'https://nrces.in/ndhm/fhir/r4/StructureDefinition/MedicationRequest',
                ],
            ],
            'status' => 'active',
            'intent' => 'order',
            'medicationCodeableConcept' => [
                'text' => $med['medicine'] . (!empty($med['type']) ? " ({$med['type']})" : ''),
            ],
            'subject' => [
                'reference' => "urn:uuid:{$patientId}",
            ],
            'authoredOn' => $authoredOn,
            'requester' => [
                'reference' => "urn:uuid:{$practitionerId}",
            ],
            'dosageInstruction' => [
                [
                    'text' => $dosageText,
                    'timing' => [
                        'code' => [
                            'text' => $med['frequency'] ?: 'As directed',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build Eye Examination Observation Resource.
     */
    protected function buildEyeExaminationObservation(
        string $id,
        string $patientId,
        string $encounterId,
        ?Consultation $consultation,
        ?OptometristWorksheet $opt
    ): array {
        $components = [];

        if (!empty($consultation?->prescription_text_re)) {
            $components[] = [
                'code' => ['text' => 'Right Eye Anterior Segment (RE-A/S)'],
                'valueString' => $consultation->prescription_text_re,
            ];
        }

        if (!empty($consultation?->tests_le)) {
            $components[] = [
                'code' => ['text' => 'Left Eye Anterior Segment (LE-A/S)'],
                'valueString' => $consultation->tests_le,
            ];
        }

        if (!empty($consultation?->prescription_text)) {
            $components[] = [
                'code' => ['text' => 'Right Eye Fundus (RE-Fundus)'],
                'valueString' => $consultation->prescription_text,
            ];
        }

        if (!empty($consultation?->tests)) {
            $components[] = [
                'code' => ['text' => 'Left Eye Fundus (LE-Fundus)'],
                'valueString' => $consultation->tests,
            ];
        }

        // Add Optometry refraction values if available
        if ($opt) {
            if ($opt->fp_re_sph || $opt->fp_re_cyl || $opt->fp_re_bcva) {
                $components[] = [
                    'code' => ['text' => 'Final Prescription Right Eye (Sph/Cyl/Axis/BCVA)'],
                    'valueString' => "Sph: {$opt->fp_re_sph}, Cyl: {$opt->fp_re_cyl}, Axis: {$opt->fp_re_axis}, BCVA: {$opt->fp_re_bcva}",
                ];
            }
            if ($opt->fp_le_sph || $opt->fp_le_cyl || $opt->fp_le_bcva) {
                $components[] = [
                    'code' => ['text' => 'Final Prescription Left Eye (Sph/Cyl/Axis/BCVA)'],
                    'valueString' => "Sph: {$opt->fp_le_sph}, Cyl: {$opt->fp_le_cyl}, Axis: {$opt->fp_le_axis}, BCVA: {$opt->fp_le_bcva}",
                ];
            }
        }

        return [
            'resourceType' => 'Observation',
            'id' => $id,
            'meta' => [
                'profile' => [
                    'https://nrces.in/ndhm/fhir/r4/StructureDefinition/Observation',
                ],
            ],
            'status' => 'final',
            'code' => [
                'coding' => [
                    [
                        'system' => 'http://snomed.info/sct',
                        'code' => '425044008',
                        'display' => 'Physical exam section',
                    ],
                ],
                'text' => 'Ophthalmic Examination & Clinical Findings',
            ],
            'subject' => [
                'reference' => "urn:uuid:{$patientId}",
            ],
            'encounter' => [
                'reference' => "urn:uuid:{$encounterId}",
            ],
            'component' => count($components) ? $components : [
                [
                    'code' => ['text' => 'Clinical Exam'],
                    'valueString' => 'Within normal limits (WNL)',
                ],
            ],
        ];
    }

    /**
     * Build CarePlan Resource for Advice and Follow-up.
     */
    protected function buildCarePlanResource(
        string $id,
        string $patientId,
        ?string $advice,
        ?string $followupDate
    ): array {
        $advices = $this->parseList($advice);
        $adviceDesc = count($advices) ? implode(', ', $advices) : 'Routine ophthalmic hygiene and precautions.';
        $followupStr = $followupDate ? Carbon::parse($followupDate)->format('Y-m-d') : null;

        $res = [
            'resourceType' => 'CarePlan',
            'id' => $id,
            'meta' => [
                'profile' => [
                    'https://nrces.in/ndhm/fhir/r4/StructureDefinition/CarePlan',
                ],
            ],
            'status' => 'active',
            'intent' => 'plan',
            'title' => 'Outpatient Care Plan & Follow-up',
            'description' => "Advice: {$adviceDesc}" . ($followupStr ? ". Scheduled next follow-up: {$followupStr}" : ''),
            'subject' => [
                'reference' => "urn:uuid:{$patientId}",
            ],
        ];

        if ($followupStr) {
            $res['period'] = [
                'end' => Carbon::parse($followupStr)->toISOString(),
            ];
            $res['activity'] = [
                [
                    'detail' => [
                        'status' => 'scheduled',
                        'scheduledTiming' => [
                            'repeat' => [
                                'boundsPeriod' => [
                                    'start' => Carbon::parse($followupStr)->toISOString(),
                                ],
                            ],
                        ],
                        'description' => 'Follow-up Consultation',
                    ],
                ],
            ];
        }

        return $res;
    }

    /**
     * Parse comma-separated list into clean string array.
     */
    public function parseList(?string $raw): array
    {
        if (empty($raw)) {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * Parse pipe-tilde prescription string into structured array.
     * Format: Type | Medicine | Frequency | Time | Duration ~ ...
     */
    public function parsePrescriptions(?string $prescriptionStr): array
    {
        if (empty($prescriptionStr)) {
            return [];
        }

        $medicines = [];
        $lines = array_filter(explode('~', $prescriptionStr));
        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) >= 2) {
                $medicines[] = [
                    'type' => $parts[0] ?? '',
                    'medicine' => $parts[1] ?? '',
                    'frequency' => $parts[2] ?? '',
                    'time' => $parts[3] ?? '',
                    'duration' => $parts[4] ?? '',
                ];
            } elseif (!empty(trim($line))) {
                $medicines[] = [
                    'type' => 'Medication',
                    'medicine' => trim($line),
                    'frequency' => '',
                    'time' => '',
                    'duration' => '',
                ];
            }
        }

        return $medicines;
    }
}
