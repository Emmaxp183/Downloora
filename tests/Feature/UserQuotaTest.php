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

    expect($user->storage_quota_bytes)->toBe(734003200);
});
