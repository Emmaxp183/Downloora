<?php

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('storage:ensure-s3-bucket')]
#[Description('Ensure the configured S3 bucket exists.')]
class EnsureObjectStorageBucket extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (config('filesystems.default') !== 's3') {
            $this->components->info('Default filesystem is not S3. Skipping bucket check.');

            return self::SUCCESS;
        }

        $config = config('filesystems.disks.s3');
        $bucket = $config['bucket'] ?? null;

        if (blank($bucket)) {
            $this->components->error('AWS_BUCKET is not configured.');

            return self::FAILURE;
        }

        try {
            $client = new S3Client([
                'version' => 'latest',
                'region' => $config['region'] ?? 'us-east-1',
                'endpoint' => $config['endpoint'] ?? null,
                'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? false),
                'credentials' => [
                    'key' => $config['key'] ?? '',
                    'secret' => $config['secret'] ?? '',
                ],
            ]);

            if (! $client->doesBucketExist($bucket)) {
                $client->createBucket(['Bucket' => $bucket]);
            }
        } catch (Throwable $throwable) {
            $this->components->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->components->info("S3 bucket [{$bucket}] is ready.");

        return self::SUCCESS;
    }
}
