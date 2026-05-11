<?php

use App\Enums\TorrentSourceType;
use App\Enums\TorrentStatus;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Torrents\QBittorrentClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it authenticates and adds a magnet torrent', function () {
    config([
        'torrents.qbittorrent.base_url' => 'http://qbittorrent.test',
        'torrents.qbittorrent.username' => 'admin',
        'torrents.qbittorrent.password' => 'secret',
        'torrents.qbittorrent.timeout' => 10,
        'torrents.qbittorrent.performance_preferences' => [
            'dl_limit' => -1,
            'up_limit' => -1,
            'queueing_enabled' => false,
            'max_active_uploads' => 150,
            'connection_speed' => 10000,
            'max_connec' => 64000,
            'max_connec_per_torrent' => 16000,
            'max_uploads' => 8000,
            'max_uploads_per_torrent' => 2000,
        ],
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'qbittorrent.test/api/v2/auth/login' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/app/setPreferences' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/torrents/add' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/torrents/setDownloadLimit' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/torrents/setUploadLimit' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/torrents/setForceStart' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/torrents/topPrio' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/torrents/reannounce' => Http::response('Ok.', 200),
    ]);

    $torrent = Torrent::factory()
        ->for(User::factory())
        ->create([
            'source_type' => TorrentSourceType::Magnet,
            'status' => TorrentStatus::Queued,
            'info_hash' => '0123456789abcdef0123456789abcdef01234567',
            'magnet_uri' => 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567',
        ]);

    app(QBittorrentClient::class)->addMagnet($torrent);

    Http::assertSent(fn ($request) => $request->url() === 'http://qbittorrent.test/api/v2/auth/login'
        && $request['username'] === 'admin'
        && $request['password'] === 'secret');

    Http::assertSent(fn ($request) => $request->url() === 'http://qbittorrent.test/api/v2/app/setPreferences'
        && json_decode($request['json'], true) === [
            'dl_limit' => -1,
            'up_limit' => -1,
            'queueing_enabled' => false,
            'max_active_uploads' => 150,
            'connection_speed' => 10000,
            'max_connec' => 64000,
            'max_connec_per_torrent' => 16000,
            'max_uploads' => 8000,
            'max_uploads_per_torrent' => 2000,
        ]);

    Http::assertSent(fn ($request) => $request->url() === 'http://qbittorrent.test/api/v2/torrents/add'
        && $request['urls'] === $torrent->magnet_uri
        && $request['paused'] === 'false'
        && $request['dlLimit'] === 0
        && $request['upLimit'] === 0
        && $request['sequentialDownload'] === 'false'
        && $request['firstLastPiecePrio'] === 'false');

    Http::assertSent(fn ($request) => $request->url() === 'http://qbittorrent.test/api/v2/torrents/setDownloadLimit'
        && $request['hashes'] === $torrent->info_hash
        && $request['limit'] === 0);

    Http::assertSent(fn ($request) => $request->url() === 'http://qbittorrent.test/api/v2/torrents/setUploadLimit'
        && $request['hashes'] === $torrent->info_hash
        && $request['limit'] === 0);

    Http::assertSent(fn ($request) => $request->url() === 'http://qbittorrent.test/api/v2/torrents/setForceStart'
        && $request['hashes'] === $torrent->info_hash
        && $request['value'] === 'true');

    Http::assertSent(fn ($request) => $request->url() === 'http://qbittorrent.test/api/v2/torrents/topPrio'
        && $request['hashes'] === $torrent->info_hash);

    Http::assertSent(fn ($request) => $request->url() === 'http://qbittorrent.test/api/v2/torrents/reannounce'
        && $request['hashes'] === $torrent->info_hash);
});

test('it reads torrent details and files then deletes the torrent', function () {
    config([
        'torrents.qbittorrent.base_url' => 'http://qbittorrent.test',
        'torrents.qbittorrent.username' => 'admin',
        'torrents.qbittorrent.password' => 'secret',
        'torrents.qbittorrent.timeout' => 10,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'qbittorrent.test/api/v2/auth/login' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/torrents/info*' => Http::response([[
            'hash' => 'abc123',
            'name' => 'Example',
            'progress' => 1,
        ]], 200),
        'qbittorrent.test/api/v2/torrents/files*' => Http::response([[
            'name' => 'video.mp4',
            'size' => 1000,
        ]], 200),
        'qbittorrent.test/api/v2/torrents/delete' => Http::response('Ok.', 200),
    ]);

    $client = app(QBittorrentClient::class);

    expect($client->getTorrent('abc123'))->toMatchArray(['hash' => 'abc123'])
        ->and($client->files('abc123'))->toHaveCount(1);

    $client->delete('abc123');

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'http://qbittorrent.test/api/v2/torrents/info')
        && $request['hashes'] === 'abc123');

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'http://qbittorrent.test/api/v2/torrents/files')
        && $request['hash'] === 'abc123');

    Http::assertSent(fn ($request) => $request->url() === 'http://qbittorrent.test/api/v2/torrents/delete'
        && $request['hashes'] === 'abc123'
        && $request['deleteFiles'] === 'true');
});

test('it reuses an existing qBittorrent torrent when starting a duplicate magnet', function () {
    config([
        'torrents.qbittorrent.base_url' => 'http://qbittorrent.test',
        'torrents.qbittorrent.username' => 'admin',
        'torrents.qbittorrent.password' => 'secret',
        'torrents.qbittorrent.timeout' => 10,
        'torrents.qbittorrent.performance_preferences' => [],
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'qbittorrent.test/api/v2/auth/login' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/torrents/add' => Http::response('Conflict', 409),
        'qbittorrent.test/api/v2/torrents/setDownloadLimit' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/torrents/setUploadLimit' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/torrents/setForceStart' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/torrents/topPrio' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/torrents/reannounce' => Http::response('Ok.', 200),
    ]);

    $torrent = Torrent::factory()
        ->for(User::factory())
        ->create([
            'source_type' => TorrentSourceType::Magnet,
            'status' => TorrentStatus::Queued,
            'info_hash' => '0123456789abcdef0123456789abcdef01234567',
            'magnet_uri' => 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567',
        ]);

    app(QBittorrentClient::class)->addMagnet($torrent);

    Http::assertSent(fn ($request) => $request->url() === 'http://qbittorrent.test/api/v2/torrents/setForceStart'
        && $request['hashes'] === $torrent->info_hash
        && $request['value'] === 'true');
});
