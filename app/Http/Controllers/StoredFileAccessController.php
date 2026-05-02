<?php

namespace App\Http\Controllers;

use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoredFileAccessController extends Controller
{
    public function download(StoredFile $storedFile): StreamedResponse
    {
        Gate::authorize('view', $storedFile);

        return response()->streamDownload(fn (): bool => $this->streamObject($storedFile), $storedFile->name, [
            'Content-Length' => (string) $storedFile->size_bytes,
            'Content-Type' => $storedFile->mime_type ?? 'application/octet-stream',
        ]);
    }

    public function stream(StoredFile $storedFile): StreamedResponse
    {
        Gate::authorize('view', $storedFile);

        return response()->stream(fn (): bool => $this->streamObject($storedFile), 200, [
            'Content-Length' => (string) $storedFile->size_bytes,
            'Content-Type' => $storedFile->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$storedFile->name.'"',
        ]);
    }

    public function destroy(StoredFile $storedFile): RedirectResponse
    {
        Gate::authorize('delete', $storedFile);

        $storedFile->s3Disk()->delete($storedFile->s3_key);

        DB::transaction(function () use ($storedFile): void {
            StorageUsageEvent::create([
                'user_id' => $storedFile->user_id,
                'stored_file_id' => $storedFile->id,
                'delta_bytes' => -$storedFile->size_bytes,
                'reason' => 'file_deleted',
                'metadata' => [
                    'path' => $storedFile->original_path,
                    's3_key' => $storedFile->s3_key,
                ],
            ]);

            $storedFile->delete();
        });

        return back();
    }

    private function streamObject(StoredFile $storedFile): bool
    {
        $stream = $storedFile->s3Disk()->readStream($storedFile->s3_key);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to read stored file [{$storedFile->id}].");
        }

        try {
            while (! feof($stream)) {
                echo fread($stream, 1024 * 1024);

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            }
        } finally {
            fclose($stream);
        }

        return true;
    }
}
