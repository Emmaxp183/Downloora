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
use App\Services\Torrents\QBittorrentClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TorrentController extends Controller
{
    /**
     * Store a newly submitted torrent.
     */
    public function store(StoreTorrentRequest $request): RedirectResponse
    {
        $user = $request->user();
        $activeErrorField = $request->filled('magnet_uri') && ! $request->filled('url')
            ? 'magnet_uri'
            : 'url';

        if ($user->torrents()->active()->exists()) {
            throw ValidationException::withMessages([
                $activeErrorField => __('You already have an active download.'),
            ]);
        }

        if ($user->mediaImports()->active()->exists()) {
            throw ValidationException::withMessages([
                $activeErrorField => __('You already have an active download.'),
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

    public function destroy(Torrent $torrent, QBittorrentClient $client): RedirectResponse
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
