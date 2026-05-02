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
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'qbittorrent.test/api/v2/auth/login' => Http::response('Ok.', 200),
        'qbittorrent.test/api/v2/torrents/add' => Http::response('Ok.', 200),
    ]);

    $torrent = Torrent::factory()
        ->for(User::factory())
        ->create([
            'source_type' => TorrentSourceType::Magnet,
            'status' => TorrentStatus::Queued,
            'magnet_uri' => 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567',
        ]);

    app(QBittorrentClient::class)->addMagnet($torrent);

    Http::assertSent(fn ($request) => $request->url() === 'http://qbittorrent.test/api/v2/auth/login'
        && $request['username'] === 'admin'
        && $request['password'] === 'secret');

    Http::assertSent(fn ($request) => $request->url() === 'http://qbittorrent.test/api/v2/torrents/add'
        && $request['urls'] === $torrent->magnet_uri
        && $request['paused'] === 'false');
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
