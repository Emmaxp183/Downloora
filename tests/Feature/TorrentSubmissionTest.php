<?php

use App\Enums\TorrentSourceType;
use App\Enums\TorrentStatus;
use App\Jobs\InspectTorrentMetadata;
use App\Models\Torrent;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

test('it identifies active torrents for a user', function () {
    $user = User::factory()->create();

    Torrent::factory()->for($user)->create(['status' => TorrentStatus::PendingMetadata]);
    Torrent::factory()->for($user)->create(['status' => TorrentStatus::Completed]);

    expect($user->torrents()->active()->count())->toBe(1);
});

test('users can submit a magnet torrent', function () {
    Bus::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/torrents', [
            'magnet_uri' => 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567',
        ])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('torrents', [
        'user_id' => $user->id,
        'status' => TorrentStatus::PendingMetadata->value,
    ]);

    Bus::assertDispatched(InspectTorrentMetadata::class);
});

test('unverified users can submit a magnet torrent', function () {
    Bus::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post('/torrents', [
            'magnet_uri' => 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567',
        ])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHasNoErrors();

    expect(Torrent::query()->count())->toBe(1);
    Bus::assertDispatched(InspectTorrentMetadata::class);
});

test('users can upload a torrent file', function () {
    Bus::fake();
    Storage::fake('s3');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/torrents', [
            'torrent_file' => UploadedFile::fake()->create('example.torrent', 12),
        ])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHasNoErrors();

    $torrent = Torrent::query()->firstOrFail();

    expect($torrent->user_id)->toBe($user->id)
        ->and($torrent->source_type)->toBe(TorrentSourceType::TorrentFile)
        ->and($torrent->magnet_uri)->toBeNull()
        ->and($torrent->status)->toBe(TorrentStatus::PendingMetadata)
        ->and($torrent->torrent_file_path)->not->toBeNull();

    Storage::disk('s3')->assertExists($torrent->torrent_file_path);
    Bus::assertDispatched(InspectTorrentMetadata::class);
});

test('uploaded torrent files must have a torrent extension', function () {
    Bus::fake();
    Storage::fake('s3');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post('/torrents', [
            'torrent_file' => UploadedFile::fake()->create('example.txt', 12),
        ])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHasErrors('torrent_file');

    expect(Torrent::query()->count())->toBe(0);
    Bus::assertNotDispatched(InspectTorrentMetadata::class);
});

test('users can submit another magnet torrent while below the active download limit', function () {
    Bus::fake();

    $user = User::factory()->create();

    Torrent::factory()->for($user)->create(['status' => TorrentStatus::Downloading]);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post('/torrents', [
            'magnet_uri' => 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567',
        ])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHasNoErrors();

    expect($user->torrents()->count())->toBe(2);
    $this->assertDatabaseHas('torrents', [
        'user_id' => $user->id,
        'magnet_uri' => 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567',
        'status' => TorrentStatus::PendingMetadata->value,
    ]);
    Bus::assertDispatched(InspectTorrentMetadata::class);
});

test('users at the active download limit save submitted magnet links to wishlist', function () {
    Bus::fake();

    config(['torrents.per_user_active_limit' => 1]);

    $user = User::factory()->create();

    Torrent::factory()->for($user)->create(['status' => TorrentStatus::Downloading]);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post('/torrents', [
            'magnet_uri' => 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567',
        ])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHasNoErrors();

    expect($user->torrents()->count())->toBe(1);
    $this->assertDatabaseHas('wishlist_items', [
        'user_id' => $user->id,
        'url' => 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567',
        'source_type' => 'magnet',
    ]);
    Bus::assertNotDispatched(InspectTorrentMetadata::class);
});

test('users at the active download limit still cannot upload a torrent file', function () {
    Bus::fake();
    Storage::fake('s3');

    config(['torrents.per_user_active_limit' => 1]);

    $user = User::factory()->create();

    Torrent::factory()->for($user)->create(['status' => TorrentStatus::Downloading]);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post('/torrents', [
            'torrent_file' => UploadedFile::fake()->create('example.torrent', 12),
        ])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHasErrors('torrent_file');

    expect($user->torrents()->count())->toBe(1)
        ->and(WishlistItem::query()->count())->toBe(0);
    Bus::assertNotDispatched(InspectTorrentMetadata::class);
});
