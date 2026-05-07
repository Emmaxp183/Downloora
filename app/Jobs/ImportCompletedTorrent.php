<?php

namespace App\Jobs;

use App\Enums\TorrentStatus;
use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Models\Torrent;
use App\Services\Storage\ObjectStorageUploader;
use App\Services\Torrents\QBittorrentClient;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ImportCompletedTorrent implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Torrent $torrent) {}

    /**
     * Execute the job.
     */
    public function handle(QBittorrentClient $client, ObjectStorageUploader $uploader): void
    {
        $torrent = $this->torrent->fresh(['files', 'user']);

        if (! $torrent instanceof Torrent || $torrent->status !== TorrentStatus::Importing) {
            return;
        }

        try {
            $storedFiles = [];

            foreach ($torrent->files as $torrentFile) {
                $sourcePath = $this->sourcePath($torrent, $torrentFile->path);

                if (! Storage::disk('local')->exists($sourcePath)) {
                    throw new FileNotFoundException("Missing completed file [{$torrentFile->path}].");
                }

                $s3Key = $this->s3Key($torrent, $torrentFile->path);

                $storedFiles[] = [
                    'torrent_file' => $torrentFile,
                    's3_key' => $s3Key,
                    'mime_type' => $uploader->uploadFile($s3Key, Storage::disk('local')->path($sourcePath)),
                ];
            }

            DB::transaction(function () use ($torrent, $storedFiles): void {
                foreach ($storedFiles as $storedFile) {
                    $torrentFile = $storedFile['torrent_file'];

                    $file = StoredFile::create([
                        'user_id' => $torrent->user_id,
                        'torrent_id' => $torrent->id,
                        's3_disk' => 's3',
                        's3_bucket' => config('filesystems.disks.s3.bucket'),
                        's3_key' => $storedFile['s3_key'],
                        'original_path' => $torrentFile->path,
                        'name' => basename($torrentFile->path),
                        'mime_type' => $storedFile['mime_type'],
                        'size_bytes' => $torrentFile->size_bytes,
                    ]);

                    StorageUsageEvent::create([
                        'user_id' => $torrent->user_id,
                        'stored_file_id' => $file->id,
                        'delta_bytes' => $torrentFile->size_bytes,
                        'reason' => 'torrent_imported',
                        'metadata' => [
                            'torrent_id' => $torrent->id,
                            'path' => $torrentFile->path,
                        ],
                    ]);
                }

                $torrent->forceFill([
                    'status' => TorrentStatus::Completed,
                    'progress' => 100,
                    'error_message' => null,
                    'completed_at' => now(),
                ])->save();
            });

            if (filled($torrent->qbittorrent_hash)) {
                $client->delete($torrent->qbittorrent_hash);
            }
        } catch (Throwable $throwable) {
            $torrent->forceFill([
                'status' => TorrentStatus::ImportFailed,
                'error_message' => $throwable->getMessage(),
            ])->save();
        }
    }

    private function sourcePath(Torrent $torrent, string $path): string
    {
        return 'qbittorrent/'.$torrent->qbittorrent_hash.'/'.ltrim($path, '/');
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
