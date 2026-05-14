<?php

use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('new users receive the default storage quota', function () {
    $this->post(route('register.store'), [
        'name' => 'Seed User',
        'email' => 'seed@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'seed@example.com')->firstOrFail();

    expect($user->storage_quota_bytes)->toBe(2 * 1024 * 1024 * 1024);
});

test('legacy starter quotas are upgraded to two gigabytes', function () {
    $user = User::factory()->create([
        'storage_quota_bytes' => 700 * 1024 * 1024,
    ]);

    $migration = require database_path('migrations/2026_05_11_221519_upgrade_existing_starter_user_quotas_to_two_gigabytes.php');

    $migration->up();

    expect($user->refresh()->storage_quota_bytes)->toBe(2 * 1024 * 1024 * 1024);
});
