<?php

namespace App\Jobs;

use App\Enums\TorrentStatus;
use App\Models\Torrent;
use App\Services\Torrents\QBittorrentClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PollTorrentProgress implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Torrent $torrent) {}

    /**
     * Execute the job.
     */
    public function handle(QBittorrentClient $client): void
    {
        $torrent = $this->torrent->fresh();

        if (! $torrent instanceof Torrent || $torrent->status !== TorrentStatus::Downloading || blank($torrent->qbittorrent_hash)) {
            return;
        }

        try {
            $details = $client->getTorrent($torrent->qbittorrent_hash);
            $progress = (int) round(((float) ($details['progress'] ?? 0)) * 100);

            $torrent->forceFill([
                'progress' => min(100, max(0, $progress)),
                'downloaded_bytes' => (int) ($details['downloaded'] ?? $torrent->downloaded_bytes),
                'error_message' => null,
            ])->save();

            if ($torrent->progress >= 100) {
                $torrent->forceFill([
                    'status' => TorrentStatus::Importing,
                    'completed_at' => now(),
                ])->save();

                ImportCompletedTorrent::dispatch($torrent);
            }
        } catch (Throwable $throwable) {
            $torrent->forceFill([
                'status' => TorrentStatus::DownloadFailed,
                'error_message' => $throwable->getMessage(),
            ])->save();
        }
    }
}
