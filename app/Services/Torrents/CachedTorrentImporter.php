<?php

namespace App\Services\Torrents;

use App\Enums\TorrentStatus;
use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Models\Torrent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CachedTorrentImporter
{
    public function importIfAvailable(Torrent $torrent): bool
    {
        $torrent = $torrent->loadMissing(['files', 'user']);
        $sourceTorrent = $this->sourceTorrent($torrent);

        if (! $sourceTorrent instanceof Torrent) {
            return false;
        }

        $sourceFiles = $sourceTorrent->storedFiles;

        if (! $this->matchesMetadata($torrent, $sourceFiles)) {
            return false;
        }

        $copiedFiles = [];

        foreach ($sourceFiles as $sourceFile) {
            $targetKey = $this->s3Key($torrent, $sourceFile->original_path);
            $copied = Storage::disk($sourceFile->s3_disk)->copy($sourceFile->s3_key, $targetKey);

            if (! $copied) {
                return false;
            }

            $copiedFiles[] = [
                'source' => $sourceFile,
                's3_key' => $targetKey,
            ];
        }

        DB::transaction(function () use ($torrent, $copiedFiles): void {
            foreach ($copiedFiles as $copiedFile) {
                /** @var StoredFile $sourceFile */
                $sourceFile = $copiedFile['source'];

                $storedFile = StoredFile::create([
                    'user_id' => $torrent->user_id,
                    'torrent_id' => $torrent->id,
                    's3_disk' => $sourceFile->s3_disk,
                    's3_bucket' => $sourceFile->s3_bucket,
                    's3_key' => $copiedFile['s3_key'],
                    'original_path' => $sourceFile->original_path,
                    'name' => $sourceFile->name,
                    'mime_type' => $sourceFile->mime_type,
                    'size_bytes' => $sourceFile->size_bytes,
                ]);

                StorageUsageEvent::create([
                    'user_id' => $torrent->user_id,
                    'stored_file_id' => $storedFile->id,
                    'delta_bytes' => $sourceFile->size_bytes,
                    'reason' => 'torrent_cache_imported',
                    'metadata' => [
                        'torrent_id' => $torrent->id,
                        'source_torrent_id' => $sourceFile->torrent_id,
                        'path' => $sourceFile->original_path,
                    ],
                ]);
            }

            $torrent->forceFill([
                'status' => TorrentStatus::Completed,
                'progress' => 100,
                'downloaded_bytes' => $torrent->total_size_bytes ?? $torrent->files->sum('size_bytes'),
                'error_message' => null,
                'completed_at' => now(),
            ])->save();
        });

        return true;
    }

    private function sourceTorrent(Torrent $torrent): ?Torrent
    {
        if (blank($torrent->info_hash)) {
            return null;
        }

        return Torrent::query()
            ->whereKeyNot($torrent->id)
            ->where('info_hash', $torrent->info_hash)
            ->where('status', TorrentStatus::Completed)
            ->where('total_size_bytes', $torrent->total_size_bytes)
            ->whereHas('storedFiles')
            ->with('storedFiles')
            ->latest('completed_at')
            ->first();
    }

    /**
     * @param  Collection<int, StoredFile>  $sourceFiles
     */
    private function matchesMetadata(Torrent $torrent, Collection $sourceFiles): bool
    {
        $expected = $torrent->files
            ->mapWithKeys(fn ($file): array => [$file->path => (int) $file->size_bytes])
            ->sortKeys();

        $available = $sourceFiles
            ->mapWithKeys(fn (StoredFile $file): array => [$file->original_path => (int) $file->size_bytes])
            ->sortKeys();

        return $expected->isNotEmpty() && $expected->all() === $available->all();
    }

    private function s3Key(Torrent $torrent, string $path): string
    {
        $cleanPath = collect(explode('/', $path))
            ->map(fn (string $segment): string => Str::of($segment)->replace(['..', '\\'], '')->trim('/')->toString())
            ->filter()
            ->implode('/');

        return "users/{$torrent->user_id}/torrents/{$torrent->id}/{$cleanPath}";
    }
}
