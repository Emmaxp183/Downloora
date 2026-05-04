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
        Schema::create('media_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stored_file_id')->nullable()->constrained('stored_files')->nullOnDelete();
            $table->text('source_url');
            $table->string('source_domain')->nullable();
            $table->string('title')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->string('status')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('estimated_size_bytes')->nullable();
            $table->unsignedBigInteger('downloaded_bytes')->default(0);
            $table->json('formats')->nullable();
            $table->json('selected_format')->nullable();
            $table->string('local_file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_imports');
    }
};
