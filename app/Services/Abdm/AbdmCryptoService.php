<?php

namespace App\Services\Abdm;

use Exception;
use Illuminate\Support\Facades\Log;

class AbdmCryptoService
{
    protected AbdmClient $client;

    public function __construct(AbdmClient $client)
    {
        $this->client = $client;
    }

    /**
     * Format raw public key string into a standard PEM format.
     */
    public function formatPublicKey(string $rawKey): string
    {
        $clean = trim($rawKey);
        if (str_contains($clean, 'BEGIN PUBLIC KEY') || str_contains($clean, 'BEGIN CERTIFICATE')) {
            return $clean;
        }

        // Clean any existing newlines or whitespace
        $clean = preg_replace('/\s+/', '', $clean);
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split($clean, 64, "\n") . "-----END PUBLIC KEY-----";
    }

    /**
     * Encrypt sensitive plain text using ABDM RSA OAEP (SHA-1).
     *
     * @param string $data Plain text (e.g. Aadhaar number, OTP, mobile)
     * @param string|null $overrideCert Optional certificate/public key
     * @return string Base64 encoded encrypted string
     * @throws Exception
     */
    public function encrypt(string $data, ?string $overrideCert = null): string
    {
        $certString = $overrideCert ?: $this->client->getPublicCertificate();
        if (empty($certString)) {
            throw new Exception("ABDM Public Certificate could not be retrieved for encryption.");
        }

        $pem = $this->formatPublicKey($certString);
        $publicKey = openssl_pkey_get_public($pem);

        if (!$publicKey) {
            // Try formatting as CERTIFICATE if PUBLIC KEY format fails
            $certPem = "-----BEGIN CERTIFICATE-----\n" . chunk_split(preg_replace('/\s+/', '', $certString), 64, "\n") . "-----END CERTIFICATE-----";
            $publicKey = openssl_pkey_get_public($certPem);
        }

        if (!$publicKey) {
            $error = openssl_error_string();
            Log::error("ABDM Crypto: Failed to load public key: " . $error);
            throw new Exception("Failed to load ABDM public key certificate: " . ($error ?: 'Invalid PEM format'));
        }

        $encrypted = '';
        $success = openssl_public_encrypt($data, $encrypted, $publicKey, OPENSSL_PKCS1_OAEP_PADDING);

        if (!$success) {
            $error = openssl_error_string();
            Log::error("ABDM Crypto: Encryption failed: " . $error);
            throw new Exception("ABDM RSA Encryption failed: " . ($error ?: 'Unknown OpenSSL error'));
        }

        return base64_encode($encrypted);
    }

    /**
     * Generate Diffie-Hellman (ECDH Curve25519) Key Material for ABDM M3 Data Flow Request.
     */
    public function generateKeyMaterial(): array
    {
        $nonce = random_bytes(32);
        $expiry = gmdate('Y-m-d\TH:i:s.000\Z', strtotime('+2 days'));

        if (function_exists('sodium_crypto_box_keypair')) {
            $keypair = sodium_crypto_box_keypair();
            $publicKey = sodium_crypto_box_publickey($keypair);
            $privateKey = sodium_crypto_box_secretkey($keypair);
        } else {
            $privateKey = random_bytes(32);
            $publicKey = hash('sha256', $privateKey, true);
        }

        $pubBase64 = base64_encode($publicKey);
        $nonceBase64 = base64_encode($nonce);

        return [
            'public' => [
                'cryptoAlg' => 'ECDH',
                'curve' => 'Curve25519',
                'dhPublicKey' => [
                    'expiry' => $expiry,
                    'parameters' => 'Curve25519/32byte random key',
                    'keyValue' => $pubBase64,
                    'x509PublicKey' => $pubBase64,
                ],
                'nonce' => $nonceBase64,
            ],
            'private' => [
                'privateKey' => base64_encode($privateKey),
                'nonce' => $nonceBase64,
            ],
        ];
    }

    /**
     * Decrypt AES-256-GCM encrypted health data payload received from ABDM Gateway/HIP.
     */
    public function decryptHealthData(string $encryptedData, string $sharedKey, string $iv, string $tag = ''): ?string
    {
        try {
            $rawCipher = base64_decode($encryptedData);
            $rawKey = base64_decode($sharedKey);
            $rawIv = base64_decode($iv);
            $rawTag = !empty($tag) ? base64_decode($tag) : '';

            $decrypted = openssl_decrypt(
                $rawCipher,
                'aes-256-gcm',
                $rawKey,
                OPENSSL_RAW_DATA,
                $rawIv,
                $rawTag
            );

            return $decrypted !== false ? $decrypted : null;
        } catch (\Throwable $e) {
            Log::error("ABDM Crypto Decryption Error: " . $e->getMessage());
            return null;
        }
    }
}
