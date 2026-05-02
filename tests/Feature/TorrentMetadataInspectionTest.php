<?php

use App\Enums\TorrentStatus;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Torrents\TorrentMetadata;
use App\Services\Torrents\TorrentMetadataInspector;
use Illuminate\Support\Facades\Bus;
use App\Jobs\InspectTorrentMetadata;

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
        ->and($torrent->error_message)->toBe('This torrent exceeds your remaining storage quota.');
});
