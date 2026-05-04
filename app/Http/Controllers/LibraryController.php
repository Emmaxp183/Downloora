<?php

namespace App\Http\Controllers;

use App\Services\Storage\StorageQuota;
use App\Services\Storage\StoredFileFolderPayloads;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    public function index(
        Request $request,
        StorageQuota $storageQuota,
        StoredFileFolderPayloads $storedFileFolderPayloads,
    ): Response {
        $user = $request->user();

        $files = $request->user()
            ->storedFiles()
            ->with(['torrent', 'mediaImport'])
            ->latest()
            ->get();

        return Inertia::render('Library/Index', [
            'quota' => [
                'used_bytes' => $storageQuota->usedBytes($user),
                'quota_bytes' => $user->storage_quota_bytes,
                'remaining_bytes' => $storageQuota->remainingBytes($user),
            ],
            'fileFolders' => $storedFileFolderPayloads->fromFiles($files),
        ]);
    }
}
