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
        Schema::create('torrents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type');
            $table->longText('magnet_uri')->nullable();
            $table->string('torrent_file_path')->nullable();
            $table->string('info_hash')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('status')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedBigInteger('total_size_bytes')->nullable();
            $table->unsignedBigInteger('downloaded_bytes')->default(0);
            $table->string('qbittorrent_hash')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('torrents');
    }
};
