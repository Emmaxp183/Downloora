<?php

namespace App\Http\Controllers;

use App\Models\StoredFile;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoredFileAccessController extends Controller
{
    public function download(StoredFile $storedFile): StreamedResponse
    {
        Gate::authorize('view', $storedFile);

        return response()->streamDownload(function () use ($storedFile): void {
            echo $storedFile->s3Disk()->get($storedFile->s3_key);
        }, $storedFile->name, [
            'Content-Type' => $storedFile->mime_type ?? 'application/octet-stream',
        ]);
    }

    public function stream(StoredFile $storedFile): StreamedResponse
    {
        Gate::authorize('view', $storedFile);

        return response()->stream(function () use ($storedFile): void {
            echo $storedFile->s3Disk()->get($storedFile->s3_key);
        }, 200, [
            'Content-Type' => $storedFile->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$storedFile->name.'"',
        ]);
    }
}
