<?php

namespace App\Console\Commands;

use App\Services\Abdm\AbdmClient;
use App\Services\Abdm\CareContextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TestAbdmOnDiscover extends Command
{
    protected $signature = 'abdm:test-ondiscover {--tx= : Transaction ID} {--req= : Request ID}';
    protected $description = 'Test outbound on-discover callback against ABDM Gateway with detailed diagnostics';

    public function handle(AbdmClient $client, CareContextService $careContextService): int
    {
        $this->info("=== Testing ABDM on-discover Diagnostics ===");

        // 1. Check Session Token
        try {
            $token = $client->getSessionToken(true);
            $this->info("Session token obtained: " . substr($token, 0, 15) . "...");
        } catch (\Throwable $e) {
            $this->error("Failed to obtain session token: " . $e->getMessage());
            return 1;
        }

        $txId = $this->option('tx') ?: '5e588c29-3c50-4059-b80d-f917dd4f4920';
        $inboundReqId = $this->option('req') ?: '80c2ac40-43f0-426a-bff7-ced67b901dba';

        $cleanDisplay = $careContextService->sanitizeAscii("Ophthalmology Consultation - 11 Sep 2026 with Dr. Vineet Gour");

        // Payload with 'resp' block
        $payloadWithResp = [
            'requestId' => (string) Str::uuid(),
            'timestamp' => $client->getIsoTimestamp(),
            'transactionId' => $txId,
            'patient' => [
                'referenceNumber' => 'P-3',
                'display' => 'Balkrishna Verma',
                'careContexts' => [
                    [
                        'referenceNumber' => 'OPD-APP-42778',
                        'display' => $cleanDisplay,
                    ],
                ],
                'matchedBy' => ['MOBILE'],
            ],
            'resp' => [
                'requestId' => $inboundReqId,
            ],
        ];

        // Payload without 'resp' block (standard V3 postman format)
        $payloadNoResp = [
            'requestId' => (string) Str::uuid(),
            'timestamp' => $client->getIsoTimestamp(),
            'transactionId' => $txId,
            'patient' => [
                'referenceNumber' => 'P-3',
                'display' => 'Balkrishna Verma',
                'careContexts' => [
                    [
                        'referenceNumber' => 'OPD-APP-42778',
                        'display' => $cleanDisplay,
                    ],
                ],
                'matchedBy' => ['MOBILE'],
            ],
        ];

        $v3Url = "{$client->getGatewayBaseUrl()}/user-initiated-linking/v3/patient/care-context/on-discover";
        $v05Url = "https://dev.abdm.gov.in/gateway/v0.5/care-contexts/on-discover";

        $headersV3 = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'X-CM-ID' => $client->getCmId(),
            'REQUEST-ID' => (string) Str::uuid(),
            'TIMESTAMP' => $client->getIsoTimestamp(),
        ];

        // Test 1: V3 with resp block
        $this->info("\n--- Test 1: V3 on-discover WITH resp block ---");
        $this->line("URL: {$v3Url}");
        $this->line("Payload: " . json_encode($payloadWithResp, JSON_PRETTY_PRINT));
        $res1 = Http::timeout(15)->withHeaders($headersV3)->post($v3Url, $payloadWithResp);
        $this->line("Status: " . $res1->status());
        $this->line("Body: " . $res1->body());
        $this->line("Headers: " . json_encode($res1->headers(), JSON_PRETTY_PRINT));

        // Test 2: V3 without resp block
        $this->info("\n--- Test 2: V3 on-discover WITHOUT resp block ---");
        $res2 = Http::timeout(15)->withHeaders($headersV3)->post($v3Url, $payloadNoResp);
        $this->line("Status: " . $res2->status());
        $this->line("Body: " . $res2->body());
        $this->line("Headers: " . json_encode($res2->headers(), JSON_PRETTY_PRINT));

        // Test 3: v0.5 endpoint
        $this->info("\n--- Test 3: v0.5 on-discover ---");
        try {
            $v05Token = $client->getV05SessionToken();
            $res3 = Http::timeout(15)->withHeaders([
                'Authorization' => 'Bearer ' . $v05Token,
                'Content-Type' => 'application/json',
                'X-CM-ID' => $client->getCmId(),
            ])->post($v05Url, $payloadWithResp);
            $this->line("Status: " . $res3->status());
            $this->line("Body: " . $res3->body());
        } catch (\Throwable $e) {
            $this->warn("v0.5 test error: " . $e->getMessage());
        }

        return 0;
    }
}
