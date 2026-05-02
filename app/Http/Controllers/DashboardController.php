<?php

namespace App\Http\Controllers;

use App\Services\Storage\StorageQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, StorageQuota $storageQuota): Response
    {
        $user = $request->user();

        $activeTorrent = $user->torrents()
            ->active()
            ->latest()
            ->first();

        return Inertia::render('Dashboard', [
            'quota' => [
                'used_bytes' => $storageQuota->usedBytes($user),
                'quota_bytes' => $user->storage_quota_bytes,
                'remaining_bytes' => $storageQuota->remainingBytes($user),
            ],
            'activeTorrent' => $activeTorrent ? $this->torrentPayload($activeTorrent) : null,
            'recentTorrents' => $user->torrents()
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn ($torrent): array => $this->torrentPayload($torrent)),
            'recentFiles' => $user->storedFiles()
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($file): array => [
                    'id' => $file->id,
                    'name' => $file->name,
                    'original_path' => $file->original_path,
                    'size_bytes' => $file->size_bytes,
                    'mime_type' => $file->mime_type,
                    'download_url' => URL::signedRoute('files.download', $file),
                    'stream_url' => URL::signedRoute('files.stream', $file),
                ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function torrentPayload($torrent): array
    {
        return [
            'id' => $torrent->id,
            'name' => $torrent->name,
            'status' => $torrent->status->value,
            'progress' => $torrent->progress,
            'total_size_bytes' => $torrent->total_size_bytes,
            'downloaded_bytes' => $torrent->downloaded_bytes,
            'error_message' => $torrent->error_message,
        ];
    }
}
