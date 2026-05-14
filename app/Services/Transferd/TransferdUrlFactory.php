<?php

namespace App\Services\Transferd;

use App\Models\StoredFile;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TransferdUrlFactory
{
    public function enabledFor(StoredFile $storedFile): bool
    {
        return $this->enabledForDisk($storedFile->s3_disk)
            && filled($storedFile->s3_key);
    }

    public function enabledForDisk(?string $diskName): bool
    {
        return (bool) config('transferd.enabled', false)
            && in_array($diskName, ['local', 's3'], true)
            && filled($this->signingKey())
            && filled((string) config('transferd.public_url'));
    }

    public function url(Request $request, StoredFile $storedFile, string $disposition): string
    {
        return $this->objectUrl(
            request: $request,
            backend: $storedFile->s3_disk,
            bucket: $storedFile->s3_bucket ?: config('filesystems.disks.s3.bucket'),
            key: $storedFile->s3_key,
            name: $storedFile->name,
            mimeType: $storedFile->mime_type ?: 'application/octet-stream',
            sizeBytes: max(0, $storedFile->size_bytes),
            disposition: $disposition,
        );
    }

    public function objectUrl(
        Request $request,
        string $backend,
        ?string $bucket,
        string $key,
        string $name,
        string $mimeType,
        int $sizeBytes,
        string $disposition,
        ?string $cacheControl = null,
        bool $cors = false,
    ): string
    {
        $payload = $this->encode([
            'backend' => $backend,
            'bucket' => $bucket,
            'key' => $key,
            'name' => $name,
            'mime_type' => $mimeType,
            'size_bytes' => max(0, $sizeBytes),
            'disposition' => $disposition,
            'cache_control' => $cacheControl,
            'cors' => $cors,
            'expires_at' => Carbon::now()->addSeconds((int) config('transferd.url_ttl_seconds', 300))->unix(),
        ]);

        $signature = $this->encode(hash_hmac('sha256', $payload, $this->signingKey(), true));
        $baseUrl = rtrim((string) config('transferd.public_url'), '/');
        $url = Str::startsWith($baseUrl, ['http://', 'https://'])
            ? $baseUrl
            : $request->getSchemeAndHttpHost().'/'.ltrim($baseUrl, '/');

        return $url.'/files?token='.$payload.'.'.$signature;
    }

    /**
     * @param  array<string, mixed>|string  $value
     */
    private function encode(array|string $value): string
    {
        $contents = is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value;

        return rtrim(strtr(base64_encode($contents), '+/', '-_'), '=');
    }

    private function signingKey(): string
    {
        return (string) config('transferd.signing_key', '');
    }
}
