<?php

use App\Enums\TorrentStatus;
use App\Jobs\ImportCompletedTorrent;
use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Models\Torrent;
use App\Models\TorrentFile;
use App\Services\Torrents\RqbitClient;
use App\Services\Torrents\TorrentEngineClient;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;

test('it instantly registers completed torrent files in place and keeps rqbit seeding by default', function () {
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
    app()->instance(TorrentEngineClient::class, new class($deleted) extends RqbitClient
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
        ->and($torrent->storedFiles()->count())->toBe(1)
        ->and((int) StorageUsageEvent::query()->where('user_id', $torrent->user_id)->sum('delta_bytes'))->toBe(12)
        ->and($torrent->storedFiles()->first()->s3_disk)->toBe('local')
        ->and($torrent->storedFiles()->first()->s3_bucket)->toBeNull()
        ->and($torrent->storedFiles()->first()->s3_key)->toBe('qbittorrent/abc123/Movies/video.mp4');

    Storage::disk('local')->assertExists('qbittorrent/abc123/Movies/video.mp4');
});

test('it can remove rqbit torrent after import when seeding retention is disabled', function () {
    Storage::fake('local');
    config(['torrents.rqbit.keep_after_import' => false]);

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
    app()->instance(TorrentEngineClient::class, new class($deleted) extends RqbitClient
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

test('it reuses existing stored files when an import job is retried', function () {
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

    $storedFile = StoredFile::factory()->for($torrent)->create([
        'user_id' => $torrent->user_id,
        's3_disk' => 'local',
        's3_bucket' => null,
        's3_key' => 'qbittorrent/abc123/Movies/video.mp4',
        'original_path' => 'Movies/video.mp4',
        'name' => 'video.mp4',
        'size_bytes' => 12,
    ]);

    StorageUsageEvent::factory()->create([
        'user_id' => $torrent->user_id,
        'stored_file_id' => $storedFile->id,
        'delta_bytes' => 12,
        'reason' => 'torrent_imported',
    ]);

    app()->call([new ImportCompletedTorrent($torrent), 'handle']);

    expect($torrent->refresh()->status)->toBe(TorrentStatus::Completed)
        ->and($torrent->storedFiles()->count())->toBe(1)
        ->and($torrent->storedFiles()->first()->is($storedFile))->toBeTrue()
        ->and(StorageUsageEvent::query()->where('stored_file_id', $storedFile->id)->count())->toBe(1);
});

test('torrent imports prevent overlapping jobs for the same torrent', function () {
    $torrent = Torrent::factory()->create();
    $middleware = (new ImportCompletedTorrent($torrent))->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[0]->releaseAfter)->toBe(10)
        ->and($middleware[0]->expiresAfter)->toBe(600);
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
        ->and($torrent->storedFiles()->count())->toBe(0)
        ->and(StorageUsageEvent::query()->where('user_id', $torrent->user_id)->count())->toBe(0);
});

test('queued imports retry when rqbit has not exposed completed files yet', function () {
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
        ->and($torrent->storedFiles()->count())->toBe(0)
        ->and(StorageUsageEvent::query()->where('user_id', $torrent->user_id)->count())->toBe(0);
});
