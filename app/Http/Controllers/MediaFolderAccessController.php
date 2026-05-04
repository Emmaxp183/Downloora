<?php

namespace App\Http\Controllers;

use App\Models\MediaImport;
use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class MediaFolderAccessController extends Controller
{
    public function download(Request $request, MediaImport $mediaImport): StreamedResponse
    {
        $files = $this->ownedFolderFiles($request, $mediaImport);
        $zipPath = $this->buildZip($mediaImport, $files);

        return response()->streamDownload(function () use ($zipPath): void {
            $stream = fopen($zipPath, 'rb');

            if (! is_resource($stream)) {
                throw new RuntimeException('Unable to read media folder zip.');
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
                @unlink($zipPath);
            }
        }, $this->zipFilename($mediaImport), [
            'Content-Type' => 'application/zip',
            'Content-Length' => (string) filesize($zipPath),
        ]);
    }

    public function destroy(Request $request, MediaImport $mediaImport): RedirectResponse
    {
        $files = $this->ownedFolderFiles($request, $mediaImport);
        $deletedBytes = (int) $files->sum('size_bytes');

        foreach ($files as $file) {
            $file->s3Disk()->delete($file->s3_key);
        }

        DB::transaction(function () use ($request, $mediaImport, $files, $deletedBytes): void {
            StorageUsageEvent::create([
                'user_id' => $request->user()->id,
                'stored_file_id' => null,
                'delta_bytes' => -$deletedBytes,
                'reason' => 'folder_deleted',
                'metadata' => [
                    'media_import_id' => $mediaImport->id,
                    'name' => $mediaImport->title,
                    'file_ids' => $files->pluck('id')->values()->all(),
                ],
            ]);

            StoredFile::query()
                ->whereKey($files->modelKeys())
                ->delete();
        });

        return back();
    }

    /**
     * @return Collection<int, StoredFile>
     */
    private function ownedFolderFiles(Request $request, MediaImport $mediaImport): Collection
    {
        if (! $mediaImport->user()->is($request->user())) {
            abort(403);
        }

        $files = $request->user()
            ->storedFiles()
            ->where('media_import_id', $mediaImport->id)
            ->oldest('original_path')
            ->get();

        if ($files->isEmpty()) {
            abort(404);
        }

        return $files;
    }

    /**
     * @param  Collection<int, StoredFile>  $files
     */
    private function buildZip(MediaImport $mediaImport, Collection $files): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'seedr-media-folder-');
        $sourceDirectory = sys_get_temp_dir().'/seedr-media-folder-sources-'.Str::uuid();

        if ($zipPath === false || ! mkdir($sourceDirectory, 0700, true)) {
            throw new RuntimeException('Unable to create media folder zip.');
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to open media folder zip.');
        }

        try {
            foreach ($files as $file) {
                $zipEntryName = $this->zipEntryName($mediaImport, $file);
                $localPath = $sourceDirectory.'/'.hash('sha256', $zipEntryName);
                $sourceStream = $file->s3Disk()->readStream($file->s3_key);

                if (! is_resource($sourceStream)) {
                    throw new RuntimeException("Unable to read stored file [{$file->id}].");
                }

                $targetStream = fopen($localPath, 'wb');

                if (! is_resource($targetStream)) {
                    fclose($sourceStream);

                    throw new RuntimeException("Unable to stage stored file [{$file->id}].");
                }

                try {
                    stream_copy_to_stream($sourceStream, $targetStream);
                } finally {
                    fclose($sourceStream);
                    fclose($targetStream);
                }

                $zip->addFile($localPath, $zipEntryName);
            }

            $zip->close();
        } catch (RuntimeException $exception) {
            $zip->close();
            @unlink($zipPath);

            throw $exception;
        } finally {
            $this->deleteDirectory($sourceDirectory);
        }

        return $zipPath;
    }

    private function zipEntryName(MediaImport $mediaImport, StoredFile $file): string
    {
        return $this->folderName($mediaImport).'/'.$file->name;
    }

    private function zipFilename(MediaImport $mediaImport): string
    {
        return $this->folderName($mediaImport).'.zip';
    }

    private function folderName(MediaImport $mediaImport): string
    {
        return Str::of($mediaImport->title ?: 'media-folder')
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->trim('-')
            ->whenEmpty(fn (): string => 'media-folder')
            ->toString();
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/*') ?: [] as $path) {
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        @rmdir($directory);
    }
}
