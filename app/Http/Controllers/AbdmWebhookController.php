<?php

namespace App\Http\Controllers;

use App\Services\Abdm\CareContextService;
use App\Services\Abdm\ScanAndShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AbdmWebhookController extends Controller
{
    protected ScanAndShareService $scanShareService;
    protected CareContextService $careContextService;

    public function __construct(ScanAndShareService $scanShareService, CareContextService $careContextService)
    {
        $this->scanShareService = $scanShareService;
        $this->careContextService = $careContextService;
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

        Log::info("ABDM Webhook: Incoming patient care context discovery", [
            'requestId' => $requestId,
            'payload' => $payload,
        ]);

        try {
            $result = $this->careContextService->handleDiscover($payload, $requestId);

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Discovery processed and acknowledged.',
                'data' => $result,
            ], 200);
        } catch (\Throwable $e) {
            Log::error("ABDM Discovery Webhook Error: " . $e->getMessage());

            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ], 500);
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

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Link Init acknowledged.',
        ], 200);
    }

    /**
     * Handle incoming care context link confirm from ABDM Gateway.
     * Route: POST /api/v3/hip/link/care-context/confirm
     */
    public function handleLinkConfirm(Request $request): JsonResponse
    {
        $requestId = $request->header('REQUEST-ID') ?? (string) Str::uuid();
        Log::info("ABDM Webhook: Link Confirm received", ['requestId' => $requestId, 'payload' => $request->all()]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Link Confirm acknowledged.',
        ], 200);
    }
}
