<?php

use App\Models\MediaImport;
use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['transferd.enabled' => false]);
});

test('users only see their own stored files in the library', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $torrent = Torrent::factory()->for($user)->create(['name' => 'Mine Folder']);

    StoredFile::factory()->for($user)->for($torrent)->create(['name' => 'mine.mp4']);
    StoredFile::factory()->for($otherUser)->create(['name' => 'theirs.mp4']);

    $this->actingAs($user)
        ->get(route('library.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Index')
            ->has('fileFolders', 1)
            ->where('fileFolders.0.name', 'Mine Folder')
            ->has('fileFolders.0.files', 1)
            ->where('fileFolders.0.files.0.name', 'mine.mp4')
            ->where('fileFolders.0.files.0.download_url', fn (string $url): bool => str_contains($url, '/files/'))
            ->where('fileFolders.0.files.0.stream_url', fn (string $url): bool => str_contains($url, '/stream'))
        );
});

test('media folders include folder download and delete metadata', function () {
    $user = User::factory()->create();
    $mediaImport = MediaImport::factory()->for($user)->create(['title' => 'Saved Video']);

    StoredFile::factory()->for($user)->create([
        'torrent_id' => null,
        'media_import_id' => $mediaImport->id,
        'name' => 'saved-video.mp4',
    ]);

    $this->actingAs($user)
        ->get(route('library.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Index')
            ->has('fileFolders', 1)
            ->where('fileFolders.0.name', 'Saved Video')
            ->where('fileFolders.0.media_import_id', $mediaImport->id)
            ->where('fileFolders.0.torrent_id', null)
            ->where('fileFolders.0.download_url', fn (?string $url): bool => str_contains($url ?? '', '/media-folders/'.$mediaImport->id.'/download'))
        );
});

test('signed download routes are required and scoped to the owner', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $file = StoredFile::factory()->for($user)->create([
        's3_disk' => 's3',
        's3_key' => 'users/'.$user->id.'/example.txt',
        'name' => 'example.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => 5,
    ]);

    Storage::disk('s3')->put($file->s3_key, 'hello');

    $this->actingAs($user)
        ->get(route('files.download', $file))
        ->assertForbidden();

    $signedUrl = URL::signedRoute('files.download', $file);

    $this->actingAs($otherUser)
        ->get($signedUrl)
        ->assertForbidden();

    $this->actingAs($user)
        ->get($signedUrl)
        ->assertOk()
        ->assertHeader('content-length', '5')
        ->assertHeader('accept-ranges', 'bytes')
        ->assertStreamedContent('hello');
});

test('signed file downloads support byte ranges for segmented clients', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $file = StoredFile::factory()->for($user)->create([
        's3_disk' => 's3',
        's3_key' => 'users/'.$user->id.'/range.txt',
        'name' => 'range.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => 10,
    ]);

    Storage::disk('s3')->put($file->s3_key, '0123456789');

    $this->actingAs($user)
        ->withHeader('Range', 'bytes=2-5')
        ->get(URL::signedRoute('files.download', $file))
        ->assertStatus(206)
        ->assertHeader('content-range', 'bytes 2-5/10')
        ->assertHeader('content-length', '4')
        ->assertHeader('accept-ranges', 'bytes')
        ->assertStreamedContent('2345');
});

test('owners can stream files through signed routes', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $file = StoredFile::factory()->for($user)->create([
        's3_disk' => 's3',
        's3_key' => 'users/'.$user->id.'/video.mp4',
        'name' => 'video.mp4',
        'mime_type' => 'video/mp4',
    ]);

    Storage::disk('s3')->put($file->s3_key, 'video');

    $this->actingAs($user)
        ->get(URL::signedRoute('files.stream', $file))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename=video.mp4')
        ->assertHeader('accept-ranges', 'bytes')
        ->assertStreamedContent('video');
});

test('signed file streams support byte ranges for online video playback', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $file = StoredFile::factory()->for($user)->create([
        's3_disk' => 's3',
        's3_key' => 'users/'.$user->id.'/movie.mp4',
        'name' => 'movie.mp4',
        'mime_type' => 'video/mp4',
        'size_bytes' => 10,
    ]);

    Storage::disk('s3')->put($file->s3_key, '0123456789');

    $this->actingAs($user)
        ->withHeader('Range', 'bytes=4-9')
        ->get(URL::signedRoute('files.stream', $file))
        ->assertStatus(206)
        ->assertHeader('content-type', 'video/mp4')
        ->assertHeader('content-disposition', 'inline; filename=movie.mp4')
        ->assertHeader('content-range', 'bytes 4-9/10')
        ->assertHeader('content-length', '6')
        ->assertHeader('accept-ranges', 'bytes')
        ->assertStreamedContent('456789');
});

test('signed file streams support suffix byte ranges for video seeking', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $file = StoredFile::factory()->for($user)->create([
        's3_disk' => 's3',
        's3_key' => 'users/'.$user->id.'/clip.mp4',
        'name' => 'clip.mp4',
        'mime_type' => 'video/mp4',
        'size_bytes' => 10,
    ]);

    Storage::disk('s3')->put($file->s3_key, '0123456789');

    $this->actingAs($user)
        ->withHeader('Range', 'bytes=-4')
        ->get(URL::signedRoute('files.stream', $file))
        ->assertStatus(206)
        ->assertHeader('content-range', 'bytes 6-9/10')
        ->assertHeader('content-length', '4')
        ->assertStreamedContent('6789');
});

test('signed file streams redirect to transferd when enabled', function () {
    config([
        'transferd.enabled' => true,
        'transferd.public_url' => '/__transferd',
        'transferd.signing_key' => 'test-transfer-secret',
        'transferd.url_ttl_seconds' => 300,
    ]);

    $user = User::factory()->create();
    $file = StoredFile::factory()->for($user)->create([
        's3_disk' => 'local',
        's3_bucket' => null,
        's3_key' => 'qbittorrent/hash/movie.mp4',
        'name' => 'movie.mp4',
        'mime_type' => 'video/mp4',
        'size_bytes' => 10,
    ]);

    $response = $this->actingAs($user)
        ->get(URL::signedRoute('files.stream', $file))
        ->assertRedirect();

    $location = $response->headers->get('Location');
    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);
    [$payload, $signature] = explode('.', $query['token']);
    $decodedPayload = json_decode(base64_decode(strtr(str_pad($payload, strlen($payload) + (4 - strlen($payload) % 4) % 4, '='), '-_', '+/')), true);
    $expectedSignature = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, 'test-transfer-secret', true)), '+/', '-_'), '=');

    expect((string) parse_url((string) $location, PHP_URL_PATH))->toBe('/__transferd/files')
        ->and($signature)->toBe($expectedSignature)
        ->and($decodedPayload)->toMatchArray([
            'backend' => 'local',
            'bucket' => config('filesystems.disks.s3.bucket'),
            'key' => 'qbittorrent/hash/movie.mp4',
            'name' => 'movie.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 10,
            'disposition' => 'inline; filename=movie.mp4',
        ]);
});

test('signed file downloads redirect s3 objects to transferd when enabled', function () {
    config([
        'transferd.enabled' => true,
        'transferd.public_url' => 'https://cdn.example.test/__transferd',
        'transferd.signing_key' => 'test-transfer-secret',
    ]);

    $user = User::factory()->create();
    $file = StoredFile::factory()->for($user)->create([
        's3_disk' => 's3',
        's3_bucket' => 'downloora',
        's3_key' => 'users/'.$user->id.'/media/movie.mp4',
        'name' => 'movie.mp4',
        'mime_type' => 'video/mp4',
        'size_bytes' => 10,
    ]);

    $response = $this->actingAs($user)
        ->get(URL::signedRoute('files.download', $file))
        ->assertRedirect();

    $location = $response->headers->get('Location');
    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);
    [$payload] = explode('.', $query['token']);
    $decodedPayload = json_decode(base64_decode(strtr(str_pad($payload, strlen($payload) + (4 - strlen($payload) % 4) % 4, '='), '-_', '+/')), true);

    expect($location)->toStartWith('https://cdn.example.test/__transferd/files?token=')
        ->and($decodedPayload)->toMatchArray([
            'backend' => 's3',
            'bucket' => 'downloora',
            'key' => 'users/'.$user->id.'/media/movie.mp4',
            'disposition' => 'attachment; filename=movie.mp4',
        ]);
});

test('signed file streams reject invalid byte ranges', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $file = StoredFile::factory()->for($user)->create([
        's3_disk' => 's3',
        's3_key' => 'users/'.$user->id.'/short.mp4',
        'name' => 'short.mp4',
        'mime_type' => 'video/mp4',
        'size_bytes' => 5,
    ]);

    Storage::disk('s3')->put($file->s3_key, 'short');

    $this->actingAs($user)
        ->withHeader('Range', 'bytes=9-12')
        ->get(URL::signedRoute('files.stream', $file))
        ->assertStatus(416)
        ->assertHeader('content-range', 'bytes */5')
        ->assertHeader('content-length', '0')
        ->assertStreamedContent('');
});

test('owners can delete files from object storage and their library', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $file = StoredFile::factory()->for($user)->create([
        's3_disk' => 's3',
        's3_key' => 'users/'.$user->id.'/delete-me.mp4',
        'name' => 'delete-me.mp4',
        'size_bytes' => 500,
    ]);

    Storage::disk('s3')->put($file->s3_key, 'video');

    $this->actingAs($user)
        ->delete(route('files.destroy', $file))
        ->assertRedirect();

    $this->assertModelMissing($file);
    Storage::disk('s3')->assertMissing('users/'.$user->id.'/delete-me.mp4');

    expect(StorageUsageEvent::query()->latest()->first())
        ->user_id->toBe($user->id)
        ->stored_file_id->toBe($file->id)
        ->delta_bytes->toBe(-500)
        ->reason->toBe('file_deleted');
});

test('users cannot delete files owned by someone else', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $file = StoredFile::factory()->for($otherUser)->create([
        's3_disk' => 's3',
        's3_key' => 'users/'.$otherUser->id.'/private.mp4',
    ]);

    Storage::disk('s3')->put($file->s3_key, 'video');

    $this->actingAs($user)
        ->delete(route('files.destroy', $file))
        ->assertForbidden();

    $this->assertModelExists($file);
    Storage::disk('s3')->assertExists($file->s3_key);
});
