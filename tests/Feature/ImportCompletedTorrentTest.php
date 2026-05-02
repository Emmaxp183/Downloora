<?php

use App\Enums\TorrentStatus;
use App\Jobs\ImportCompletedTorrent;
use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Models\Torrent;
use App\Models\TorrentFile;
use App\Services\Torrents\QBittorrentClient;
use Illuminate\Support\Facades\Storage;

test('it imports completed torrent files into s3 and removes qBittorrent torrent', function () {
    Storage::fake('s3');
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
            $this->deleted = $hash === 'abc123' && $deleteFiles;
        }
    });

    app()->call([new ImportCompletedTorrent($torrent), 'handle']);

    $torrent->refresh();

    expect($torrent->status)->toBe(TorrentStatus::Completed)
        ->and($deleted)->toBeTrue()
        ->and(StoredFile::query()->count())->toBe(1)
        ->and(StorageUsageEvent::query()->sum('delta_bytes'))->toBe(12);

    Storage::disk('s3')->assertExists("users/{$torrent->user_id}/torrents/{$torrent->id}/Movies/video.mp4");
});
