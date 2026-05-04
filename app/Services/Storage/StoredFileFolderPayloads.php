<?php

namespace App\Services\Storage;

use App\Models\StoredFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class StoredFileFolderPayloads
{
    /**
     * Build folder payloads from stored files.
     *
     * @param  Collection<int, StoredFile>  $files
     * @return Collection<int, array{
     *     id: string,
     *     torrent_id: int|null,
     *     media_import_id: int|null,
     *     name: string,
     *     download_url: string|null,
     *     size_bytes: int,
     *     updated_at: string|null,
     *     files: array<int, array<string, mixed>>
     * }>
     */
    public function fromFiles(Collection $files): Collection
    {
        return $files
            ->groupBy(fn (StoredFile $file): string => $this->folderKey($file))
            ->map(fn (Collection $folderFiles): array => $this->folderPayload($folderFiles))
            ->sortByDesc('updated_at')
            ->values();
    }

    private function folderKey(StoredFile $file): string
    {
        if ($file->torrent_id !== null) {
            return 'torrent-'.$file->torrent_id;
        }

        if ($file->media_import_id !== null) {
            return 'media-'.$file->media_import_id;
        }

        return 'file-'.$file->id;
    }

    /**
     * @param  Collection<int, StoredFile>  $files
     * @return array{
     *     id: string,
     *     torrent_id: int|null,
     *     media_import_id: int|null,
     *     name: string,
     *     download_url: string|null,
     *     size_bytes: int,
     *     updated_at: string|null,
     *     files: array<int, array<string, mixed>>
     * }
     */
    private function folderPayload(Collection $files): array
    {
        /** @var StoredFile $firstFile */
        $firstFile = $files->first();
        $updatedAt = $files->max('updated_at');

        return [
            'id' => $this->folderKey($firstFile),
            'torrent_id' => $firstFile->torrent_id,
            'media_import_id' => $firstFile->media_import_id,
            'name' => $firstFile->torrent?->name ?: ($firstFile->mediaImport?->title ?: $this->fallbackFolderName($firstFile)),
            'download_url' => $this->downloadUrl($firstFile),
            'size_bytes' => (int) $files->sum('size_bytes'),
            'updated_at' => $updatedAt?->toIso8601String(),
            'files' => $files
                ->sortBy('original_path', SORT_NATURAL)
                ->map(fn (StoredFile $file): array => $this->filePayload($file))
                ->values()
                ->all(),
        ];
    }

    private function downloadUrl(StoredFile $file): ?string
    {
        if ($file->torrent_id !== null) {
            return URL::signedRoute('folders.download', $file->torrent_id);
        }

        if ($file->media_import_id !== null) {
            return URL::signedRoute('media-folders.download', $file->media_import_id);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function filePayload(StoredFile $file): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'original_path' => $file->original_path,
            'mime_type' => $file->mime_type,
            'size_bytes' => $file->size_bytes,
            'download_url' => URL::signedRoute('files.download', $file),
            'stream_url' => URL::signedRoute('files.stream', $file),
            'updated_at' => $file->updated_at?->toIso8601String(),
        ];
    }

    private function fallbackFolderName(StoredFile $file): string
    {
        $directory = dirname($file->original_path);

        return $directory === '.' ? $file->name : basename($directory);
    }
}
