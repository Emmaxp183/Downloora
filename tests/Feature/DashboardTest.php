<?php

use App\Enums\TorrentStatus;
use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Models\Torrent;
use App\Models\User;
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

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('quota.used_bytes', 250)
            ->where('quota.quota_bytes', 1000)
            ->where('quota.remaining_bytes', 750)
            ->where('activeTorrent.name', 'Active Torrent')
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
        );
});
