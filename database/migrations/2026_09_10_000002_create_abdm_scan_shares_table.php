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
        Schema::create('abdm_scan_shares', function (Blueprint $table) {
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
            $table->string('status', 20)->default('pending'); // pending, registered, cancelled
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('counter_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abdm_scan_shares');
    }
};
