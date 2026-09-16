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
        Schema::dropIfExists('abdm_consents');

        Schema::create('abdm_consents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->string('consent_request_id')->unique()->index();
            $table->string('consent_id')->nullable()->index();
            $table->string('status', 50)->default('REQUESTED')->index(); // REQUESTED, GRANTED, DENIED, EXPIRED, REVOKED, TRANSFERRED
            $table->string('purpose_code', 50)->default('CAREMGT');
            $table->json('hi_types')->nullable(); // ["Prescription", "DiagnosticReport", "OPConsultation"]
            $table->timestamp('date_from')->nullable();
            $table->timestamp('date_to')->nullable();
            $table->timestamp('data_erase_at')->nullable();
            $table->string('transaction_id')->nullable()->index(); // for data flow
            $table->json('consent_artefact')->nullable();
            $table->json('key_material')->nullable(); // Ephemeral ECDH keys & nonce
            $table->json('transferred_records')->nullable(); // Decrypted FHIR bundles
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abdm_consents');
    }
};
