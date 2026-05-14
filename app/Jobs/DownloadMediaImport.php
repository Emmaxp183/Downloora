<?php

namespace App\Jobs;

use App\Enums\MediaImportStatus;
use App\Models\MediaImport;
use App\Models\StorageUsageEvent;
use App\Models\StoredFile;
use App\Services\Media\YtDlpClient;
use App\Services\Storage\ObjectStorageUploader;
use App\Services\Storage\StorageQuota;
use App\Support\VideoFiles;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DownloadMediaImport implements ShouldQueue
{
    use Queueable;

    public function __construct(public MediaImport $mediaImport) {}

    public function handle(YtDlpClient $client, StorageQuota $quota, ObjectStorageUploader $uploader): void
    {
        $mediaImport = $this->mediaImport->fresh(['user']);

        if (! $mediaImport instanceof MediaImport || $mediaImport->status !== MediaImportStatus::Queued) {
            return;
        }

        $directory = storage_path('app/media-imports/'.$mediaImport->id);

        try {
            $selectedFormat = $mediaImport->selected_format;
            $formatSelector = $selectedFormat['selector'] ?? null;

            if (! is_string($formatSelector) || $formatSelector === '') {
                throw new RuntimeException('Select a media format before downloading.');
            }

            $mediaImport->forceFill([
                'status' => MediaImportStatus::Downloading,
                'progress' => 0,
                'started_at' => now(),
                'error_message' => null,
            ])->save();

            $localPath = $client->download(
                $mediaImport->source_url,
                $formatSelector,
                $directory,
                function (int $progress, int $downloadedBytes) use ($mediaImport): void {
                    $mediaImport->refresh();

                    if ($mediaImport->status === MediaImportStatus::Cancelled) {
                        throw new RuntimeException('Media download was cancelled.');
                    }

                    $mediaImport->forceFill([
                        'progress' => min(99, $progress),
                        'downloaded_bytes' => max($mediaImport->downloaded_bytes, $downloadedBytes),
                    ])->save();
                },
            );

            $mediaImport->refresh();

            if ($mediaImport->status === MediaImportStatus::Cancelled) {
                @unlink($localPath);

                return;
            }

            $sizeBytes = filesize($localPath);

            if ($sizeBytes === false) {
                throw new RuntimeException('Unable to read downloaded media size.');
            }

            if (! $quota->canStore($mediaImport->user, (int) $sizeBytes)) {
                @unlink($localPath);

                $mediaImport->forceFill([
                    'status' => MediaImportStatus::QuotaExceeded,
                    'error_message' => 'This media file exceeds your remaining storage quota.',
                ])->save();

                return;
            }

            $mediaImport->forceFill([
                'status' => MediaImportStatus::Importing,
                'progress' => 99,
                'downloaded_bytes' => (int) $sizeBytes,
                'local_file_path' => $localPath,
            ])->save();

            $s3Key = $this->s3Key($mediaImport, $localPath);
            $mimeType = $uploader->uploadFile($s3Key, $localPath);

            $storedFile = null;

            DB::transaction(function () use ($mediaImport, $s3Key, $localPath, $sizeBytes, $mimeType, &$storedFile): void {
                $storedFile = StoredFile::create([
                    'user_id' => $mediaImport->user_id,
                    'torrent_id' => null,
                    'media_import_id' => $mediaImport->id,
                    's3_disk' => 's3',
                    's3_bucket' => config('filesystems.disks.s3.bucket'),
                    's3_key' => $s3Key,
                    'original_path' => 'Media/'.$this->folderName($mediaImport).'/'.$this->safeFilename($localPath),
                    'name' => $this->safeFilename($localPath),
                    'mime_type' => $mimeType,
                    'size_bytes' => (int) $sizeBytes,
                ]);

                StorageUsageEvent::create([
                    'user_id' => $mediaImport->user_id,
                    'stored_file_id' => $storedFile->id,
                    'delta_bytes' => (int) $sizeBytes,
                    'reason' => 'media_imported',
                    'metadata' => [
                        'media_import_id' => $mediaImport->id,
                        'source_url' => $mediaImport->source_url,
                        'format' => $mediaImport->selected_format,
                    ],
                ]);

                $mediaImport->forceFill([
                    'stored_file_id' => $storedFile->id,
                    'status' => MediaImportStatus::Completed,
                    'progress' => 100,
                    'completed_at' => now(),
                    'error_message' => null,
                ])->save();
            });

            if ($storedFile instanceof StoredFile && $this->shouldGenerateAdaptiveStream($storedFile)) {
                GenerateAdaptiveStream::dispatch($storedFile);
            }

            @unlink($localPath);
        } catch (Throwable $throwable) {
            if ($mediaImport->fresh()?->status === MediaImportStatus::Cancelled) {
                $this->cleanupDirectory($directory);

                return;
            }

            $mediaImport->forceFill([
                'status' => $mediaImport->status === MediaImportStatus::Importing
                    ? MediaImportStatus::ImportFailed
                    : MediaImportStatus::DownloadFailed,
                'error_message' => $throwable->getMessage(),
            ])->save();
        }
    }

    private function s3Key(MediaImport $mediaImport, string $localPath): string
    {
        return "users/{$mediaImport->user_id}/media/{$mediaImport->id}/{$this->folderName($mediaImport)}/".$this->safeFilename($localPath);
    }

    private function safeFilename(string $path): string
    {
        $name = pathinfo($path, PATHINFO_FILENAME);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $filename = Str::of($name)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->whenEmpty(fn (): string => 'media-download')
            ->toString();

        if ($extension === '') {
            return $filename;
        }

        $extension = Str::of($extension)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9]+/', '')
            ->lower()
            ->toString();

        return $extension === '' ? $filename : "{$filename}.{$extension}";
    }

    private function folderName(MediaImport $mediaImport): string
    {
        return Str::of($mediaImport->title ?: 'media-download')
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->whenEmpty(fn (): string => 'media-download')
            ->toString();
    }

    private function cleanupDirectory(string $directory): void
    {
        foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function shouldGenerateAdaptiveStream(StoredFile $storedFile): bool
    {
        return (bool) config('media.adaptive.enabled', true)
            && ! app()->environment('testing')
            && VideoFiles::isVideo($storedFile);
    }
}
