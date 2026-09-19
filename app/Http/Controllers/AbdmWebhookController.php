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
    protected \App\Services\Abdm\AbdmConsentService $consentService;

    public function __construct(
        ScanAndShareService $scanShareService,
        CareContextService $careContextService,
        FhirBundleService $fhirBundleService,
        \App\Services\Abdm\AbdmConsentService $consentService
    ) {
        $this->scanShareService = $scanShareService;
        $this->careContextService = $careContextService;
        $this->fhirBundleService = $fhirBundleService;
        $this->consentService = $consentService;
    }

    /**
     * Handle incoming patient share callback from ABDM Gateway.
     * Route: POST /api/v3/hip/patient/share
     */
    public function handlePatientShare(Request $request): JsonResponse
    {
        $requestId = $request->header('REQUEST-ID') ?? (string) Str::uuid();
        $payload = $request->all();
        if (empty($payload)) {
            $payload = json_decode($request->getContent(), true) ?: [];
        }

        Log::info("ABDM Webhook: Incoming patient share [{$request->method()} {$request->fullUrl()}]", [
            'requestId' => $requestId,
            'headers' => $request->headers->all(),
            'payload' => $payload,
        ]);

        try {
            $result = $this->scanShareService->processIncomingShare($payload, $requestId);
            $tokenNumber = (string) $result['token_number'];

            $responseHeaders = [
                'Content-Type' => 'application/json',
                'REQUEST-ID' => $requestId,
                'TIMESTAMP' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.000\Z'),
            ];

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Patient profile received and queued.',
                'tokenNumber' => $tokenNumber,
                'acknowledgement' => [
                    'status' => 'SUCCESS',
                    'tokenNumber' => $tokenNumber,
                ],
                'response' => [
                    'requestId' => $requestId,
                ],
            ], 200, $responseHeaders);
        } catch (\Throwable $e) {
            Log::error("ABDM Webhook Error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
                'error' => [
                    'code' => 500,
                    'message' => $e->getMessage(),
                ],
            ], 200, [
                'Content-Type' => 'application/json',
                'REQUEST-ID' => $requestId,
            ]);
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

        Log::info("ABDM Webhook: Health Information Request received (HIP role)", [
            'requestId' => $requestId,
            'payload' => $payload,
        ]);

        try {
            $this->consentService->transferHealthDataAsHip($payload, $requestId);
        } catch (\Throwable $e) {
            Log::warning("ABDM Webhook: HIP Health Data Transfer warning: " . $e->getMessage());
        }

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Health information request received and processed.',
            'timestamp' => now()->toISOString(),
        ], 200);
    }

    /**
     * Handle incoming consent notification from ABDM Gateway (HIU / HIP).
     * Routes: POST /api/v3/hiu/consent/notify, POST /api/v3/hip/consent/notify
     */
    public function handleConsentNotify(Request $request): JsonResponse
    {
        $requestId = $request->header('REQUEST-ID') ?? (string) Str::uuid();
        $payload = $request->all();

        Log::info("ABDM Webhook: Consent Notification received", [
            'requestId' => $requestId,
            'payload' => $payload,
        ]);

        try {
            $result = $this->consentService->handleConsentNotify($payload, $requestId);
            return response()->json($result, 200);
        } catch (\Throwable $e) {
            Log::error("ABDM Webhook: Consent Notify Error: " . $e->getMessage());
            return response()->json(['error' => ['code' => 2500, 'message' => $e->getMessage()]], 200);
        }
    }

    /**
     * Handle incoming consent on-init callback from ABDM Gateway.
     * Route: POST /api/v3/hiu/consent/request/on-init
     */
    public function handleConsentOnInit(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info("ABDM Webhook: Consent on-init callback received", ['payload' => $payload]);

        $consentReqId = $payload['consentRequest']['id'] ?? null;
        if ($consentReqId) {
            $consent = \App\Models\AbdmConsent::where('status', 'REQUESTED')->latest()->first()
                ?: \App\Models\AbdmConsent::latest()->first();
            if ($consent) {
                $consent->update([
                    'metadata' => array_merge($consent->metadata ?? [], [
                        'gateway_consent_request_id' => $consentReqId,
                        'on_init_at' => now()->toDateTimeString(),
                        'on_init_payload' => $payload,
                    ]),
                ]);
                Log::info("ABDM Webhook: Updated AbdmConsent record with gateway ID {$consentReqId}");
            }
        }

        return response()->json(['status' => 'acknowledged'], 202);
    }

    /**
     * Handle incoming consent on-status callback from ABDM Gateway.
     * Route: POST /api/v3/hiu/consent/request/on-status
     */
    public function handleConsentOnStatus(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info("ABDM Webhook: Consent on-status callback received", ['payload' => $payload]);

        $consentReq = $payload['consentRequest'] ?? [];
        $reqId = $consentReq['id'] ?? null;
        $status = strtoupper($consentReq['status'] ?? 'REQUESTED');
        $consentArtefacts = $consentReq['consentArtefacts'] ?? [];

        $consentId = null;
        if (!empty($consentArtefacts)) {
            $consentId = $consentArtefacts[0]['id'] ?? null;
        }

        if ($reqId) {
            $consent = \App\Models\AbdmConsent::where('consent_request_id', $reqId)
                ->orWhereJsonContains('metadata->gateway_consent_request_id', $reqId)
                ->first()
                ?: \App\Models\AbdmConsent::where('status', 'REQUESTED')->latest()->first()
                ?: \App\Models\AbdmConsent::latest()->first();

            if ($consent) {
                $consent->update([
                    'status' => $status,
                    'consent_id' => $consentId ?: $consent->consent_id,
                    'metadata' => array_merge($consent->metadata ?? [], [
                        'gateway_consent_request_id' => $reqId,
                        'on_status_payload' => $payload,
                        'status_updated_at' => now()->toDateTimeString(),
                    ]),
                ]);

                Log::info("ABDM Webhook: Updated consent status to {$status} for req {$reqId}");

                if ($status === 'GRANTED' && $consentId) {
                    try {
                        $this->consentService->fetchConsentArtefact($consentId);
                    } catch (\Throwable $e) {
                        Log::warning("ABDM Consent Fetch error: " . $e->getMessage());
                    }
                }
            }
        }

        return response()->json(['status' => 'acknowledged'], 202);
    }

    /**
     * Handle incoming consent on-fetch callback from ABDM Gateway.
     * Route: POST /api/v3/hiu/consent/on-fetch
     */
    public function handleConsentOnFetch(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info("ABDM Webhook: Consent on-fetch callback received", ['payload' => $payload]);

        $artefact = $payload['consent']['consentDetail'] ?? $payload['consent'] ?? null;
        if ($artefact) {
            $consentId = $artefact['consentId'] ?? $payload['consent']['consentId'] ?? null;
            $consent = \App\Models\AbdmConsent::where('consent_id', $consentId)->orWhere('status', 'GRANTED')->latest()->first();
            if ($consent) {
                $consent->update([
                    'consent_artefact' => $artefact,
                    'status' => 'GRANTED',
                ]);
            }
        }

        return response()->json(['status' => 'acknowledged'], 202);
    }

    /**
     * Handle incoming health-information on-request callback from ABDM Gateway.
     * Route: POST /api/v3/hiu/health-information/on-request
     */
    public function handleHealthInfoOnRequest(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info("ABDM Webhook: Health Info on-request callback received", ['payload' => $payload]);

        $txId = $payload['hiRequest']['transactionId'] ?? null;
        if ($txId) {
            $consent = \App\Models\AbdmConsent::where('status', 'GRANTED')->latest()->first();
            if ($consent) {
                $consent->update([
                    'transaction_id' => $txId,
                    'metadata' => array_merge($consent->metadata ?? [], [
                        'transaction_id' => $txId,
                        'data_request_status' => $payload['hiRequest']['sessionStatus'] ?? 'REQUESTED',
                        'hi_on_request_payload' => $payload,
                    ]),
                ]);
                Log::info("ABDM Webhook: Stored transactionId {$txId} on AbdmConsent");
            }
        }

        return response()->json(['status' => 'acknowledged'], 202);
    }

    /**
     * Handle incoming encrypted health data notification pushed by external HIP.
     * Route: POST /api/v3/hiu/data/notification
     */
    public function handleDataNotification(Request $request): JsonResponse
    {
        $requestId = $request->header('REQUEST-ID') ?? (string) Str::uuid();
        Log::info("ABDM Webhook: Incoming encrypted health data notification", ['requestId' => $requestId, 'payload' => $request->all()]);

        try {
            $result = $this->consentService->handleDataNotification($request->all(), $requestId);
            return response()->json($result, 200);
        } catch (\Throwable $e) {
            Log::error("ABDM Data Notification Error: " . $e->getMessage());
            return response()->json(['error' => ['code' => 2500, 'message' => $e->getMessage()]], 200);
        }
    }
}
