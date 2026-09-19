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

    /**
     * Generate the Counter QR code data string according to ABDM specs.
     *
     * @param string|null $counterId
     * @return array QR string and metadata
     */
    public function generateCounterQrPayload(?string $counterId = null): array
    {
        $hipId = $this->client->getHipId() ?: (config('abdm.hip_id') ?: 'IN2310001444');
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
        $token = $this->client->getSessionToken();

        $payload = [
            'acknowledgement' => [
                'status' => 'SUCCESS',
                'abhaAddress' => $abhaAddress,
                'healthId' => $abhaAddress,
                'profile' => [
                    'context' => $context,
                    'tokenNumber' => $tokenNumber,
                    'expiry' => '1800', // 30 minutes validity
                ],
            ],
            'error' => null,
            'response' => [
                'requestId' => $requestId,
            ],
            'resp' => [
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

        // 1. Try V1.0 Gateway URL first (standard for ABHA app Scan & Share)
        $bridgeUrl = rtrim(config('abdm.bridge_base_url', 'https://dev.abdm.gov.in'), '/');
        $v1Url = "{$bridgeUrl}/gateway/v1.0/patients/profile/on-share";

        try {
            $response = Http::timeout(8)->withHeaders($headers)->post($v1Url, $payload);
            if ($response->successful()) {
                Log::info("ABDM On-Share Acknowledgement (v1.0) successful [{$response->status()}]");
                return true;
            }
            Log::warning("ABDM On-Share v1.0 returned {$response->status()}: " . $response->body());
        } catch (\Throwable $e) {
            Log::warning("ABDM On-Share v1.0 call failed: " . $e->getMessage());
        }

        // 2. Try V3 Gateway URL
        $v3Url = "{$this->client->getGatewayBaseUrl()}/patient-share/v3/on-share";
        try {
            $responseV3 = Http::timeout(8)->withHeaders($headers)->post($v3Url, $payload);
            if ($responseV3->successful()) {
                Log::info("ABDM On-Share Acknowledgement (v3) successful [{$responseV3->status()}]");
                return true;
            }
            Log::warning("ABDM On-Share v3 returned {$responseV3->status()}: " . $responseV3->body());
        } catch (\Throwable $e) {
            Log::warning("ABDM On-Share v3 call failed: " . $e->getMessage());
        }

        // 3. Fallback: Direct v1.0 without /gateway prefix if sandbox proxy requires it
        $directV1Url = "{$bridgeUrl}/v1.0/patients/profile/on-share";
        try {
            $responseDirect = Http::timeout(8)->withHeaders($headers)->post($directV1Url, $payload);
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
}
