<?php

use App\Enums\MediaImportStatus;
use App\Jobs\DownloadMediaImport;
use App\Jobs\InspectMediaImport;
use App\Models\MediaImport;
use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Services\Downloads\DownloadResourceProfile;
use App\Services\Media\YtDlpClient;
use App\Services\Storage\ObjectStorageUploader;
use Illuminate\Support\Facades\File;
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
    config([
        'media.yt_dlp.adaptive_segments' => false,
        'media.yt_dlp.concurrent_fragments' => 12,
    ]);

    $client = new YtDlpClient;
    $method = new ReflectionMethod(YtDlpClient::class, 'downloadCommand');
    $command = $method->invoke($client, 'https://example.com/video', 'best', '/tmp/downloora-media-test');

    expect($command)->toContain('--concurrent-fragments')
        ->and($command)->toContain('12');
});

test('it passes configured performance options for media downloads', function () {
    config([
        'media.yt_dlp.adaptive_segments' => false,
        'media.yt_dlp.external_downloader' => 'aria2c',
        'media.yt_dlp.external_downloader_args' => null,
        'media.yt_dlp.http_chunk_size' => '16M',
        'media.yt_dlp.segment_connections' => 32,
        'media.yt_dlp.segment_split' => 32,
        'media.yt_dlp.segment_min_split_size' => '1M',
        'media.yt_dlp.segment_piece_length' => '1M',
        'media.yt_dlp.segment_disk_cache' => '64M',
    ]);

    $client = new YtDlpClient;
    $method = new ReflectionMethod(YtDlpClient::class, 'downloadCommand');
    $command = $method->invoke($client, 'https://example.com/video', 'best', '/tmp/downloora-media-test');

    expect($command)->toContain('--http-chunk-size')
        ->and($command)->toContain('16M')
        ->and($command)->toContain('--downloader')
        ->and($command)->toContain('aria2c')
        ->and($command)->toContain('--downloader-args')
        ->and($command)->toContain('aria2c:--continue=true --allow-overwrite=true --auto-file-renaming=false --file-allocation=none --optimize-concurrent-downloads=true --summary-interval=1 --max-connection-per-server=16 --split=32 --min-split-size=1M --piece-length=1M --disk-cache=64M');
});

test('it adapts media downloader segmentation to available CPU and RAM', function () {
    config([
        'media.yt_dlp.adaptive_segments' => true,
        'media.yt_dlp.concurrent_fragments' => 64,
        'media.yt_dlp.max_concurrent_fragments' => 192,
        'media.yt_dlp.external_downloader' => 'aria2c',
        'media.yt_dlp.external_downloader_args' => null,
        'media.yt_dlp.segment_connections' => 64,
        'media.yt_dlp.max_segment_connections' => 192,
        'media.yt_dlp.segment_split' => 64,
        'media.yt_dlp.max_segment_split' => 192,
        'media.yt_dlp.segment_min_split_size' => '1M',
        'media.yt_dlp.segment_piece_length' => '1M',
        'media.yt_dlp.segment_disk_cache' => '256M',
    ]);

    app()->instance(DownloadResourceProfile::class, new class extends DownloadResourceProfile
    {
        protected function read(string $path): ?string
        {
            return match ($path) {
                '/proc/stat' => "cpu  1 0 1 1 0 0 0 0 0 0\n".collect(range(0, 31))->map(fn (int $core): string => "cpu{$core} 1 0 1 1 0 0 0 0 0 0")->implode("\n"),
                '/proc/meminfo' => "MemTotal:       67108864 kB\nMemAvailable:  60000000 kB\n",
                default => null,
            };
        }
    });

    $client = new YtDlpClient;
    $method = new ReflectionMethod(YtDlpClient::class, 'downloadCommand');
    $command = $method->invoke($client, 'https://example.com/video', 'best', '/tmp/downloora-media-test');

    expect($command)->toContain('--concurrent-fragments')
        ->and($command)->toContain('192')
        ->and($command)->toContain('aria2c:--continue=true --allow-overwrite=true --auto-file-renaming=false --file-allocation=none --optimize-concurrent-downloads=true --summary-interval=1 --max-connection-per-server=16 --split=192 --min-split-size=1M --piece-length=1M --disk-cache=1024M');
});

test('it targets ten gigabit media downloads on high capacity servers', function () {
    config([
        'media.yt_dlp.target_bandwidth_mbps' => 10000,
        'media.yt_dlp.concurrent_fragments' => 256,
        'media.yt_dlp.max_concurrent_fragments' => 1024,
        'media.yt_dlp.external_downloader' => 'aria2c',
        'media.yt_dlp.external_downloader_args' => null,
        'media.yt_dlp.segment_connections' => 256,
        'media.yt_dlp.max_segment_connections' => 1024,
        'media.yt_dlp.segment_split' => 256,
        'media.yt_dlp.max_segment_split' => 1024,
        'media.yt_dlp.segment_min_split_size' => '1M',
        'media.yt_dlp.segment_piece_length' => '1M',
        'media.yt_dlp.segment_disk_cache' => '1024M',
    ]);

    app()->instance(DownloadResourceProfile::class, new class extends DownloadResourceProfile
    {
        protected function read(string $path): ?string
        {
            return match ($path) {
                '/proc/stat' => "cpu  1 0 1 1 0 0 0 0 0 0\n".collect(range(0, 15))->map(fn (int $core): string => "cpu{$core} 1 0 1 1 0 0 0 0 0 0")->implode("\n"),
                '/proc/meminfo' => "MemTotal:       67108864 kB\nMemAvailable:  60000000 kB\n",
                default => null,
            };
        }
    });

    $client = new YtDlpClient;
    $method = new ReflectionMethod(YtDlpClient::class, 'downloadCommand');
    $command = $method->invoke($client, 'https://example.com/video', 'best', '/tmp/downloora-media-test');

    expect($command)->toContain('--concurrent-fragments')
        ->and($command)->toContain('1000')
        ->and($command)->toContain('aria2c:--continue=true --allow-overwrite=true --auto-file-renaming=false --file-allocation=none --optimize-concurrent-downloads=true --summary-interval=1 --max-connection-per-server=16 --split=1000 --min-split-size=1M --piece-length=1M --disk-cache=1024M');
});

test('it converts configured gigabyte cache sizes to aria2 compatible megabytes', function () {
    config([
        'media.yt_dlp.adaptive_segments' => false,
        'media.yt_dlp.external_downloader' => 'aria2c',
        'media.yt_dlp.external_downloader_args' => null,
        'media.yt_dlp.segment_disk_cache' => '2G',
    ]);

    $client = new YtDlpClient;
    $method = new ReflectionMethod(YtDlpClient::class, 'downloadCommand');
    $command = $method->invoke($client, 'https://example.com/video', 'best', '/tmp/downloora-media-test');

    expect(collect($command)->contains(fn (string $argument): bool => str_contains($argument, '--disk-cache=2048M')))->toBeTrue();
});

test('it estimates downloaded bytes from yt-dlp progress output', function () {
    $client = new YtDlpClient;
    $method = new ReflectionMethod(YtDlpClient::class, 'parseProgress');
    $samples = [];

    $method->invoke(
        $client,
        '[download]  50.0% of 100.00MiB at 5.00MiB/s ETA 00:10',
        function (int $progress, int $downloadedBytes) use (&$samples): void {
            $samples[] = [$progress, $downloadedBytes];
        },
    );

    expect($samples)->toBe([[50, 52_428_800]]);
});

test('it passes configured cookie file to yt-dlp commands', function () {
    config(['media.yt_dlp.cookies' => '/var/www/html/storage/app/private/youtube-cookies.txt']);

    $client = new YtDlpClient;
    $inspectMethod = new ReflectionMethod(YtDlpClient::class, 'inspectCommand');
    $downloadMethod = new ReflectionMethod(YtDlpClient::class, 'downloadCommand');

    $inspectCommand = $inspectMethod->invoke($client, 'https://www.youtube.com/watch?v=example');
    $downloadCommand = $downloadMethod->invoke($client, 'https://www.youtube.com/watch?v=example', 'best', '/tmp/downloora-media-test');

    expect($inspectCommand)->toContain('--cookies')
        ->and($inspectCommand)->toContain('/var/www/html/storage/app/private/youtube-cookies.txt')
        ->and($downloadCommand)->toContain('--cookies')
        ->and($downloadCommand)->toContain('/var/www/html/storage/app/private/youtube-cookies.txt');
});

test('it passes configured browser cookies to yt-dlp commands', function () {
    config([
        'media.yt_dlp.cookies' => null,
        'media.yt_dlp.cookies_from_browser' => 'firefox',
    ]);

    $client = new YtDlpClient;
    $inspectMethod = new ReflectionMethod(YtDlpClient::class, 'inspectCommand');
    $downloadMethod = new ReflectionMethod(YtDlpClient::class, 'downloadCommand');

    $inspectCommand = $inspectMethod->invoke($client, 'https://www.youtube.com/watch?v=example');
    $downloadCommand = $downloadMethod->invoke($client, 'https://www.youtube.com/watch?v=example', 'best', '/tmp/downloora-media-test');

    expect($inspectCommand)->toContain('--cookies-from-browser')
        ->and($inspectCommand)->toContain('firefox')
        ->and($downloadCommand)->toContain('--cookies-from-browser')
        ->and($downloadCommand)->toContain('firefox');
});

test('it passes configured po token provider to yt-dlp commands', function () {
    config([
        'media.yt_dlp.pot_provider_url' => 'http://yt-dlp-pot-provider:4416',
        'media.yt_dlp.youtube_player_clients' => 'mweb,web_safari,android_vr',
    ]);

    $client = new YtDlpClient;
    $inspectMethod = new ReflectionMethod(YtDlpClient::class, 'inspectCommand');
    $downloadMethod = new ReflectionMethod(YtDlpClient::class, 'downloadCommand');

    $inspectCommand = $inspectMethod->invoke($client, 'https://www.youtube.com/watch?v=example');
    $downloadCommand = $downloadMethod->invoke($client, 'https://www.youtube.com/watch?v=example', 'best', '/tmp/downloora-media-test');

    expect($inspectCommand)->toContain('--extractor-args')
        ->and($inspectCommand)->toContain('youtube:player_client=mweb,web_safari,android_vr')
        ->and($inspectCommand)->toContain('youtubepot-bgutilhttp:base_url=http://yt-dlp-pot-provider:4416;disable_innertube=1')
        ->and($downloadCommand)->toContain('--extractor-args')
        ->and($downloadCommand)->toContain('youtube:player_client=mweb,web_safari,android_vr')
        ->and($downloadCommand)->toContain('youtubepot-bgutilhttp:base_url=http://yt-dlp-pot-provider:4416;disable_innertube=1');
});

test('it can keep innertube po token mode when configured', function () {
    config([
        'media.yt_dlp.pot_provider_url' => 'http://yt-dlp-pot-provider:4416',
        'media.yt_dlp.pot_disable_innertube' => false,
    ]);

    $client = new YtDlpClient;
    $inspectMethod = new ReflectionMethod(YtDlpClient::class, 'inspectCommand');
    $downloadMethod = new ReflectionMethod(YtDlpClient::class, 'downloadCommand');

    $inspectCommand = $inspectMethod->invoke($client, 'https://www.youtube.com/watch?v=example');
    $downloadCommand = $downloadMethod->invoke($client, 'https://www.youtube.com/watch?v=example', 'best', '/tmp/downloora-media-test');

    expect($inspectCommand)->toContain('youtubepot-bgutilhttp:base_url=http://yt-dlp-pot-provider:4416')
        ->and($inspectCommand)->not->toContain('youtubepot-bgutilhttp:base_url=http://yt-dlp-pot-provider:4416;disable_innertube=1')
        ->and($downloadCommand)->toContain('youtubepot-bgutilhttp:base_url=http://yt-dlp-pot-provider:4416')
        ->and($downloadCommand)->not->toContain('youtubepot-bgutilhttp:base_url=http://yt-dlp-pot-provider:4416;disable_innertube=1');
});

test('it reports guest youtube fallback failure clearly when youtube requires sign in', function () {
    $path = storage_path('framework/testing/youtube-cookies.txt');

    File::ensureDirectoryExists(dirname($path));
    File::put($path, implode("\n", [
        '# Netscape HTTP Cookie File',
        ".youtube.com\tTRUE\t/\tTRUE\t1793927348\tVISITOR_INFO1_LIVE\tguest-cookie-value",
        '',
    ]));

    config(['media.yt_dlp.cookies' => $path]);

    $client = new YtDlpClient;
    $failureMethod = new ReflectionMethod(YtDlpClient::class, 'failureMessage');

    $message = $failureMethod->invoke($client, 'ERROR: Sign in to confirm you’re not a bot.', 'Unable to inspect media URL.');

    expect($message)->toBe('YouTube blocked anonymous guest access from this server for this video. Guest cookies, no-cookie mode, visitor-data mode, and the PO-token provider were tried.');
});

test('it can build youtube visitor-data fallback commands from guest cookies', function () {
    $path = storage_path('framework/testing/youtube-cookies.txt');

    File::ensureDirectoryExists(dirname($path));
    File::put($path, implode("\n", [
        '# Netscape HTTP Cookie File',
        ".youtube.com\tTRUE\t/\tTRUE\t1793927348\tVISITOR_INFO1_LIVE\tguest-visitor-value",
        '',
    ]));

    config([
        'media.yt_dlp.cookies' => $path,
        'media.yt_dlp.youtube_player_clients' => 'mweb,web_safari,android_vr',
    ]);

    $client = new YtDlpClient;
    $inspectMethod = new ReflectionMethod(YtDlpClient::class, 'inspectCommand');

    $inspectCommand = $inspectMethod->invoke($client, 'https://www.youtube.com/watch?v=example', 'visitor');

    expect($inspectCommand)->not->toContain('--cookies')
        ->and($inspectCommand)->toContain('youtubetab:skip=webpage')
        ->and($inspectCommand)->toContain('youtube:player_client=mweb,web_safari,android_vr;player_skip=webpage,configs;visitor_data=guest-visitor-value');
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
        ->and((int) StorageUsageEvent::query()->sum('delta_bytes'))->toBe(11)
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
