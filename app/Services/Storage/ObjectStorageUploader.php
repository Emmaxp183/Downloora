<?php

namespace App\Services\Storage;

use Aws\S3\MultipartUploader;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ObjectStorageUploader
{
    public function uploadFile(string $key, string $localPath): string
    {
        if (! is_file($localPath)) {
            throw new RuntimeException("Unable to read local file [{$localPath}].");
        }

        $mimeType = $this->mimeType($localPath);

        if ($this->shouldUseFilesystemDisk()) {
            $stream = fopen($localPath, 'rb');

            if (! is_resource($stream)) {
                throw new RuntimeException("Unable to open local file [{$localPath}].");
            }

            try {
                $stored = Storage::disk('s3')->put($key, $stream);
            } finally {
                fclose($stream);
            }

            if (! $stored) {
                throw new RuntimeException("Unable to store file [{$key}] in object storage.");
            }

            return $mimeType;
        }

        $uploader = new MultipartUploader($this->client(), $localPath, [
            'bucket' => $this->bucket(),
            'key' => $key,
            'concurrency' => $this->concurrency(),
            'part_size' => $this->partSizeBytes(),
            'params' => [
                'ContentType' => $mimeType,
            ],
        ]);

        $uploader->upload();

        return $mimeType;
    }

    private function shouldUseFilesystemDisk(): bool
    {
        return app()->environment('testing')
            || config('filesystems.disks.s3.driver') !== 's3';
    }

    private function client(): S3Client
    {
        return new S3Client(array_filter([
            'version' => 'latest',
            'region' => config('filesystems.disks.s3.region', 'us-east-1'),
            'credentials' => [
                'key' => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
            'endpoint' => config('filesystems.disks.s3.endpoint'),
            'use_path_style_endpoint' => config('filesystems.disks.s3.use_path_style_endpoint'),
        ], fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    private function bucket(): string
    {
        $bucket = config('filesystems.disks.s3.bucket');

        if (! is_string($bucket) || $bucket === '') {
            throw new RuntimeException('Object storage bucket is not configured.');
        }

        return $bucket;
    }

    private function concurrency(): int
    {
        return max(1, (int) config('filesystems.import_upload_concurrency', 8));
    }

    private function partSizeBytes(): int
    {
        return max(5, (int) config('filesystems.import_upload_part_size_mb', 16)) * 1024 * 1024;
    }

    private function mimeType(string $localPath): string
    {
        $mimeType = mime_content_type($localPath);

        return is_string($mimeType) && $mimeType !== '' ? $mimeType : 'application/octet-stream';
    }
}
