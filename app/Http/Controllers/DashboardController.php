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

        $activeMediaImport = $user->mediaImports()
            ->active()
            ->latest()
            ->first();

        $recentFiles = $user->storedFiles()
            ->with(['torrent', 'mediaImport'])
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
            'activeMediaImport' => $activeMediaImport ? $this->mediaImportPayload($activeMediaImport) : null,
            'prefillUrl' => $this->prefillUrl($request),
            'prefillAutoSubmit' => $this->prefillAutoSubmit($request),
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

    /**
     * @return array<string, mixed>
     */
    private function mediaImportPayload($mediaImport): array
    {
        return [
            'id' => $mediaImport->id,
            'title' => $mediaImport->title,
            'source_url' => $mediaImport->source_url,
            'source_domain' => $mediaImport->source_domain,
            'thumbnail_url' => $mediaImport->thumbnail_url,
            'status' => $mediaImport->status->value,
            'progress' => $mediaImport->progress,
            'duration_seconds' => $mediaImport->duration_seconds,
            'estimated_size_bytes' => $mediaImport->estimated_size_bytes,
            'downloaded_bytes' => $mediaImport->downloaded_bytes,
            'formats' => $mediaImport->formats ?? [],
            'selected_format' => $mediaImport->selected_format,
            'error_message' => $mediaImport->error_message,
        ];
    }

    private function prefillUrl(Request $request): ?string
    {
        $url = $request->string('url')->trim()->toString();

        if ($url === '') {
            return null;
        }

        return filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            ? $url
            : null;
    }

    private function prefillAutoSubmit(Request $request): bool
    {
        return $request->string('source')->toString() === 'browser-extension'
            && $request->boolean('auto');
    }
}
