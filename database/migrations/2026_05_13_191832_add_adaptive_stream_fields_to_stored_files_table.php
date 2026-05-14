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
        Schema::table('stored_files', function (Blueprint $table) {
            $table->string('adaptive_stream_status')->nullable()->after('size_bytes');
            $table->string('adaptive_stream_disk')->nullable()->after('adaptive_stream_status');
            $table->string('adaptive_stream_bucket')->nullable()->after('adaptive_stream_disk');
            $table->string('adaptive_stream_playlist_key')->nullable()->after('adaptive_stream_bucket');
            $table->json('adaptive_stream_variants')->nullable()->after('adaptive_stream_playlist_key');
            $table->text('adaptive_stream_error')->nullable()->after('adaptive_stream_variants');
            $table->timestamp('adaptive_stream_generated_at')->nullable()->after('adaptive_stream_error');

            $table->index(['adaptive_stream_status', 'adaptive_stream_generated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stored_files', function (Blueprint $table) {
            $table->dropIndex(['adaptive_stream_status', 'adaptive_stream_generated_at']);
            $table->dropColumn([
                'adaptive_stream_status',
                'adaptive_stream_disk',
                'adaptive_stream_bucket',
                'adaptive_stream_playlist_key',
                'adaptive_stream_variants',
                'adaptive_stream_error',
                'adaptive_stream_generated_at',
            ]);
        });
    }
};
