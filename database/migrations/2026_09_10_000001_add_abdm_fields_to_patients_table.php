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
        Schema::table('patients', function (Blueprint $table) {
            $table->string('abha_number', 50)->nullable()->index()->after('mobile');
            $table->string('abha_address', 100)->nullable()->index()->after('abha_number');
            $table->string('abdm_status', 30)->default('unverified')->after('abha_address');
            $table->json('abdm_profile')->nullable()->after('abdm_status');
            $table->timestamp('abdm_verified_at')->nullable()->after('abdm_profile');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn([
                'abha_number',
                'abha_address',
                'abdm_status',
                'abdm_profile',
                'abdm_verified_at',
            ]);
        });
    }
};
