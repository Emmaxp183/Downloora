<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    public function index(Request $request): Response
    {
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
            ]);

        return Inertia::render('Library/Index', [
            'files' => $files,
        ]);
    }
}
