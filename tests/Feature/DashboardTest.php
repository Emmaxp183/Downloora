<?php

use App\Enums\TorrentStatus;
use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Models\Torrent;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\Downloads\DownloadSpeedSampler;
use App\Services\System\ServerMetrics;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard includes quota, active torrent, recent torrents, and recent files', function () {
    app()->instance(ServerMetrics::class, new class extends ServerMetrics
    {
        public function snapshot(): array
        {
            return [
                'cpu' => ['usage_percent' => 41, 'cores' => 8],
                'memory' => ['used_bytes' => 4_294_967_296, 'total_bytes' => 8_589_934_592, 'usage_percent' => 50],
                'network' => ['received_bytes_per_second' => 125_000_000, 'transmitted_bytes_per_second' => 12_500_000, 'total_bytes_per_second' => 137_500_000],
                'sampled_at' => '2026-05-10T00:00:00.000000Z',
            ];
        }
    });

    app()->instance(DownloadSpeedSampler::class, new class extends DownloadSpeedSampler
    {
        public function sample(string $key, int $downloadedBytes): int
        {
            return 64_000_000;
        }
    });

    $user = User::factory()->create(['storage_quota_bytes' => 1000]);
    StorageUsageEvent::factory()->for($user)->create(['delta_bytes' => 250]);

    Torrent::factory()->for($user)->create([
        'name' => 'Active Torrent',
        'status' => TorrentStatus::Downloading,
        'progress' => 42,
    ]);

    $completedTorrent = Torrent::factory()->for($user)->create([
        'name' => 'Stored Torrent',
        'status' => TorrentStatus::Completed,
    ]);

    StoredFile::factory()->for($user)->for($completedTorrent)->create(['name' => 'video.mp4']);
    WishlistItem::factory()->for($user)->create([
        'title' => 'Saved Later',
        'url' => 'magnet:?xt=urn:btih:example',
        'url_hash' => hash('sha256', 'magnet:?xt=urn:btih:example'),
        'source_type' => 'magnet',
        'source_domain' => null,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('quota.used_bytes', 250)
            ->where('quota.quota_bytes', 1000)
            ->where('quota.remaining_bytes', 750)
            ->where('activeTorrent.name', 'Active Torrent')
            ->where('activeTorrent.download_speed_bytes_per_second', 64_000_000)
            ->where('activeDownloadCount', 1)
            ->where('activeDownloadLimit', 5)
            ->where('activeDownloadLimitReached', false)
            ->where('systemMetrics.cpu.usage_percent', 41)
            ->where('systemMetrics.cpu.cores', 8)
            ->where('systemMetrics.memory.usage_percent', 50)
            ->where('systemMetrics.network.total_bytes_per_second', 137_500_000)
            ->has('wishlistItems', 1)
            ->where('wishlistItems.0.title', 'Saved Later')
            ->where('wishlistItems.0.source_type', 'magnet')
            ->has('recentTorrents', 2)
            ->has('recentFileFolders', 1)
            ->where('recentFileFolders.0.name', 'Stored Torrent')
            ->has('recentFileFolders.0.files', 1)
        );
});

test('dashboard accepts a safe browser extension url prefill', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard', [
            'url' => 'https://example.com/video.mp4',
            'source' => 'browser-extension',
            'auto' => '1',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('prefillUrl', 'https://example.com/video.mp4')
            ->where('prefillAutoSubmit', true)
            ->where('prefillWishlistSave', false)
        );
});

test('dashboard accepts a safe browser extension wishlist prefill', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard', [
            'url' => 'https://example.com/video.mp4',
            'source' => 'browser-extension',
            'wishlist' => '1',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('prefillUrl', 'https://example.com/video.mp4')
            ->where('prefillAutoSubmit', false)
            ->where('prefillWishlistSave', true)
        );
});

test('dashboard ignores invalid browser extension url prefill values', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard', ['url' => 'javascript:alert(1)']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('prefillUrl', null)
            ->where('prefillAutoSubmit', false)
            ->where('prefillWishlistSave', false)
        );
});
