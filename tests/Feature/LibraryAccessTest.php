<?php

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
        ->assertOk();
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
        ->assertOk();
});
