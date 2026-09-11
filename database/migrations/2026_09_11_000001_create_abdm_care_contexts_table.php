<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('abdm_care_contexts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id')->index();
            $table->string('care_context_reference')->unique();
            $table->string('display_name');
            $table->string('hi_type')->default('OPConsultation'); // OPConsultation, Prescription, DiagnosticReport
            $table->unsignedBigInteger('appointment_id')->nullable()->index();
            $table->unsignedBigInteger('consultation_id')->nullable()->index();
            $table->string('patient_reference')->nullable();
            $table->string('status')->default('created'); // created, linked, unlinked, failed
            $table->timestamp('linked_at')->nullable();
            $table->string('link_token')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abdm_care_contexts');
    }
};
