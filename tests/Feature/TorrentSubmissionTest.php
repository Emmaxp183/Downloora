<?php

use App\Enums\TorrentSourceType;
use App\Enums\TorrentStatus;
use App\Jobs\InspectTorrentMetadata;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

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

test('verified users can upload a torrent file', function () {
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
