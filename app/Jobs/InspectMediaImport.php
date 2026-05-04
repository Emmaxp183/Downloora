<?php

namespace App\Jobs;

use App\Enums\MediaImportStatus;
use App\Models\MediaImport;
use App\Services\Media\YtDlpClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class InspectMediaImport implements ShouldQueue
{
    use Queueable;

    public function __construct(public MediaImport $mediaImport) {}

    public function handle(YtDlpClient $client): void
    {
        $mediaImport = $this->mediaImport->fresh(['user']);

        if (! $mediaImport instanceof MediaImport || $mediaImport->status !== MediaImportStatus::Inspecting) {
            return;
        }

        try {
            $metadata = $client->inspect($mediaImport->source_url);
            $formats = $metadata['formats'];
            $estimatedSize = collect($formats)
                ->pluck('size_bytes')
                ->filter()
                ->max();

            $mediaImport->forceFill([
                'source_domain' => $metadata['source_domain'],
                'title' => $metadata['title'] ?: parse_url($mediaImport->source_url, PHP_URL_HOST),
                'thumbnail_url' => $metadata['thumbnail_url'],
                'duration_seconds' => $metadata['duration_seconds'],
                'estimated_size_bytes' => $estimatedSize,
                'formats' => $formats,
                'status' => MediaImportStatus::Ready,
                'progress' => 0,
                'error_message' => null,
                'inspected_at' => now(),
            ])->save();
        } catch (Throwable $throwable) {
            $mediaImport->forceFill([
                'status' => MediaImportStatus::InspectionFailed,
                'error_message' => $throwable->getMessage(),
            ])->save();
        }
    }
}
