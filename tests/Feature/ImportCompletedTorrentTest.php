<?php

use App\Enums\TorrentStatus;
use App\Jobs\ImportCompletedTorrent;
use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Models\Torrent;
use App\Models\TorrentFile;
use App\Services\Torrents\QBittorrentClient;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Storage;

test('it instantly registers completed torrent files in place and keeps qBittorrent seeding by default', function () {
    Storage::fake('local');

    $torrent = Torrent::factory()->create([
        'status' => TorrentStatus::Importing,
        'qbittorrent_hash' => 'abc123',
    ]);

    TorrentFile::factory()->for($torrent)->create([
        'path' => 'Movies/video.mp4',
        'size_bytes' => 12,
    ]);

    Storage::disk('local')->put('qbittorrent/abc123/Movies/video.mp4', 'video-bytes');

    $deleted = false;
    app()->instance(QBittorrentClient::class, new class($deleted) extends QBittorrentClient
    {
        public function __construct(private bool &$deleted) {}

        public function delete(string $hash, bool $deleteFiles = true): void
        {
            $this->deleted = $hash === 'abc123' && ! $deleteFiles;
        }
    });

    app()->call([new ImportCompletedTorrent($torrent), 'handle']);

    $torrent->refresh();

    expect($torrent->status)->toBe(TorrentStatus::Completed)
        ->and($deleted)->toBeFalse()
        ->and(StoredFile::query()->count())->toBe(1)
        ->and((int) StorageUsageEvent::query()->sum('delta_bytes'))->toBe(12)
        ->and(StoredFile::query()->first()->s3_disk)->toBe('local')
        ->and(StoredFile::query()->first()->s3_bucket)->toBeNull()
        ->and(StoredFile::query()->first()->s3_key)->toBe('qbittorrent/abc123/Movies/video.mp4');

    Storage::disk('local')->assertExists('qbittorrent/abc123/Movies/video.mp4');
});

test('it can remove qBittorrent torrent after import when seeding retention is disabled', function () {
    Storage::fake('local');
    config(['torrents.qbittorrent.keep_after_import' => false]);

    $torrent = Torrent::factory()->create([
        'status' => TorrentStatus::Importing,
        'qbittorrent_hash' => 'abc123',
    ]);

    TorrentFile::factory()->for($torrent)->create([
        'path' => 'Movies/video.mp4',
        'size_bytes' => 12,
    ]);

    Storage::disk('local')->put('qbittorrent/abc123/Movies/video.mp4', 'video-bytes');

    $deleted = false;
    app()->instance(QBittorrentClient::class, new class($deleted) extends QBittorrentClient
    {
        public function __construct(private bool &$deleted) {}

        public function delete(string $hash, bool $deleteFiles = true): void
        {
            $this->deleted = $hash === 'abc123' && ! $deleteFiles;
        }
    });

    app()->call([new ImportCompletedTorrent($torrent), 'handle']);

    expect($torrent->refresh()->status)->toBe(TorrentStatus::Completed)
        ->and($deleted)->toBeTrue();

    Storage::disk('local')->assertExists('qbittorrent/abc123/Movies/video.mp4');
});

test('it does not create stored file records when import storage fails', function () {
    Storage::fake('s3');
    Storage::fake('local');

    $torrent = Torrent::factory()->create([
        'status' => TorrentStatus::Importing,
        'qbittorrent_hash' => 'abc123',
    ]);

    TorrentFile::factory()->for($torrent)->create([
        'path' => 'Movies/missing.mp4',
        'size_bytes' => 12,
    ]);

    app()->call([new ImportCompletedTorrent($torrent), 'handle']);

    expect($torrent->refresh()->status)->toBe(TorrentStatus::ImportFailed)
        ->and(StoredFile::query()->count())->toBe(0)
        ->and(StorageUsageEvent::query()->count())->toBe(0);
});

test('queued imports retry when qBittorrent has not exposed completed files yet', function () {
    Storage::fake('local');

    $torrent = Torrent::factory()->create([
        'status' => TorrentStatus::Importing,
        'qbittorrent_hash' => 'abc123',
    ]);

    TorrentFile::factory()->for($torrent)->create([
        'path' => 'Movies/not-visible-yet.mp4',
        'size_bytes' => 12,
    ]);

    $job = (new ImportCompletedTorrent($torrent))->withFakeQueueInteractions();

    expect(fn () => app()->call([$job, 'handle']))
        ->toThrow(FileNotFoundException::class, 'Missing completed file [Movies/not-visible-yet.mp4].');

    expect($torrent->refresh()->status)->toBe(TorrentStatus::Importing)
        ->and(StoredFile::query()->count())->toBe(0)
        ->and(StorageUsageEvent::query()->count())->toBe(0);
});
