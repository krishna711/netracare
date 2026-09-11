<?php

namespace App\Services\Abdm;

use App\Models\AbdmCareContext;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Patient;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CareContextService
{
    protected AbdmClient $client;

    public function __construct(AbdmClient $client)
    {
        $this->client = $client;
    }

    /**
     * Create or retrieve an ABDM Care Context for an Appointment.
     */
    public function createOrGetForAppointment(Appointment $appointment): AbdmCareContext
    {
        $patient = $appointment->patient;
        if (!$patient) {
            throw new Exception("Appointment #{$appointment->id} is not associated with a patient.");
        }

        $dateStr = $appointment->appointment_time 
            ? Carbon::parse($appointment->appointment_time)->format('d M Y') 
            : Carbon::now()->format('d M Y');

        $doctorStr = $appointment->doctor ? " with Dr. {$appointment->doctor->name}" : "";
        $ref = "OPD-APP-{$appointment->id}";
        $display = "Ophthalmology Consultation — {$dateStr}{$doctorStr}";

        return AbdmCareContext::firstOrCreate(
            ['care_context_reference' => $ref],
            [
                'patient_id' => $patient->id,
                'display_name' => $display,
                'hi_type' => 'OPConsultation',
                'appointment_id' => $appointment->id,
                'patient_reference' => "P-{$patient->id}",
                'status' => 'created',
            ]
        );
    }

    /**
     * Create or retrieve an ABDM Care Context for a Consultation.
     */
    public function createOrGetForConsultation(Consultation $consultation): AbdmCareContext
    {
        $patient = $consultation->patient;
        if (!$patient) {
            throw new Exception("Consultation #{$consultation->id} is not associated with a patient.");
        }

        $dateStr = $consultation->created_at 
            ? $consultation->created_at->format('d M Y') 
            : Carbon::now()->format('d M Y');

        $ref = "OPD-CONS-{$consultation->id}";
        $display = "Eye Clinical Consultation — {$dateStr}";

        return AbdmCareContext::firstOrCreate(
            ['care_context_reference' => $ref],
            [
                'patient_id' => $patient->id,
                'display_name' => $display,
                'hi_type' => 'OPConsultation',
                'appointment_id' => $consultation->appointment_id,
                'consultation_id' => $consultation->id,
                'patient_reference' => "P-{$patient->id}",
                'status' => 'created',
            ]
        );
    }

    /**
     * Link an existing Care Context directly to the patient's ABHA account (HIP-Initiated Linking).
     *
     * @param AbdmCareContext $context
     * @return array
     * @throws Exception
     */
    public function linkCareContext(AbdmCareContext $context): array
    {
        $patient = $context->patient;
        if (!$patient) {
            throw new Exception("Care Context has no associated patient.");
        }

        if (!$patient->isAbhaVerified() && empty($patient->abha_address) && empty($patient->abha_number)) {
            throw new Exception("Patient {$patient->name} does not have a verified ABHA Number or Address. Please verify ABHA first.");
        }

        // Determine effective ABHA address
        $abhaAddress = $patient->abha_address;
        if (empty($abhaAddress) && !empty($patient->abha_number)) {
            $digits = preg_replace('/[^0-9]/', '', $patient->abha_number);
            $abhaAddress = "{$digits}@abdm";
        }

        if (empty($abhaAddress)) {
            throw new Exception("Patient has no valid ABHA Address for care context linking.");
        }

        $now = now()->toISOString();
        $patientRef = $context->patient_reference ?: "P-{$patient->id}";

        Log::info("ABDM M2: Initiating Care Context Linking for Patient #{$patient->id} ({$abhaAddress}), Context: {$context->care_context_reference}");

        // Step 1: Generate Linking Token for Patient
        // Note: Bridge URL (https://dev.abdm.gov.in) is the primary base in V3 sandbox
        $bridgeBase = $this->client->getBridgeBaseUrl() ?: 'https://dev.abdm.gov.in';
        $gwBase = $this->client->getGatewayBaseUrl();

        $tokenUrls = array_unique([
            "{$bridgeBase}/v3/token/generate-token",
            "{$bridgeBase}/gateway/v3/token/generate-token",
            "{$gwBase}/v3/token/generate-token",
        ]);

        $tokenPayload = [
            'requestId' => (string) Str::uuid(),
            'timestamp' => $now,
            'patientId' => $abhaAddress,
        ];

        $tokenResponse = $this->tryPostEndpoints($tokenUrls, $tokenPayload);

        $linkToken = null;
        if ($tokenResponse && $tokenResponse->successful()) {
            $linkToken = $tokenResponse->json('accessToken') 
                ?? $tokenResponse->json('token') 
                ?? $tokenResponse->json('data.accessToken');
        } else {
            $errStatus = $tokenResponse ? $tokenResponse->status() : 'N/A';
            Log::warning("ABDM M2: Generate token returned {$errStatus}. Proceeding with direct link payload.");
        }

        // Fallback placeholder token if gateway requires value
        $linkToken = $linkToken ?: (string) Str::uuid();

        // Step 2: Add Care Context to ABDM Gateway
        $linkUrls = array_unique([
            "{$bridgeBase}/hip/v3/link/carecontext",
            "{$bridgeBase}/gateway/v3/link/carecontext",
            "{$gwBase}/hip/v3/link/carecontext",
        ]);

        $linkPayload = [
            'requestId' => (string) Str::uuid(),
            'timestamp' => $now,
            'link' => [
                'accessToken' => $linkToken,
                'patient' => [
                    'referenceNumber' => $patientRef,
                    'display' => $patient->name,
                    'careContexts' => [
                        [
                            'referenceNumber' => $context->care_context_reference,
                            'display' => $context->display_name,
                        ],
                    ],
                ],
            ],
        ];

        $linkResponse = $this->tryPostEndpoints($linkUrls, $linkPayload);

        if (!$linkResponse || !$linkResponse->successful()) {
            $status = $linkResponse ? $linkResponse->status() : 500;
            $err = $linkResponse ? ($linkResponse->json('message') ?? $linkResponse->json('description') ?? $linkResponse->body()) : 'No response from gateway';

            // Check if this is the known Sandbox Gateway limitation (401 / 403 on HIP-initiated push)
            if (in_array($status, [401, 403])) {
                Log::warning("ABDM M2: Direct push returned {$status} (Known Sandbox WSO2 bridge subscription limitation). Registering care context locally for discovery.");

                $context->update([
                    'status' => 'registered',
                    'linked_at' => now(),
                    'link_token' => $linkToken,
                    'metadata' => [
                        'notice' => 'Registered in NetraCare for ABHA discovery. ABDM Sandbox returned ' . $status . ' for direct push linking (NHA bridge ticket activation pending).',
                        'gateway_status' => $status,
                        'gateway_error' => $err,
                        'attempted_at' => now()->toDateTimeString(),
                    ],
                ]);

                return [
                    'status' => 'registered_locally',
                    'care_context_reference' => $context->care_context_reference,
                    'display_name' => $context->display_name,
                    'message' => "Care Context registered in NetraCare. Note: Sandbox Gateway returned {$status} (NHA bridge activation pending). The visit is ready for ABHA App discovery.",
                ];
            }

            $context->update([
                'status' => 'failed',
                'metadata' => [
                    'last_error' => $err,
                    'attempted_at' => now()->toDateTimeString(),
                    'payload' => $linkPayload,
                ],
            ]);

            throw new Exception("Care Context Linking Failed ({$status}): {$err}");
        }

        // Step 3: Trigger Link Context Notification to Patient
        $hipId = $this->client->getHipId() ?: 'NETRA_CARE_HIP_01';
        $notifyUrls = array_unique([
            "{$bridgeBase}/hip/v3/link/context/notify",
            "{$gwBase}/hip/v3/link/context/notify",
        ]);

        $notifyPayload = [
            'requestId' => (string) Str::uuid(),
            'timestamp' => $now,
            'notification' => [
                'patientId' => $abhaAddress,
                'careContexts' => [
                    [
                        'referenceNumber' => $context->care_context_reference,
                        'display' => $context->display_name,
                        'hiType' => $context->hi_type ?: 'OPConsultation',
                    ],
                ],
                'hipId' => $hipId,
                'date' => $now,
            ],
        ];

        try {
            $this->tryPostEndpoints($notifyUrls, $notifyPayload);
        } catch (\Throwable $e) {
            Log::warning("ABDM M2: Link Context Notification warning: " . $e->getMessage());
        }

        // Step 4: Mark Context as Linked locally
        $context->update([
            'status' => 'linked',
            'linked_at' => now(),
            'link_token' => $linkToken,
            'metadata' => [
                'linked_abha_address' => $abhaAddress,
                'hip_response' => $linkResponse->json() ?? $linkResponse->body(),
                'linked_at' => now()->toDateTimeString(),
            ],
        ]);

        return [
            'status' => 'success',
            'care_context_reference' => $context->care_context_reference,
            'display_name' => $context->display_name,
            'linked_at' => $context->linked_at->toDateTimeString(),
        ];
    }

    /**
     * Try candidate endpoints sequentially until one succeeds.
     */
    protected function tryPostEndpoints(array $urls, array $payload): ?\Illuminate\Http\Client\Response
    {
        $lastResponse = null;
        foreach ($urls as $url) {
            try {
                Log::info("ABDM M2: Attempting POST to {$url}");
                $response = $this->client->sendRequest('POST', $url, $payload);
                if ($response->successful()) {
                    return $response;
                }
                Log::warning("ABDM M2: Endpoint {$url} returned {$response->status()}: " . substr($response->body(), 0, 200));
                $lastResponse = $response;
            } catch (\Throwable $e) {
                Log::warning("ABDM M2: Error calling {$url}: " . $e->getMessage());
            }
        }
        return $lastResponse;
    }

    /**
     * Handle Gateway Inbound Patient Discovery Callback (/api/v3/hip/patient/care-context/discover).
     */
    public function handleDiscover(array $payload, string $requestId): array
    {
        $patientData = $payload['patient'] ?? [];
        $abhaAddress = $patientData['id'] ?? null;
        $name = $patientData['name'] ?? null;

        // Extract verified mobile
        $mobile = null;
        foreach ($patientData['verifiedIdentifiers'] ?? [] as $id) {
            if (($id['type'] ?? '') === 'MOBILE') {
                $mobile = preg_replace('/[^0-9]/', '', $id['value'] ?? '');
                if (strlen($mobile) > 10) {
                    $mobile = substr($mobile, -10);
                }
            }
        }

        // Find patient in NetraCare
        $patient = null;
        if (!empty($abhaAddress)) {
            $patient = Patient::where('abha_address', $abhaAddress)->first();
        }
        if (!$patient && !empty($mobile)) {
            $patient = Patient::where('mobile', 'like', "%{$mobile}%")->first();
        }

        if (!$patient) {
            return [
                'status' => 'NOT_FOUND',
                'message' => 'No matching patient records found in NetraCare.',
            ];
        }

        // Ensure care contexts exist for patient's appointments
        $appointments = Appointment::where('patient_id', $patient->id)->latest()->take(10)->get();
        $careContexts = [];
        foreach ($appointments as $apt) {
            $cc = $this->createOrGetForAppointment($apt);
            $careContexts[] = [
                'referenceNumber' => $cc->care_context_reference,
                'display' => $cc->display_name,
            ];
        }

        // Respond back to ABDM Gateway via on-discover
        $onDiscoverUrl = "{$this->client->getGatewayBaseUrl()}/user-initiated-linking/v3/patient/care-context/on-discover";
        $responsePayload = [
            'requestId' => (string) Str::uuid(),
            'timestamp' => now()->toISOString(),
            'transactionId' => $payload['transactionId'] ?? (string) Str::uuid(),
            'patient' => [
                'referenceNumber' => "P-{$patient->id}",
                'display' => $patient->name,
                'careContexts' => $careContexts,
                'matchedBy' => $mobile ? ['MOBILE'] : ['MR'],
            ],
            'resp' => [
                'requestId' => $requestId,
            ],
        ];

        try {
            $this->client->sendRequest('POST', $onDiscoverUrl, $responsePayload);
        } catch (\Throwable $e) {
            Log::error("Failed to send on-discover callback: " . $e->getMessage());
        }

        return [
            'status' => 'SUCCESS',
            'patientReference' => "P-{$patient->id}",
            'contextsCount' => count($careContexts),
        ];
    }
}
