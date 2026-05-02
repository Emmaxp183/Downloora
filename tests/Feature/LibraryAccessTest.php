<?php

use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

test('users only see their own stored files in the library', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    StoredFile::factory()->for($user)->create(['name' => 'mine.mp4']);
    StoredFile::factory()->for($otherUser)->create(['name' => 'theirs.mp4']);

    $this->actingAs($user)
        ->get(route('library.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Index')
            ->has('files', 1)
            ->where('files.0.name', 'mine.mp4')
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
        ->assertStreamedContent('hello');
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
        ->assertStreamedContent('video');
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
