<?php

namespace App\Jobs;

use App\Enums\TorrentSourceType;
use App\Enums\TorrentStatus;
use App\Models\Torrent;
use App\Services\Torrents\QBittorrentClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class StartTorrentDownload implements ShouldQueue
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

        if (! $torrent instanceof Torrent || $torrent->status !== TorrentStatus::Queued) {
            return;
        }

        try {
            if ($torrent->source_type === TorrentSourceType::Magnet) {
                $client->addMagnet($torrent);
            } elseif ($torrent->source_type === TorrentSourceType::TorrentFile) {
                $client->addTorrentFile($torrent);
            }

            $torrent->forceFill([
                'status' => TorrentStatus::Downloading,
                'qbittorrent_hash' => $torrent->qbittorrent_hash ?: $torrent->info_hash,
                'started_at' => now(),
                'error_message' => null,
            ])->save();

            PollTorrentProgress::dispatch($torrent)->delay(now()->addSeconds(2));
        } catch (Throwable $throwable) {
            $torrent->forceFill([
                'status' => TorrentStatus::DownloadFailed,
                'error_message' => $throwable->getMessage(),
            ])->save();
        }
    }
}
