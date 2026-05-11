<?php

use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

test('owners can download a torrent folder as a zip file', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $torrent = Torrent::factory()->for($user)->create(['name' => 'Example Folder']);

    $video = StoredFile::factory()->for($user)->for($torrent)->create([
        's3_disk' => 's3',
        's3_key' => 'users/'.$user->id.'/torrents/'.$torrent->id.'/Example Folder/video.mp4',
        'original_path' => 'Example Folder/video.mp4',
        'name' => 'video.mp4',
        'size_bytes' => 11,
    ]);

    StoredFile::factory()->for($user)->for($torrent)->create([
        's3_disk' => 's3',
        's3_key' => 'users/'.$user->id.'/torrents/'.$torrent->id.'/Example Folder/subtitle.srt',
        'original_path' => 'Example Folder/subtitle.srt',
        'name' => 'subtitle.srt',
        'size_bytes' => 8,
    ]);

    Storage::disk('s3')->put($video->s3_key, 'video-bytes');
    Storage::disk('s3')->put('users/'.$user->id.'/torrents/'.$torrent->id.'/Example Folder/subtitle.srt', 'subtitles');

    $response = $this->actingAs($user)
        ->get(URL::signedRoute('folders.download', $torrent))
        ->assertOk()
        ->assertHeader('content-type', 'application/zip');

    $zipPath = tempnam(sys_get_temp_dir(), 'seedr-test-folder-zip');
    file_put_contents($zipPath, $response->streamedContent());

    $zip = new ZipArchive;

    expect($zip->open($zipPath))->toBeTrue()
        ->and($zip->getFromName('Example Folder/video.mp4'))->toBe('video-bytes')
        ->and($zip->getFromName('Example Folder/subtitle.srt'))->toBe('subtitles');

    $zip->close();
    unlink($zipPath);
});

test('owners can download a locally imported torrent folder as a zip file', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $torrent = Torrent::factory()->for($user)->create(['name' => 'Cached Folder']);

    $video = StoredFile::factory()->for($user)->for($torrent)->create([
        's3_disk' => 'local',
        's3_key' => 'qbittorrent/'.$torrent->hash.'/Cached Folder/video.mp4',
        'original_path' => 'Cached Folder/video.mp4',
        'name' => 'video.mp4',
        'size_bytes' => 11,
    ]);

    StoredFile::factory()->for($user)->for($torrent)->create([
        's3_disk' => 'local',
        's3_key' => 'qbittorrent/'.$torrent->hash.'/Cached Folder/subtitle.srt',
        'original_path' => 'Cached Folder/subtitle.srt',
        'name' => 'subtitle.srt',
        'size_bytes' => 8,
    ]);

    Storage::disk('local')->put($video->s3_key, 'video-bytes');
    Storage::disk('local')->put('qbittorrent/'.$torrent->hash.'/Cached Folder/subtitle.srt', 'subtitles');

    $response = $this->actingAs($user)
        ->get(URL::signedRoute('folders.download', $torrent))
        ->assertOk()
        ->assertHeader('content-type', 'application/zip');

    $zipPath = tempnam(sys_get_temp_dir(), 'seedr-test-local-folder-zip');
    file_put_contents($zipPath, $response->streamedContent());

    $zip = new ZipArchive;

    expect($zip->open($zipPath))->toBeTrue()
        ->and($zip->getFromName('Cached Folder/video.mp4'))->toBe('video-bytes')
        ->and($zip->getFromName('Cached Folder/subtitle.srt'))->toBe('subtitles');

    $zip->close();
    unlink($zipPath);
});

test('folder zip downloads require a valid signature and ownership', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $torrent = Torrent::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('folders.download', $torrent))
        ->assertForbidden();

    $this->actingAs($otherUser)
        ->get(URL::signedRoute('folders.download', $torrent))
        ->assertForbidden();
});

test('owners can delete torrent folders and all contained files', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $torrent = Torrent::factory()->for($user)->create([
        'name' => 'Example Folder',
        'torrent_file_path' => 'torrents/uploaded-source.torrent',
    ]);

    $firstFile = StoredFile::factory()->for($user)->for($torrent)->create([
        's3_disk' => 's3',
        's3_key' => 'users/'.$user->id.'/torrents/'.$torrent->id.'/one.bin',
        'size_bytes' => 12,
    ]);

    $secondFile = StoredFile::factory()->for($user)->for($torrent)->create([
        's3_disk' => 's3',
        's3_key' => 'users/'.$user->id.'/torrents/'.$torrent->id.'/two.bin',
        'size_bytes' => 8,
    ]);

    Storage::disk('s3')->put($firstFile->s3_key, 'one');
    Storage::disk('s3')->put($secondFile->s3_key, 'two');
    Storage::disk('s3')->put($torrent->torrent_file_path, 'torrent-bytes');

    $this->actingAs($user)
        ->delete(route('folders.destroy', $torrent))
        ->assertRedirect();

    $this->assertModelMissing($firstFile);
    $this->assertModelMissing($secondFile);
    Storage::disk('s3')->assertMissing($firstFile->s3_key);
    Storage::disk('s3')->assertMissing($secondFile->s3_key);
    Storage::disk('s3')->assertMissing($torrent->torrent_file_path);

    expect(StorageUsageEvent::query()->latest()->first())
        ->user_id->toBe($user->id)
        ->stored_file_id->toBeNull()
        ->delta_bytes->toBe(-20)
        ->reason->toBe('folder_deleted');
});

test('users cannot delete torrent folders owned by someone else', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $torrent = Torrent::factory()->for($otherUser)->create();
    $file = StoredFile::factory()->for($otherUser)->for($torrent)->create([
        's3_disk' => 's3',
        's3_key' => 'users/'.$otherUser->id.'/torrents/'.$torrent->id.'/private.bin',
    ]);

    Storage::disk('s3')->put($file->s3_key, 'private');

    $this->actingAs($user)
        ->delete(route('folders.destroy', $torrent))
        ->assertForbidden();

    $this->assertModelExists($file);
    Storage::disk('s3')->assertExists($file->s3_key);
});
