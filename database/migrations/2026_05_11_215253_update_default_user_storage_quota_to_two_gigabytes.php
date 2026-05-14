<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const StarterQuotaBytes = 2 * 1024 * 1024 * 1024;

    private const LegacyStarterQuotaBytes = 700 * 1024 * 1024;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('storage_quota_bytes')
                ->default(self::StarterQuotaBytes)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('storage_quota_bytes')
                ->default(self::LegacyStarterQuotaBytes)
                ->change();
        });
    }
};
