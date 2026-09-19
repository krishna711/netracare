<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class SyncLegacyDatabaseCommand extends Command
{
    protected $signature = 'db:sync-legacy 
                            {--connection=mysql2 : The legacy database connection name}
                            {--dry-run : Simulate the migration without executing modifications}
                            {--force : Run the migration without prompt confirmation}';

    protected $description = 'Safely upgrade legacy hospital database (mysql2) schema and migrate data with zero data loss';

    public function handle(): int
    {
        $conn = $this->option('connection');
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("=========================================================");
        $this->info(" NetraCare: Legacy Database Synchronization & Upgrade");
        $this->info(" Target Connection : {$conn}");
        $this->info(" Dry Run Mode      : " . ($isDryRun ? "YES (Simulation Only)" : "NO (Live Execution)"));
        $this->info("=========================================================\n");

        // 1. Pre-flight connection test
        try {
            $dbName = DB::connection($conn)->getDatabaseName();
            $this->info("✔ Connected to database: {$dbName}");
        } catch (\Throwable $e) {
            $this->error("✖ Failed to connect to database '{$conn}': " . $e->getMessage());
            return Command::FAILURE;
        }

        // Verify key tables exist
        $requiredTables = ['appointments', 'patients', 'consultations', 'payments', 'users', 'ipds'];
        foreach ($requiredTables as $table) {
            if (!Schema::connection($conn)->hasTable($table)) {
                $this->error("✖ Critical table '{$table}' missing from connection '{$conn}'. Aborting.");
                return Command::FAILURE;
            }
        }

        // Display pre-flight counts
        $aptCount = DB::connection($conn)->table('appointments')->count();
        $patCount = DB::connection($conn)->table('patients')->count();
        $payCount = DB::connection($conn)->table('payments')->count();
        $conCount = DB::connection($conn)->table('consultations')->count();
        $ipdCount = DB::connection($conn)->table('ipds')->count();

        $this->info("\n--- Existing Hospital Records in {$dbName} ---");
        $this->line(" • Appointments  : " . number_format($aptCount));
        $this->line(" • Patients      : " . number_format($patCount));
        $this->line(" • Payments      : " . number_format($payCount));
        $this->line(" • Consultations : " . number_format($conCount));
        $this->line(" • Legacy IPDs   : " . number_format($ipdCount));
        $this->line("--------------------------------------------------\n");

        if (!$isDryRun && !$force) {
            if (!$this->confirm('Are you sure you want to proceed with upgrading the schema on ' . $dbName . '?', true)) {
                $this->warn('Aborted by user.');
                return Command::SUCCESS;
            }
        }

        // 2. Upgrade `users` table
        $this->info("\n[1/6] Upgrading 'users' table schema...");
        $this->upgradeUsersTable($conn, $isDryRun);

        // 3. Upgrade `patients` table
        $this->info("\n[2/6] Upgrading 'patients' table schema (ABDM fields)...");
        $this->upgradePatientsTable($conn, $isDryRun);

        // 4. Create missing tables
        $this->info("\n[3/6] Creating missing tables...");
        $this->createMissingTables($conn, $isDryRun);

        // 5. Migrate legacy IPD data (ipds -> ipds_v1)
        $this->info("\n[4/6] Migrating legacy IPD records to 'ipds_v1'...");
        $this->migrateIpdData($conn, $isDryRun);

        // 6. Sync clinic branding settings
        $this->info("\n[5/6] Synchronizing clinic branding & header settings...");
        $this->syncSettings($conn, $isDryRun);

        // 7. Update migrations table
        $this->info("\n[6/6] Updating migrations ledger...");
        $this->syncMigrationsLedger($conn, $isDryRun);

        $this->info("\n=========================================================");
        $this->info(" ✔ Synchronization completed successfully!");
        if ($isDryRun) {
            $this->warn(" (Note: This was a dry-run. No changes were committed to {$dbName}.)");
        } else {
            $this->info(" The database '{$dbName}' is now 100% compatible with NetraCare.");
        }
        $this->info("=========================================================\n");

        return Command::SUCCESS;
    }

    protected function upgradeUsersTable(string $conn, bool $dryRun): void
    {
        $schema = Schema::connection($conn);

        if (!$schema->hasColumn('users', 'role')) {
            $this->line("  + Adding column 'role' (VARCHAR 255 DEFAULT 'manager') to 'users'");
            if (!$dryRun) {
                $schema->table('users', function (Blueprint $table) {
                    $table->string('role')->default('manager')->after('email');
                });
            }
        } else {
            $this->line("  ✔ Column 'role' already exists on 'users'");
        }

        if (!$schema->hasColumn('users', 'permissions')) {
            $this->line("  + Adding column 'permissions' (JSON/LONGTEXT NULL) to 'users'");
            if (!$dryRun) {
                $schema->table('users', function (Blueprint $table) {
                    $table->json('permissions')->nullable()->after('role');
                });
            }
        } else {
            $this->line("  ✔ Column 'permissions' already exists on 'users'");
        }

        // Upgrade admin roles
        $this->line("  → Updating user roles: krishna711@gmail.com => super_admin, reception@gmail.com => manager");
        if (!$dryRun) {
            DB::connection($conn)->table('users')
                ->where('email', 'krishna711@gmail.com')
                ->update(['role' => 'super_admin']);

            DB::connection($conn)->table('users')
                ->where('email', 'reception@gmail.com')
                ->update(['role' => 'manager']);
        }
    }

    protected function upgradePatientsTable(string $conn, bool $dryRun): void
    {
        $schema = Schema::connection($conn);

        $fields = [
            'abha_number'      => fn(Blueprint $table) => $table->string('abha_number', 50)->nullable()->index()->after('mobile'),
            'abha_address'     => fn(Blueprint $table) => $table->string('abha_address', 100)->nullable()->index()->after('abha_number'),
            'abdm_status'      => fn(Blueprint $table) => $table->string('abdm_status', 30)->default('unverified')->after('abha_address'),
            'abdm_profile'     => fn(Blueprint $table) => $table->json('abdm_profile')->nullable()->after('abdm_status'),
            'abdm_verified_at' => fn(Blueprint $table) => $table->timestamp('abdm_verified_at')->nullable()->after('abdm_profile'),
        ];

        foreach ($fields as $field => $closure) {
            if (!$schema->hasColumn('patients', $field)) {
                $this->line("  + Adding column '{$field}' to 'patients'");
                if (!$dryRun) {
                    $schema->table('patients', $closure);
                }
            } else {
                $this->line("  ✔ Column '{$field}' already exists on 'patients'");
            }
        }
    }

    protected function createMissingTables(string $conn, bool $dryRun): void
    {
        $schema = Schema::connection($conn);

        // 1. ipds_v1
        if (!$schema->hasTable('ipds_v1')) {
            $this->line("  + Creating table 'ipds_v1' (Legacy HTML rich-text details)");
            if (!$dryRun) {
                $schema->create('ipds_v1', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('patient_id')->index();
                    $table->unsignedBigInteger('appointment_id')->index();
                    $table->longText('details')->nullable();
                    $table->timestamps();
                });
            }
        } else {
            $this->line("  ✔ Table 'ipds_v1' already exists");
        }

        // 2. ipds_v2
        if (!$schema->hasTable('ipds_v2')) {
            $this->line("  + Creating table 'ipds_v2' (Structured discharge summaries)");
            if (!$dryRun) {
                $schema->create('ipds_v2', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('patient_id')->index();
                    $table->unsignedBigInteger('appointment_id')->index();

                    $table->date('date_of_admission')->nullable();
                    $table->date('date_of_surgery')->nullable();
                    $table->date('date_of_discharge')->nullable();

                    $table->string('final_diagnosis')->nullable();
                    $table->text('procedure_surgery')->nullable();
                    $table->string('surgeon_name')->nullable();
                    $table->text('investigation_during_hospitalization')->nullable();
                    $table->string('condition_on_discharge')->nullable();

                    $table->date('next_followup_date')->nullable();
                    $table->string('post_operative_rest')->nullable();
                    $table->text('special_instruction')->nullable();

                    $table->text('prescription')->nullable();
                    $table->text('instruction')->nullable();

                    $table->timestamps();
                });
            }
        } else {
            $this->line("  ✔ Table 'ipds_v2' already exists");
        }

        // 3. optometrist_worksheets
        if (!$schema->hasTable('optometrist_worksheets')) {
            $this->line("  + Creating table 'optometrist_worksheets'");
            if (!$dryRun) {
                $schema->create('optometrist_worksheets', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('patient_id')->index();
                    $table->unsignedBigInteger('appointment_id')->index();

                    $table->string('optometrist_name')->nullable();
                    $table->string('optometrist_id_no')->nullable();

                    // Visual Acuity
                    $table->string('va_re_unaided')->nullable();
                    $table->string('va_re_with_glass')->nullable();
                    $table->string('va_re_near')->nullable();
                    $table->string('va_re_pg_sph')->nullable();
                    $table->string('va_re_pg_cyl')->nullable();
                    $table->string('va_re_pg_axis')->nullable();

                    $table->string('va_le_unaided')->nullable();
                    $table->string('va_le_with_glass')->nullable();
                    $table->string('va_le_near')->nullable();
                    $table->string('va_le_pg_sph')->nullable();
                    $table->string('va_le_pg_cyl')->nullable();
                    $table->string('va_le_pg_axis')->nullable();

                    // Dry Retinoscopy
                    $table->string('dry_re_sph')->nullable();
                    $table->string('dry_re_cyl')->nullable();
                    $table->string('dry_re_axis')->nullable();
                    $table->string('dry_re_vision')->nullable();

                    $table->string('dry_le_sph')->nullable();
                    $table->string('dry_le_cyl')->nullable();
                    $table->string('dry_le_axis')->nullable();
                    $table->string('dry_le_vision')->nullable();

                    $table->string('dry_remark')->nullable();
                    $table->string('dry_dd')->nullable();

                    // Wet Retinoscopy
                    $table->string('wet_re_sph')->nullable();
                    $table->string('wet_re_cyl')->nullable();
                    $table->string('wet_re_axis')->nullable();
                    $table->string('wet_re_vision')->nullable();

                    $table->string('wet_le_sph')->nullable();
                    $table->string('wet_le_cyl')->nullable();
                    $table->string('wet_le_axis')->nullable();
                    $table->string('wet_le_vision')->nullable();

                    // Final Prescription
                    $table->string('fp_re_sph')->nullable();
                    $table->string('fp_re_cyl')->nullable();
                    $table->string('fp_re_axis')->nullable();
                    $table->string('fp_re_bcva')->nullable();
                    $table->string('fp_re_near_add')->nullable();

                    $table->string('fp_le_sph')->nullable();
                    $table->string('fp_le_cyl')->nullable();
                    $table->string('fp_le_axis')->nullable();
                    $table->string('fp_le_bcva')->nullable();
                    $table->string('fp_le_near_add')->nullable();

                    $table->string('fp_remark')->nullable();
                    $table->string('fp_amount_glass')->nullable();

                    $table->timestamps();
                });
            }
        } else {
            $this->line("  ✔ Table 'optometrist_worksheets' already exists");
        }

        // 4. abdm_scan_shares
        if (!$schema->hasTable('abdm_scan_shares')) {
            $this->line("  + Creating table 'abdm_scan_shares' (ABDM M1)");
            if (!$dryRun) {
                $schema->create('abdm_scan_shares', function (Blueprint $table) {
                    $table->id();
                    $table->string('request_id')->unique();
                    $table->string('hip_id')->nullable();
                    $table->string('counter_id')->default('1');
                    $table->string('token_number')->nullable();
                    $table->string('abha_number', 50)->nullable();
                    $table->string('abha_address', 100)->nullable();
                    $table->string('name')->nullable();
                    $table->string('gender', 10)->nullable();
                    $table->string('dob', 20)->nullable();
                    $table->string('mobile', 20)->nullable();
                    $table->text('address')->nullable();
                    $table->json('raw_profile')->nullable();
                    $table->string('status', 20)->default('pending');
                    $table->unsignedBigInteger('patient_id')->nullable();
                    $table->unsignedBigInteger('appointment_id')->nullable();
                    $table->timestamps();

                    $table->index('status');
                    $table->index('counter_id');
                });
            }
        } else {
            $this->line("  ✔ Table 'abdm_scan_shares' already exists");
        }

        // 5. abdm_care_contexts
        if (!$schema->hasTable('abdm_care_contexts')) {
            $this->line("  + Creating table 'abdm_care_contexts' (ABDM M2)");
            if (!$dryRun) {
                $schema->create('abdm_care_contexts', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('patient_id')->index();
                    $table->string('care_context_reference')->unique();
                    $table->string('display_name');
                    $table->string('hi_type')->default('OPConsultation');
                    $table->unsignedBigInteger('appointment_id')->nullable()->index();
                    $table->unsignedBigInteger('consultation_id')->nullable()->index();
                    $table->string('patient_reference')->nullable();
                    $table->string('status')->default('created');
                    $table->timestamp('linked_at')->nullable();
                    $table->string('link_token')->nullable();
                    $table->json('metadata')->nullable();
                    $table->timestamps();
                });
            }
        } else {
            $this->line("  ✔ Table 'abdm_care_contexts' already exists");
        }

        // 6. abdm_consents
        if (!$schema->hasTable('abdm_consents')) {
            $this->line("  + Creating table 'abdm_consents' (ABDM M3)");
            if (!$dryRun) {
                $schema->create('abdm_consents', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('patient_id')->nullable()->index();
                    $table->string('consent_request_id')->unique()->index();
                    $table->string('consent_id')->nullable()->index();
                    $table->string('status', 50)->default('REQUESTED')->index();
                    $table->string('purpose_code', 50)->default('CAREMGT');
                    $table->json('hi_types')->nullable();
                    $table->timestamp('date_from')->nullable();
                    $table->timestamp('date_to')->nullable();
                    $table->timestamp('data_erase_at')->nullable();
                    $table->string('transaction_id')->nullable()->index();
                    $table->json('consent_artefact')->nullable();
                    $table->json('key_material')->nullable();
                    $table->json('transferred_records')->nullable();
                    $table->json('metadata')->nullable();
                    $table->timestamps();
                });
            }
        } else {
            $this->line("  ✔ Table 'abdm_consents' already exists");
        }
    }

    protected function migrateIpdData(string $conn, bool $dryRun): void
    {
        $sourceCount = DB::connection($conn)->table('ipds')->count();
        $this->line("  → Found {$sourceCount} legacy records in 'ipds'");

        if ($dryRun) {
            $this->line("  [Dry Run] Would copy {$sourceCount} rows from 'ipds' to 'ipds_v1'");
            return;
        }

        // Copy records preserving ID, patient_id, appointment_id, details, timestamps
        $copied = DB::connection($conn)->affectingStatement("
            INSERT IGNORE INTO `ipds_v1` (`id`, `patient_id`, `appointment_id`, `details`, `created_at`, `updated_at`)
            SELECT `id`, `patient_id`, `appointment_id`, `details`, `created_at`, `updated_at`
            FROM `ipds`
        ");

        $targetCount = DB::connection($conn)->table('ipds_v1')->count();
        $this->info("  ✔ Successfully migrated to 'ipds_v1'. Current row count: {$targetCount} / {$sourceCount}");
    }

    protected function syncSettings(string $conn, bool $dryRun): void
    {
        $existingKeys = DB::connection($conn)->table('settings')->pluck('key')->toArray();

        // Standard default branding & print header settings
        $defaultSettings = [
            'title_english'       => ['name' => 'Title English', 'description' => 'Hospital Title in English', 'value' => 'NetraCare'],
            'title_hindi'         => ['name' => 'Title Hindi', 'description' => 'Hospital Title in Hindi', 'value' => 'नेत्रिका नेत्रालय'],
            'phone_1'             => ['name' => 'Phone Number 1', 'description' => 'Primary Phone Number', 'value' => ' 0755-4225186, 9893086699, 8959885339'],
            'phone_2'             => ['name' => 'Phone Number 2', 'description' => 'Secondary Phone Number', 'value' => null],
            'phone_3'             => ['name' => 'Phone Number 3', 'description' => 'Tertiary Phone Number', 'value' => null],
            'address_english'     => ['name' => 'Address English', 'description' => 'Address in English', 'value' => '113, Jyoti Nagar, Near Kendriya Vidhyalaya-3 & Aashima Mall, Narmadapuram Road, Bhopal - 462026 Phone: 9893086699'],
            'address_hindi'       => ['name' => 'Address Hindi', 'description' => 'Address in Hindi', 'value' => null],
            'timings_english'     => ['name' => 'Timings English', 'description' => 'Timings in English', 'value' => 'Morning 10AM To 1PM & Evening 6PM To 8PM, Sunday Closed'],
            'timings_hindi'       => ['name' => 'Timings Hindi', 'description' => 'Timings in Hindi', 'value' => null],
            'registration_number' => ['name' => 'Registration Number', 'description' => 'Hospital Registration Number', 'value' => 'BH/123456'],
            'info_english'        => ['name' => 'Addition Information English', 'description' => 'Additional Information in English', 'value' => 'चश्मे से सम्बंधित कोई भी जानकारी के लिए संपर्क करें : 7909444901'],
            'info_hindi'          => ['name' => 'Addition Information Hindi', 'description' => 'Additional Information in Hindi', 'value' => 'चश्मे से सम्बंधित कोई भी जानकारी के लिए संपर्क करें : 7909444901'],
            'banner_1'            => ['name' => 'Banner Image 1', 'description' => 'Banner Image 1', 'value' => 'banners/01KPJXNMWGR3PE3HJH3R2QCFKK.jpg'],
            'banner_2'            => ['name' => 'Banner Image 2', 'description' => 'Banner Image 2', 'value' => 'banners/01KPJP6MP42TP53KDDQK7TFHDQ.jpg'],
            'banner_3'            => ['name' => 'Banner Image 3', 'description' => 'Banner Image 3', 'value' => 'banners/01KPJP78CJTBHBTRWPE47XGYRX.jpg'],
            'abdm_hip_id'         => ['name' => 'ABDM HIP ID', 'description' => 'ABDM Health Facility ID', 'value' => 'IN2310001444'],
            'abdm_facility_name'  => ['name' => 'ABDM Facility Name', 'description' => 'ABDM Facility Name', 'value' => 'Netrika Netralaya'],
            'abdm_counter_id'     => ['name' => 'ABDM Counter ID', 'description' => 'Counter ID for Scan & Share', 'value' => '1'],
            'abdm_client_id'      => ['name' => 'ABDM Client ID', 'description' => 'Bridge ID for ABDM Gateway', 'value' => 'SBXID_075083'],
            'abdm_env'            => ['name' => 'ABDM Environment', 'description' => 'sandbox or production', 'value' => 'sandbox'],
            'abdm_cm_id'          => ['name' => 'ABDM CM ID', 'description' => 'sbx or abdm', 'value' => 'sbx'],
            'abdm_public_url'     => ['name' => 'ABDM Public URL', 'description' => 'Public webhook URL', 'value' => 'https://netracare.netrikanetralaya.com'],
        ];

        // Also try reading from netracare DB if available
        try {
            $rawNetraCareSettings = DB::select("SELECT * FROM netracare.settings");
            foreach ($rawNetraCareSettings as $row) {
                if (!isset($defaultSettings[$row->key]) && !in_array($row->key, $existingKeys)) {
                    $defaultSettings[$row->key] = [
                        'name'        => $row->name ?? $row->key,
                        'description' => $row->description ?? '',
                        'value'       => $row->value ?? '',
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Ignore if netracare DB does not exist
        }

        $added = 0;
        foreach ($defaultSettings as $key => $data) {
            if (!in_array($key, $existingKeys)) {
                $this->line("  + Adding setting '{$key}'");
                if (!$dryRun) {
                    DB::connection($conn)->table('settings')->insert([
                        'key'         => $key,
                        'name'        => $data['name'],
                        'description' => $data['description'] ?? '',
                        'value'       => $data['value'],
                        'field'       => '{"name":"value","type":"text","title":"Value"}',
                        'active'      => 1,
                    ]);
                }
                $added++;
            }
        }

        $this->info("  ✔ {$added} new settings synchronized into '{$conn}.settings'");
    }

    protected function syncMigrationsLedger(string $conn, bool $dryRun): void
    {
        $newMigrations = [
            '2026_04_24_054130_create_ipds_v1_and_v2_tables',
            '2026_04_26_145947_add_role_and_permissions_to_users_table',
            '2026_04_27_000001_create_optometrist_worksheets_table',
            '2026_09_10_000001_add_abdm_fields_to_patients_table',
            '2026_09_10_000002_create_abdm_scan_shares_table',
            '2026_09_11_000001_create_abdm_care_contexts_table',
            '2026_09_16_000001_create_abdm_consents_table',
        ];

        $existingMigrations = DB::connection($conn)->table('migrations')->pluck('migration')->toArray();
        $maxBatch = (int) (DB::connection($conn)->table('migrations')->max('batch') ?? 0);
        $nextBatch = $maxBatch + 1;

        $recorded = 0;
        foreach ($newMigrations as $migration) {
            if (!in_array($migration, $existingMigrations)) {
                $this->line("  + Recording '{$migration}' in migrations ledger");
                if (!$dryRun) {
                    DB::connection($conn)->table('migrations')->insert([
                        'migration' => $migration,
                        'batch'     => $nextBatch,
                    ]);
                }
                $recorded++;
            }
        }

        $this->info("  ✔ {$recorded} migrations recorded in '{$conn}.migrations' (batch {$nextBatch})");
    }
}
