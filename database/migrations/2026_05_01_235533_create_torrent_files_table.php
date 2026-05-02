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
        Schema::create('torrent_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('torrent_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->boolean('selected')->default(true);
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamps();

            $table->index(['torrent_id', 'path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('torrent_files');
    }
};
