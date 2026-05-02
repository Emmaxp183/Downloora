<?php

use App\Enums\TorrentStatus;
use App\Models\Torrent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('non admin users cannot access admin routes', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('admins can view users and torrent states', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['name' => 'Storage User']);

    Torrent::factory()->for($user)->create([
        'name' => 'Active Torrent',
        'status' => TorrentStatus::Downloading,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Index')
            ->has('users', 2)
        );

    $this->actingAs($admin)
        ->get(route('admin.torrents.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Torrents/Index')
            ->has('torrents', 1)
            ->where('torrents.0.name', 'Active Torrent')
        );
});
