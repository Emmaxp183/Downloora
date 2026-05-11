<?php

namespace App\Http\Controllers;

use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Models\Torrent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class TorrentFolderAccessController extends Controller
{
    public function download(Request $request, Torrent $torrent): StreamedResponse
    {
        $files = $this->ownedFolderFiles($request, $torrent);
        $zipPath = $this->buildZip($torrent, $files);

        return response()->streamDownload(function () use ($zipPath): void {
            $stream = fopen($zipPath, 'rb');

            if (! is_resource($stream)) {
                throw new RuntimeException('Unable to read folder zip.');
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
        }, $this->zipFilename($torrent), [
            'Content-Type' => 'application/zip',
            'Content-Length' => (string) filesize($zipPath),
        ]);
    }

    public function destroy(Request $request, Torrent $torrent): RedirectResponse
    {
        $files = $this->ownedFolderFiles($request, $torrent);
        $deletedBytes = (int) $files->sum('size_bytes');

        foreach ($files as $file) {
            $file->s3Disk()->delete($file->s3_key);
        }

        if (filled($torrent->torrent_file_path)) {
            Storage::delete($torrent->torrent_file_path);
        }

        DB::transaction(function () use ($request, $torrent, $files, $deletedBytes): void {
            StorageUsageEvent::create([
                'user_id' => $request->user()->id,
                'stored_file_id' => null,
                'delta_bytes' => -$deletedBytes,
                'reason' => 'folder_deleted',
                'metadata' => [
                    'torrent_id' => $torrent->id,
                    'name' => $torrent->name,
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
    private function ownedFolderFiles(Request $request, Torrent $torrent): Collection
    {
        if (! $torrent->user()->is($request->user())) {
            abort(403);
        }

        $files = $request->user()
            ->storedFiles()
            ->where('torrent_id', $torrent->id)
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
    private function buildZip(Torrent $torrent, Collection $files): string
    {
        set_time_limit(0);

        $zipPath = tempnam(sys_get_temp_dir(), 'seedr-folder-');
        $sourceDirectory = sys_get_temp_dir().'/seedr-folder-sources-'.Str::uuid();

        if ($zipPath === false || ! mkdir($sourceDirectory, 0700, true)) {
            throw new RuntimeException('Unable to create folder zip.');
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to open folder zip.');
        }

        try {
            foreach ($files as $file) {
                $zipEntryName = $this->zipEntryName($torrent, $file);
                $localPath = $this->zipSourcePath($file, $sourceDirectory, $zipEntryName);

                if (! $zip->addFile($localPath, $zipEntryName)) {
                    throw new RuntimeException("Unable to add stored file [{$file->id}] to folder zip.");
                }

                $zip->setCompressionName($zipEntryName, ZipArchive::CM_STORE);
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

    private function zipSourcePath(StoredFile $file, string $sourceDirectory, string $zipEntryName): string
    {
        if ($file->s3_disk === 'local') {
            return Storage::disk('local')->path($file->s3_key);
        }

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

        return $localPath;
    }

    private function zipEntryName(Torrent $torrent, StoredFile $file): string
    {
        $path = collect(explode('/', $file->original_path))
            ->map(fn (string $segment): string => Str::of($segment)->replace(['..', '\\'], '')->trim('/')->toString())
            ->filter()
            ->implode('/');

        if ($path === '') {
            $path = $file->name;
        }

        return $path;
    }

    private function zipFilename(Torrent $torrent): string
    {
        return Str::of($torrent->name ?: 'torrent-folder')
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->trim('-')
            ->append('.zip')
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
