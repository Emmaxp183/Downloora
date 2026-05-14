<?php

use App\Models\StoredFile;
use App\Services\Media\AdaptiveStreamGenerator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

test('adaptive stream generation normalizes unsupported codecs into browser safe hls', function () {
    Storage::fake('s3');

    $argumentsPath = storage_path('framework/testing/fake-ffmpeg-arguments.json');
    $binaryPath = storage_path('framework/testing/fake-ffmpeg');

    File::ensureDirectoryExists(dirname($binaryPath));
    File::put($binaryPath, <<<'SH'
#!/bin/sh
php -r '
$arguments = array_slice($argv, 1);
file_put_contents(getenv("FAKE_FFMPEG_ARGUMENTS"), json_encode($arguments));

$playlist = end($arguments);
$segmentPattern = null;

foreach ($arguments as $index => $argument) {
    if ($argument === "-hls_segment_filename") {
        $segmentPattern = $arguments[$index + 1] ?? null;
    }
}

if (is_string($segmentPattern)) {
    $segment = str_replace("%05d", "00000", $segmentPattern);
    @mkdir(dirname($segment), 0777, true);
    file_put_contents($segment, "segment");
}

@mkdir(dirname($playlist), 0777, true);
file_put_contents($playlist, "#EXTM3U\n#EXTINF:1.000000,\nsegment_00000.ts\n");
' -- "$@"
SH);
    chmod($binaryPath, 0755);
    putenv('FAKE_FFMPEG_ARGUMENTS='.$argumentsPath);

    config([
        'media.adaptive.ffmpeg_binary' => $binaryPath,
        'media.adaptive.variants' => [
            [
                'name' => '240p',
                'height' => 240,
                'width' => 426,
                'video_bitrate' => '350k',
                'audio_bitrate' => '48k',
                'bandwidth' => 450000,
            ],
        ],
    ]);

    $file = StoredFile::factory()->create([
        's3_disk' => 's3',
        's3_bucket' => 'seedr',
        's3_key' => 'sources/unsupported-codec.mkv',
        'name' => 'unsupported-codec.mkv',
        'mime_type' => 'video/x-matroska',
    ]);

    Storage::disk('s3')->put($file->s3_key, 'source-video');

    $output = app(AdaptiveStreamGenerator::class)->generate($file);
    $arguments = json_decode((string) file_get_contents($argumentsPath), true);

    expect($arguments)->toContain('-fflags', '+genpts', '-dn', '-sn', '-pix_fmt', 'yuv420p', '-max_muxing_queue_size', '4096')
        ->and($output['playlist_key'])->toStartWith('adaptive-streams/'.$file->id.'/')
        ->and($output['variants'])->toBe(config('media.adaptive.variants'));

    Storage::disk('s3')->assertExists($output['playlist_key']);
});
