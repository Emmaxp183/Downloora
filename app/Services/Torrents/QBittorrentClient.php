<?php

namespace App\Services\Torrents;

use App\Models\Torrent;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class QBittorrentClient
{
    public function addMagnet(Torrent $torrent, bool $paused = false): void
    {
        $this->request()
            ->asForm()
            ->post($this->url('/api/v2/torrents/add'), [
                'urls' => $torrent->magnet_uri,
                'savepath' => '/downloads/'.($torrent->info_hash ?: $torrent->id),
                'paused' => $paused ? 'true' : 'false',
            ])
            ->throw();
    }

    public function addTorrentFile(Torrent $torrent, bool $paused = false): void
    {
        if ($torrent->torrent_file_path === null || ! Storage::exists($torrent->torrent_file_path)) {
            throw new RuntimeException('Torrent file is missing.');
        }

        $this->request()
            ->attach(
                'torrents',
                Storage::get($torrent->torrent_file_path),
                basename($torrent->torrent_file_path),
            )
            ->post($this->url('/api/v2/torrents/add'), [
                'savepath' => '/downloads/'.($torrent->info_hash ?: $torrent->id),
                'paused' => $paused ? 'true' : 'false',
            ])
            ->throw();
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
        $cookieJar = new CookieJar();

        $request = Http::withOptions(['cookies' => $cookieJar])
            ->timeout((int) config('torrents.qbittorrent.timeout', 10))
            ->connectTimeout(5);

        $request
            ->asForm()
            ->post($this->url('/api/v2/auth/login'), [
                'username' => config('torrents.qbittorrent.username'),
                'password' => config('torrents.qbittorrent.password'),
            ])
            ->throw();

        return $request;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('torrents.qbittorrent.base_url'), '/').$path;
    }
}
