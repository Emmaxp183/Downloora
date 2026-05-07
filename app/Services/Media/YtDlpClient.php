<?php

namespace App\Services\Media;

use RuntimeException;
use Symfony\Component\Process\Process;

class YtDlpClient
{
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
        $json = $this->run([
            $this->binary(),
            '--ignore-config',
            '--dump-single-json',
            '--no-playlist',
            '--skip-download',
            '--no-warnings',
            '--extractor-args',
            'generic:impersonate',
            $url,
        ], $this->inspectTimeout());

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
        $process = new Process($this->downloadCommand($url, $formatSelector, $directory));

        $process->setTimeout($this->downloadTimeout());
        $process->run(function (string $type, string $buffer) use ($progress): void {
            $this->parseProgress($buffer, $progress);
        });

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Unable to download media.');
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
    private function run(array $command, int $timeout): string
    {
        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Unable to inspect media URL.');
        }

        return $process->getOutput();
    }

    /**
     * @return array<int, string>
     */
    private function downloadCommand(string $url, string $formatSelector, string $directory): array
    {
        return [
            $this->binary(),
            '--ignore-config',
            '--no-playlist',
            '--newline',
            '--concurrent-fragments',
            (string) $this->concurrentFragments(),
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
            '--extractor-args',
            'generic:impersonate',
            $url,
        ];
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
                $progress(min(100, (int) round((float) $matches[1])), 0);
            }
        }
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
        return max(1, (int) config('media.yt_dlp.concurrent_fragments', 8));
    }
}
