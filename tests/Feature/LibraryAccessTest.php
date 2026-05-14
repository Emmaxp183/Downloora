<?php

use App\Models\MediaImport;
use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Models\Torrent;
use App\Models\User;
use App\Jobs\GenerateAdaptiveStream;
use App\Services\Media\AdaptiveStreamGenerator;
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

    StoredFile::factory()->for($user)->for($torrent)->create([
        'name' => 'mine.mp4',
        'mime_type' => 'video/mp4',
    ]);
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
            ->where('fileFolders.0.files.0.cast_url', fn (string $url): bool => str_contains($url, '/files/') && str_contains($url, '/cast') && str_contains($url, 'expires='))
            ->where('fileFolders.0.files.0.adaptive_stream_url', null)
            ->where('fileFolders.0.files.0.adaptive_stream_status', null)
        );
});

test('non-video files do not receive cast urls', function () {
    $user = User::factory()->create();

    StoredFile::factory()->for($user)->create([
        'name' => 'notes.txt',
        'mime_type' => 'text/plain',
    ]);

    $this->actingAs($user)
        ->get(route('library.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Index')
            ->where('fileFolders.0.files.0.cast_url', null)
        );
});

test('video extensions receive cast urls when the mime type is generic', function () {
    $user = User::factory()->create();

    StoredFile::factory()->for($user)->create([
        'name' => 'encoded-movie.vob',
        'mime_type' => 'application/octet-stream',
    ]);

    $this->actingAs($user)
        ->get(route('library.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Index')
            ->where('fileFolders.0.files.0.cast_url', fn (string $url): bool => str_contains($url, '/cast') && str_contains($url, 'expires='))
        );
});

test('ready adaptive streams are exposed in library payloads', function () {
    $user = User::factory()->create();

    StoredFile::factory()->for($user)->create([
        'name' => 'movie.mp4',
        'mime_type' => 'video/mp4',
        'adaptive_stream_status' => 'ready',
        'adaptive_stream_disk' => 's3',
        'adaptive_stream_playlist_key' => 'adaptive-streams/1/example/master.m3u8',
    ]);

    $this->actingAs($user)
        ->get(route('library.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Index')
            ->where('fileFolders.0.files.0.adaptive_stream_url', fn (string $url): bool => str_contains($url, '/hls/master.m3u8') && str_contains($url, 'expires='))
            ->where('fileFolders.0.files.0.adaptive_stream_status', 'ready')
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

test('temporary signed cast streams work without an authenticated browser session', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $file = StoredFile::factory()->for($user)->create([
        's3_disk' => 's3',
        's3_key' => 'users/'.$user->id.'/cast/movie.mp4',
        'name' => 'movie.mp4',
        'mime_type' => 'video/mp4',
        'size_bytes' => 10,
    ]);

    Storage::disk('s3')->put($file->s3_key, '0123456789');

    $this->withHeader('Range', 'bytes=1-3')
        ->get(URL::temporarySignedRoute('files.cast', now()->addMinutes(10), $file))
        ->assertStatus(206)
        ->assertHeader('content-type', 'video/mp4')
        ->assertHeader('content-disposition', 'inline; filename=movie.mp4')
        ->assertHeader('content-range', 'bytes 1-3/10')
        ->assertStreamedContent('123');
});

test('temporary signed cast streams are limited to video files', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $file = StoredFile::factory()->for($user)->create([
        's3_disk' => 's3',
        's3_key' => 'users/'.$user->id.'/cast/notes.txt',
        'name' => 'notes.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => 5,
    ]);

    Storage::disk('s3')->put($file->s3_key, 'notes');

    $this->get(URL::temporarySignedRoute('files.cast', now()->addMinutes(10), $file))
        ->assertNotFound();
});

test('temporary signed adaptive stream manifests rewrite playlist assets', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $file = StoredFile::factory()->for($user)->create([
        's3_disk' => 's3',
        'name' => 'movie.mp4',
        'mime_type' => 'video/mp4',
        'adaptive_stream_status' => 'ready',
        'adaptive_stream_disk' => 's3',
        'adaptive_stream_playlist_key' => 'adaptive-streams/1/example/master.m3u8',
    ]);

    Storage::disk('s3')->put($file->adaptive_stream_playlist_key, "#EXTM3U\n#EXT-X-STREAM-INF:BANDWIDTH=1500000,RESOLUTION=854x480\n480p/index.m3u8\n");

    $this->get(URL::temporarySignedRoute('files.hls.manifest', now()->addMinutes(10), $file))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.apple.mpegurl')
        ->assertSee('#EXTM3U', false)
        ->assertSee('/files/'.$file->id.'/hls/480p/index.m3u8', false)
        ->assertSee('signature=', false);
});

test('temporary signed adaptive stream assets are scoped and streamed', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $file = StoredFile::factory()->for($user)->create([
        's3_disk' => 's3',
        'name' => 'movie.mp4',
        'mime_type' => 'video/mp4',
        'adaptive_stream_status' => 'ready',
        'adaptive_stream_disk' => 's3',
        'adaptive_stream_playlist_key' => 'adaptive-streams/1/example/master.m3u8',
    ]);

    Storage::disk('s3')->put('adaptive-streams/1/example/480p/index.m3u8', "#EXTM3U\n#EXTINF:6.000000,\nsegment_00000.ts\n");
    Storage::disk('s3')->put('adaptive-streams/1/example/480p/segment_00000.ts', 'segment');

    $this->get(URL::temporarySignedRoute('files.hls.asset', now()->addMinutes(10), [
        'storedFile' => $file,
        'path' => '480p/index.m3u8',
    ]))
        ->assertOk()
        ->assertSee('/files/'.$file->id.'/hls/480p/segment_00000.ts', false);

    $this->get(URL::temporarySignedRoute('files.hls.asset', now()->addMinutes(10), [
        'storedFile' => $file,
        'path' => '480p/segment_00000.ts',
    ]))
        ->assertOk()
        ->assertHeader('content-type', 'video/mp2t')
        ->assertStreamedContent('segment');

    $this->get(URL::temporarySignedRoute('files.hls.asset', now()->addMinutes(10), [
        'storedFile' => $file,
        'path' => '../private.ts',
    ]))->assertNotFound();
});

test('temporary signed adaptive stream segments redirect to transferd when enabled', function () {
    config([
        'transferd.enabled' => true,
        'transferd.public_url' => '/__transferd',
        'transferd.signing_key' => 'test-transfer-secret',
        'transferd.url_ttl_seconds' => 300,
    ]);
    Storage::fake('s3');

    $user = User::factory()->create();
    $file = StoredFile::factory()->for($user)->create([
        's3_disk' => 's3',
        'name' => 'movie.mp4',
        'mime_type' => 'video/mp4',
        'adaptive_stream_status' => 'ready',
        'adaptive_stream_disk' => 's3',
        'adaptive_stream_bucket' => 'downloora-hls',
        'adaptive_stream_playlist_key' => 'adaptive-streams/1/example/master.m3u8',
    ]);

    Storage::disk('s3')->put('adaptive-streams/1/example/240p/segment_00000.ts', 'segment');

    $response = $this->get(URL::temporarySignedRoute('files.hls.asset', now()->addMinutes(10), [
        'storedFile' => $file,
        'path' => '240p/segment_00000.ts',
    ]))->assertRedirect();

    $location = $response->headers->get('Location');
    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);
    [$payload, $signature] = explode('.', $query['token']);
    $decodedPayload = json_decode(base64_decode(strtr(str_pad($payload, strlen($payload) + (4 - strlen($payload) % 4) % 4, '='), '-_', '+/')), true);
    $expectedSignature = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, 'test-transfer-secret', true)), '+/', '-_'), '=');

    expect((string) parse_url((string) $location, PHP_URL_PATH))->toBe('/__transferd/files')
        ->and($signature)->toBe($expectedSignature)
        ->and($decodedPayload)->toMatchArray([
            'backend' => 's3',
            'bucket' => 'downloora-hls',
            'key' => 'adaptive-streams/1/example/240p/segment_00000.ts',
            'name' => 'segment_00000.ts',
            'mime_type' => 'video/mp2t',
            'size_bytes' => 7,
            'disposition' => 'inline; filename=segment_00000.ts',
            'cache_control' => 'private, max-age=300',
            'cors' => true,
        ]);
});

test('adaptive stream variants include low bandwidth mobile renditions', function () {
    $variants = collect(config('media.adaptive.variants'));

    expect($variants->pluck('name')->all())->toContain('360p', '240p')
        ->and($variants->firstWhere('name', '360p'))->toMatchArray([
            'height' => 360,
            'bandwidth' => 850000,
        ])
        ->and($variants->firstWhere('name', '240p'))->toMatchArray([
            'height' => 240,
            'bandwidth' => 450000,
        ]);
});

test('adaptive stream generation job stores ready metadata', function () {
    $user = User::factory()->create();
    $file = StoredFile::factory()->for($user)->create([
        'name' => 'movie.mp4',
        'mime_type' => 'video/mp4',
    ]);

    $this->mock(AdaptiveStreamGenerator::class, function ($mock): void {
        $mock->shouldReceive('generate')
            ->once()
            ->andReturn([
                'disk' => 's3',
                'bucket' => 'seedr',
                'playlist_key' => 'adaptive-streams/1/generated/master.m3u8',
                'variants' => [
                    ['name' => '480p', 'height' => 480],
                ],
            ]);
    });

    GenerateAdaptiveStream::dispatchSync($file);

    expect($file->fresh())
        ->adaptive_stream_status->toBe('ready')
        ->adaptive_stream_disk->toBe('s3')
        ->adaptive_stream_bucket->toBe('seedr')
        ->adaptive_stream_playlist_key->toBe('adaptive-streams/1/generated/master.m3u8')
        ->adaptive_stream_variants->toBe([['name' => '480p', 'height' => 480]]);
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
