<?php

use App\Enums\TorrentStatus;
use App\Jobs\InspectTorrentMetadata;
use App\Jobs\StartTorrentDownload;
use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Torrents\TorrentMetadata;
use App\Services\Torrents\TorrentMetadataInspector;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

test('it records metadata and queues torrents that fit the user quota', function () {
    Bus::fake();

    $user = User::factory()->create(['storage_quota_bytes' => 1000]);
    $torrent = Torrent::factory()->for($user)->create([
        'status' => TorrentStatus::PendingMetadata,
        'total_size_bytes' => null,
    ]);

    app()->bind(TorrentMetadataInspector::class, fn () => new class implements TorrentMetadataInspector
    {
        public function inspect(Torrent $torrent): TorrentMetadata
        {
            return new TorrentMetadata(
                name: 'Example Torrent',
                infoHash: '0123456789abcdef0123456789abcdef01234567',
                totalSizeBytes: 900,
                files: [
                    ['path' => 'video.mp4', 'size_bytes' => 900],
                ],
            );
        }
    });

    app()->call([new InspectTorrentMetadata($torrent), 'handle']);

    $torrent->refresh();

    expect($torrent->status)->toBe(TorrentStatus::Queued)
        ->and($torrent->name)->toBe('Example Torrent')
        ->and($torrent->info_hash)->toBe('0123456789abcdef0123456789abcdef01234567')
        ->and($torrent->total_size_bytes)->toBe(900)
        ->and($torrent->files)->toHaveCount(1);
});

test('it rejects torrents that exceed remaining quota', function () {
    Bus::fake();

    $user = User::factory()->create(['storage_quota_bytes' => 1000]);
    $torrent = Torrent::factory()->for($user)->create(['status' => TorrentStatus::PendingMetadata]);

    app()->bind(TorrentMetadataInspector::class, fn () => new class implements TorrentMetadataInspector
    {
        public function inspect(Torrent $torrent): TorrentMetadata
        {
            return new TorrentMetadata(
                name: 'Large Torrent',
                infoHash: 'abcdef0123456789abcdef0123456789abcdef01',
                totalSizeBytes: 1001,
                files: [
                    ['path' => 'large.bin', 'size_bytes' => 1001],
                ],
            );
        }
    });

    app()->call([new InspectTorrentMetadata($torrent), 'handle']);

    expect($torrent->refresh()->status)->toBe(TorrentStatus::QuotaExceeded)
        ->and($torrent->error_message)->toBe('This torrent exceeds your remaining storage quota.')
        ->and($user->torrents()->active()->whereKey($torrent)->exists())->toBeTrue();
});

test('it keeps metadata failures visible as active torrents', function () {
    $user = User::factory()->create();
    $torrent = Torrent::factory()->for($user)->create(['status' => TorrentStatus::PendingMetadata]);

    app()->bind(TorrentMetadataInspector::class, fn () => new class implements TorrentMetadataInspector
    {
        public function inspect(Torrent $torrent): TorrentMetadata
        {
            throw new RuntimeException('Unable to inspect metadata.');
        }
    });

    app()->call([new InspectTorrentMetadata($torrent), 'handle']);

    expect($torrent->refresh()->status)->toBe(TorrentStatus::MetadataFailed)
        ->and($torrent->error_message)->toBe('Unable to inspect metadata.')
        ->and($user->torrents()->active()->whereKey($torrent)->exists())->toBeTrue();
});

test('it quickly retries when rqbit metadata is not ready yet', function () {
    config([
        'torrents.rqbit.metadata_poll_attempts' => 90,
        'torrents.rqbit.metadata_poll_interval_ms' => 2000,
    ]);

    $user = User::factory()->create();
    $torrent = Torrent::factory()->for($user)->create(['status' => TorrentStatus::PendingMetadata]);

    app()->bind(TorrentMetadataInspector::class, fn () => new class implements TorrentMetadataInspector
    {
        public function inspect(Torrent $torrent): TorrentMetadata
        {
            throw new RuntimeException('rqbit did not return torrent metadata files.');
        }
    });

    $job = (new InspectTorrentMetadata($torrent))->withFakeQueueInteractions();

    app()->call([$job, 'handle']);

    $job->assertReleased(delay: 2);

    expect($torrent->refresh()->status)->toBe(TorrentStatus::PendingMetadata)
        ->and($torrent->error_message)->toBeNull();
});

test('it completes instantly when matching torrent is already cached', function () {
    Bus::fake();
    Storage::fake('s3');

    $infoHash = '0123456789abcdef0123456789abcdef01234567';
    $sourceTorrent = Torrent::factory()->create([
        'info_hash' => $infoHash,
        'status' => TorrentStatus::Completed,
        'total_size_bytes' => 900,
        'completed_at' => now()->subMinute(),
    ]);

    Storage::disk('s3')->put('users/1/torrents/1/video.mp4', 'cached-video-bytes');

    StoredFile::factory()->for($sourceTorrent)->create([
        'user_id' => $sourceTorrent->user_id,
        's3_disk' => 's3',
        's3_bucket' => config('filesystems.disks.s3.bucket'),
        's3_key' => 'users/1/torrents/1/video.mp4',
        'original_path' => 'video.mp4',
        'name' => 'video.mp4',
        'mime_type' => 'video/mp4',
        'size_bytes' => 900,
    ]);

    $user = User::factory()->create(['storage_quota_bytes' => 1000]);
    $torrent = Torrent::factory()->for($user)->create([
        'status' => TorrentStatus::PendingMetadata,
        'total_size_bytes' => null,
    ]);

    app()->bind(TorrentMetadataInspector::class, fn () => new class($infoHash) implements TorrentMetadataInspector
    {
        public function __construct(private readonly string $infoHash) {}

        public function inspect(Torrent $torrent): TorrentMetadata
        {
            return new TorrentMetadata(
                name: 'Example Torrent',
                infoHash: $this->infoHash,
                totalSizeBytes: 900,
                files: [
                    ['path' => 'video.mp4', 'size_bytes' => 900],
                ],
            );
        }
    });

    app()->call([new InspectTorrentMetadata($torrent), 'handle']);

    $torrent->refresh();
    $storedFile = StoredFile::query()
        ->whereBelongsTo($torrent)
        ->first();

    expect($torrent->status)->toBe(TorrentStatus::Completed)
        ->and($torrent->progress)->toBe(100)
        ->and($torrent->downloaded_bytes)->toBe(900)
        ->and($storedFile)->not->toBeNull()
        ->and($storedFile->s3_key)->toBe("users/{$user->id}/torrents/{$torrent->id}/video.mp4")
        ->and(Storage::disk('s3')->get($storedFile->s3_key))->toBe('cached-video-bytes')
        ->and((int) StorageUsageEvent::query()->where('reason', 'torrent_cache_imported')->sum('delta_bytes'))->toBe(900);

    Bus::assertNotDispatched(StartTorrentDownload::class);
});
