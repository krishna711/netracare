<?php

namespace App\Http\Controllers;

use App\Services\Abdm\ScanAndShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AbdmWebhookController extends Controller
{
    protected ScanAndShareService $scanShareService;

    public function __construct(ScanAndShareService $scanShareService)
    {
        $this->scanShareService = $scanShareService;
    }

    /**
     * Handle incoming patient share callback from ABDM Gateway.
     * Route: POST /api/v3/hip/patient/share
     */
    public function handlePatientShare(Request $request): JsonResponse
    {
        $requestId = $request->header('REQUEST-ID') ?? (string) \Illuminate\Support\Str::uuid();
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
}
