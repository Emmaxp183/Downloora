<?php

namespace App\Http\Controllers;

use App\Services\Storage\StorageQuota;
use App\Services\Storage\StoredFileFolderPayloads;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        StorageQuota $storageQuota,
        StoredFileFolderPayloads $storedFileFolderPayloads,
    ): Response {
        $user = $request->user();

        $activeTorrent = $user->torrents()
            ->active()
            ->latest()
            ->first();

        $recentFiles = $user->storedFiles()
            ->with('torrent')
            ->latest()
            ->limit(50)
            ->get();

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
            'recentFileFolders' => $storedFileFolderPayloads
                ->fromFiles($recentFiles)
                ->take(5)
                ->values(),
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
