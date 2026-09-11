<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\Abdm\FhirBundleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AbdmFhirController extends Controller
{
    protected FhirBundleService $fhirService;

    public function __construct(FhirBundleService $fhirService)
    {
        $this->fhirService = $fhirService;
    }

    /**
     * Download or view FHIR R4 Bundle for an appointment.
     * URL: /abdm/fhir/{id}/{type?}
     */
    public function export(Request $request, int $id, string $type = 'opconsult')
    {
        $appointment = Appointment::with(['patient', 'doctor', 'consultation'])->findOrFail($id);

        if ($type === 'prescription') {
            $bundle = $this->fhirService->buildPrescriptionBundle($appointment);
            $filename = "abdm-fhir-prescription-appointment-{$id}.json";
        } else {
            $bundle = $this->fhirService->buildOpConsultationBundle($appointment);
            $filename = "abdm-fhir-opconsult-appointment-{$id}.json";
        }

        if ($request->query('preview') === '1') {
            return response()->json($bundle, 200, [
                'Content-Type' => 'application/json',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $jsonContent = json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return response()->streamDownload(function () use ($jsonContent) {
            echo $jsonContent;
        }, $filename, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
