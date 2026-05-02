<?php

use App\Enums\TorrentStatus;
use App\Jobs\ImportCompletedTorrent;
use App\Jobs\PollTorrentProgress;
use App\Jobs\StartTorrentDownload;
use App\Models\Torrent;
use App\Services\Torrents\QBittorrentClient;
use Illuminate\Support\Facades\Bus;

test('queued torrents become downloading when qBittorrent accepts them', function () {
    $torrent = Torrent::factory()->create([
        'status' => TorrentStatus::Queued,
        'qbittorrent_hash' => null,
    ]);

    app()->instance(QBittorrentClient::class, new class extends QBittorrentClient
    {
        public function addMagnet(Torrent $torrent, bool $paused = false): void
        {
            $torrent->forceFill(['qbittorrent_hash' => 'abc123'])->save();
        }
    });

    app()->call([new StartTorrentDownload($torrent), 'handle']);

    expect($torrent->refresh()->status)->toBe(TorrentStatus::Downloading)
        ->and($torrent->qbittorrent_hash)->toBe('abc123')
        ->and($torrent->started_at)->not->toBeNull();
});

test('qBittorrent start failures mark torrents as download failed', function () {
    $torrent = Torrent::factory()->create(['status' => TorrentStatus::Queued]);

    app()->instance(QBittorrentClient::class, new class extends QBittorrentClient
    {
        public function addMagnet(Torrent $torrent, bool $paused = false): void
        {
            throw new RuntimeException('qBittorrent unavailable');
        }
    });

    app()->call([new StartTorrentDownload($torrent), 'handle']);

    expect($torrent->refresh()->status)->toBe(TorrentStatus::DownloadFailed)
        ->and($torrent->error_message)->toBe('qBittorrent unavailable');
});

test('polling updates download progress', function () {
    $torrent = Torrent::factory()->create([
        'status' => TorrentStatus::Downloading,
        'qbittorrent_hash' => 'abc123',
    ]);

    app()->instance(QBittorrentClient::class, new class extends QBittorrentClient
    {
        public function getTorrent(string $hash): array
        {
            return [
                'hash' => $hash,
                'progress' => 0.42,
                'downloaded' => 420,
            ];
        }
    });

    app()->call([new PollTorrentProgress($torrent), 'handle']);

    expect($torrent->refresh()->progress)->toBe(42)
        ->and($torrent->downloaded_bytes)->toBe(420)
        ->and($torrent->status)->toBe(TorrentStatus::Downloading);
});

test('completed torrents are marked importing and dispatch import job', function () {
    Bus::fake();

    $torrent = Torrent::factory()->create([
        'status' => TorrentStatus::Downloading,
        'qbittorrent_hash' => 'abc123',
    ]);

    app()->instance(QBittorrentClient::class, new class extends QBittorrentClient
    {
        public function getTorrent(string $hash): array
        {
            return [
                'hash' => $hash,
                'progress' => 1,
                'downloaded' => 1000,
            ];
        }
    });

    app()->call([new PollTorrentProgress($torrent), 'handle']);

    expect($torrent->refresh()->status)->toBe(TorrentStatus::Importing)
        ->and($torrent->progress)->toBe(100);

    Bus::assertDispatched(ImportCompletedTorrent::class);
});
