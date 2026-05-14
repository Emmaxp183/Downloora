<?php

namespace App\Services\Media;

use App\Models\StoredFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class AdaptiveStreamGenerator
{
    /**
     * @return array{
     *     disk: string,
     *     bucket: string|null,
     *     playlist_key: string,
     *     variants: array<int, array<string, mixed>>
     * }
     */
    public function generate(StoredFile $storedFile): array
    {
        $workingDirectory = storage_path('app/adaptive-streams/work/'.$storedFile->id.'-'.Str::uuid());
        $inputPath = $workingDirectory.'/source/'.Str::uuid().'-'.$storedFile->name;
        $outputDirectory = $workingDirectory.'/hls';

        File::ensureDirectoryExists(dirname($inputPath));
        File::ensureDirectoryExists($outputDirectory);

        try {
            $this->copySourceToLocalPath($storedFile, $inputPath);

            $variants = $this->variants();

            foreach ($variants as $variant) {
                $this->generateVariant($inputPath, $outputDirectory, $variant);
            }

            $this->writeMasterPlaylist($outputDirectory, $variants);

            return $this->storeOutput($storedFile, $outputDirectory, $variants);
        } finally {
            File::deleteDirectory($workingDirectory);
        }
    }

    private function copySourceToLocalPath(StoredFile $storedFile, string $localPath): void
    {
        $stream = $storedFile->s3Disk()->readStream($storedFile->s3_key);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to read stored file [{$storedFile->id}] for adaptive streaming.");
        }

        $destination = fopen($localPath, 'wb');

        if (! is_resource($destination)) {
            fclose($stream);

            throw new RuntimeException("Unable to create adaptive streaming source file [{$localPath}].");
        }

        try {
            stream_copy_to_stream($stream, $destination);
        } finally {
            fclose($stream);
            fclose($destination);
        }
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     height: int,
     *     width: int,
     *     video_bitrate: string,
     *     audio_bitrate: string,
     *     bandwidth: int
     * }>
     */
    private function variants(): array
    {
        $variants = config('media.adaptive.variants', []);

        if (! is_array($variants) || $variants === []) {
            throw new RuntimeException('Adaptive streaming variants are not configured.');
        }

        return array_values(array_map(fn (array $variant): array => [
            'name' => (string) $variant['name'],
            'height' => (int) $variant['height'],
            'width' => (int) $variant['width'],
            'video_bitrate' => (string) $variant['video_bitrate'],
            'audio_bitrate' => (string) $variant['audio_bitrate'],
            'bandwidth' => (int) $variant['bandwidth'],
        ], $variants));
    }

    /**
     * @param  array{name: string, height: int, width: int, video_bitrate: string, audio_bitrate: string, bandwidth: int}  $variant
     */
    private function generateVariant(string $inputPath, string $outputDirectory, array $variant): void
    {
        $variantDirectory = $outputDirectory.'/'.$variant['name'];
        File::ensureDirectoryExists($variantDirectory);

        $this->runProcess([
            (string) config('media.adaptive.ffmpeg_binary', 'ffmpeg'),
            '-y',
            '-fflags',
            '+genpts',
            '-i',
            $inputPath,
            '-map',
            '0:v:0',
            '-map',
            '0:a:0?',
            '-dn',
            '-sn',
            '-vf',
            "scale=-2:{$variant['height']}",
            '-c:v',
            'libx264',
            '-preset',
            (string) config('media.adaptive.ffmpeg_preset', 'veryfast'),
            '-profile:v',
            'main',
            '-pix_fmt',
            'yuv420p',
            '-b:v',
            $variant['video_bitrate'],
            '-maxrate',
            $variant['video_bitrate'],
            '-bufsize',
            $variant['video_bitrate'],
            '-c:a',
            'aac',
            '-b:a',
            $variant['audio_bitrate'],
            '-ac',
            '2',
            '-f',
            'hls',
            '-hls_time',
            (string) config('media.adaptive.hls_time_seconds', 6),
            '-hls_playlist_type',
            'vod',
            '-max_muxing_queue_size',
            '4096',
            '-hls_segment_filename',
            $variantDirectory.'/segment_%05d.ts',
            $variantDirectory.'/index.m3u8',
        ]);
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function runProcess(array $arguments): void
    {
        $command = implode(' ', array_map('escapeshellarg', $arguments));
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start adaptive stream generation.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $errorOutput = '';
        $deadline = time() + (int) config('media.adaptive.ffmpeg_timeout_seconds', 3600);

        do {
            $status = proc_get_status($process);
            $output .= stream_get_contents($pipes[1]);
            $errorOutput .= stream_get_contents($pipes[2]);

            if (! $status['running']) {
                break;
            }

            if (time() > $deadline) {
                proc_terminate($process);

                throw new RuntimeException('Adaptive stream generation timed out.');
            }

            usleep(100_000);
        } while (true);

        $output .= stream_get_contents($pipes[1]);
        $errorOutput .= stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if (($status['exitcode'] ?? 1) !== 0) {
            throw new RuntimeException(trim($errorOutput) ?: trim($output) ?: 'Adaptive stream generation failed.');
        }
    }

    /**
     * @param  array<int, array{name: string, height: int, width: int, video_bitrate: string, audio_bitrate: string, bandwidth: int}>  $variants
     */
    private function writeMasterPlaylist(string $outputDirectory, array $variants): void
    {
        $contents = "#EXTM3U\n#EXT-X-VERSION:3\n";

        foreach ($variants as $variant) {
            $contents .= "#EXT-X-STREAM-INF:BANDWIDTH={$variant['bandwidth']},RESOLUTION={$variant['width']}x{$variant['height']}\n";
            $contents .= "{$variant['name']}/index.m3u8\n";
        }

        File::put($outputDirectory.'/master.m3u8', $contents);
    }

    /**
     * @param  array<int, array<string, mixed>>  $variants
     * @return array{
     *     disk: string,
     *     bucket: string|null,
     *     playlist_key: string,
     *     variants: array<int, array<string, mixed>>
     * }
     */
    private function storeOutput(StoredFile $storedFile, string $outputDirectory, array $variants): array
    {
        $diskName = $storedFile->s3_disk;
        $disk = Storage::disk($diskName);
        $baseKey = 'adaptive-streams/'.$storedFile->id.'/'.Str::uuid();

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($outputDirectory));

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = Str::after($file->getPathname(), rtrim($outputDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
            $stream = fopen($file->getPathname(), 'rb');

            if (! is_resource($stream)) {
                throw new RuntimeException("Unable to read adaptive stream asset [{$relativePath}].");
            }

            try {
                $stored = $disk->put($baseKey.'/'.$relativePath, $stream);
            } finally {
                fclose($stream);
            }

            if (! $stored) {
                throw new RuntimeException("Unable to store adaptive stream asset [{$relativePath}].");
            }
        }

        return [
            'disk' => $diskName,
            'bucket' => $storedFile->s3_bucket ?: config("filesystems.disks.{$diskName}.bucket"),
            'playlist_key' => $baseKey.'/master.m3u8',
            'variants' => $variants,
        ];
    }
}
