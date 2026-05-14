<?php

use App\Enums\TorrentSourceType;
use App\Enums\TorrentStatus;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Torrents\RqbitClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config([
        'torrents.rqbit.base_url' => 'http://rqbit.test',
        'torrents.rqbit.timeout' => 10,
        'torrents.rqbit.add_timeout' => 120,
        'torrents.rqbit.metadata_timeout' => 30,
        'torrents.rqbit.download_path' => '/downloads',
    ]);

    Http::preventStrayRequests();
});

test('it adds a magnet torrent to rqbit', function () {
    Http::fake([
        'rqbit.test/torrents?*' => Http::response([
            'id' => 0,
            'details' => [
                'info_hash' => '0123456789abcdef0123456789abcdef01234567',
                'name' => 'Example Torrent',
            ],
        ], 200),
    ]);

    $torrent = Torrent::factory()
        ->for(User::factory())
        ->create([
            'source_type' => TorrentSourceType::Magnet,
            'status' => TorrentStatus::Queued,
            'info_hash' => '0123456789abcdef0123456789abcdef01234567',
            'magnet_uri' => 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567',
        ]);

    app(RqbitClient::class)->addMagnet($torrent);

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'http://rqbit.test/torrents?')
        && str_contains($request->url(), 'overwrite=true')
        && str_contains($request->url(), 'output_folder=%2Fdownloads%2F0123456789abcdef0123456789abcdef01234567')
        && $request->body() === $torrent->magnet_uri);

    expect($torrent->refresh()->qbittorrent_hash)->toBe('0123456789abcdef0123456789abcdef01234567');
});

test('it lists torrent metadata without adding the torrent', function () {
    Http::fake([
        'rqbit.test/torrents?*' => Http::response([
            'id' => null,
            'details' => [
                'info_hash' => '0123456789abcdef0123456789abcdef01234567',
                'name' => 'Example Torrent',
                'output_folder' => '/downloads/0123456789abcdef0123456789abcdef01234567',
                'files' => [[
                    'name' => 'video.mp4',
                    'components' => ['Movies', 'video.mp4'],
                    'length' => 1000,
                ]],
            ],
        ], 200),
    ]);

    $torrent = Torrent::factory()
        ->for(User::factory())
        ->create([
            'source_type' => TorrentSourceType::Magnet,
            'status' => TorrentStatus::PendingMetadata,
            'info_hash' => '0123456789abcdef0123456789abcdef01234567',
            'magnet_uri' => 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567',
        ]);

    $metadata = app(RqbitClient::class)->inspect($torrent);

    expect(data_get($metadata, 'details.name'))->toBe('Example Torrent')
        ->and(app(RqbitClient::class)->normalizeFiles(data_get($metadata, 'details')))->toBe([
            ['name' => 'Movies/video.mp4', 'size' => 1000],
        ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'list_only=true'));
});

test('it reads torrent details, stats, files, and deletes the torrent', function () {
    Http::fake([
        'rqbit.test/torrents/abc123/stats/v1' => Http::response([
            'state' => 'live',
            'progress_bytes' => 420,
            'total_bytes' => 1000,
            'finished' => false,
        ], 200),
        'rqbit.test/torrents/abc123' => Http::response([
            'id' => 0,
            'info_hash' => 'abc123',
            'name' => 'Example',
            'output_folder' => '/downloads/abc123',
            'files' => [[
                'name' => 'video.mp4',
                'components' => ['video.mp4'],
                'length' => 1000,
            ]],
        ], 200),
        'rqbit.test/torrents/abc123/delete' => Http::response([], 200),
    ]);

    $client = app(RqbitClient::class);

    expect($client->getTorrent('abc123'))->toMatchArray([
        'hash' => 'abc123',
        'progress' => 0.42,
        'downloaded' => 420,
    ])->and($client->files('abc123'))->toBe([
        ['name' => 'video.mp4', 'size' => 1000],
    ]);

    $client->delete('abc123');

    Http::assertSent(fn ($request) => $request->url() === 'http://rqbit.test/torrents/abc123/delete');
});
