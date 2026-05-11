<?php

namespace App\Services\Media;

use App\Services\Downloads\DownloadResourceProfile;
use RuntimeException;
use Symfony\Component\Process\Process;

class YtDlpClient
{
    private const CookieConfigured = 'configured';

    private const CookieNone = 'none';

    private const CookieVisitor = 'visitor';

    /**
     * Inspect a public URL and return normalized metadata.
     *
     * @return array{
     *     title: string|null,
     *     source_domain: string|null,
     *     thumbnail_url: string|null,
     *     duration_seconds: int|null,
     *     formats: array<int, array<string, mixed>>
     * }
     */
    public function inspect(string $url): array
    {
        $json = $this->runWithCookieFallback(
            $url,
            fn (string $cookieMode): array => $this->inspectCommand($url, $cookieMode),
            $this->inspectTimeout(),
            'Unable to inspect media URL.',
        );

        $metadata = json_decode($json, true);

        if (! is_array($metadata)) {
            throw new RuntimeException('Unable to read media metadata.');
        }

        return [
            'title' => $metadata['title'] ?? null,
            'source_domain' => $metadata['extractor_key'] ?? parse_url($url, PHP_URL_HOST),
            'thumbnail_url' => $metadata['thumbnail'] ?? null,
            'duration_seconds' => isset($metadata['duration']) ? (int) round((float) $metadata['duration']) : null,
            'formats' => $this->formats($metadata),
        ];
    }

    /**
     * Download a selected format into the given directory.
     *
     * @param  callable(int $progress, int $downloadedBytes): void  $progress
     */
    public function download(string $url, string $formatSelector, string $directory, callable $progress): string
    {
        if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
            throw new RuntimeException('Unable to create media download directory.');
        }

        $before = $this->files($directory);
        $lastErrorOutput = '';

        foreach ($this->cookieFallbackModes($url) as $cookieMode) {
            $process = new Process($this->downloadCommand($url, $formatSelector, $directory, $cookieMode));

            $process->setTimeout($this->downloadTimeout());
            $process->run(function (string $type, string $buffer) use ($progress): void {
                $this->parseProgress($buffer, $progress);
            });

            if ($process->isSuccessful()) {
                break;
            }

            $lastErrorOutput = $process->getErrorOutput();

            if (! $this->shouldTryNextCookieFallback($url, $lastErrorOutput, $cookieMode)) {
                break;
            }
        }

        if (! isset($process) || ! $process->isSuccessful()) {
            throw new RuntimeException($this->failureMessage($lastErrorOutput, 'Unable to download media.'));
        }

        $downloaded = collect($this->files($directory))
            ->reject(fn (string $file): bool => in_array($file, $before, true))
            ->filter(fn (string $file): bool => is_file($file) && filesize($file) !== false && filesize($file) > 0)
            ->sortByDesc(fn (string $file): int => (int) filemtime($file))
            ->first();

        if (! is_string($downloaded)) {
            throw new RuntimeException('Media download finished without a readable file.');
        }

        return $downloaded;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<int, array<string, mixed>>
     */
    private function formats(array $metadata): array
    {
        $formats = collect($metadata['formats'] ?? [])
            ->filter(fn (mixed $format): bool => is_array($format) && isset($format['format_id']))
            ->map(fn (array $format): array => $this->format($format))
            ->filter(fn (array $format): bool => $format['selector'] !== '')
            ->unique('selector')
            ->values();

        $curated = collect([360, 720, 1080])
            ->map(fn (int $height): ?array => $this->videoFormat($formats->all(), $height, $metadata))
            ->filter();

        $audio = $this->audioFormat($formats->all(), $metadata);

        if ($audio !== null) {
            $curated->push($audio);
        }

        return $curated
            ->whenEmpty(fn () => $formats->reject(fn (array $format): bool => $format['extension'] === 'mhtml')->take(6))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $formats
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>|null
     */
    private function videoFormat(array $formats, int $height, array $metadata): ?array
    {
        $matching = collect($formats)
            ->filter(fn (array $format): bool => $format['type'] === 'video')
            ->filter(fn (array $format): bool => ($format['height'] ?? null) === $height)
            ->reject(fn (array $format): bool => $format['extension'] === 'mhtml');

        if ($matching->isEmpty()) {
            return null;
        }

        $sizeBytes = $matching
            ->pluck('size_bytes')
            ->filter()
            ->max();

        return [
            'id' => 'video-'.$height,
            'selector' => "bestvideo*[height<={$height}]+bestaudio/best[height<={$height}]/best",
            'type' => 'video',
            'extension' => 'mp4',
            'quality' => $height.'p',
            'duration_seconds' => isset($metadata['duration']) ? (int) round((float) $metadata['duration']) : null,
            'size_bytes' => $sizeBytes,
            'width' => null,
            'height' => $height,
            'fps' => null,
            'video_codec' => null,
            'audio_codec' => null,
            'source' => $metadata['extractor_key'] ?? null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $formats
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>|null
     */
    private function audioFormat(array $formats, array $metadata): ?array
    {
        $matching = collect($formats)
            ->filter(fn (array $format): bool => $format['type'] === 'audio');

        if ($matching->isEmpty()) {
            return null;
        }

        return [
            'id' => 'audio',
            'selector' => 'bestaudio/best',
            'type' => 'audio',
            'extension' => 'm4a',
            'quality' => 'Audio',
            'duration_seconds' => isset($metadata['duration']) ? (int) round((float) $metadata['duration']) : null,
            'size_bytes' => $matching->pluck('size_bytes')->filter()->max(),
            'width' => null,
            'height' => null,
            'fps' => null,
            'video_codec' => null,
            'audio_codec' => null,
            'source' => $metadata['extractor_key'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $format
     * @return array<string, mixed>
     */
    private function format(array $format): array
    {
        $width = isset($format['width']) ? (int) $format['width'] : null;
        $height = isset($format['height']) ? (int) $format['height'] : null;
        $hasVideo = ($format['vcodec'] ?? 'none') !== 'none';
        $hasAudio = ($format['acodec'] ?? 'none') !== 'none';
        $size = $format['filesize'] ?? $format['filesize_approx'] ?? null;

        return [
            'id' => (string) $format['format_id'],
            'selector' => (string) $format['format_id'],
            'type' => $hasVideo ? 'video' : ($hasAudio ? 'audio' : 'file'),
            'extension' => $format['ext'] ?? null,
            'quality' => $this->quality($format, $width, $height),
            'duration_seconds' => isset($format['duration']) ? (int) round((float) $format['duration']) : null,
            'size_bytes' => $size === null ? null : (int) $size,
            'width' => $width,
            'height' => $height,
            'fps' => isset($format['fps']) ? (float) $format['fps'] : null,
            'video_codec' => $format['vcodec'] ?? null,
            'audio_codec' => $format['acodec'] ?? null,
            'source' => $format['protocol'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $format
     */
    private function quality(array $format, ?int $width, ?int $height): string
    {
        if ($height !== null) {
            return $height.'p';
        }

        if ($width !== null) {
            return $width.'px wide';
        }

        return (string) ($format['format_note'] ?? $format['format'] ?? 'Unknown');
    }

    /**
     * @param  array<int, string>  $command
     */
    private function runWithCookieFallback(string $url, callable $command, int $timeout, string $fallback): string
    {
        $lastErrorOutput = '';

        foreach ($this->cookieFallbackModes($url) as $cookieMode) {
            $process = new Process($command($cookieMode));
            $process->setTimeout($timeout);
            $process->run();

            if ($process->isSuccessful()) {
                return $process->getOutput();
            }

            $lastErrorOutput = $process->getErrorOutput();

            if (! $this->shouldTryNextCookieFallback($url, $lastErrorOutput, $cookieMode)) {
                break;
            }
        }

        throw new RuntimeException($this->failureMessage($lastErrorOutput, $fallback));
    }

    /**
     * @param  array<int, string>  $command
     */
    private function run(array $command, int $timeout): string
    {
        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException($this->failureMessage($process->getErrorOutput(), 'Unable to inspect media URL.'));
        }

        return $process->getOutput();
    }

    /**
     * @return array<int, string>
     */
    private function inspectCommand(string $url, string $cookieMode = self::CookieConfigured): array
    {
        return [
            $this->binary(),
            '--ignore-config',
            ...$this->cookieOptions($cookieMode),
            '--dump-single-json',
            '--no-playlist',
            '--skip-download',
            '--no-warnings',
            ...$this->extractorOptions($cookieMode),
            $url,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function downloadCommand(string $url, string $formatSelector, string $directory, string $cookieMode = self::CookieConfigured): array
    {
        return [
            $this->binary(),
            '--ignore-config',
            ...$this->cookieOptions($cookieMode),
            '--no-playlist',
            '--newline',
            '--concurrent-fragments',
            (string) $this->concurrentFragments(),
            ...$this->downloadPerformanceOptions(),
            '--retries',
            (string) config('media.yt_dlp.retries', 10),
            '--fragment-retries',
            (string) config('media.yt_dlp.fragment_retries', 10),
            '--socket-timeout',
            (string) config('media.yt_dlp.socket_timeout', 30),
            '--format',
            $formatSelector,
            '--merge-output-format',
            'mp4',
            '--paths',
            $directory,
            '--output',
            '%(title).180B [%(id)s].%(ext)s',
            ...$this->extractorOptions($cookieMode),
            $url,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function downloadPerformanceOptions(): array
    {
        $options = [];
        $httpChunkSize = config('media.yt_dlp.http_chunk_size');

        if (is_string($httpChunkSize) && $httpChunkSize !== '') {
            array_push($options, '--http-chunk-size', $httpChunkSize);
        }

        $externalDownloader = config('media.yt_dlp.external_downloader');

        if (is_string($externalDownloader) && $externalDownloader !== '') {
            array_push($options, '--downloader', $externalDownloader);

            $externalDownloaderArgs = config('media.yt_dlp.external_downloader_args');

            if (is_string($externalDownloaderArgs) && $externalDownloaderArgs !== '') {
                array_push($options, '--downloader-args', $externalDownloader.':'.$externalDownloaderArgs);
            } elseif ($externalDownloader === 'aria2c') {
                array_push($options, '--downloader-args', $externalDownloader.':'.$this->aria2SegmentArgs());
            }
        }

        return $options;
    }

    private function aria2SegmentArgs(): string
    {
        $profile = $this->downloadResourceProfile()->ytDlp();
        $connectionsPerServer = min(16, max(1, (int) $profile['segment_connections']));
        $diskCache = $this->aria2Size((string) $profile['segment_disk_cache']);

        return implode(' ', [
            '--continue=true',
            '--allow-overwrite=true',
            '--auto-file-renaming=false',
            '--file-allocation=none',
            '--optimize-concurrent-downloads=true',
            '--summary-interval=1',
            "--max-connection-per-server={$connectionsPerServer}",
            "--split={$profile['segment_split']}",
            "--min-split-size={$profile['segment_min_split_size']}",
            "--piece-length={$profile['segment_piece_length']}",
            "--disk-cache={$diskCache}",
        ]);
    }

    private function aria2Size(string $size): string
    {
        $size = trim($size);

        if (preg_match('/\A(\d+)G\z/i', $size, $matches) === 1) {
            return ((int) $matches[1] * 1024).'M';
        }

        return $size;
    }

    /**
     * @return array<int, string>
     */
    private function cookieOptions(string $cookieMode = self::CookieConfigured): array
    {
        if ($cookieMode !== self::CookieConfigured) {
            return [];
        }

        $cookies = config('media.yt_dlp.cookies');

        if (is_string($cookies) && $cookies !== '') {
            return ['--cookies', $cookies];
        }

        $browser = config('media.yt_dlp.cookies_from_browser');

        if (is_string($browser) && $browser !== '') {
            return ['--cookies-from-browser', $browser];
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function extractorOptions(string $cookieMode = self::CookieConfigured): array
    {
        $options = [
            '--extractor-args',
            'generic:impersonate',
        ];

        if ($cookieMode === self::CookieVisitor) {
            array_push($options, '--extractor-args', 'youtubetab:skip=webpage');
        }

        $youtubePlayerClients = config('media.yt_dlp.youtube_player_clients');

        if (is_string($youtubePlayerClients) && $youtubePlayerClients !== '') {
            $youtubeArguments = 'youtube:player_client='.$youtubePlayerClients;

            if ($cookieMode === self::CookieVisitor) {
                $visitorData = $this->youtubeVisitorData();

                if ($visitorData !== null) {
                    $youtubeArguments .= ';player_skip=webpage,configs;visitor_data='.$visitorData;
                }
            }

            array_push($options, '--extractor-args', $youtubeArguments);
        }

        $providerUrl = config('media.yt_dlp.pot_provider_url');

        if (is_string($providerUrl) && $providerUrl !== '') {
            $providerArguments = 'youtubepot-bgutilhttp:base_url='.$providerUrl;

            if ((bool) config('media.yt_dlp.pot_disable_innertube', true)) {
                $providerArguments .= ';disable_innertube=1';
            }

            array_push($options, '--extractor-args', $providerArguments);
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private function cookieFallbackModes(string $url): array
    {
        if (! $this->isYoutubeUrl($url) || ! $this->configuredCookiesAreMissingYoutubeAccountCookie()) {
            return [self::CookieConfigured];
        }

        $modes = [self::CookieConfigured, self::CookieNone];

        if ($this->youtubeVisitorData() !== null) {
            $modes[] = self::CookieVisitor;
        }

        return $modes;
    }

    private function shouldTryNextCookieFallback(string $url, string $errorOutput, string $cookieMode): bool
    {
        if ($cookieMode === self::CookieVisitor) {
            return false;
        }

        return $this->isYoutubeUrl($url)
            && $this->configuredCookiesAreMissingYoutubeAccountCookie()
            && str_contains($errorOutput, 'Sign in to confirm you')
            && str_contains($errorOutput, 'not a bot');
    }

    private function failureMessage(string $errorOutput, string $fallback): string
    {
        $message = trim($errorOutput) ?: $fallback;

        if (str_contains($message, 'Sign in to confirm you') && str_contains($message, 'not a bot')) {
            if ($this->configuredCookiesAreMissingYoutubeAccountCookie()) {
                return 'YouTube blocked anonymous guest access from this server for this video. Guest cookies, no-cookie mode, visitor-data mode, and the PO-token provider were tried.';
            }

            return 'YouTube blocked this server as an automated client. Configure YTDLP_POT_PROVIDER_URL for the yt-dlp PO-token provider, or use a full Netscape cookies.txt file with YTDLP_COOKIES.';
        }

        return $message;
    }

    private function configuredCookiesAreMissingYoutubeAccountCookie(): bool
    {
        $cookies = config('media.yt_dlp.cookies');

        if (! is_string($cookies) || $cookies === '' || ! is_file($cookies)) {
            return false;
        }

        $contents = file_get_contents($cookies);

        if (! is_string($contents) || $contents === '') {
            return false;
        }

        $accountCookieNames = [
            'SID',
            'HSID',
            'SSID',
            'APISID',
            'SAPISID',
            '__Secure-1PSID',
            '__Secure-3PSID',
            '__Secure-1PSIDTS',
            '__Secure-3PSIDTS',
        ];

        foreach (preg_split('/\r?\n/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $columns = preg_split('/\t+/', $line);

            if (is_array($columns) && count($columns) >= 7 && in_array($columns[5] ?? null, $accountCookieNames, true)) {
                return false;
            }
        }

        return str_contains($contents, 'youtube.com');
    }

    private function youtubeVisitorData(): ?string
    {
        $cookies = config('media.yt_dlp.cookies');

        if (! is_string($cookies) || $cookies === '' || ! is_file($cookies)) {
            return null;
        }

        $contents = file_get_contents($cookies);

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        foreach (preg_split('/\r?\n/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $columns = preg_split('/\t+/', $line);

            if (is_array($columns) && count($columns) >= 7 && ($columns[5] ?? null) === 'VISITOR_INFO1_LIVE') {
                return (string) $columns[6];
            }
        }

        return null;
    }

    private function isYoutubeUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host)) {
            return false;
        }

        $host = ltrim(strtolower($host), '.');

        return $host === 'youtube.com'
            || $host === 'youtu.be'
            || str_ends_with($host, '.youtube.com');
    }

    /**
     * @return array<int, string>
     */
    private function files(string $directory): array
    {
        return glob(rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*') ?: [];
    }

    /**
     * @param  callable(int $progress, int $downloadedBytes): void  $progress
     */
    private function parseProgress(string $buffer, callable $progress): void
    {
        foreach (preg_split('/\r?\n/', $buffer) ?: [] as $line) {
            if (preg_match('/\[download\]\s+([0-9.]+)%/', $line, $matches) === 1) {
                $percent = min(100, (int) round((float) $matches[1]));
                $downloadedBytes = 0;

                if (preg_match('/\bof\s+~?\s*([0-9.]+)\s*([KMGTPE]?i?B)\b/i', $line, $sizeMatches) === 1) {
                    $totalBytes = $this->parseSizeBytes((float) $sizeMatches[1], $sizeMatches[2]);
                    $downloadedBytes = (int) round($totalBytes * ($percent / 100));
                }

                $progress($percent, $downloadedBytes);
            }
        }
    }

    private function parseSizeBytes(float $amount, string $unit): int
    {
        $unit = strtoupper($unit);
        $power = match ($unit) {
            'KB', 'KIB' => 1,
            'MB', 'MIB' => 2,
            'GB', 'GIB' => 3,
            'TB', 'TIB' => 4,
            'PB', 'PIB' => 5,
            'EB', 'EIB' => 6,
            default => 0,
        };

        return (int) round($amount * (1024 ** $power));
    }

    private function binary(): string
    {
        return (string) config('media.yt_dlp.binary', 'yt-dlp');
    }

    private function inspectTimeout(): int
    {
        return (int) config('media.yt_dlp.inspect_timeout', 60);
    }

    private function downloadTimeout(): int
    {
        return (int) config('media.yt_dlp.download_timeout', 3600);
    }

    private function concurrentFragments(): int
    {
        return $this->downloadResourceProfile()->ytDlp()['concurrent_fragments'];
    }

    private function downloadResourceProfile(): DownloadResourceProfile
    {
        return app(DownloadResourceProfile::class);
    }
}
