<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

test('guests can start google authentication', function () {
    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn(new class
        {
            public function redirect(): SymfonyRedirectResponse
            {
                return new SymfonyRedirectResponse('https://accounts.google.com/o/oauth2/auth');
            }
        });

    $this->get(route('auth.google.redirect'))
        ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
});

test('google callback creates a verified user and logs them in', function () {
    $googleUser = new SocialiteUser;
    $googleUser->map([
        'id' => 'google-123',
        'name' => 'Google User',
        'email' => 'google@example.com',
        'avatar' => 'https://example.com/avatar.jpg',
    ]);

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn(new class($googleUser)
        {
            public function __construct(private readonly SocialiteUser $googleUser) {}

            public function user(): SocialiteUser
            {
                return $this->googleUser;
            }
        });

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'google@example.com')->firstOrFail();

    expect($user->google_id)->toBe('google-123')
        ->and($user->avatar_url)->toBe('https://example.com/avatar.jpg')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->toBeNull();

    $this->assertAuthenticatedAs($user);
});

test('google callback links an existing email account', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'existing@example.com',
        'google_id' => null,
        'avatar_url' => null,
    ]);

    $googleUser = new SocialiteUser;
    $googleUser->map([
        'id' => 'google-existing',
        'name' => 'Existing User',
        'email' => 'existing@example.com',
        'avatar' => 'https://example.com/existing.jpg',
    ]);

    Socialite::shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn(new class($googleUser)
        {
            public function __construct(private readonly SocialiteUser $googleUser) {}

            public function user(): SocialiteUser
            {
                return $this->googleUser;
            }
        });

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard'));

    $user->refresh();

    expect($user->google_id)->toBe('google-existing')
        ->and($user->avatar_url)->toBe('https://example.com/existing.jpg')
        ->and($user->email_verified_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
});
