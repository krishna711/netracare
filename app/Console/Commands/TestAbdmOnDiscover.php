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

        $txId = $this->option('tx') ?: '82f43d76-0f9e-498f-844b-dc2e8d6c67be';
        $inboundReqId = $this->option('req') ?: '950f75b5-efbd-41a0-87ed-74acb807407b';

        $cleanDisplay = $careContextService->sanitizeAscii("Ophthalmology Consultation - 11 Sep 2026 with Dr. Vineet Gour");

        // Official NHA Milestone 2 Postman Collection Payload
        $payloadOfficialNha = [
            'transactionId' => $txId,
            'patient' => [
                [
                    'referenceNumber' => 'P-3',
                    'display' => 'Balkrishna Verma',
                    'careContexts' => [
                        [
                            'referenceNumber' => 'OPD-APP-42778',
                            'display' => $cleanDisplay,
                        ],
                    ],
                    'hiType' => 'OPConsultation',
                    'count' => 1,
                ],
            ],
            'matchedBy' => ['MOBILE'],
            'response' => [
                'requestId' => $inboundReqId,
            ],
        ];

        $v3Url = "{$client->getGatewayBaseUrl()}/user-initiated-linking/v3/patient/care-context/on-discover";

        $headersV3 = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'X-CM-ID' => $client->getCmId(),
            'REQUEST-ID' => (string) Str::uuid(),
            'TIMESTAMP' => $client->getIsoTimestamp(),
        ];

        // Test 1: Official NHA Milestone 2 Postman Schema
        $this->info("\n--- Test 1: Official NHA Milestone 2 on-discover ---");
        $this->line("URL: {$v3Url}");
        $this->line("Payload: " . json_encode($payloadOfficialNha, JSON_PRETTY_PRINT));
        $res1 = Http::timeout(15)->withHeaders($headersV3)->post($v3Url, $payloadOfficialNha);
        $this->line("Status: " . $res1->status());
        $this->line("Body: " . $res1->body());
        $this->line("Headers: " . json_encode($res1->headers(), JSON_PRETTY_PRINT));

        return 0;
    }
}
