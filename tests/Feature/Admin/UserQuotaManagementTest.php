<?php

use App\Enums\TorrentStatus;
use App\Models\Torrent;
use App\Models\User;

test('admins can update user quotas', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['storage_quota_bytes' => 1000]);

    $this->actingAs($admin)
        ->patch(route('admin.users.quota.update', $user), [
            'storage_quota_mb' => 25,
        ])
        ->assertRedirect(route('admin.users.index', absolute: false));

    expect($user->refresh()->storage_quota_bytes)->toBe(26214400);
});

test('admins can cancel active torrents', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $torrent = Torrent::factory()->create(['status' => TorrentStatus::Downloading]);

    $this->actingAs($admin)
        ->delete(route('admin.torrents.destroy', $torrent))
        ->assertRedirect(route('admin.torrents.index', absolute: false));

    expect($torrent->refresh()->status)->toBe(TorrentStatus::Cancelled);
});
