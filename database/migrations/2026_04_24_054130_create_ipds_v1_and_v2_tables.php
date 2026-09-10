<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // IPD V1 - Simple HTML rich-text details (legacy/original)
        Schema::create('ipds_v1', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('appointment_id');
            $table->longText('details')->nullable();
            $table->timestamps();
        });

        // IPD V2 - Structured discharge sheet with multiple fields
        Schema::create('ipds_v2', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('appointment_id');

            // Admission info
            $table->date('date_of_admission')->nullable();
            $table->date('date_of_surgery')->nullable();
            $table->date('date_of_discharge')->nullable();

            // Clinical fields
            $table->string('final_diagnosis')->nullable();
            $table->text('procedure_surgery')->nullable();
            $table->string('surgeon_name')->nullable();
            $table->text('investigation_during_hospitalization')->nullable();
            $table->string('condition_on_discharge')->nullable();

            // Follow-up & instructions
            $table->date('next_followup_date')->nullable();
            $table->string('post_operative_rest')->nullable();
            $table->text('special_instruction')->nullable();

            // Medication (stored like consultation prescription: pipe+tilde separated)
            $table->text('prescription')->nullable();

            // Next follow-up note (cut-paste / free text)
            $table->text('instruction')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipds_v1');
        Schema::dropIfExists('ipds_v2');
    }
};
