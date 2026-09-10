<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('optometrist_worksheets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('appointment_id');

            // Optometrist info
            $table->string('optometrist_name')->nullable();
            $table->string('optometrist_id_no')->nullable();

            // Visual Acuity - Right Eye
            $table->string('va_re_unaided')->nullable();
            $table->string('va_re_with_glass')->nullable();
            $table->string('va_re_near')->nullable();
            $table->string('va_re_pg_sph')->nullable();
            $table->string('va_re_pg_cyl')->nullable();
            $table->string('va_re_pg_axis')->nullable();

            // Visual Acuity - Left Eye
            $table->string('va_le_unaided')->nullable();
            $table->string('va_le_with_glass')->nullable();
            $table->string('va_le_near')->nullable();
            $table->string('va_le_pg_sph')->nullable();
            $table->string('va_le_pg_cyl')->nullable();
            $table->string('va_le_pg_axis')->nullable();

            // Dry Retinoscopy (Dry Acceptance) - Right Eye
            $table->string('dry_re_sph')->nullable();
            $table->string('dry_re_cyl')->nullable();
            $table->string('dry_re_axis')->nullable();
            $table->string('dry_re_vision')->nullable();

            // Dry Retinoscopy - Left Eye
            $table->string('dry_le_sph')->nullable();
            $table->string('dry_le_cyl')->nullable();
            $table->string('dry_le_axis')->nullable();
            $table->string('dry_le_vision')->nullable();

            $table->string('dry_remark')->nullable();
            $table->string('dry_dd')->nullable();

            // Wet Retinoscopy - Right Eye
            $table->string('wet_re_sph')->nullable();
            $table->string('wet_re_cyl')->nullable();
            $table->string('wet_re_axis')->nullable();
            $table->string('wet_re_vision')->nullable();

            // Wet Retinoscopy - Left Eye
            $table->string('wet_le_sph')->nullable();
            $table->string('wet_le_cyl')->nullable();
            $table->string('wet_le_axis')->nullable();
            $table->string('wet_le_vision')->nullable();

            // Final Glass Prescription - Right Eye
            $table->string('fp_re_sph')->nullable();
            $table->string('fp_re_cyl')->nullable();
            $table->string('fp_re_axis')->nullable();
            $table->string('fp_re_bcva')->nullable();
            $table->string('fp_re_near_add')->nullable();

            // Final Glass Prescription - Left Eye
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

    public function down(): void
    {
        Schema::dropIfExists('optometrist_worksheets');
    }
};
