<?php

namespace App\Http\Controllers;

use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Services\Transferd\TransferdUrlFactory;
use App\Support\VideoFiles;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoredFileAccessController extends Controller
{
    public function __construct(private TransferdUrlFactory $transferdUrls) {}

    public function download(Request $request, StoredFile $storedFile): RedirectResponse|StreamedResponse
    {
        Gate::authorize('view', $storedFile);

        $disposition = $this->contentDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $storedFile->name);

        if ($this->transferdUrls->enabledFor($storedFile)) {
            return redirect()->away($this->transferdUrls->url($request, $storedFile, $disposition));
        }

        [$start, $end, $status, $headers] = $this->rangeResponse($request, $storedFile, $disposition);

        return response()->stream(fn (): bool => $this->streamObject($storedFile, $start, $end), $status, [
            'Content-Type' => $storedFile->mime_type ?? 'application/octet-stream',
            ...$headers,
        ]);
    }

    public function stream(Request $request, StoredFile $storedFile): RedirectResponse|StreamedResponse
    {
        Gate::authorize('view', $storedFile);

        $disposition = $this->contentDisposition(HeaderUtils::DISPOSITION_INLINE, $storedFile->name);

        return $this->streamResponse($request, $storedFile, $disposition);
    }

    public function cast(Request $request, StoredFile $storedFile): RedirectResponse|StreamedResponse
    {
        abort_unless(VideoFiles::isVideo($storedFile), 404);

        $disposition = $this->contentDisposition(HeaderUtils::DISPOSITION_INLINE, $storedFile->name);

        return $this->streamResponse($request, $storedFile, $disposition);
    }

    public function hlsManifest(StoredFile $storedFile): Response
    {
        abort_unless($this->hasAdaptiveStream($storedFile), 404);

        return $this->playlistResponse($storedFile, $storedFile->adaptive_stream_playlist_key);
    }

    public function hlsAsset(Request $request, StoredFile $storedFile, string $path): RedirectResponse|Response|StreamedResponse
    {
        abort_unless($this->hasAdaptiveStream($storedFile), 404);
        abort_if(Str::contains($path, ['..', '\\']) || Str::startsWith($path, '/'), 404);

        $key = dirname((string) $storedFile->adaptive_stream_playlist_key).'/'.$path;

        abort_unless($storedFile->adaptiveStreamDisk()->exists($key), 404);

        if (Str::endsWith($key, '.m3u8')) {
            return $this->playlistResponse($storedFile, $key);
        }

        if ($this->transferdUrls->enabledForDisk((string) $storedFile->adaptive_stream_disk)) {
            return redirect()->away($this->transferdUrls->objectUrl(
                request: $request,
                backend: (string) $storedFile->adaptive_stream_disk,
                bucket: $storedFile->adaptive_stream_bucket ?: config('filesystems.disks.s3.bucket'),
                key: $key,
                name: basename($path),
                mimeType: $this->hlsContentType($path),
                sizeBytes: (int) $storedFile->adaptiveStreamDisk()->size($key),
                disposition: $this->contentDisposition(HeaderUtils::DISPOSITION_INLINE, basename($path)),
                cacheControl: 'private, max-age=300',
                cors: true,
            ));
        }

        $stream = $storedFile->adaptiveStreamDisk()->readStream($key);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to read adaptive stream asset [{$storedFile->id}:{$path}].");
        }

        return response()->stream(function () use ($stream): void {
            try {
                while (! feof($stream)) {
                    $chunk = fread($stream, 1024 * 1024);

                    if ($chunk === false || $chunk === '') {
                        break;
                    }

                    echo $chunk;
                }
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'private, max-age=300',
            'Content-Type' => $this->hlsContentType($path),
        ]);
    }

    private function streamResponse(Request $request, StoredFile $storedFile, string $disposition): RedirectResponse|StreamedResponse
    {
        if ($this->transferdUrls->enabledFor($storedFile)) {
            return redirect()->away($this->transferdUrls->url($request, $storedFile, $disposition));
        }

        [$start, $end, $status, $headers] = $this->rangeResponse($request, $storedFile, $disposition);

        return response()->stream(fn (): bool => $this->streamObject($storedFile, $start, $end), $status, [
            'Content-Type' => $storedFile->mime_type ?? 'application/octet-stream',
            ...$headers,
        ]);
    }

    private function playlistResponse(StoredFile $storedFile, ?string $key): Response
    {
        abort_unless(is_string($key) && $key !== '', 404);

        $contents = $storedFile->adaptiveStreamDisk()->get($key);

        if (! is_string($contents)) {
            throw new RuntimeException("Unable to read adaptive stream playlist [{$storedFile->id}].");
        }

        return response($this->signedPlaylist($storedFile, $key, $contents), 200, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'private, max-age=300',
            'Content-Type' => 'application/vnd.apple.mpegurl',
        ]);
    }

    private function signedPlaylist(StoredFile $storedFile, string $playlistKey, string $contents): string
    {
        $basePath = trim(Str::after(dirname($playlistKey), dirname((string) $storedFile->adaptive_stream_playlist_key)), '/');
        $expiresAt = now()->addSeconds((int) config('media.adaptive.url_ttl_seconds', 21600));

        return collect(preg_split('/\r\n|\r|\n/', $contents))
            ->map(function (string $line) use ($storedFile, $basePath, $expiresAt): string {
                $trimmed = trim($line);

                if ($trimmed === '' || Str::startsWith($trimmed, '#') || Str::startsWith($trimmed, ['http://', 'https://'])) {
                    return $line;
                }

                $path = $basePath === '' ? $trimmed : $basePath.'/'.$trimmed;

                return URL::temporarySignedRoute('files.hls.asset', $expiresAt, [
                    'storedFile' => $storedFile,
                    'path' => $path,
                ]);
            })
            ->implode("\n");
    }

    private function hasAdaptiveStream(StoredFile $storedFile): bool
    {
        return $storedFile->adaptive_stream_status === 'ready'
            && filled($storedFile->adaptive_stream_disk)
            && filled($storedFile->adaptive_stream_playlist_key);
    }

    private function hlsContentType(string $path): string
    {
        return match (Str::lower(pathinfo($path, PATHINFO_EXTENSION))) {
            'm3u8' => 'application/vnd.apple.mpegurl',
            'ts' => 'video/mp2t',
            default => 'application/octet-stream',
        };
    }

    private function deleteAdaptiveStream(StoredFile $storedFile): void
    {
        if (! filled($storedFile->adaptive_stream_disk) || ! filled($storedFile->adaptive_stream_playlist_key)) {
            return;
        }

        $storedFile->adaptiveStreamDisk()->deleteDirectory(dirname((string) $storedFile->adaptive_stream_playlist_key));
    }

    public function destroy(StoredFile $storedFile): RedirectResponse
    {
        Gate::authorize('delete', $storedFile);

        $storedFile->s3Disk()->delete($storedFile->s3_key);
        $this->deleteAdaptiveStream($storedFile);

        DB::transaction(function () use ($storedFile): void {
            StorageUsageEvent::create([
                'user_id' => $storedFile->user_id,
                'stored_file_id' => $storedFile->id,
                'delta_bytes' => -$storedFile->size_bytes,
                'reason' => 'file_deleted',
                'metadata' => [
                    'path' => $storedFile->original_path,
                    's3_key' => $storedFile->s3_key,
                ],
            ]);

            $storedFile->delete();
        });

        return back();
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: array<string, string>}
     */
    private function rangeResponse(Request $request, StoredFile $storedFile, string $disposition): array
    {
        $size = max(0, $storedFile->size_bytes);
        $start = 0;
        $end = max(0, $size - 1);
        $status = 200;

        if ($size > 0 && preg_match('/\Abytes=(\d*)-(\d*)\z/', (string) $request->header('Range'), $matches) === 1) {
            $rangeStart = $matches[1] === '' ? null : (int) $matches[1];
            $rangeEnd = $matches[2] === '' ? null : (int) $matches[2];

            if ($rangeStart === null && $rangeEnd !== null) {
                $start = max(0, $size - $rangeEnd);
            } elseif ($rangeStart !== null) {
                $start = min($rangeStart, $end);
            }

            if ($rangeEnd !== null && $rangeStart !== null) {
                $end = min($rangeEnd, $end);
            }

            if (
                ($rangeStart === null && ($rangeEnd === null || $rangeEnd <= 0))
                || ($rangeStart !== null && $rangeStart > max(0, $size - 1))
                || $start > $end
            ) {
                return [0, -1, 416, [
                    'Accept-Ranges' => 'bytes',
                    'Content-Disposition' => $disposition,
                    'Content-Length' => '0',
                    'Content-Range' => "bytes */{$size}",
                ]];
            }

            $status = 206;
        }

        $length = $size === 0 ? 0 : max(0, $end - $start + 1);
        $headers = [
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => $disposition,
            'Content-Length' => (string) $length,
        ];

        if ($status === 206) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return [$start, $end, $status, $headers];
    }

    private function contentDisposition(string $disposition, string $filename): string
    {
        $fallback = trim((string) preg_replace('/[^\x20-\x7E]/', '', Str::ascii($filename)));

        return HeaderUtils::makeDisposition($disposition, $filename, $fallback !== '' ? $fallback : 'download');
    }

    private function streamObject(StoredFile $storedFile, int $start = 0, ?int $end = null): bool
    {
        $stream = $storedFile->s3Disk()->readStream($storedFile->s3_key);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to read stored file [{$storedFile->id}].");
        }

        try {
            if ($start > 0) {
                $this->seekStream($stream, $start);
            }

            $remaining = ($end ?? ($storedFile->size_bytes - 1)) - $start + 1;

            while (! feof($stream) && $remaining > 0) {
                $chunk = fread($stream, min(1024 * 1024, $remaining));

                if ($chunk === false || $chunk === '') {
                    break;
                }

                echo $chunk;
                $remaining -= strlen($chunk);

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            }
        } finally {
            fclose($stream);
        }

        return true;
    }

    /**
     * @param  resource  $stream
     */
    private function seekStream($stream, int $offset): void
    {
        if (stream_get_meta_data($stream)['seekable'] === true) {
            fseek($stream, $offset);

            return;
        }

        $remaining = $offset;

        while ($remaining > 0 && ! feof($stream)) {
            $discarded = fread($stream, min(1024 * 1024, $remaining));

            if ($discarded === false || $discarded === '') {
                break;
            }

            $remaining -= strlen($discarded);
        }
    }
}
