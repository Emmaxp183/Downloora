<?php

namespace App\Console\Commands;

use App\Jobs\GenerateAdaptiveStream;
use App\Models\StoredFile;
use App\Support\VideoFiles;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:generate-adaptive-streams {--force : Regenerate streams that are already ready or processing}')]
#[Description('Queue adaptive HLS generation for stored video files.')]
class GenerateAdaptiveStreams extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! (bool) config('media.adaptive.enabled', true)) {
            $this->warn('Adaptive streaming is disabled.');

            return self::SUCCESS;
        }

        $queued = 0;
        $force = (bool) $this->option('force');

        StoredFile::query()
            ->when(! $force, fn ($query) => $query->where(function ($query): void {
                $query->whereNull('adaptive_stream_status')
                    ->orWhere('adaptive_stream_status', 'failed');
            }))
            ->orderBy('id')
            ->cursor()
            ->each(function (StoredFile $storedFile) use (&$queued): void {
                if (! VideoFiles::isVideo($storedFile)) {
                    return;
                }

                GenerateAdaptiveStream::dispatch($storedFile);
                $queued++;
            });

        $this->info("Queued {$queued} video file(s) for adaptive streaming.");

        return self::SUCCESS;
    }
}
