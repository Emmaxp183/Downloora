<?php

use App\Models\MediaImport;
use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

test('owners can download a media folder as a zip file', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $mediaImport = MediaImport::factory()->for($user)->create([
        'title' => 'Do Not Marry A Lady Who Cannot Cook',
    ]);

    $file = StoredFile::factory()->for($user)->create([
        'torrent_id' => null,
        'media_import_id' => $mediaImport->id,
        's3_disk' => 's3',
        's3_key' => 'users/'.$user->id.'/media/'.$mediaImport->id.'/Do-Not-Marry-A-Lady-Who-Cannot-Cook/video.mp4',
        'original_path' => 'Media/Do-Not-Marry-A-Lady-Who-Cannot-Cook/video.mp4',
        'name' => 'video.mp4',
        'size_bytes' => 11,
    ]);

    Storage::disk('s3')->put($file->s3_key, 'video-bytes');

    $response = $this->actingAs($user)
        ->get(URL::signedRoute('media-folders.download', $mediaImport))
        ->assertOk()
        ->assertHeader('content-type', 'application/zip');

    $zipPath = tempnam(sys_get_temp_dir(), 'seedr-test-media-folder-zip');
    file_put_contents($zipPath, $response->streamedContent());

    $zip = new ZipArchive;

    expect($zip->open($zipPath))->toBeTrue()
        ->and($zip->getFromName('Do-Not-Marry-A-Lady-Who-Cannot-Cook/video.mp4'))->toBe('video-bytes');

    $zip->close();
    unlink($zipPath);
});

test('owners can download a locally imported media folder as a zip file', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $mediaImport = MediaImport::factory()->for($user)->create([
        'title' => 'Cached Media',
    ]);

    $file = StoredFile::factory()->for($user)->create([
        'torrent_id' => null,
        'media_import_id' => $mediaImport->id,
        's3_disk' => 'local',
        's3_key' => 'media/'.$mediaImport->id.'/Cached-Media/video.mp4',
        'original_path' => 'Media/Cached-Media/video.mp4',
        'name' => 'video.mp4',
        'size_bytes' => 11,
    ]);

    Storage::disk('local')->put($file->s3_key, 'video-bytes');

    $response = $this->actingAs($user)
        ->get(URL::signedRoute('media-folders.download', $mediaImport))
        ->assertOk()
        ->assertHeader('content-type', 'application/zip');

    $zipPath = tempnam(sys_get_temp_dir(), 'seedr-test-local-media-folder-zip');
    file_put_contents($zipPath, $response->streamedContent());

    $zip = new ZipArchive;

    expect($zip->open($zipPath))->toBeTrue()
        ->and($zip->getFromName('Cached-Media/video.mp4'))->toBe('video-bytes');

    $zip->close();
    unlink($zipPath);
});

test('media folder zip downloads require a valid signature and ownership', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $mediaImport = MediaImport::factory()->for($user)->create();

    StoredFile::factory()->for($user)->create([
        'torrent_id' => null,
        'media_import_id' => $mediaImport->id,
    ]);

    $this->actingAs($user)
        ->get(route('media-folders.download', $mediaImport))
        ->assertForbidden();

    $this->actingAs($otherUser)
        ->get(URL::signedRoute('media-folders.download', $mediaImport))
        ->assertForbidden();
});

test('owners can delete media folders and all contained files', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $mediaImport = MediaImport::factory()->for($user)->create([
        'title' => 'Example Media',
    ]);

    $firstFile = StoredFile::factory()->for($user)->create([
        'torrent_id' => null,
        'media_import_id' => $mediaImport->id,
        's3_key' => 'users/'.$user->id.'/media/'.$mediaImport->id.'/one.mp4',
        'size_bytes' => 12,
    ]);

    $secondFile = StoredFile::factory()->for($user)->create([
        'torrent_id' => null,
        'media_import_id' => $mediaImport->id,
        's3_key' => 'users/'.$user->id.'/media/'.$mediaImport->id.'/two.mp4',
        'size_bytes' => 8,
    ]);

    Storage::disk('s3')->put($firstFile->s3_key, 'one');
    Storage::disk('s3')->put($secondFile->s3_key, 'two');

    $this->actingAs($user)
        ->delete(route('media-folders.destroy', $mediaImport))
        ->assertRedirect();

    $this->assertModelMissing($firstFile);
    $this->assertModelMissing($secondFile);
    Storage::disk('s3')->assertMissing($firstFile->s3_key);
    Storage::disk('s3')->assertMissing($secondFile->s3_key);

    expect(StorageUsageEvent::query()->latest()->first())
        ->user_id->toBe($user->id)
        ->stored_file_id->toBeNull()
        ->delta_bytes->toBe(-20)
        ->reason->toBe('folder_deleted');
});

test('users cannot delete media folders owned by someone else', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $mediaImport = MediaImport::factory()->for($otherUser)->create();
    $file = StoredFile::factory()->for($otherUser)->create([
        'torrent_id' => null,
        'media_import_id' => $mediaImport->id,
        's3_key' => 'users/'.$otherUser->id.'/media/'.$mediaImport->id.'/private.mp4',
    ]);

    Storage::disk('s3')->put($file->s3_key, 'private');

    $this->actingAs($user)
        ->delete(route('media-folders.destroy', $mediaImport))
        ->assertForbidden();

    $this->assertModelExists($file);
    Storage::disk('s3')->assertExists($file->s3_key);
});
