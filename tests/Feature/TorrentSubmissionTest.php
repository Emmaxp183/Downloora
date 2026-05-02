<?php

use App\Enums\TorrentStatus;
use App\Jobs\InspectTorrentMetadata;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

test('it identifies active torrents for a user', function () {
    $user = User::factory()->create();

    Torrent::factory()->for($user)->create(['status' => TorrentStatus::PendingMetadata]);
    Torrent::factory()->for($user)->create(['status' => TorrentStatus::Completed]);

    expect($user->torrents()->active()->count())->toBe(1);
});

test('verified users can submit a magnet torrent', function () {
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

test('unverified users cannot submit a magnet torrent', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post('/torrents', [
            'magnet_uri' => 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567',
        ])
        ->assertRedirect(route('verification.notice', absolute: false));

    expect(Torrent::query()->count())->toBe(0);
});

test('users with an active torrent cannot submit another torrent', function () {
    Bus::fake();

    $user = User::factory()->create();

    Torrent::factory()->for($user)->create(['status' => TorrentStatus::Downloading]);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post('/torrents', [
            'magnet_uri' => 'magnet:?xt=urn:btih:0123456789abcdef0123456789abcdef01234567',
        ])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHasErrors('magnet_uri');

    expect($user->torrents()->count())->toBe(1);
    Bus::assertNotDispatched(InspectTorrentMetadata::class);
});
