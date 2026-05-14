<?php

use App\Enums\TorrentStatus;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Torrents\RqbitClient;
use App\Services\Torrents\TorrentEngineClient;

test('owners can cancel active torrents and delete partial rqbit files', function () {
    $user = User::factory()->create();
    $torrent = Torrent::factory()->for($user)->create([
        'status' => TorrentStatus::Downloading,
        'qbittorrent_hash' => 'abc123',
        'error_message' => 'old error',
    ]);

    $deleted = false;

    app()->instance(TorrentEngineClient::class, new class($deleted) extends RqbitClient
    {
        public function __construct(private bool &$deleted) {}

        public function delete(string $hash, bool $deleteFiles = true): void
        {
            $this->deleted = $hash === 'abc123' && $deleteFiles;
        }
    });

    $this->actingAs($user)
        ->delete(route('torrents.destroy', $torrent))
        ->assertRedirect(route('dashboard', absolute: false));

    expect($torrent->refresh()->status)->toBe(TorrentStatus::Cancelled)
        ->and($torrent->error_message)->toBeNull()
        ->and($deleted)->toBeTrue();
});

test('users cannot cancel torrents owned by someone else', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $torrent = Torrent::factory()->for($otherUser)->create([
        'status' => TorrentStatus::Downloading,
        'qbittorrent_hash' => 'abc123',
    ]);

    $deleted = false;

    app()->instance(TorrentEngineClient::class, new class($deleted) extends RqbitClient
    {
        public function __construct(private bool &$deleted) {}

        public function delete(string $hash, bool $deleteFiles = true): void
        {
            $this->deleted = true;
        }
    });

    $this->actingAs($user)
        ->delete(route('torrents.destroy', $torrent))
        ->assertForbidden();

    expect($torrent->refresh()->status)->toBe(TorrentStatus::Downloading)
        ->and($deleted)->toBeFalse();
});
