<?php

namespace App\Http\Controllers;

use App\Services\Abdm\CareContextService;
use App\Services\Abdm\FhirBundleService;
use App\Services\Abdm\ScanAndShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AbdmWebhookController extends Controller
{
    protected ScanAndShareService $scanShareService;
    protected CareContextService $careContextService;
    protected FhirBundleService $fhirBundleService;

    public function __construct(
        ScanAndShareService $scanShareService,
        CareContextService $careContextService,
        FhirBundleService $fhirBundleService
    ) {
        $this->scanShareService = $scanShareService;
        $this->careContextService = $careContextService;
        $this->fhirBundleService = $fhirBundleService;
    }

    /**
     * Handle incoming patient share callback from ABDM Gateway.
     * Route: POST /api/v3/hip/patient/share
     */
    public function handlePatientShare(Request $request): JsonResponse
    {
        $requestId = $request->header('REQUEST-ID') ?? (string) Str::uuid();
        $payload = $request->all();

        Log::info("ABDM Webhook: Incoming patient share", [
            'requestId' => $requestId,
            'headers' => $request->headers->all(),
            'payload' => $payload,
        ]);

        try {
            $result = $this->scanShareService->processIncomingShare($payload, $requestId);

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Patient profile received and queued.',
                'tokenNumber' => $result['token_number'],
            ], 200);
        } catch (\Throwable $e) {
            Log::error("ABDM Webhook Error: " . $e->getMessage());

            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle incoming patient care context discovery from ABDM Gateway.
     * Route: POST /api/v3/hip/patient/care-context/discover
     */
    public function handleCareContextDiscover(Request $request): JsonResponse
    {
        $requestId = $request->header('REQUEST-ID') ?? (string) Str::uuid();
        $payload = $request->all();

        Log::info("ABDM Webhook: Incoming patient care context discovery [{$request->method()} {$request->fullUrl()}]", [
            'requestId' => $requestId,
            'headers' => $request->headers->all(),
            'payload' => $payload,
        ]);

        try {
            $result = $this->careContextService->handleDiscover($payload, $requestId);

            // Dispatch official V3 on-discover callback to ABDM Gateway
            $this->careContextService->dispatchOnDiscover($result, $requestId);

            return response()->json($result, 200, [
                'Content-Type' => 'application/json',
                'REQUEST-ID' => $requestId,
                'TIMESTAMP' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z'),
            ]);
        } catch (\Throwable $e) {
            Log::error("ABDM Discovery Webhook Error: " . $e->getMessage());

            return response()->json([
                'transactionId' => $payload['transactionId'] ?? null,
                'error' => [
                    'code' => 2500,
                    'message' => $e->getMessage(),
                ],
            ], 200, [
                'Content-Type' => 'application/json',
                'REQUEST-ID' => $requestId,
            ]);
        }
    }

    /**
     * Handle incoming care context link init from ABDM Gateway.
     * Route: POST /api/v3/hip/link/care-context/init
     */
    public function handleLinkInit(Request $request): JsonResponse
    {
        $requestId = $request->header('REQUEST-ID') ?? (string) Str::uuid();
        Log::info("ABDM Webhook: Link Init received", ['requestId' => $requestId, 'payload' => $request->all()]);

        try {
            $result = $this->careContextService->handleLinkInit($request->all(), $requestId);
            return response()->json($result, 200);
        } catch (\Throwable $e) {
            Log::error("ABDM Link Init Error: " . $e->getMessage());
            return response()->json(['error' => ['code' => 2500, 'message' => $e->getMessage()]], 200);
        }
    }

    /**
     * Handle incoming care context link confirm from ABDM Gateway.
     * Route: POST /api/v3/hip/link/care-context/confirm
     */
    public function handleLinkConfirm(Request $request): JsonResponse
    {
        $requestId = $request->header('REQUEST-ID') ?? (string) Str::uuid();
        Log::info("ABDM Webhook: Link Confirm received", ['requestId' => $requestId, 'payload' => $request->all()]);

        try {
            $result = $this->careContextService->handleLinkConfirm($request->all(), $requestId);
            return response()->json($result, 200);
        } catch (\Throwable $e) {
            Log::error("ABDM Link Confirm Error: " . $e->getMessage());
            return response()->json(['error' => ['code' => 2500, 'message' => $e->getMessage()]], 200);
        }
    }

    /**
     * Handle incoming health information request from ABDM Gateway.
     * Route: POST /api/v3/hip/health-information/request
     */
    public function handleHealthInfoRequest(Request $request): JsonResponse
    {
        $requestId = $request->header('REQUEST-ID') ?? (string) Str::uuid();
        $payload = $request->all();

        Log::info("ABDM Webhook: Health Information Request received", [
            'requestId' => $requestId,
            'payload' => $payload,
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Health information request received and queued for transfer.',
            'timestamp' => now()->toISOString(),
        ], 200);
    }

    /**
     * Handle incoming consent notification from ABDM Gateway.
     * Route: POST /api/v3/hip/consent/notify
     */
    public function handleConsentNotify(Request $request): JsonResponse
    {
        $requestId = $request->header('REQUEST-ID') ?? (string) Str::uuid();
        $payload = $request->all();

        Log::info("ABDM Webhook: Consent Notification received", [
            'requestId' => $requestId,
            'payload' => $payload,
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Consent notification acknowledged.',
            'timestamp' => now()->toISOString(),
        ], 200);
    }
}
