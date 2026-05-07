<?php

use App\Enums\MediaImportStatus;
use App\Jobs\DownloadMediaImport;
use App\Jobs\InspectMediaImport;
use App\Models\MediaImport;
use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Services\Media\YtDlpClient;
use App\Services\Storage\ObjectStorageUploader;
use Illuminate\Support\Facades\Storage;

test('it inspects media urls and stores available formats', function () {
    $mediaImport = MediaImport::factory()->create([
        'source_url' => 'https://www.youtube.com/watch?v=example',
        'status' => MediaImportStatus::Inspecting,
    ]);

    app()->instance(YtDlpClient::class, new class extends YtDlpClient
    {
        public function inspect(string $url): array
        {
            return [
                'title' => 'Example video',
                'source_domain' => 'YouTube',
                'thumbnail_url' => 'https://example.com/thumb.jpg',
                'duration_seconds' => 90,
                'formats' => [
                    [
                        'id' => 'best',
                        'selector' => 'bestvideo*+bestaudio/best',
                        'type' => 'video',
                        'extension' => 'mp4',
                        'quality' => 'Best available',
                        'duration_seconds' => 90,
                        'size_bytes' => 2048,
                        'source' => 'YouTube',
                    ],
                ],
            ];
        }
    });

    app()->call([new InspectMediaImport($mediaImport), 'handle']);

    $mediaImport->refresh();

    expect($mediaImport->status)->toBe(MediaImportStatus::Ready)
        ->and($mediaImport->title)->toBe('Example video')
        ->and($mediaImport->formats)->toHaveCount(1)
        ->and($mediaImport->estimated_size_bytes)->toBe(2048);
});

test('it only exposes useful media save formats', function () {
    $client = new YtDlpClient;
    $method = new ReflectionMethod(YtDlpClient::class, 'formats');

    $formats = $method->invoke($client, [
        'duration' => 1207,
        'extractor_key' => 'Youtube',
        'formats' => [
            [
                'format_id' => 'sb0',
                'ext' => 'mhtml',
                'height' => 27,
                'vcodec' => 'none',
                'acodec' => 'none',
                'protocol' => 'mhtml',
            ],
            [
                'format_id' => '18',
                'ext' => 'mp4',
                'height' => 360,
                'vcodec' => 'avc1',
                'acodec' => 'mp4a',
                'filesize_approx' => 70_000_000,
                'protocol' => 'https',
            ],
            [
                'format_id' => '22',
                'ext' => 'mp4',
                'height' => 720,
                'vcodec' => 'avc1',
                'acodec' => 'mp4a',
                'filesize_approx' => 300_000_000,
                'protocol' => 'https',
            ],
            [
                'format_id' => '137',
                'ext' => 'mp4',
                'height' => 1080,
                'vcodec' => 'avc1',
                'acodec' => 'none',
                'filesize_approx' => 500_000_000,
                'protocol' => 'https',
            ],
            [
                'format_id' => '140',
                'ext' => 'm4a',
                'vcodec' => 'none',
                'acodec' => 'mp4a',
                'filesize_approx' => 30_000_000,
                'protocol' => 'https',
            ],
            [
                'format_id' => '244',
                'ext' => 'webm',
                'height' => 480,
                'vcodec' => 'vp9',
                'acodec' => 'none',
                'filesize_approx' => 95_000_000,
                'protocol' => 'https',
            ],
        ],
    ]);

    expect(array_column($formats, 'id'))->toBe([
        'video-360',
        'video-720',
        'video-1080',
        'audio',
    ])
        ->and(array_column($formats, 'quality'))->toBe([
            '360p',
            '720p',
            '1080p',
            'Audio',
        ]);
});

test('it falls back to a short list when common video formats are unavailable', function () {
    $client = new YtDlpClient;
    $method = new ReflectionMethod(YtDlpClient::class, 'formats');

    $formats = $method->invoke($client, [
        'duration' => 30,
        'extractor_key' => 'Generic',
        'formats' => [
            [
                'format_id' => 'storyboard',
                'ext' => 'mhtml',
                'height' => 45,
                'vcodec' => 'none',
                'acodec' => 'none',
                'protocol' => 'mhtml',
            ],
            [
                'format_id' => 'file',
                'ext' => 'jpg',
                'vcodec' => 'none',
                'acodec' => 'none',
                'filesize_approx' => 1000,
                'protocol' => 'https',
            ],
        ],
    ]);

    expect($formats)->toHaveCount(1)
        ->and($formats[0]['id'])->toBe('file');
});

test('it uses configured concurrent fragments for media downloads', function () {
    config(['media.yt_dlp.concurrent_fragments' => 12]);

    $client = new YtDlpClient;
    $method = new ReflectionMethod(YtDlpClient::class, 'downloadCommand');
    $command = $method->invoke($client, 'https://example.com/video', 'best', '/tmp/downloora-media-test');

    expect($command)->toContain('--concurrent-fragments')
        ->and($command)->toContain('12');
});

test('it downloads selected media into object storage', function () {
    Storage::fake('s3');

    $mediaImport = MediaImport::factory()->create([
        'source_url' => 'https://www.youtube.com/watch?v=example',
        'title' => 'Example video',
        'status' => MediaImportStatus::Queued,
        'selected_format' => [
            'id' => 'best',
            'selector' => 'bestvideo*+bestaudio/best',
            'type' => 'video',
            'extension' => 'mp4',
            'quality' => 'Best available',
            'duration_seconds' => 90,
            'size_bytes' => 11,
            'source' => 'YouTube',
        ],
    ]);

    app()->instance(YtDlpClient::class, new class extends YtDlpClient
    {
        public function download(string $url, string $formatSelector, string $directory, callable $progress): string
        {
            if (! is_dir($directory)) {
                mkdir($directory, 0700, true);
            }

            $path = $directory.'/Example video [abc123].mp4';
            file_put_contents($path, 'video-bytes');
            $progress(75, 0);

            return $path;
        }
    });

    app()->instance(ObjectStorageUploader::class, new class extends ObjectStorageUploader
    {
        public function uploadFile(string $key, string $localPath): string
        {
            Storage::disk('s3')->put($key, file_get_contents($localPath));

            return 'video/mp4';
        }
    });

    app()->call([new DownloadMediaImport($mediaImport), 'handle']);

    $mediaImport->refresh();

    expect($mediaImport->status)->toBe(MediaImportStatus::Completed)
        ->and($mediaImport->progress)->toBe(100)
        ->and(StoredFile::query()->count())->toBe(1)
        ->and(StorageUsageEvent::query()->sum('delta_bytes'))->toBe(11)
        ->and(StoredFile::query()->first()->mime_type)->toBe('video/mp4');

    Storage::disk('s3')->assertExists("users/{$mediaImport->user_id}/media/{$mediaImport->id}/Example-video/Example-video-abc123.mp4");
});

test('it stops storing media when a download is cancelled', function () {
    Storage::fake('s3');

    $mediaImport = MediaImport::factory()->create([
        'source_url' => 'https://www.youtube.com/watch?v=example',
        'status' => MediaImportStatus::Queued,
        'selected_format' => [
            'id' => 'best',
            'selector' => 'bestvideo*+bestaudio/best',
            'type' => 'video',
            'extension' => 'mp4',
            'quality' => 'Best available',
            'duration_seconds' => 90,
            'size_bytes' => 11,
            'source' => 'YouTube',
        ],
    ]);

    app()->instance(YtDlpClient::class, new class($mediaImport) extends YtDlpClient
    {
        public function __construct(private MediaImport $mediaImport) {}

        public function download(string $url, string $formatSelector, string $directory, callable $progress): string
        {
            if (! is_dir($directory)) {
                mkdir($directory, 0700, true);
            }

            file_put_contents($directory.'/partial.mp4', 'partial-video-bytes');

            $this->mediaImport->forceFill([
                'status' => MediaImportStatus::Cancelled,
            ])->save();

            $progress(12, 0);

            return $directory.'/partial.mp4';
        }
    });

    app()->call([new DownloadMediaImport($mediaImport), 'handle']);

    $mediaImport->refresh();

    expect($mediaImport->status)->toBe(MediaImportStatus::Cancelled)
        ->and(StoredFile::query()->count())->toBe(0)
        ->and(StorageUsageEvent::query()->count())->toBe(0);

    Storage::disk('s3')->assertMissing("users/{$mediaImport->user_id}/media/{$mediaImport->id}/partial.mp4");
});
