<?php

use App\Enums\TorrentSourceType;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Torrents\QBittorrentMetadataInspector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'torrents.qbittorrent.base_url' => 'http://qbittorrent.test',
        'torrents.qbittorrent.username' => 'admin',
        'torrents.qbittorrent.password' => 'secret',
        'torrents.qbittorrent.timeout' => 10,
        'torrents.qbittorrent.metadata_poll_attempts' => 1,
        'torrents.qbittorrent.metadata_poll_interval_ms' => 0,
    ]);

    Http::preventStrayRequests();
});

test('it inspects magnet metadata with qbittorrent', function () {
    Http::fake([
        'qbittorrent.test/api/v2/auth/login' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/torrents/add' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/torrents/info*' => Http::response([[
            'hash' => '0123456789abcdef0123456789abcdef01234567',
            'name' => 'Example Torrent',
            'save_path' => '/downloads/0123456789abcdef0123456789abcdef01234567',
        ]], 200),
        'qbittorrent.test/api/v2/torrents/files*' => Http::response([
            ['name' => 'video.mp4', 'size' => 700],
            ['name' => 'poster.jpg', 'size' => 300],
        ], 200),
        'qbittorrent.test/api/v2/torrents/delete' => Http::response('Ok.', 200),
    ]);

    $torrent = Torrent::factory()
        ->for(User::factory())
        ->create([
            'source_type' => TorrentSourceType::Magnet,
            'magnet_uri' => 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567',
        ]);

    $metadata = app(QBittorrentMetadataInspector::class)->inspect($torrent);

    expect($metadata->name)->toBe('Example Torrent')
        ->and($metadata->infoHash)->toBe('0123456789abcdef0123456789abcdef01234567')
        ->and($metadata->totalSizeBytes)->toBe(1000)
        ->and($metadata->files)->toHaveCount(2);

    Http::assertSent(fn ($request) => $request->url() === 'http://qbittorrent.test/api/v2/torrents/add'
        && $request['paused'] === 'true');

    Http::assertSent(fn ($request) => $request->url() === 'http://qbittorrent.test/api/v2/torrents/delete'
        && $request['hashes'] === '0123456789abcdef0123456789abcdef01234567');
});

test('it inspects torrent file metadata by matching qbittorrent save path', function () {
    Storage::fake('local');
    Storage::disk('local')->put('torrents/example.torrent', 'torrent-bytes');

    Http::fake([
        'qbittorrent.test/api/v2/auth/login' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/torrents/add' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/torrents/info*' => Http::response([[
            'hash' => 'abcdef0123456789abcdef0123456789abcdef01',
            'name' => 'Uploaded Torrent',
            'save_path' => '/downloads/1/',
        ]], 200),
        'qbittorrent.test/api/v2/torrents/files*' => Http::response([
            ['name' => 'archive.zip', 'size' => 1000],
        ], 200),
        'qbittorrent.test/api/v2/torrents/delete' => Http::response('Ok.', 200),
    ]);

    $torrent = Torrent::factory()
        ->for(User::factory())
        ->create([
            'id' => 1,
            'source_type' => TorrentSourceType::TorrentFile,
            'magnet_uri' => null,
            'torrent_file_path' => 'torrents/example.torrent',
        ]);

    $metadata = app(QBittorrentMetadataInspector::class)->inspect($torrent);

    expect($metadata->name)->toBe('Uploaded Torrent')
        ->and($metadata->infoHash)->toBe('abcdef0123456789abcdef0123456789abcdef01')
        ->and($metadata->totalSizeBytes)->toBe(1000)
        ->and($metadata->files)->toBe([
            ['path' => 'archive.zip', 'size_bytes' => 1000],
        ]);
});
