<?php

namespace App\Console\Commands;

use App\Models\AbdmCareContext;
use App\Models\AbdmConsent;
use App\Models\AbdmScanShare;
use App\Models\Patient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AbdmResetSandboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'abdm:reset-sandbox 
                            {--patient= : Reset ABHA for a specific Patient UHID only} 
                            {--all : Purge all ABDM records (including non-sbx if any)}
                            {--dry-run : Display what would be deleted without making any changes}
                            {--force : Force operation without interactive confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely reset ABDM Sandbox test data (ABHA IDs, Care Contexts, Consents) before switching to Production';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("=================================================");
        $this->info("   ABDM Sandbox Test Data Reset Utility");
        $this->info("=================================================");

        $patientId = $this->option('patient');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $purgeAll = (bool) $this->option('all');

        // 1. Query Affected Patients
        $patientsQuery = Patient::query();
        if ($patientId) {
            $patientsQuery->where('id', $patientId);
        } elseif (!$purgeAll) {
            // Default: Target sandbox accounts (ending in @sbx or with test abdm status)
            $patientsQuery->where(function ($q) {
                $q->where('abha_address', 'like', '%@sbx%')
                  ->orWhere('abdm_status', 'like', '%sbx%')
                  ->orWhereNotNull('abdm_verified_at');
            });
        } else {
            $patientsQuery->whereNotNull('abha_number')->orWhereNotNull('abha_address');
        }

        $patients = $patientsQuery->get(['id', 'name', 'mobile', 'abha_number', 'abha_address', 'abdm_status']);

        // 2. Query Affected ABDM Bridge Records
        $careContextsQuery = AbdmCareContext::query();
        $consentsQuery = AbdmConsent::query();
        $scanSharesQuery = AbdmScanShare::query();

        if ($patientId) {
            $careContextsQuery->where('patient_id', $patientId);
            $consentsQuery->where('patient_id', $patientId);
            $scanSharesQuery->where('patient_id', $patientId);
        }

        $careContextsCount = $careContextsQuery->count();
        $consentsCount = $consentsQuery->count();
        $scanSharesCount = $scanSharesQuery->count();

        // 3. Display Summary Table
        $this->table(
            ['Resource', 'Targeted Count', 'Action Taken'],
            [
                ['Patients with ABHA Linked', $patients->count(), 'Reset ABHA fields (Number, Address, Status, Profile)'],
                ['ABDM Care Contexts (M2)', $careContextsCount, 'Delete Sandbox Care Context Linkages'],
                ['ABDM Consents & Records (M3)', $consentsCount, 'Delete Test Consent Artefacts & Data Flow Logs'],
                ['ABDM Scan & Share Tokens (M1)', $scanSharesCount, 'Delete Test Counter Tokens'],
                ['Appointments & Medical History', 'ALL PRESERVED', 'NOT TOUCHED (Zero data loss to clinical records)'],
            ]
        );

        if ($patients->isNotEmpty()) {
            $this->newLine();
            $this->info("Patients targeted for ABHA reset:");
            $this->table(
                ['UHID', 'Name', 'Mobile', 'ABHA Number', 'ABHA Address', 'Status'],
                $patients->map(fn ($p) => [
                    $p->id,
                    $p->name,
                    $p->mobile,
                    $p->abha_number ?? 'None',
                    $p->abha_address ?? 'None',
                    $p->abdm_status ?? 'unverified',
                ])
            );
        } else {
            $this->warn("No sandbox patients found matching the criteria.");
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn("DRY-RUN MODE: No changes were committed to the database.");
            return 0;
        }

        if ($patients->isEmpty() && $careContextsCount === 0 && $consentsCount === 0 && $scanSharesCount === 0) {
            $this->info("Nothing to reset. Database is already clean.");
            return 0;
        }

        // 4. Confirmation Prompt
        if (!$force) {
            $this->newLine();
            $confirmed = $this->confirm(
                "Are you sure you want to reset these ABDM Sandbox records? Clinical appointments and patient records will NOT be deleted.",
                false
            );

            if (!$confirmed) {
                $this->warn("Operation cancelled by user.");
                return 0;
            }
        }

        // 5. Execute Reset inside Transaction
        DB::transaction(function () use ($patients, $careContextsQuery, $consentsQuery, $scanSharesQuery) {
            // Reset Patient ABHA fields
            foreach ($patients as $patient) {
                $patient->update([
                    'abha_number' => null,
                    'abha_address' => null,
                    'abdm_status' => 'unverified',
                    'abdm_profile' => null,
                    'abdm_verified_at' => null,
                ]);
            }

            // Delete bridge records
            $careContextsQuery->delete();
            $consentsQuery->delete();
            $scanSharesQuery->delete();
        });

        $this->newLine();
        $this->info("✓ Successfully reset ABDM Sandbox data!");
        $this->info("  - {$patients->count()} patient ABHA profile(s) reset to unverified.");
        $this->info("  - {$careContextsCount} care context linkage(s) removed.");
        $this->info("  - {$consentsCount} consent record(s) removed.");
        $this->info("  - {$scanSharesCount} scan & share token(s) removed.");
        $this->info("  - All patient visits, consultations, and prescriptions remain 100% intact.");
        $this->newLine();
        $this->comment("You can now connect to ABDM Production and link real ABHA credentials.");

        return 0;
    }
}
