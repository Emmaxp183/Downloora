<?php

namespace App\Http\Controllers;

use App\Enums\MediaImportStatus;
use App\Enums\TorrentSourceType;
use App\Enums\TorrentStatus;
use App\Http\Requests\StoreTorrentRequest;
use App\Jobs\InspectMediaImport;
use App\Jobs\InspectTorrentMetadata;
use App\Models\MediaImport;
use App\Models\Torrent;
use App\Services\Torrents\TorrentEngineClient;
use App\Services\Wishlist\WishlistSaver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TorrentController extends Controller
{
    /**
     * Store a newly submitted torrent.
     */
    public function store(StoreTorrentRequest $request, WishlistSaver $wishlistSaver): RedirectResponse
    {
        $user = $request->user();
        $activeErrorField = match (true) {
            $request->hasFile('torrent_file') => 'torrent_file',
            $request->filled('magnet_uri') && ! $request->filled('url') => 'magnet_uri',
            default => 'url',
        };

        $activeDownloadCount = $user->torrents()->active()->count()
            + $user->mediaImports()->active()->count();
        $activeDownloadLimit = max(1, (int) config('torrents.per_user_active_limit', 5));

        if ($activeDownloadCount >= $activeDownloadLimit) {
            if ($request->downloadUrl() !== null) {
                $wishlistSaver->save($user, $request->downloadUrl());

                return to_route('dashboard');
            }

            throw ValidationException::withMessages([
                $activeErrorField => __('Upload torrent files after one of your active downloads finishes.'),
            ]);
        }

        if ($request->isMediaUrl()) {
            $mediaImport = MediaImport::create([
                'user_id' => $user->id,
                'source_url' => $request->downloadUrl(),
                'source_domain' => parse_url((string) $request->downloadUrl(), PHP_URL_HOST),
                'status' => MediaImportStatus::Inspecting,
                'progress' => 0,
            ]);

            InspectMediaImport::dispatch($mediaImport);

            return to_route('dashboard');
        }

        $torrentFilePath = $request->hasFile('torrent_file')
            ? $request->file('torrent_file')->store('torrents')
            : null;

        $torrent = Torrent::create([
            'user_id' => $user->id,
            'source_type' => $request->isMagnet()
                ? TorrentSourceType::Magnet
                : TorrentSourceType::TorrentFile,
            'magnet_uri' => $request->isMagnet() ? $request->downloadUrl() : null,
            'torrent_file_path' => $torrentFilePath,
            'status' => TorrentStatus::PendingMetadata,
            'progress' => 0,
        ]);

        InspectTorrentMetadata::dispatch($torrent);

        return to_route('dashboard');
    }

    public function destroy(Torrent $torrent, TorrentEngineClient $client): RedirectResponse
    {
        Gate::authorize('delete', $torrent);

        if (filled($torrent->qbittorrent_hash)) {
            $client->delete($torrent->qbittorrent_hash);
        }

        $torrent->forceFill([
            'status' => TorrentStatus::Cancelled,
            'error_message' => null,
        ])->save();

        return to_route('dashboard');
    }
}
