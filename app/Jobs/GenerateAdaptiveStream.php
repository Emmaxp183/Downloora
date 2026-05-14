<?php

namespace App\Jobs;

use App\Models\StoredFile;
use App\Services\Media\AdaptiveStreamGenerator;
use App\Support\VideoFiles;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateAdaptiveStream implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 3900;

    public function __construct(public StoredFile $storedFile) {}

    public function handle(AdaptiveStreamGenerator $generator): void
    {
        $storedFile = $this->storedFile->fresh();

        if (! $storedFile instanceof StoredFile || ! VideoFiles::isVideo($storedFile)) {
            return;
        }

        if ($storedFile->adaptive_stream_status === 'ready') {
            return;
        }

        $storedFile->forceFill([
            'adaptive_stream_status' => 'processing',
            'adaptive_stream_error' => null,
        ])->save();

        $previousDisk = $storedFile->adaptive_stream_disk;
        $previousPlaylistKey = $storedFile->adaptive_stream_playlist_key;

        try {
            $output = $generator->generate($storedFile);

            $storedFile->forceFill([
                'adaptive_stream_status' => 'ready',
                'adaptive_stream_disk' => $output['disk'],
                'adaptive_stream_bucket' => $output['bucket'],
                'adaptive_stream_playlist_key' => $output['playlist_key'],
                'adaptive_stream_variants' => $output['variants'],
                'adaptive_stream_error' => null,
                'adaptive_stream_generated_at' => now(),
            ])->save();

            $this->deletePreviousStream($previousDisk, $previousPlaylistKey);
        } catch (Throwable $throwable) {
            $storedFile->forceFill([
                'adaptive_stream_status' => 'failed',
                'adaptive_stream_error' => $throwable->getMessage(),
            ])->save();

            throw $throwable;
        }
    }

    private function deletePreviousStream(?string $disk, ?string $playlistKey): void
    {
        if ($disk === null || $playlistKey === null) {
            return;
        }

        Storage::disk($disk)->deleteDirectory(dirname($playlistKey));
    }
}
