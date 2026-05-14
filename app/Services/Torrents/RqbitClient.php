<?php

namespace App\Services\Torrents;

use App\Models\Torrent;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RqbitClient implements TorrentEngineClient
{
    public function addMagnet(Torrent $torrent, bool $paused = false, bool $reuseExisting = true): void
    {
        if ($torrent->magnet_uri === null) {
            throw new RuntimeException('Magnet URI is missing.');
        }

        try {
            $response = $this->postTorrent($torrent->magnet_uri, 'text/plain', [
                'overwrite' => $reuseExisting ? 'true' : 'false',
                'output_folder' => $this->downloadPath($torrent),
            ], timeout: $this->addTimeout())->throw()->json();
        } catch (RequestException $exception) {
            if (! $reuseExisting || $exception->response->status() !== 409) {
                throw $exception;
            }

            $response = [];
        }

        $this->rememberEngineHash($torrent, $response);

        if ($paused && filled($torrent->qbittorrent_hash)) {
            $this->request()
                ->post($this->url("/torrents/{$torrent->qbittorrent_hash}/pause"))
                ->throw();
        }
    }

    public function addTorrentFile(Torrent $torrent, bool $paused = false): void
    {
        if ($torrent->torrent_file_path === null || ! Storage::exists($torrent->torrent_file_path)) {
            throw new RuntimeException('Torrent file is missing.');
        }

        $response = $this->postTorrent(Storage::get($torrent->torrent_file_path), 'application/x-bittorrent', [
            'overwrite' => 'true',
            'output_folder' => $this->downloadPath($torrent),
        ], timeout: $this->addTimeout())->throw()->json();

        $this->rememberEngineHash($torrent, $response);

        if ($paused && filled($torrent->qbittorrent_hash)) {
            $this->request()
                ->post($this->url("/torrents/{$torrent->qbittorrent_hash}/pause"))
                ->throw();
        }
    }

    public function inspect(Torrent $torrent): array
    {
        if ($torrent->magnet_uri !== null) {
            return $this->postTorrent($torrent->magnet_uri, 'text/plain', [
                'list_only' => 'true',
                'output_folder' => $this->downloadPath($torrent),
            ], timeout: $this->metadataTimeout())->throw()->json();
        }

        if ($torrent->torrent_file_path === null || ! Storage::exists($torrent->torrent_file_path)) {
            throw new RuntimeException('Torrent file is missing.');
        }

        return $this->postTorrent(Storage::get($torrent->torrent_file_path), 'application/x-bittorrent', [
            'list_only' => 'true',
            'output_folder' => $this->downloadPath($torrent),
        ], timeout: $this->metadataTimeout())->throw()->json();
    }

    public function getTorrent(string $hash): array
    {
        $details = $this->request()
            ->get($this->url("/torrents/{$hash}"))
            ->throw()
            ->json();

        $stats = $this->request()
            ->get($this->url("/torrents/{$hash}/stats/v1"))
            ->throw()
            ->json();

        $totalBytes = (int) ($stats['total_bytes'] ?? $this->totalSize($details));
        $downloadedBytes = (int) ($stats['progress_bytes'] ?? 0);
        $progress = $totalBytes > 0
            ? min(1, max(0, $downloadedBytes / $totalBytes))
            : ((bool) ($stats['finished'] ?? false) ? 1 : 0);

        return [
            'hash' => strtolower((string) ($details['info_hash'] ?? $hash)),
            'name' => (string) ($details['name'] ?? 'Unnamed torrent'),
            'progress' => $progress,
            'downloaded' => $downloadedBytes,
            'total_size' => $totalBytes,
            'save_path' => rtrim((string) ($details['output_folder'] ?? ''), '/'),
            'state' => (string) ($stats['state'] ?? ''),
            'finished' => (bool) ($stats['finished'] ?? false),
        ];
    }

    public function torrents(): array
    {
        return collect($this->request()->get($this->url('/torrents'))->throw()->json('torrents') ?? [])
            ->map(fn (array $torrent): array => [
                'hash' => strtolower((string) ($torrent['info_hash'] ?? '')),
                'name' => (string) ($torrent['name'] ?? 'Unnamed torrent'),
                'save_path' => rtrim((string) ($torrent['output_folder'] ?? ''), '/'),
            ])
            ->values()
            ->all();
    }

    public function files(string $hash): array
    {
        $details = $this->request()
            ->get($this->url("/torrents/{$hash}"))
            ->throw()
            ->json();

        return $this->normalizeFiles($details);
    }

    public function delete(string $hash, bool $deleteFiles = true): void
    {
        $action = $deleteFiles ? 'delete' : 'forget';

        $this->request()
            ->post($this->url("/torrents/{$hash}/{$action}"))
            ->throw();
    }

    /**
     * @return array<int, array{name: string, size: int}>
     */
    public function normalizeFiles(array $details): array
    {
        return collect($details['files'] ?? [])
            ->map(fn (array $file): array => [
                'name' => $this->filePath($file),
                'size' => (int) ($file['length'] ?? $file['size'] ?? 0),
            ])
            ->filter(fn (array $file): bool => $file['name'] !== '' && $file['size'] > 0)
            ->values()
            ->all();
    }

    private function postTorrent(string $body, string $contentType, array $query, ?int $timeout = null): Response
    {
        return $this->request($timeout)
            ->withQueryParameters($query)
            ->withBody($body, $contentType)
            ->post($this->url('/torrents'));
    }

    private function rememberEngineHash(Torrent $torrent, array $response): void
    {
        $hash = strtolower((string) data_get($response, 'details.info_hash', $torrent->info_hash));

        if ($hash === '') {
            return;
        }

        $torrent->forceFill([
            'info_hash' => $torrent->info_hash ?: $hash,
            'qbittorrent_hash' => $torrent->qbittorrent_hash ?: $hash,
        ])->save();
    }

    private function totalSize(array $details): int
    {
        return collect($details['files'] ?? [])->sum(fn (array $file): int => (int) ($file['length'] ?? $file['size'] ?? 0));
    }

    private function filePath(array $file): string
    {
        $components = $file['components'] ?? null;

        if (is_array($components) && $components !== []) {
            return collect($components)
                ->map(fn (mixed $component): string => trim((string) $component, '/'))
                ->filter()
                ->implode('/');
        }

        return trim((string) ($file['name'] ?? ''), '/');
    }

    private function downloadPath(Torrent $torrent): string
    {
        return rtrim((string) config('torrents.rqbit.download_path', '/downloads'), '/').'/'.($torrent->info_hash ?: $torrent->id);
    }

    private function request(?int $timeout = null): PendingRequest
    {
        $request = Http::acceptJson()
            ->timeout($timeout ?? (int) config('torrents.rqbit.timeout', 10))
            ->connectTimeout(5);

        $username = config('torrents.rqbit.username');
        $password = config('torrents.rqbit.password');

        if (filled($username) && filled($password)) {
            $request = $request->withBasicAuth((string) $username, (string) $password);
        }

        return $request;
    }

    private function metadataTimeout(): int
    {
        return max(1, (int) config('torrents.rqbit.metadata_timeout', 8));
    }

    private function addTimeout(): int
    {
        return max(1, (int) config('torrents.rqbit.add_timeout', 120));
    }

    private function url(string $path): string
    {
        return rtrim((string) config('torrents.rqbit.base_url'), '/').$path;
    }
}
