<?php

use App\Jobs\InspectTorrentMetadata;
use App\Models\Torrent;
use Illuminate\Queue\Middleware\WithoutOverlapping;

test('queue retry window covers long-running workers and metadata polling', function () {
    $metadataWaitSeconds = (int) ceil(
        ((int) config('torrents.rqbit.metadata_poll_attempts') * (int) config('torrents.rqbit.metadata_poll_interval_ms')) / 1000
    );

    expect(config('queue.connections.redis.retry_after'))->toBeGreaterThan(3600)
        ->and(config('queue.connections.redis.retry_after'))->toBeGreaterThan($metadataWaitSeconds)
        ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan(3600)
        ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan($metadataWaitSeconds);
});

test('rqbit throughput settings use uncapped transfers and expanded stream concurrency', function () {
    expect(config('torrents.global_active_limit'))->toBeGreaterThanOrEqual(100)
        ->and(config('torrents.per_user_active_limit'))->toBeGreaterThanOrEqual(50)
        ->and(config('torrents.rqbit.add_timeout'))->toBeGreaterThanOrEqual(120)
        ->and(config('torrents.rqbit.download_limit_bytes'))->toBe(4294967295)
        ->and(config('torrents.rqbit.upload_limit_bytes'))->toBe(4294967295)
        ->and(config('torrents.rqbit.keep_after_import'))->toBeTrue()
        ->and(config('torrents.rqbit.torrenting_max_port'))->toBeGreaterThanOrEqual(6999)
        ->and(config('torrents.rqbit.worker_threads'))->toBeGreaterThanOrEqual(32)
        ->and(config('torrents.rqbit.max_blocking_threads'))->toBeGreaterThanOrEqual(128)
        ->and(config('torrents.rqbit.concurrent_init_limit'))->toBeGreaterThanOrEqual(64)
        ->and(config('torrents.rqbit.tracker_refresh_interval'))->toBe('30s')
        ->and(config('torrents.rqbit.peer_connect_timeout'))->toBe('2s')
        ->and(config('torrents.rqbit.peer_read_write_timeout'))->toBe('10s')
        ->and(config('torrents.rqbit.dht_queries_per_second'))->toBeGreaterThanOrEqual(256);
});

test('torrent metadata inspection prevents overlapping jobs for the same torrent', function () {
    config([
        'torrents.rqbit.metadata_poll_attempts' => 90,
        'torrents.rqbit.metadata_poll_interval_ms' => 2000,
    ]);

    $torrent = Torrent::factory()->create();
    $middleware = (new InspectTorrentMetadata($torrent))->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[0]->releaseAfter)->toBeNull()
        ->and($middleware[0]->expiresAfter)->toBeGreaterThanOrEqual(600);
});
