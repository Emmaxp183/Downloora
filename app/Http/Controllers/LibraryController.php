<?php

namespace App\Http\Controllers;

use App\Services\Storage\StorageQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    public function index(Request $request, StorageQuota $storageQuota): Response
    {
        $user = $request->user();

        $files = $request->user()
            ->storedFiles()
            ->latest()
            ->get()
            ->map(fn ($file): array => [
                'id' => $file->id,
                'name' => $file->name,
                'original_path' => $file->original_path,
                'mime_type' => $file->mime_type,
                'size_bytes' => $file->size_bytes,
                'download_url' => URL::signedRoute('files.download', $file),
                'stream_url' => URL::signedRoute('files.stream', $file),
                'updated_at' => $file->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('Library/Index', [
            'quota' => [
                'used_bytes' => $storageQuota->usedBytes($user),
                'quota_bytes' => $user->storage_quota_bytes,
                'remaining_bytes' => $storageQuota->remainingBytes($user),
            ],
            'files' => $files,
        ]);
    }
}
