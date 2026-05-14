<?php

use App\Enums\TorrentStatus;
use App\Jobs\ImportCompletedTorrent;
use App\Jobs\PollTorrentProgress;
use App\Jobs\StartTorrentDownload;
use App\Models\Torrent;
use App\Services\Torrents\TorrentEngineClient;
use Illuminate\Support\Facades\Bus;

test('queued torrents become downloading when the torrent engine accepts them', function () {
    Bus::fake([PollTorrentProgress::class]);

    $torrent = Torrent::factory()->create([
        'status' => TorrentStatus::Queued,
        'qbittorrent_hash' => null,
    ]);

    app()->instance(TorrentEngineClient::class, new class implements TorrentEngineClient
    {
        public function addMagnet(Torrent $torrent, bool $paused = false, bool $reuseExisting = true): void
        {
            $torrent->forceFill(['qbittorrent_hash' => 'abc123'])->save();
        }

        public function addTorrentFile(Torrent $torrent, bool $paused = false): void {}

        public function getTorrent(string $hash): array
        {
            return [];
        }

        public function torrents(): array
        {
            return [];
        }

        public function files(string $hash): array
        {
            return [];
        }

        public function delete(string $hash, bool $deleteFiles = true): void {}
    });

    app()->call([new StartTorrentDownload($torrent), 'handle']);

    expect($torrent->refresh()->status)->toBe(TorrentStatus::Downloading)
        ->and($torrent->qbittorrent_hash)->toBe('abc123')
        ->and($torrent->started_at)->not->toBeNull();

    Bus::assertDispatched(PollTorrentProgress::class);
});

test('torrent engine start failures mark torrents as download failed', function () {
    $torrent = Torrent::factory()->create(['status' => TorrentStatus::Queued]);

    app()->instance(TorrentEngineClient::class, new class implements TorrentEngineClient
    {
        public function addMagnet(Torrent $torrent, bool $paused = false, bool $reuseExisting = true): void
        {
            throw new RuntimeException('Torrent engine unavailable');
        }

        public function addTorrentFile(Torrent $torrent, bool $paused = false): void {}

        public function getTorrent(string $hash): array
        {
            return [];
        }

        public function torrents(): array
        {
            return [];
        }

        public function files(string $hash): array
        {
            return [];
        }

        public function delete(string $hash, bool $deleteFiles = true): void {}
    });

    app()->call([new StartTorrentDownload($torrent), 'handle']);

    expect($torrent->refresh()->status)->toBe(TorrentStatus::DownloadFailed)
        ->and($torrent->error_message)->toBe('Torrent engine unavailable');
});

test('polling updates download progress', function () {
    Bus::fake([PollTorrentProgress::class]);

    $torrent = Torrent::factory()->create([
        'status' => TorrentStatus::Downloading,
        'qbittorrent_hash' => 'abc123',
    ]);

    app()->instance(TorrentEngineClient::class, new class implements TorrentEngineClient
    {
        public function addMagnet(Torrent $torrent, bool $paused = false, bool $reuseExisting = true): void {}

        public function addTorrentFile(Torrent $torrent, bool $paused = false): void {}

        public function getTorrent(string $hash): array
        {
            return [
                'hash' => $hash,
                'progress' => 0.42,
                'downloaded' => 420,
            ];
        }

        public function torrents(): array
        {
            return [];
        }

        public function files(string $hash): array
        {
            return [];
        }

        public function delete(string $hash, bool $deleteFiles = true): void {}
    });

    app()->call([new PollTorrentProgress($torrent), 'handle']);

    expect($torrent->refresh()->progress)->toBe(42)
        ->and($torrent->downloaded_bytes)->toBe(420)
        ->and($torrent->status)->toBe(TorrentStatus::Downloading);

    Bus::assertDispatched(PollTorrentProgress::class);
});

test('completed torrents are marked importing and dispatch import job', function () {
    Bus::fake();

    $torrent = Torrent::factory()->create([
        'status' => TorrentStatus::Downloading,
        'qbittorrent_hash' => 'abc123',
    ]);

    app()->instance(TorrentEngineClient::class, new class implements TorrentEngineClient
    {
        public function addMagnet(Torrent $torrent, bool $paused = false, bool $reuseExisting = true): void {}

        public function addTorrentFile(Torrent $torrent, bool $paused = false): void {}

        public function getTorrent(string $hash): array
        {
            return [
                'hash' => $hash,
                'progress' => 1,
                'downloaded' => 1000,
            ];
        }

        public function torrents(): array
        {
            return [];
        }

        public function files(string $hash): array
        {
            return [];
        }

        public function delete(string $hash, bool $deleteFiles = true): void {}
    });

    app()->call([new PollTorrentProgress($torrent), 'handle']);

    expect($torrent->refresh()->status)->toBe(TorrentStatus::Importing)
        ->and($torrent->progress)->toBe(100);

    Bus::assertDispatched(ImportCompletedTorrent::class);
});
