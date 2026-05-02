<?php

namespace App\Jobs;

use App\Enums\TorrentStatus;
use App\Models\Torrent;
use App\Services\Storage\StorageQuota;
use App\Services\Torrents\TorrentMetadataInspector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class InspectTorrentMetadata implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Torrent $torrent) {}

    /**
     * Execute the job.
     */
    public function handle(TorrentMetadataInspector $inspector, StorageQuota $quota): void
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

            StartTorrentDownload::dispatch($torrent);
        } catch (Throwable $throwable) {
            $torrent->forceFill([
                'status' => TorrentStatus::MetadataFailed,
                'error_message' => $throwable->getMessage(),
            ])->save();
        }
    }
}
