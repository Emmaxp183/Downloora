<?php

use App\Enums\TorrentSourceType;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Torrents\RqbitMetadataInspector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'torrents.rqbit.base_url' => 'http://rqbit.test',
        'torrents.rqbit.metadata_timeout' => 30,
        'torrents.rqbit.download_path' => '/downloads',
    ]);

    Http::preventStrayRequests();
});

test('it inspects magnet metadata with rqbit list only mode', function () {
    Http::fake([
        'rqbit.test/torrents?*' => Http::response([
            'id' => null,
            'details' => [
                'info_hash' => '0123456789abcdef0123456789abcdef01234567',
                'name' => 'Example Torrent',
                'output_folder' => '/downloads/0123456789abcdef0123456789abcdef01234567',
                'files' => [
                    ['name' => 'video.mp4', 'components' => ['video.mp4'], 'length' => 700],
                    ['name' => 'poster.jpg', 'components' => ['poster.jpg'], 'length' => 300],
                ],
            ],
        ], 200),
    ]);

    $torrent = Torrent::factory()
        ->for(User::factory())
        ->create([
            'source_type' => TorrentSourceType::Magnet,
            'magnet_uri' => 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567',
        ]);

    $metadata = app(RqbitMetadataInspector::class)->inspect($torrent);

    expect($metadata->name)->toBe('Example Torrent')
        ->and($metadata->infoHash)->toBe('0123456789abcdef0123456789abcdef01234567')
        ->and($metadata->totalSizeBytes)->toBe(1000)
        ->and($metadata->files)->toHaveCount(2);

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'http://rqbit.test/torrents?')
        && str_contains($request->url(), 'list_only=true')
        && $request->body() === $torrent->magnet_uri);
});

test('it inspects torrent file metadata with rqbit', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('torrents/example.torrent', 'torrent-bytes');

    Http::fake([
        'rqbit.test/torrents?*' => Http::response([
            'id' => null,
            'details' => [
                'info_hash' => 'abcdef0123456789abcdef0123456789abcdef01',
                'name' => 'Uploaded Torrent',
                'output_folder' => '/downloads/1',
                'files' => [[
                    'name' => 'archive.zip',
                    'components' => ['archive.zip'],
                    'length' => 1000,
                ]],
            ],
        ], 200),
    ]);

    $torrent = Torrent::factory()
        ->for(User::factory())
        ->create([
            'id' => 1,
            'source_type' => TorrentSourceType::TorrentFile,
            'magnet_uri' => null,
            'torrent_file_path' => 'torrents/example.torrent',
        ]);

    $metadata = app(RqbitMetadataInspector::class)->inspect($torrent);

    expect($metadata->name)->toBe('Uploaded Torrent')
        ->and($metadata->infoHash)->toBe('abcdef0123456789abcdef0123456789abcdef01')
        ->and($metadata->totalSizeBytes)->toBe(1000)
        ->and($metadata->files)->toBe([
            ['path' => 'archive.zip', 'size_bytes' => 1000],
        ]);

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'http://rqbit.test/torrents?')
        && str_contains($request->url(), 'list_only=true')
        && $request->body() === 'torrent-bytes');
});
