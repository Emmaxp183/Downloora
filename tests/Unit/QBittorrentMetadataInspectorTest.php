<?php

use App\Enums\TorrentSourceType;
use App\Enums\TorrentStatus;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Torrents\RqbitMetadataInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it inspects rqbit magnet metadata', function () {
    config([
        'torrents.rqbit.base_url' => 'http://rqbit.test',
        'torrents.rqbit.metadata_timeout' => 30,
        'torrents.rqbit.download_path' => '/downloads',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'rqbit.test/torrents?*' => Http::response([
            'id' => null,
            'details' => [
                'info_hash' => '3f2f600c7a5637de5adf972b053996e57f2b8b0d',
                'name' => 'Existing Torrent',
                'files' => [[
                    'name' => 'movie.mp4',
                    'components' => ['movie.mp4'],
                    'length' => 1234,
                ]],
            ],
        ], 200),
    ]);

    $torrent = Torrent::factory()
        ->for(User::factory())
        ->create([
            'source_type' => TorrentSourceType::Magnet,
            'status' => TorrentStatus::PendingMetadata,
            'magnet_uri' => 'magnet:?xt=urn:btih:3f2f600c7a5637de5adf972b053996e57f2b8b0d&dn=Existing',
            'info_hash' => null,
        ]);

    $metadata = app(RqbitMetadataInspector::class)->inspect($torrent);

    expect($metadata->name)->toBe('Existing Torrent')
        ->and($metadata->infoHash)->toBe('3f2f600c7a5637de5adf972b053996e57f2b8b0d')
        ->and($metadata->totalSizeBytes)->toBe(1234)
        ->and($metadata->files)->toBe([
            ['path' => 'movie.mp4', 'size_bytes' => 1234],
        ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'list_only=true'));
});
