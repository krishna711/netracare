<?php

namespace App\Services\Abdm;

use App\Models\AbdmScanShare;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ScanAndShareService
{
    protected AbdmClient $client;

    public function __construct(AbdmClient $client)
    {
        $this->client = $client;
    }

    public function getClient(): AbdmClient
    {
        return $this->client;
    }

    /**
     * Generate the Counter QR code data string according to ABDM specs.
     *
     * @param string|null $counterId
     * @param string|null $overrideHipId
     * @return array QR string and metadata
     */
    public function generateCounterQrPayload(?string $counterId = null, ?string $overrideHipId = null): array
    {
        $hipId = $overrideHipId ?: ($this->client->getHipId() ?: (config('abdm.hip_id') ?: 'IN2310001444'));
        $facilityName = config('abdm.facility_name') ?: 'Netrika Netralaya';
        $counter = $counterId ?: (config('abdm.counter_id') ?: '1');

        $isProduction = config('abdm.env') === 'production';
        $baseUrl = $isProduction 
            ? 'https://phr.abdm.gov.in/share-profile' 
            : 'https://phrsbx.abdm.gov.in/share-profile';

        // Official ABDM Scan & Share QR URL format
        $qrString = "{$baseUrl}?hip-id={$hipId}&counter-id={$counter}";

        $qrData = [
            'hip_id' => $hipId,
            'counter_id' => (string) $counter,
            'facility_name' => $facilityName,
            'url' => $qrString,
            'timestamp' => now()->timestamp,
        ];

        return [
            'hip_id' => $hipId,
            'facility_name' => $facilityName,
            'counter_id' => $counter,
            'qr_data' => $qrData,
            'qr_string' => $qrString,
        ];
    }

    /**
     * Process incoming profile-share callback pushed by ABDM Gateway.
     * Endpoint: POST /api/v3/hip/patient/share
     *
     * @param array $payload
     * @param string $requestId
     * @return array Acknowledgement details
     * @throws Exception
     */
    public function processIncomingShare(array $payload, string $requestId): array
    {
        Log::info("ABDM Scan & Share: Received profile share request [RequestID: {$requestId}]", [
            'payload' => $payload,
        ]);

        $metaData = $payload['metaData'] ?? $payload['metadata'] ?? [];
        $profile = $payload['profile'] ?? [];
        $patient = $profile['patient'] ?? $payload['patient'] ?? $profile ?? [];

        $hipId = $metaData['hipId'] ?? $payload['hipId'] ?? $this->client->getHipId();
        $counterId = (string) ($metaData['context'] ?? $metaData['counterId'] ?? $payload['counterId'] ?? '1');
        
        // Flexible key resolution for ABHA details across ABDM versions
        $abhaNumber = (string) ($patient['abhaNumber'] ?? $patient['healthIdNumber'] ?? '');
        $abhaAddress = (string) ($patient['abhaAddress'] ?? $patient['healthId'] ?? $patient['id'] ?? '');
        $name = $patient['name'] ?? '';
        $gender = $patient['gender'] ?? '';
        $mobile = (string) ($patient['phoneNumber'] ?? $patient['mobile'] ?? '');

        // Format DOB
        $dob = null;
        if (!empty($patient['yearOfBirth'])) {
            $y = $patient['yearOfBirth'];
            $m = str_pad($patient['monthOfBirth'] ?? '01', 2, '0', STR_PAD_LEFT);
            $d = str_pad($patient['dayOfBirth'] ?? '01', 2, '0', STR_PAD_LEFT);
            $dob = "{$y}-{$m}-{$d}";
        } elseif (!empty($patient['dateOfBirth']) || !empty($patient['dob'])) {
            $dob = $patient['dateOfBirth'] ?? $patient['dob'];
        }

        // Format address line
        $address = null;
        if (isset($patient['address']) && is_array($patient['address'])) {
            $address = implode(', ', array_filter([
                $patient['address']['line'] ?? '',
                $patient['address']['district'] ?? '',
                $patient['address']['state'] ?? '',
                $patient['address']['pincode'] ?? $patient['address']['pinCode'] ?? '',
            ]));
        } elseif (is_string($patient['address'] ?? null)) {
            $address = $patient['address'];
        }

        // Generate counter token number (e.g. 101, 102, ...)
        $todayCount = AbdmScanShare::whereDate('created_at', today())->count();
        $tokenNumber = (string) (100 + $todayCount + 1);

        // Store in local database queue
        $scanShare = AbdmScanShare::updateOrCreate(
            ['request_id' => $requestId],
            [
                'hip_id' => $hipId,
                'counter_id' => $counterId,
                'token_number' => $tokenNumber,
                'abha_number' => $abhaNumber,
                'abha_address' => $abhaAddress,
                'name' => $name,
                'gender' => $gender,
                'dob' => $dob,
                'mobile' => $mobile,
                'address' => $address,
                'raw_profile' => $payload,
                'status' => 'pending',
            ]
        );

        // Send acknowledgement to ABDM Gateway (profile-on-share)
        $ackSent = false;
        try {
            $ackSent = $this->sendOnShareAcknowledgement(
                $requestId,
                $abhaAddress,
                $tokenNumber,
                $counterId
            );
        } catch (\Throwable $e) {
            Log::warning("ABDM Scan & Share: Could not send on-share acknowledgement immediately: " . $e->getMessage());
        }

        return [
            'status' => 'success',
            'token_number' => $tokenNumber,
            'ack_sent' => $ackSent,
            'record_id' => $scanShare->id,
        ];
    }

    /**
     * Send profile-on-share acknowledgement back to ABDM Gateway.
     * Supports both v1.0 standard (/gateway/v1.0/patients/profile/on-share)
     * and v3 standard (/patient-share/v3/on-share).
     */
    public function sendOnShareAcknowledgement(
        string $requestId,
        string $abhaAddress,
        string $tokenNumber,
        string $context = '1'
    ): bool {
        try {
            $token = $this->client->getSessionToken();
        } catch (\Throwable $e) {
            Log::warning("ABDM Scan & Share: Unable to get session token for on-share: " . $e->getMessage());
            return false;
        }

        // V3 compliant payload (per official M1 collection)
        $payloadV3 = [
            'acknowledgement' => [
                'status' => 'SUCCESS',
                'abhaAddress' => $abhaAddress,
                'profile' => [
                    'context' => (string) $context,
                    'tokenNumber' => (string) $tokenNumber,
                    'expiry' => '1800',
                ],
            ],
            'response' => [
                'requestId' => $requestId,
            ],
        ];

        // Legacy V1.0 fallback payload
        $payloadV1 = [
            'acknowledgement' => [
                'status' => 'SUCCESS',
                'abhaAddress' => $abhaAddress,
                'healthId' => $abhaAddress,
                'profile' => [
                    'context' => (string) $context,
                    'tokenNumber' => (string) $tokenNumber,
                    'expiry' => '1800',
                ],
            ],
            'error' => null,
            'resp' => [
                'requestId' => $requestId,
            ],
            'response' => [
                'requestId' => $requestId,
            ],
        ];

        Log::info("ABDM Scan & Share: Sending on-share ack for request {$requestId} with Token {$tokenNumber} to ABHA {$abhaAddress}");

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'REQUEST-ID' => (string) Str::uuid(),
            'TIMESTAMP' => $this->client->getIsoTimestamp(),
            'X-CM-ID' => $this->client->getCmId(),
        ];

        // 1. Primary: Official V3 Gateway URL (Standard for M1 in Sandbox)
        $v3Url = "{$this->client->getGatewayBaseUrl()}/patient-share/v3/on-share";
        try {
            $responseV3 = Http::timeout(6.0)->withHeaders($headers)->post($v3Url, $payloadV3);
            if ($responseV3->successful()) {
                Log::info("ABDM On-Share Acknowledgement (v3) successful [{$responseV3->status()}]");
                return true;
            }
            Log::warning("ABDM On-Share v3 returned {$responseV3->status()}: " . $responseV3->body());
        } catch (\Throwable $e) {
            Log::warning("ABDM On-Share v3 call failed: " . $e->getMessage());
        }

        // 2. Secondary: V1.0 Gateway URL
        $bridgeUrl = rtrim(config('abdm.bridge_base_url', 'https://dev.abdm.gov.in'), '/');
        $v1Url = "{$bridgeUrl}/gateway/v1.0/patients/profile/on-share";
        try {
            $responseV1 = Http::timeout(4.0)->withHeaders($headers)->post($v1Url, $payloadV1);
            if ($responseV1->successful()) {
                Log::info("ABDM On-Share Acknowledgement (v1.0) successful [{$responseV1->status()}]");
                return true;
            }
            Log::warning("ABDM On-Share v1.0 returned {$responseV1->status()}: " . $responseV1->body());
        } catch (\Throwable $e) {
            Log::warning("ABDM On-Share v1.0 call failed: " . $e->getMessage());
        }

        // 3. Fallback: Direct v1.0 without /gateway prefix
        $directV1Url = "{$bridgeUrl}/v1.0/patients/profile/on-share";
        try {
            $responseDirect = Http::timeout(3.0)->withHeaders($headers)->post($directV1Url, $payloadV1);
            if ($responseDirect->successful()) {
                Log::info("ABDM On-Share Acknowledgement (direct v1.0) successful [{$responseDirect->status()}]");
                return true;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        Log::error("ABDM On-Share Acknowledgement could not be delivered to any gateway endpoint.");
        return false;
    }

    /**
     * Process running token request from ABDM Gateway / PHR App.
     * Collection: Running token status (M1)
     *
     * @param array $payload
     * @param string $requestId
     * @return array
     */
    public function handleRunningTokenStatus(array $payload, string $requestId): array
    {
        Log::info("ABDM Scan & Share: Received running token status request [RequestID: {$requestId}]", [
            'payload' => $payload,
        ]);

        $hipId = $payload['hipId'] ?? $this->client->getHipId();
        $context = (string) ($payload['context'] ?? '1');

        $latestToken = AbdmScanShare::whereDate('created_at', today())
            ->whereNotNull('token_number')
            ->latest()
            ->value('token_number');
        $runningTokenNumber = $latestToken ? (string) $latestToken : '100';
        $avgTime = 3; // minutes average service time

        // Dispatch async on-status acknowledgment to ABDM Gateway
        $ackSent = false;
        try {
            $ackSent = $this->sendRunningTokenOnStatus($requestId, $hipId, $context, $runningTokenNumber, $avgTime);
        } catch (\Throwable $e) {
            Log::warning("ABDM Running Token: Could not send on-status immediately: " . $e->getMessage());
        }

        return [
            'status' => 'SUCCESS',
            'token' => [
                'hipId' => $hipId,
                'context' => $context,
                'runningTokenNumber' => $runningTokenNumber,
                'averageTokenServiceTimeInMinutes' => $avgTime,
            ],
            'response' => [
                'requestId' => $requestId,
            ],
            'ack_sent' => $ackSent,
        ];
    }

    /**
     * Send running-token/on-status to ABDM Gateway (v3).
     * Endpoint: POST /api/hiecm/patient-share/v3/running-token/on-status
     *
     * @param string $requestId
     * @param string $hipId
     * @param string $context
     * @param string $runningTokenNumber
     * @param int $averageTimeInMinutes
     * @return bool
     */
    public function sendRunningTokenOnStatus(
        string $requestId,
        string $hipId,
        string $context = '1',
        string $runningTokenNumber = '100',
        int $averageTimeInMinutes = 3
    ): bool {
        try {
            $token = $this->client->getSessionToken();
        } catch (\Throwable $e) {
            Log::warning("ABDM Running Token: Unable to get session token: " . $e->getMessage());
            return false;
        }

        $url = "{$this->client->getGatewayBaseUrl()}/patient-share/v3/running-token/on-status";
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'REQUEST-ID' => (string) Str::uuid(),
            'TIMESTAMP' => $this->client->getIsoTimestamp(),
            'X-CM-ID' => $this->client->getCmId() ?: 'sbx',
        ];

        $payload = [
            'token' => [
                'hipId' => $hipId,
                'context' => (string) $context,
                'runningTokenNumber' => (string) $runningTokenNumber,
                'averageTokenServiceTimeInMinutes' => $averageTimeInMinutes,
            ],
            'response' => [
                'requestId' => $requestId,
            ],
        ];

        Log::info("ABDM Running Token: Sending on-status for request {$requestId} (Token: {$runningTokenNumber}) to {$url}");

        try {
            $response = Http::timeout(6.0)->withHeaders($headers)->post($url, $payload);
            if ($response->successful()) {
                Log::info("ABDM Running Token on-status successful [{$response->status()}]");
                return true;
            }
            Log::warning("ABDM Running Token on-status returned {$response->status()}: " . $response->body());
        } catch (\Throwable $e) {
            Log::warning("ABDM Running Token on-status call failed: " . $e->getMessage());
        }

        return false;
    }
}
