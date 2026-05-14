<?php

namespace App\Jobs;

use App\Enums\TorrentStatus;
use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Models\Torrent;
use App\Services\Torrents\TorrentEngineClient;
use App\Support\VideoFiles;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ImportCompletedTorrent implements ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    /**
     * Create a new job instance.
     */
    public function __construct(public Torrent $torrent) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("torrent-import:{$this->torrent->getKey()}"))
                ->releaseAfter(10)
                ->expireAfter(600),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 10, 15, 30, 60, 120, 180];
    }

    /**
     * Execute the job.
     */
    public function handle(TorrentEngineClient $client): void
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

                $storedFiles[] = [
                    'torrent_file' => $torrentFile,
                    's3_disk' => 'local',
                    's3_bucket' => null,
                    's3_key' => $sourcePath,
                    'mime_type' => $this->mimeType($sourcePath),
                ];
            }

            $createdFiles = collect();

            DB::transaction(function () use ($torrent, $storedFiles, $createdFiles): void {
                foreach ($storedFiles as $storedFile) {
                    $torrentFile = $storedFile['torrent_file'];

                    $file = StoredFile::firstOrNew([
                        's3_key' => $storedFile['s3_key'],
                    ]);

                    $wasRecentlyCreated = ! $file->exists;

                    $file->fill([
                        'user_id' => $torrent->user_id,
                        'torrent_id' => $torrent->id,
                        's3_disk' => $storedFile['s3_disk'],
                        's3_bucket' => $storedFile['s3_bucket'],
                        'original_path' => $torrentFile->path,
                        'name' => basename($torrentFile->path),
                        'mime_type' => $storedFile['mime_type'],
                        'size_bytes' => $torrentFile->size_bytes,
                    ])->save();

                    if ($wasRecentlyCreated) {
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

                        $createdFiles->push($file);
                    }
                }

                $torrent->forceFill([
                    'status' => TorrentStatus::Completed,
                    'progress' => 100,
                    'error_message' => null,
                    'completed_at' => now(),
                ])->save();
            });

            $createdFiles
                ->filter(fn (StoredFile $file): bool => $this->shouldGenerateAdaptiveStream($file))
                ->each(fn (StoredFile $file): mixed => GenerateAdaptiveStream::dispatch($file));

            if (! (bool) config('torrents.rqbit.keep_after_import', true) && filled($torrent->qbittorrent_hash)) {
                $client->delete($torrent->qbittorrent_hash, deleteFiles: false);
            }
        } catch (FileNotFoundException $throwable) {
            if ($this->job !== null && $this->attempts() < $this->tries) {
                throw $throwable;
            }

            $torrent->forceFill([
                'status' => TorrentStatus::ImportFailed,
                'error_message' => $throwable->getMessage(),
            ])->save();
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

    private function mimeType(string $sourcePath): string
    {
        $localPath = Storage::disk('local')->path($sourcePath);

        if (! is_file($localPath)) {
            throw new RuntimeException("Unable to read completed file [{$sourcePath}].");
        }

        $mimeType = mime_content_type($localPath);

        return is_string($mimeType) && $mimeType !== '' ? $mimeType : 'application/octet-stream';
    }

    private function shouldGenerateAdaptiveStream(StoredFile $storedFile): bool
    {
        return (bool) config('media.adaptive.enabled', true)
            && ! app()->environment('testing')
            && VideoFiles::isVideo($storedFile);
    }
}
