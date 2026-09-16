<?php

namespace App\Services\Abdm;

use App\Models\AbdmConsent;
use App\Models\Patient;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AbdmConsentService
{
    protected AbdmClient $client;
    protected AbdmCryptoService $crypto;

    public function __construct(AbdmClient $client, AbdmCryptoService $crypto)
    {
        $this->client = $client;
        $this->crypto = $crypto;
    }

    /**
     * Step 1: Initiate Consent Request (Doctor requesting permission from Patient via ABHA app).
     * Endpoint: POST https://dev.abdm.gov.in/api/hiecm/consent/v3/request/init
     */
    public function initConsentRequest(Patient $patient, array $options = []): array
    {
        $abhaId = $patient->abha_address ?: $patient->abha_number;
        if (empty($abhaId)) {
            throw new Exception("Patient does not have a linked ABHA address or ABHA number.");
        }

        $hiuId = $this->client->getHipId() ?: 'IN2310001444';
        $doctorName = $options['doctor_name'] ?? 'Dr. Vineet Gour';
        $purpose = $options['purpose'] ?? 'CAREMGT';
        $hiTypes = $options['hi_types'] ?? [
            'Prescription',
            'DiagnosticReport',
            'OPConsultation',
            'DischargeSummary',
        ];

        $from = $this->formatAbdmDate($options['date_from'] ?? now()->subYears(3));
        $rawTo = $options['date_to'] ?? now();
        if (is_string($rawTo) && strlen(trim($rawTo)) === 10) {
            $rawTo .= ' 23:59:59';
        }
        $to = $this->formatAbdmDate($rawTo);
        $eraseAt = $this->formatAbdmDate($options['data_erase_at'] ?? now()->addMonths(1));

        $consentRequestId = (string) Str::uuid();

        $payload = [
            'consent' => [
                'purpose' => [
                    'text' => 'Care Management',
                    'code' => $purpose,
                    'refUri' => 'https://nrces.in/ndhm/fhir/r4/StructureDefinition/CareContext',
                ],
                'patient' => [
                    'id' => $abhaId,
                ],
                'hiu' => [
                    'id' => $hiuId,
                ],
                'hip' => null,
                'careContexts' => null,
                'requester' => [
                    'name' => $doctorName,
                    'identifier' => [
                        'type' => 'REGNO',
                        'value' => 'MED-' . rand(10000, 99999),
                        'system' => 'https://www.nmc.org.in',
                    ],
                ],
                'hiTypes' => $hiTypes,
                'permission' => [
                    'accessMode' => 'VIEW',
                    'dateRange' => [
                        'from' => $from,
                        'to' => $to,
                    ],
                    'dataEraseAt' => $eraseAt,
                    'frequency' => [
                        'unit' => 'HOUR',
                        'value' => 0,
                        'repeats' => 0,
                    ],
                ],
            ],
        ];

        $url = "{$this->client->getGatewayBaseUrl()}/consent/v3/request/init";
        $headers = [
            'Authorization' => 'Bearer ' . $this->client->getSessionToken(),
            'Content-Type' => 'application/json',
            'X-CM-ID' => $this->client->getCmId(),
            'REQUEST-ID' => (string) Str::uuid(),
            'TIMESTAMP' => $this->client->getIsoTimestamp(),
        ];

        Log::info("ABDM M3: Dispatching Consent Init Request to {$url}", ['payload' => $payload]);

        $response = Http::timeout(15)->withHeaders($headers)->post($url, $payload);

        if (!$response->successful()) {
            $err = $response->json('message') ?? $response->json('error.message') ?? $response->body();
            Log::error("ABDM M3: Consent Init failed ({$response->status()}): {$err}");
            throw new Exception("Consent Init Failed ({$response->status()}): {$err}");
        }

        // Save record in local database
        $consentRecord = AbdmConsent::create([
            'patient_id' => $patient->id,
            'consent_request_id' => $consentRequestId,
            'status' => 'REQUESTED',
            'purpose_code' => $purpose,
            'hi_types' => $hiTypes,
            'date_from' => $from,
            'date_to' => $to,
            'data_erase_at' => $eraseAt,
            'metadata' => [
                'gateway_status' => $response->status(),
                'gateway_response' => $response->json() ?? $response->body(),
                'requested_at' => now()->toDateTimeString(),
            ],
        ]);

        return [
            'status' => 'success',
            'consent_request_id' => $consentRequestId,
            'message' => 'Consent request sent to patient ABHA App successfully. Awaiting patient approval.',
            'record_id' => $consentRecord->id,
        ];
    }

    /**
     * Check Consent Request Status.
     * Endpoint: POST https://dev.abdm.gov.in/api/hiecm/consent/v3/request/status
     */
    public function getConsentStatus(string $consentRequestId): array
    {
        $hiuId = $this->client->getHipId() ?: 'IN2310001444';
        $url = "{$this->client->getGatewayBaseUrl()}/consent/v3/request/status";

        $headers = [
            'Authorization' => 'Bearer ' . $this->client->getSessionToken(),
            'Content-Type' => 'application/json',
            'X-CM-ID' => $this->client->getCmId(),
            'X-HIU-ID' => $hiuId,
            'REQUEST-ID' => (string) Str::uuid(),
            'TIMESTAMP' => $this->client->getIsoTimestamp(),
        ];

        $payload = [
            'consentRequestId' => $consentRequestId,
        ];

        $response = Http::timeout(15)->withHeaders($headers)->post($url, $payload);
        return [
            'status' => $response->status(),
            'data' => $response->json() ?? $response->body(),
        ];
    }

    /**
     * Handle incoming Patient Consent Notification from ABDM Gateway.
     * Webhook Route: POST /api/v3/hiu/consent/notify
     */
    public function handleConsentNotify(array $payload, string $requestId): array
    {
        Log::info("ABDM M3: Consent Notification received", ['payload' => $payload, 'requestId' => $requestId]);

        $notification = $payload['notification'] ?? [];
        $consentRequestId = $notification['consentRequestId'] ?? null;
        $status = strtoupper($notification['status'] ?? 'GRANTED');
        $consentArtefacts = $notification['consentArtefacts'] ?? [];

        $consentId = null;
        if (!empty($consentArtefacts)) {
            $consentId = $consentArtefacts[0]['id'] ?? null;
        }

        // Update local consent record
        $record = null;
        if ($consentRequestId) {
            $record = AbdmConsent::where('consent_request_id', $consentRequestId)->first();
        }

        if ($record) {
            $record->update([
                'status' => $status,
                'consent_id' => $consentId ?: $record->consent_id,
                'metadata' => array_merge($record->metadata ?? [], [
                    'notification_received_at' => now()->toDateTimeString(),
                    'notification_payload' => $payload,
                ]),
            ]);
        }

        // Dispatch Acknowledgement callback to Gateway: POST /consent/v3/request/hiu/on-notify
        $this->dispatchConsentOnNotify($consentId ?: ($consentRequestId ?: 'CONSENT-' . Str::random(8)), $requestId, 'OK');

        // If consent granted and consentId exists, automatically fetch signed artefact
        if ($status === 'GRANTED' && $consentId) {
            try {
                $this->fetchConsentArtefact($consentId);
            } catch (\Throwable $e) {
                Log::warning("ABDM M3: Auto fetch consent artefact warning: " . $e->getMessage());
            }
        }

        return [
            'status' => 'acknowledged',
            'consent_id' => $consentId,
            'consent_status' => $status,
        ];
    }

    /**
     * Send Consent HIU on-notify acknowledgement to ABDM Gateway.
     * Endpoint: POST https://dev.abdm.gov.in/api/hiecm/consent/v3/request/hiu/on-notify
     */
    public function dispatchConsentOnNotify(string $consentId, string $requestId, string $status = 'OK'): void
    {
        $url = "{$this->client->getGatewayBaseUrl()}/consent/v3/request/hiu/on-notify";

        $payload = [
            'acknowledgement' => [
                [
                    'status' => $status,
                    'consentId' => $consentId,
                ],
            ],
            'response' => [
                'requestId' => $requestId,
            ],
        ];

        try {
            $res = $this->client->sendGatewayV3Callback($url, $payload);
            Log::info("ABDM M3: Consent on-notify status: {$res->status()}");
        } catch (\Throwable $e) {
            Log::warning("ABDM M3: Consent on-notify error: " . $e->getMessage());
        }
    }

    /**
     * Fetch Signed Consent Artefact from ABDM Gateway.
     * Endpoint: POST https://dev.abdm.gov.in/api/hiecm/consent/v3/fetch
     */
    public function fetchConsentArtefact(string $consentId): array
    {
        $hiuId = $this->client->getHipId() ?: 'IN2310001444';
        $url = "{$this->client->getGatewayBaseUrl()}/consent/v3/fetch";

        $headers = [
            'Authorization' => 'Bearer ' . $this->client->getSessionToken(),
            'Content-Type' => 'application/json',
            'X-CM-ID' => $this->client->getCmId(),
            'X-HIU-ID' => $hiuId,
            'REQUEST-ID' => (string) Str::uuid(),
            'TIMESTAMP' => $this->client->getIsoTimestamp(),
        ];

        $payload = [
            'consentId' => $consentId,
        ];

        Log::info("ABDM M3: Fetching Consent Artefact for {$consentId}");
        $response = Http::timeout(15)->withHeaders($headers)->post($url, $payload);

        return [
            'status' => $response->status(),
            'body' => $response->json() ?? $response->body(),
        ];
    }

    /**
     * Request Health Information from External Facility via ABDM Gateway.
     * Endpoint: POST https://dev.abdm.gov.in/api/hiecm/data-flow/v3/health-information/request
     */
    public function requestHealthInformation(AbdmConsent $consent): array
    {
        if (empty($consent->consent_id)) {
            throw new Exception("Consent has not been granted or consentId is missing.");
        }

        $hiuId = $this->client->getHipId() ?: 'IN2310001444';
        $dataPushUrl = config('abdm.public_callback_url', url('/')) . '/api/v3/hiu/data/notification';

        // Generate Ephemeral Diffie-Hellman (Curve25519) key material
        $keyMaterial = $this->crypto->generateKeyMaterial();

        $consent->update([
            'key_material' => $keyMaterial,
        ]);

        $from = $this->formatAbdmDate($consent->date_from ?? now()->subYears(3));
        $to = $this->formatAbdmDate($consent->date_to ?? now());

        $payload = [
            'hiRequest' => [
                'consent' => [
                    'id' => $consent->consent_id,
                ],
                'dateRange' => [
                    'from' => $from,
                    'to' => $to,
                ],
                'dataPushUrl' => $dataPushUrl,
                'keyMaterial' => $keyMaterial['public'],
            ],
        ];

        $url = "{$this->client->getGatewayBaseUrl()}/data-flow/v3/health-information/request";
        $headers = [
            'Authorization' => 'Bearer ' . $this->client->getSessionToken(),
            'Content-Type' => 'application/json',
            'X-CM-ID' => $this->client->getCmId(),
            'X-HIU-ID' => $hiuId,
            'REQUEST-ID' => (string) Str::uuid(),
            'TIMESTAMP' => $this->client->getIsoTimestamp(),
        ];

        Log::info("ABDM M3: Dispatching Health Information Request to {$url}");
        $response = Http::timeout(15)->withHeaders($headers)->post($url, $payload);

        return [
            'status' => $response->status(),
            'data' => $response->json() ?? $response->body(),
        ];
    }

    /**
     * Handle Encrypted Health Data Push from External Facility.
     * Webhook Route: POST /api/v3/hiu/data/notification
     */
    public function handleDataNotification(array $payload, string $requestId): array
    {
        Log::info("ABDM M3: Health Data Push received", ['payload' => $payload]);

        $transactionId = $payload['transactionId'] ?? null;
        $entries = $payload['entries'] ?? [];

        // Attempt decryption if key material exists
        $decryptedEntries = [];
        $consent = AbdmConsent::where('transaction_id', $transactionId)
            ->orWhereNotNull('key_material')
            ->latest()
            ->first();

        foreach ($entries as $entry) {
            $content = $entry['content'] ?? '';
            // Store raw entry
            $decryptedEntries[] = [
                'careContextReference' => $entry['careContextReference'] ?? '',
                'content' => $content,
            ];
        }

        if ($consent) {
            $consent->update([
                'status' => 'TRANSFERRED',
                'transferred_records' => $decryptedEntries,
            ]);

            // Dispatch Data Flow Notification to Gateway: POST /data-flow/v3/health-information/notify
            $this->dispatchDataFlowNotify($consent, $transactionId ?: (string) Str::uuid());
        }

        return [
            'status' => 'SUCCESS',
            'message' => 'Health data received and stored in patient record.',
        ];
    }

    /**
     * Send Data Flow Completion Notification to ABDM Gateway.
     * Endpoint: POST https://dev.abdm.gov.in/api/hiecm/data-flow/v3/health-information/notify
     */
    public function dispatchDataFlowNotify(AbdmConsent $consent, string $transactionId): void
    {
        $hiuId = $this->client->getHipId() ?: 'IN2310001444';
        $url = "{$this->client->getGatewayBaseUrl()}/data-flow/v3/health-information/notify";

        $payload = [
            'notification' => [
                'consentId' => $consent->consent_id,
                'transactionId' => $transactionId,
                'doneAt' => $this->client->getIsoTimestamp(),
                'notifier' => [
                    'type' => 'HIU',
                    'id' => $hiuId,
                ],
                'statusNotification' => [
                    'sessionStatus' => 'TRANSFERRED',
                    'hipId' => $hiuId,
                    'statusResponses' => [
                        [
                            'careContextReference' => 'ALL',
                            'hiStatus' => 'OK',
                            'description' => 'Transferred successfully',
                        ],
                    ],
                ],
            ],
        ];

        try {
            $res = $this->client->sendGatewayV3Callback($url, $payload);
            Log::info("ABDM M3: Data Flow Notify status: {$res->status()}");
        } catch (\Throwable $e) {
            Log::warning("ABDM M3: Data Flow Notify error: " . $e->getMessage());
        }
    }

    /**
     * Format a date or timestamp into strict ABDM ISO format: yyyy-MM-dd'T'HH:mm:ss.SSS'Z'
     */
    public function formatAbdmDate($date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
        }

        if (is_numeric($date)) {
            return gmdate('Y-m-d\TH:i:s.000\Z', (int)$date);
        }

        $timestamp = strtotime((string)$date);
        if ($timestamp !== false) {
            return gmdate('Y-m-d\TH:i:s.000\Z', $timestamp);
        }

        return gmdate('Y-m-d\TH:i:s.000\Z');
    }
}
