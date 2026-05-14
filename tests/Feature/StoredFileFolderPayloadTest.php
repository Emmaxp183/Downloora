<?php

use App\Models\StoredFile;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Storage\StoredFileFolderPayloads;

test('stored files are grouped into torrent folders', function () {
    $user = User::factory()->create();
    $torrent = Torrent::factory()->for($user)->create([
        'name' => 'Example Torrent',
    ]);

    StoredFile::factory()->for($user)->for($torrent)->create([
        'name' => 'video.mp4',
        'original_path' => 'Example Torrent/video.mp4',
        'size_bytes' => 700,
    ]);

    StoredFile::factory()->for($user)->for($torrent)->create([
        'name' => 'poster.jpg',
        'original_path' => 'Example Torrent/poster.jpg',
        'size_bytes' => 300,
    ]);

    StoredFile::factory()->for($user)->for($torrent)->create([
        'name' => 'subtitle.srt',
        'original_path' => 'Example Torrent/Subs/subtitle.srt',
        'size_bytes' => 25,
    ]);

    $folders = app(StoredFileFolderPayloads::class)
        ->fromFiles($user->storedFiles()->with('torrent')->get());

    $payloadFiles = collect($folders->first()['files']);

    expect($folders)->toHaveCount(1)
        ->and($folders->first()['id'])->toBe('torrent-'.$torrent->id)
        ->and($folders->first()['torrent_id'])->toBe($torrent->id)
        ->and($folders->first()['name'])->toBe('Example Torrent')
        ->and($folders->first()['download_url'])->toContain('/folders/'.$torrent->id.'/download')
        ->and($folders->first()['size_bytes'])->toBe(1025)
        ->and($folders->first()['files'])->toHaveCount(3)
        ->and($payloadFiles->pluck('name')->all())->toContain('poster.jpg', 'subtitle.srt', 'video.mp4')
        ->and($payloadFiles->pluck('original_path')->all())->toContain('Example Torrent/Subs/subtitle.srt');
});
