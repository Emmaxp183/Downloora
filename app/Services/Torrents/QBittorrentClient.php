<?php

namespace App\Services\Torrents;

use App\Models\Torrent;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class QBittorrentClient
{
    public function addMagnet(Torrent $torrent, bool $paused = false): void
    {
        $request = $this->request();

        $this->applyPerformancePreferences($request);

        try {
            $request
                ->asForm()
                ->post($this->url('/api/v2/torrents/add'), [
                    'urls' => $torrent->magnet_uri,
                    'savepath' => '/downloads/'.($torrent->info_hash ?: $torrent->id),
                    'paused' => $paused ? 'true' : 'false',
                    'dlLimit' => 0,
                    'upLimit' => 0,
                    'sequentialDownload' => 'false',
                    'firstLastPiecePrio' => 'false',
                ])
                ->throw();
        } catch (RequestException $exception) {
            if ($paused || $exception->response->status() !== 409) {
                throw $exception;
            }
        }

        if (! $paused) {
            $this->prioritizeTorrent($request, $torrent);
        }
    }

    public function addTorrentFile(Torrent $torrent, bool $paused = false): void
    {
        if ($torrent->torrent_file_path === null || ! Storage::exists($torrent->torrent_file_path)) {
            throw new RuntimeException('Torrent file is missing.');
        }

        $request = $this->request();

        $this->applyPerformancePreferences($request);

        try {
            $request
                ->attach(
                    'torrents',
                    Storage::get($torrent->torrent_file_path),
                    basename($torrent->torrent_file_path),
                )
                ->post($this->url('/api/v2/torrents/add'), [
                    'savepath' => '/downloads/'.($torrent->info_hash ?: $torrent->id),
                    'paused' => $paused ? 'true' : 'false',
                    'dlLimit' => 0,
                    'upLimit' => 0,
                    'sequentialDownload' => 'false',
                    'firstLastPiecePrio' => 'false',
                ])
                ->throw();
        } catch (RequestException $exception) {
            if ($paused || $exception->response->status() !== 409) {
                throw $exception;
            }
        }

        if (! $paused) {
            $this->prioritizeTorrent($request, $torrent);
        }
    }

    private function applyPerformancePreferences(PendingRequest $request): void
    {
        $preferences = config('torrents.qbittorrent.performance_preferences', []);

        if (! is_array($preferences) || $preferences === []) {
            return;
        }

        $request
            ->asForm()
            ->post($this->url('/api/v2/app/setPreferences'), [
                'json' => json_encode($preferences, JSON_THROW_ON_ERROR),
            ])
            ->throw();
    }

    private function prioritizeTorrent(PendingRequest $request, Torrent $torrent): void
    {
        $hash = $torrent->qbittorrent_hash ?: $torrent->info_hash;

        if (blank($hash)) {
            return;
        }

        try {
            $request
                ->asForm()
                ->post($this->url('/api/v2/torrents/setDownloadLimit'), [
                    'hashes' => $hash,
                    'limit' => 0,
                ])
                ->throw();

            $request
                ->asForm()
                ->post($this->url('/api/v2/torrents/setUploadLimit'), [
                    'hashes' => $hash,
                    'limit' => 0,
                ])
                ->throw();

            $request
                ->asForm()
                ->post($this->url('/api/v2/torrents/setForceStart'), [
                    'hashes' => $hash,
                    'value' => 'true',
                ])
                ->throw();

            $request
                ->asForm()
                ->post($this->url('/api/v2/torrents/topPrio'), [
                    'hashes' => $hash,
                ])
                ->throw();

            $request
                ->asForm()
                ->post($this->url('/api/v2/torrents/reannounce'), [
                    'hashes' => $hash,
                ])
                ->throw();
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }

    /**
     * Get qBittorrent details for a torrent hash.
     *
     * @return array<string, mixed>
     */
    public function getTorrent(string $hash): array
    {
        $response = $this->request()
            ->get($this->url('/api/v2/torrents/info'), [
                'hashes' => $hash,
            ])
            ->throw()
            ->json();

        return $response[0] ?? [];
    }

    /**
     * Get all torrents currently known to qBittorrent.
     *
     * @return array<int, array<string, mixed>>
     */
    public function torrents(): array
    {
        return $this->request()
            ->get($this->url('/api/v2/torrents/info'))
            ->throw()
            ->json();
    }

    /**
     * Get files for a torrent hash.
     *
     * @return array<int, array<string, mixed>>
     */
    public function files(string $hash): array
    {
        return $this->request()
            ->get($this->url('/api/v2/torrents/files'), [
                'hash' => $hash,
            ])
            ->throw()
            ->json();
    }

    public function delete(string $hash, bool $deleteFiles = true): void
    {
        $this->request()
            ->asForm()
            ->post($this->url('/api/v2/torrents/delete'), [
                'hashes' => $hash,
                'deleteFiles' => $deleteFiles ? 'true' : 'false',
            ])
            ->throw();
    }

    private function request(): PendingRequest
    {
        $cookieJar = new CookieJar;

        $this->baseRequest($cookieJar)
            ->asForm()
            ->post($this->url('/api/v2/auth/login'), [
                'username' => config('torrents.qbittorrent.username'),
                'password' => config('torrents.qbittorrent.password'),
            ])
            ->throw();

        return $this->baseRequest($cookieJar);
    }

    private function baseRequest(CookieJar $cookieJar): PendingRequest
    {
        return Http::withOptions(['cookies' => $cookieJar])
            ->timeout((int) config('torrents.qbittorrent.timeout', 10))
            ->connectTimeout(5);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('torrents.qbittorrent.base_url'), '/').$path;
    }
}
