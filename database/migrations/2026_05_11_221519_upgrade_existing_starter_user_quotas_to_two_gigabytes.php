<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const StarterQuotaBytes = 2 * 1024 * 1024 * 1024;

    private const LegacyStarterQuotaBytes = 700 * 1024 * 1024;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('storage_quota_bytes', self::LegacyStarterQuotaBytes)
            ->update(['storage_quota_bytes' => self::StarterQuotaBytes]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->where('storage_quota_bytes', self::StarterQuotaBytes)
            ->update(['storage_quota_bytes' => self::LegacyStarterQuotaBytes]);
    }
};
