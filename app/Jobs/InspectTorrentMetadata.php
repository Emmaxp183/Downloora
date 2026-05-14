<?php

namespace App\Jobs;

use App\Enums\TorrentStatus;
use App\Models\Torrent;
use App\Services\Storage\StorageQuota;
use App\Services\Torrents\CachedTorrentImporter;
use App\Services\Torrents\TorrentMetadataInspector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use RuntimeException;
use Throwable;

class InspectTorrentMetadata implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Torrent $torrent) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("torrent-metadata:{$this->torrent->getKey()}"))
                ->expireAfter($this->metadataLockSeconds())
                ->dontRelease(),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(TorrentMetadataInspector $inspector, StorageQuota $quota, CachedTorrentImporter $cachedTorrents): void
    {
        $torrent = $this->torrent->fresh(['user']);

        if (! $torrent instanceof Torrent || $torrent->status !== TorrentStatus::PendingMetadata) {
            return;
        }

        try {
            $metadata = $inspector->inspect($torrent);

            $torrent->files()->delete();

            $torrent->forceFill([
                'name' => $metadata->name,
                'info_hash' => $metadata->infoHash,
                'total_size_bytes' => $metadata->totalSizeBytes,
            ])->save();

            foreach ($metadata->files as $file) {
                $torrent->files()->create([
                    'path' => $file['path'],
                    'size_bytes' => $file['size_bytes'],
                    'selected' => true,
                    'progress' => 0,
                ]);
            }

            if (! $quota->canStore($torrent->user, $metadata->totalSizeBytes)) {
                $torrent->forceFill([
                    'status' => TorrentStatus::QuotaExceeded,
                    'error_message' => 'This torrent exceeds your remaining storage quota.',
                ])->save();

                return;
            }

            $torrent->forceFill([
                'status' => TorrentStatus::Queued,
                'error_message' => null,
            ])->save();

            if ($cachedTorrents->importIfAvailable($torrent)) {
                return;
            }

            StartTorrentDownload::dispatch($torrent);
        } catch (Throwable $throwable) {
            if ($this->shouldRetryMetadataInspection($throwable)) {
                $this->release($this->metadataPollIntervalSeconds());

                return;
            }

            $torrent->forceFill([
                'status' => TorrentStatus::MetadataFailed,
                'error_message' => $throwable->getMessage(),
            ])->save();
        }
    }

    private function metadataLockSeconds(): int
    {
        $attempts = $this->metadataPollAttempts();
        $intervalMs = $this->metadataPollIntervalMilliseconds();

        return max(600, (int) ceil(($attempts * $intervalMs) / 1000) + 120);
    }

    private function shouldRetryMetadataInspection(Throwable $throwable): bool
    {
        if ($this->attempts() >= $this->metadataPollAttempts()) {
            return false;
        }

        if ($throwable instanceof ConnectionException) {
            return true;
        }

        if ($throwable instanceof RequestException) {
            return in_array($throwable->response->status(), [408, 425, 429, 500, 502, 503, 504], true);
        }

        if (! $throwable instanceof RuntimeException) {
            return false;
        }

        return str_contains($throwable->getMessage(), 'rqbit did not return a torrent hash')
            || str_contains($throwable->getMessage(), 'rqbit did not return torrent metadata files')
            || str_contains($throwable->getMessage(), 'cURL error 28');
    }

    private function metadataPollAttempts(): int
    {
        return max(1, (int) config('torrents.rqbit.metadata_poll_attempts', 90));
    }

    private function metadataPollIntervalMilliseconds(): int
    {
        return max(0, (int) config('torrents.rqbit.metadata_poll_interval_ms', 2000));
    }

    private function metadataPollIntervalSeconds(): int
    {
        return max(1, (int) ceil($this->metadataPollIntervalMilliseconds() / 1000));
    }
}
