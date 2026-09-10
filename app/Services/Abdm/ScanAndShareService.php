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
        $hipId = $this->client->getHipId() ?: config('abdm.hip_id');
        $facilityName = config('abdm.facility_name', 'Netrika Netralaya');
        $counter = $counterId ?: config('abdm.counter_id', '1');

        $qrData = [
            'hip_id' => $hipId,
            'counter_id' => (string) $counter,
            'facility_name' => $facilityName,
            'timestamp' => now()->timestamp,
        ];

        // Format as JSON string for QR scanner (ABHA App format)
        $qrString = json_encode($qrData);

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
        Log::info("ABDM Scan & Share: Received profile share request [RequestID: {$requestId}]");

        $metaData = $payload['metaData'] ?? [];
        $profile = $payload['profile'] ?? [];
        $patient = $profile['patient'] ?? [];

        $hipId = $metaData['hipId'] ?? $this->client->getHipId();
        $counterId = $metaData['context'] ?? '1';
        $abhaNumber = (string) ($patient['abhaNumber'] ?? '');
        $abhaAddress = (string) ($patient['abhaAddress'] ?? '');
        $name = $patient['name'] ?? '';
        $gender = $patient['gender'] ?? '';
        $mobile = $patient['phoneNumber'] ?? '';

        // Format DOB
        $dob = null;
        if (!empty($patient['yearOfBirth'])) {
            $y = $patient['yearOfBirth'];
            $m = str_pad($patient['monthOfBirth'] ?? '01', 2, '0', STR_PAD_LEFT);
            $d = str_pad($patient['dayOfBirth'] ?? '01', 2, '0', STR_PAD_LEFT);
            $dob = "{$y}-{$m}-{$d}";
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
     * Endpoint: POST https://dev.abdm.gov.in/api/hiecm/patient-share/v3/on-share
     */
    public function sendOnShareAcknowledgement(
        string $requestId,
        string $abhaAddress,
        string $tokenNumber,
        string $context = '1'
    ): bool {
        $url = "{$this->client->getGatewayBaseUrl()}/patient-share/v3/on-share";
        $token = $this->client->getSessionToken();

        $payload = [
            'acknowledgement' => [
                'status' => 'SUCCESS',
                'abhaAddress' => $abhaAddress,
                'profile' => [
                    'context' => $context,
                    'tokenNumber' => $tokenNumber,
                    'expiry' => '1800', // 30 minutes validity
                ],
            ],
            'response' => [
                'requestId' => $requestId,
            ],
        ];

        Log::info("ABDM Scan & Share: Sending on-share ack for request {$requestId} with Token {$tokenNumber}");

        $response = Http::timeout(15)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'REQUEST-ID' => (string) Str::uuid(),
                'TIMESTAMP' => now()->toIso8601String(),
                'X-CM-ID' => $this->client->getCmId(),
            ])
            ->post($url, $payload);

        if (!$response->successful()) {
            Log::error("ABDM On-Share Acknowledgement failed ({$response->status()}): " . $response->body());
            return false;
        }

        return true;
    }
}
