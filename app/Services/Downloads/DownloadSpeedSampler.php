<?php

namespace App\Services\Downloads;

use Illuminate\Support\Facades\Cache;

class DownloadSpeedSampler
{
    public function sample(string $key, int $downloadedBytes): int
    {
        $cacheKey = 'download-speed:'.hash('sha256', $key);
        $now = microtime(true);
        $previous = Cache::get($cacheKey);

        Cache::put($cacheKey, [
            'downloaded_bytes' => $downloadedBytes,
            'sampled_at' => $now,
        ], now()->addMinutes(5));

        if (! is_array($previous)) {
            return 0;
        }

        $previousBytes = (int) ($previous['downloaded_bytes'] ?? 0);
        $previousSampledAt = (float) ($previous['sampled_at'] ?? 0);
        $elapsedSeconds = $now - $previousSampledAt;

        if ($elapsedSeconds <= 0 || $downloadedBytes < $previousBytes) {
            return 0;
        }

        return (int) round(($downloadedBytes - $previousBytes) / $elapsedSeconds);
    }
}
