<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

test('users can sync youtube cookies for yt-dlp', function () {
    $path = storage_path('framework/testing/youtube-cookies.txt');

    config(['media.yt_dlp.cookies' => $path]);
    File::delete($path);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('youtube-cookies.store'), [
            'cookies' => [
                [
                    'domain' => '.youtube.com',
                    'expirationDate' => 1793927348,
                    'hostOnly' => false,
                    'httpOnly' => true,
                    'name' => '__Secure-3PSID',
                    'path' => '/',
                    'sameSite' => 'no_restriction',
                    'secure' => true,
                    'session' => false,
                    'value' => 'secret-cookie-value',
                ],
            ],
        ])
        ->assertNoContent();

    expect(File::exists($path))->toBeTrue()
        ->and(File::get($path))->toContain(".youtube.com\tTRUE\t/\tTRUE\t1793927348\t__Secure-3PSID\tsecret-cookie-value");
});

test('youtube cookie sync rejects non-youtube cookies', function () {
    $path = storage_path('framework/testing/youtube-cookies.txt');

    config(['media.yt_dlp.cookies' => $path]);
    File::delete($path);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('youtube-cookies.store'), [
            'cookies' => [
                [
                    'domain' => '.example.com',
                    'expirationDate' => 1793927348,
                    'hostOnly' => false,
                    'httpOnly' => true,
                    'name' => 'SID',
                    'path' => '/',
                    'sameSite' => 'no_restriction',
                    'secure' => true,
                    'session' => false,
                    'value' => 'secret-cookie-value',
                ],
            ],
        ])
        ->assertUnprocessable();

    expect(File::exists($path))->toBeFalse();
});

test('youtube cookie sync accepts guest-only youtube cookies', function () {
    $path = storage_path('framework/testing/youtube-cookies.txt');

    config(['media.yt_dlp.cookies' => $path]);
    File::delete($path);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('youtube-cookies.store'), [
            'cookies' => [
                [
                    'domain' => '.youtube.com',
                    'expirationDate' => 1793927348,
                    'hostOnly' => false,
                    'httpOnly' => false,
                    'name' => 'VISITOR_INFO1_LIVE',
                    'path' => '/',
                    'sameSite' => 'no_restriction',
                    'secure' => true,
                    'session' => false,
                    'value' => 'guest-cookie-value',
                ],
            ],
        ])
        ->assertNoContent();

    expect(File::exists($path))->toBeTrue()
        ->and(File::get($path))->toContain(".youtube.com\tTRUE\t/\tTRUE\t1793927348\tVISITOR_INFO1_LIVE\tguest-cookie-value");
});

test('users can upload a youtube cookies file for yt-dlp', function () {
    $path = storage_path('framework/testing/youtube-cookies.txt');
    $contents = implode("\n", [
        '# Netscape HTTP Cookie File',
        ".youtube.com\tTRUE\t/\tTRUE\t1793927348\t__Secure-3PSID\tsecret-cookie-value",
        '',
    ]);

    config(['media.yt_dlp.cookies' => $path]);
    File::delete($path);

    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('cookies.txt', $contents);

    $this->actingAs($user)
        ->post(route('youtube-cookies.store'), [
            'cookies_file' => $file,
        ])
        ->assertNoContent();

    expect(File::exists($path))->toBeTrue()
        ->and(File::get($path))->toBe($contents);
});

test('youtube cookie upload replaces an existing cookie file', function () {
    $path = storage_path('framework/testing/youtube-cookies.txt');
    $contents = implode("\n", [
        '# Netscape HTTP Cookie File',
        ".youtube.com\tTRUE\t/\tTRUE\t1793927348\tSID\tnew-secret-cookie-value",
        '',
    ]);

    config(['media.yt_dlp.cookies' => $path]);
    File::ensureDirectoryExists(dirname($path));
    File::put($path, 'old-cookie-file');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('cookies.txt', $contents);

    $this->actingAs($user)
        ->post(route('youtube-cookies.store'), [
            'cookies_file' => $file,
        ])
        ->assertNoContent();

    expect(File::get($path))->toBe($contents);
});


test('youtube cookie upload rejects files without youtube cookies', function () {
    $path = storage_path('framework/testing/youtube-cookies.txt');
    $contents = implode("\n", [
        '# Netscape HTTP Cookie File',
        ".example.com\tTRUE\t/\tTRUE\t1793927348\tSID\tsecret-cookie-value",
        '',
    ]);

    config(['media.yt_dlp.cookies' => $path]);
    File::delete($path);

    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('cookies.txt', $contents);

    $this->actingAs($user)
        ->post(route('youtube-cookies.store'), [
            'cookies_file' => $file,
        ])
        ->assertSessionHasErrors('cookies_file');

    expect(File::exists($path))->toBeFalse();
});

test('youtube cookie upload accepts guest-only youtube cookies', function () {
    $path = storage_path('framework/testing/youtube-cookies.txt');
    $contents = implode("\n", [
        '# Netscape HTTP Cookie File',
        ".youtube.com\tTRUE\t/\tTRUE\t1793927348\tVISITOR_INFO1_LIVE\tguest-cookie-value",
        '',
    ]);

    config(['media.yt_dlp.cookies' => $path]);
    File::delete($path);

    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('cookies.txt', $contents);

    $this->actingAs($user)
        ->post(route('youtube-cookies.store'), [
            'cookies_file' => $file,
        ])
        ->assertNoContent();

    expect(File::exists($path))->toBeTrue()
        ->and(File::get($path))->toBe($contents);
});
