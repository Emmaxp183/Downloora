<?php

use App\Enums\TorrentSourceType;
use App\Enums\TorrentStatus;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Torrents\QBittorrentMetadataInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it inspects an existing qBittorrent torrent when adding the magnet conflicts', function () {
    config([
        'torrents.qbittorrent.base_url' => 'http://qbittorrent.test',
        'torrents.qbittorrent.username' => 'admin',
        'torrents.qbittorrent.password' => 'secret',
        'torrents.qbittorrent.timeout' => 10,
        'torrents.qbittorrent.metadata_poll_attempts' => 1,
        'torrents.qbittorrent.metadata_poll_interval_ms' => 0,
        'torrents.qbittorrent.performance_preferences' => [],
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'qbittorrent.test/api/v2/auth/login' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/torrents/add' => Http::response('Conflict', 409),
        'qbittorrent.test/api/v2/torrents/info*' => Http::response([[
            'hash' => '3f2f600c7a5637de5adf972b053996e57f2b8b0d',
            'name' => 'Existing Torrent',
        ]], 200),
        'qbittorrent.test/api/v2/torrents/files*' => Http::response([[
            'name' => 'movie.mp4',
            'size' => 1234,
        ]], 200),
    ]);

    $torrent = Torrent::factory()
        ->for(User::factory())
        ->create([
            'source_type' => TorrentSourceType::Magnet,
            'status' => TorrentStatus::PendingMetadata,
            'magnet_uri' => 'magnet:?xt=urn:btih:3f2f600c7a5637de5adf972b053996e57f2b8b0d&dn=Existing',
            'info_hash' => null,
        ]);

    $metadata = app(QBittorrentMetadataInspector::class)->inspect($torrent);

    expect($metadata->name)->toBe('Existing Torrent')
        ->and($metadata->infoHash)->toBe('3f2f600c7a5637de5adf972b053996e57f2b8b0d')
        ->and($metadata->totalSizeBytes)->toBe(1234)
        ->and($metadata->files)->toBe([
            ['path' => 'movie.mp4', 'size_bytes' => 1234],
        ]);

    Http::assertSent(fn ($request) => $request->url() === 'http://qbittorrent.test/api/v2/torrents/add'
        && $request['paused'] === 'true');

    Http::assertNotSent(fn ($request) => $request->url() === 'http://qbittorrent.test/api/v2/torrents/delete');
});
