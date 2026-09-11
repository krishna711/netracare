<?php

namespace App\Services\Abdm;

use Exception;
use Illuminate\Support\Facades\Log;

class AbhaEnrollmentService
{
    protected AbdmClient $client;
    protected AbdmCryptoService $crypto;

    public function __construct(AbdmClient $client, AbdmCryptoService $crypto)
    {
        $this->client = $client;
        $this->crypto = $crypto;
    }

    /**
     * Request Aadhaar OTP for ABHA creation.
     *
     * @param string $aadhaar Clean 12-digit Aadhaar number
     * @return array Contains txnId and message
     * @throws Exception
     */
    public function requestAadhaarOtp(string $aadhaar): array
    {
        $cleanAadhaar = preg_replace('/[^0-9]/', '', $aadhaar);
        if (strlen($cleanAadhaar) !== 12) {
            throw new Exception("Aadhaar number must be exactly 12 digits.");
        }

        $encryptedAadhaar = $this->crypto->encrypt($cleanAadhaar);
        $url = "{$this->client->getAbhaBaseUrl()}/v3/enrollment/request/otp";

        $payload = [
            'txnId' => '',
            'scope' => ['abha-enrol'],
            'loginHint' => 'aadhaar',
            'loginId' => $encryptedAadhaar,
            'otpSystem' => 'aadhaar',
        ];

        Log::info("ABDM ABHA Enrol: Requesting Aadhaar OTP");

        $response = $this->client->sendRequest('POST', $url, $payload);

        if (!$response->successful()) {
            $err = $response->json('message') 
                ?? $response->json('error.message') 
                ?? $response->json('details.0.message') 
                ?? $response->body();
            Log::error("ABDM Aadhaar OTP request failed: {$err}");
            throw new Exception("Aadhaar OTP Request Failed ({$response->status()}): {$err}");
        }

        $data = $response->json();
        return [
            'status' => 'success',
            'txnId' => $data['txnId'] ?? null,
            'message' => $data['message'] ?? 'OTP sent to mobile registered with Aadhaar.',
            'raw' => $data,
        ];
    }

    /**
     * Verify Aadhaar OTP and complete ABHA Creation.
     *
     * @param string $txnId Transaction ID from requestAadhaarOtp
     * @param string $otp 6-digit OTP received on mobile
     * @param string $mobile Communication mobile number
     * @return array ABHA Profile details & user token
     * @throws Exception
     */
    public function verifyAadhaarOtp(string $txnId, string $otp, string $mobile): array
    {
        $cleanOtp = trim($otp);
        $cleanMobile = preg_replace('/[^0-9]/', '', $mobile);

        if (empty($txnId)) {
            throw new Exception("Transaction ID is missing. Please request OTP first.");
        }
        if (empty($cleanOtp)) {
            throw new Exception("Please provide the OTP.");
        }

        $encryptedOtp = $this->crypto->encrypt($cleanOtp);
        $url = "{$this->client->getAbhaBaseUrl()}/v3/enrollment/enrol/byAadhaar";

        $payload = [
            'authData' => [
                'authMethods' => ['otp'],
                'otp' => [
                    'txnId' => $txnId,
                    'otpValue' => $encryptedOtp,
                    'mobile' => $cleanMobile,
                ],
            ],
            'consent' => [
                'code' => 'abha-enrollment',
                'version' => '1.4',
            ],
        ];

        Log::info("ABDM ABHA Enrol: Verifying OTP for txnId {$txnId}");

        $response = $this->client->sendRequest('POST', $url, $payload);

        if (!$response->successful()) {
            $err = $response->json('message') 
                ?? $response->json('error.message') 
                ?? $response->json('details.0.message') 
                ?? $response->body();
            Log::error("ABDM OTP verification failed: {$err}");
            throw new Exception("ABHA Creation / OTP Verification Failed ({$response->status()}): {$err}");
        }

        $data = $response->json();
        Log::info("ABDM ABHA Enrol verify response: ", ['data' => $data]);

        // Profile can be inside ABHAProfile, profile, or directly in root
        $profile = $data['ABHAProfile'] ?? $data['profile'] ?? $data;

        // Extract tokens (ABDM v3 returns jwtToken or tokens array)
        $tokens = $data['tokens'] ?? [];
        $jwtToken = $tokens['token'] ?? $data['jwtToken'] ?? $data['token'] ?? null;
        $xToken = $tokens['token'] ?? $jwtToken;

        // Decode JWT token payload if available to extract sub / abhaNumber
        $jwtPayload = [];
        if (!empty($jwtToken) && substr_count($jwtToken, '.') >= 2) {
            $parts = explode('.', $jwtToken);
            $decoded = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            if (is_array($decoded)) {
                $jwtPayload = $decoded;
            }
        }

        // Extract ABHA number
        $abhaNumber = $profile['ABHANumber'] 
            ?? $profile['abhaNumber'] 
            ?? $jwtPayload['abhaNumber']
            ?? $jwtPayload['sub']
            ?? $data['ABHANumber'] 
            ?? $data['abhaNumber'] 
            ?? null;

        // Extract ABHA address
        $abhaAddress = $profile['preferredAbhaAddress'] 
            ?? (is_array($profile['phrAddress'] ?? null) ? ($profile['phrAddress'][0] ?? null) : ($profile['phrAddress'] ?? null))
            ?? $profile['abhaAddress'] 
            ?? $jwtPayload['preferredAbhaAddress']
            ?? $data['preferredAbhaAddress'] 
            ?? ($abhaNumber ? str_replace('-', '', $abhaNumber) . '@abdm' : null);

        $firstName = $profile['firstName'] ?? $data['firstName'] ?? '';
        $lastName = $profile['lastName'] ?? $data['lastName'] ?? '';
        $name = $profile['name'] ?? $data['name'] ?? trim("{$firstName} {$lastName}");

        return [
            'status' => 'success',
            'txnId' => $data['txnId'] ?? $txnId,
            'abhaNumber' => $abhaNumber,
            'abhaAddress' => $abhaAddress,
            'name' => $name ?: 'ABHA User',
            'gender' => $profile['gender'] ?? $data['gender'] ?? null,
            'dob' => $profile['dob'] ?? $data['dob'] ?? trim(($profile['yearOfBirth'] ?? '') . '-' . ($profile['monthOfBirth'] ?? '') . '-' . ($profile['dayOfBirth'] ?? ''), '-'),
            'mobile' => $profile['mobile'] ?? $data['mobile'] ?? $cleanMobile,
            'address' => $profile['address'] ?? $data['address'] ?? null,
            'photo' => $profile['profilePhoto'] ?? $profile['photo'] ?? $data['profilePhoto'] ?? $data['photo'] ?? null,
            'xToken' => $xToken,
            'raw' => $data,
        ];
    }

    /**
     * Fetch suggested ABHA Addresses for the created account.
     */
    public function getAbhaSuggestions(string $txnId): array
    {
        $url = "{$this->client->getAbhaBaseUrl()}/v3/enrollment/enrol/suggestion?txnId={$txnId}";
        $response = $this->client->sendRequest('GET', $url);

        if ($response->successful()) {
            $json = $response->json();
            return $json['abhaAddressList'] ?? $json['suggestions'] ?? (is_array($json) ? $json : []);
        }

        return [];
    }

    /**
     * Assign preferred ABHA address (e.g., username@abdm).
     */
    public function setAbhaAddress(string $txnId, string $preferredAddress): array
    {
        $url = "{$this->client->getAbhaBaseUrl()}/v3/enrollment/enrol/abha-address";
        $payload = [
            'txnId' => $txnId,
            'abhaAddress' => trim($preferredAddress),
        ];

        $response = $this->client->sendRequest('POST', $url, $payload);

        if (!$response->successful()) {
            $err = $response->json('message') ?? $response->body();
            throw new Exception("Failed to set ABHA Address: {$err}");
        }

        return $response->json() ?? ['status' => 'success'];
    }

    /**
     * Download ABHA Card (base64 image or PDF).
     */
    public function downloadAbhaCard(string $xToken): string
    {
        $url = "{$this->client->getAbhaBaseUrl()}/v3/profile/account/abha-card";
        $response = $this->client->sendRequest('GET', $url, [], $xToken);

        if (!$response->successful()) {
            throw new Exception("Failed to download ABHA card ({$response->status()}): " . $response->body());
        }

        return $response->body();
    }
}
