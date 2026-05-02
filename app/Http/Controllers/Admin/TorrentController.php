<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\TorrentStatus;
use App\Models\Torrent;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TorrentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Torrents/Index', [
            'torrents' => Torrent::query()
                ->with('user:id,name,email')
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (Torrent $torrent): array => [
                    'id' => $torrent->id,
                    'name' => $torrent->name,
                    'status' => $torrent->status->value,
                    'progress' => $torrent->progress,
                    'total_size_bytes' => $torrent->total_size_bytes,
                    'error_message' => $torrent->error_message,
                    'user' => [
                        'id' => $torrent->user->id,
                        'name' => $torrent->user->name,
                        'email' => $torrent->user->email,
                    ],
                ]),
        ]);
    }

    public function destroy(Torrent $torrent): RedirectResponse
    {
        $torrent->forceFill([
            'status' => TorrentStatus::Cancelled,
            'error_message' => null,
        ])->save();

        return to_route('admin.torrents.index');
    }
}
