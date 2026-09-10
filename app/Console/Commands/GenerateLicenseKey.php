<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LicenseService;

class GenerateLicenseKey extends Command
{
    protected $signature = 'license:generate {hwid} {--name=Clinic} {--days=365}';

    protected $description = 'Generate a time-limited software license key for a specific Hardware ID';

    public function handle()
    {
        $hwid = $this->argument('hwid');
        $licenseTo = $this->option('name');
        $days = (int) $this->option('days');
        
        $key = LicenseService::generateLicensePayload($hwid, $licenseTo, $days);
        
        $this->info("Generated License Key for HWID: {$hwid}");
        $this->info("Licensed To: {$licenseTo}");
        $this->info("Valid For: {$days} days");
        $this->line("");
        $this->info("License Key:");
        $this->line($key);
        
        return Command::SUCCESS;
    }
}
