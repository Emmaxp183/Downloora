<?php

namespace App\Services\Downloads;

class DownloadResourceProfile
{
    /**
     * @return array{concurrent_fragments: int, segment_connections: int, segment_split: int, segment_min_split_size: string, segment_piece_length: string, segment_disk_cache: string}
     */
    public function ytDlp(): array
    {
        $configuredFragments = max(1, (int) config('media.yt_dlp.concurrent_fragments', 32));
        $configuredConnections = max(1, (int) config('media.yt_dlp.segment_connections', 32));
        $configuredSplit = max(1, (int) config('media.yt_dlp.segment_split', $configuredConnections));
        $configuredDiskCache = (string) config('media.yt_dlp.segment_disk_cache', '64M');

        if (! (bool) config('media.yt_dlp.adaptive_segments', true)) {
            return [
                'concurrent_fragments' => $configuredFragments,
                'segment_connections' => $configuredConnections,
                'segment_split' => $configuredSplit,
                'segment_min_split_size' => (string) config('media.yt_dlp.segment_min_split_size', '1M'),
                'segment_piece_length' => (string) config('media.yt_dlp.segment_piece_length', '1M'),
                'segment_disk_cache' => $configuredDiskCache,
            ];
        }

        $targetSegments = $this->targetSegments();
        $maxConnections = max($configuredConnections, (int) config('media.yt_dlp.max_segment_connections', 64));
        $maxSplit = max($configuredSplit, (int) config('media.yt_dlp.max_segment_split', $maxConnections));
        $maxFragments = max($configuredFragments, (int) config('media.yt_dlp.max_concurrent_fragments', $maxConnections));

        return [
            'concurrent_fragments' => min($maxFragments, max($configuredFragments, $targetSegments)),
            'segment_connections' => min($maxConnections, max($configuredConnections, $targetSegments)),
            'segment_split' => min($maxSplit, max($configuredSplit, $targetSegments)),
            'segment_min_split_size' => (string) config('media.yt_dlp.segment_min_split_size', '1M'),
            'segment_piece_length' => (string) config('media.yt_dlp.segment_piece_length', '1M'),
            'segment_disk_cache' => $this->diskCache($configuredDiskCache),
        ];
    }

    private function targetSegments(): int
    {
        $cores = $this->cpuCores();
        $memoryBytes = $this->memoryBytes();
        $targetBandwidthMbps = max(1, (int) config('media.yt_dlp.target_bandwidth_mbps', 10000));
        $bandwidthSegments = max(32, (int) ceil($targetBandwidthMbps / 10));

        if ($cores >= 32 && $memoryBytes >= 32 * 1024 * 1024 * 1024) {
            return max(192, $bandwidthSegments);
        }

        if ($cores >= 16 && $memoryBytes >= 16 * 1024 * 1024 * 1024) {
            return max(128, $bandwidthSegments);
        }

        if ($cores >= 8 && $memoryBytes >= 8 * 1024 * 1024 * 1024) {
            return max(96, min(192, $bandwidthSegments));
        }

        if ($cores >= 4 && $memoryBytes >= 4 * 1024 * 1024 * 1024) {
            return max(64, min(128, $bandwidthSegments));
        }

        return 32;
    }

    private function diskCache(string $configuredDiskCache): string
    {
        $memoryBytes = $this->memoryBytes();

        if ($memoryBytes >= 32 * 1024 * 1024 * 1024) {
            return '1024M';
        }

        if ($memoryBytes >= 16 * 1024 * 1024 * 1024) {
            return '768M';
        }

        if ($memoryBytes >= 8 * 1024 * 1024 * 1024) {
            return '512M';
        }

        if ($memoryBytes >= 4 * 1024 * 1024 * 1024) {
            return '256M';
        }

        return $configuredDiskCache;
    }

    private function cpuCores(): int
    {
        $stat = $this->read('/proc/stat');

        if ($stat === null) {
            return 1;
        }

        preg_match_all('/^cpu\d+\s/m', $stat, $matches);

        return max(1, count($matches[0]));
    }

    private function memoryBytes(): int
    {
        $meminfo = $this->read('/proc/meminfo');

        if ($meminfo === null || preg_match('/^MemTotal:\s+(\d+)\s+kB$/m', $meminfo, $matches) !== 1) {
            return 1024 * 1024 * 1024;
        }

        return (int) $matches[1] * 1024;
    }

    protected function read(string $path): ?string
    {
        if (! is_readable($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        return is_string($contents) ? $contents : null;
    }
}
