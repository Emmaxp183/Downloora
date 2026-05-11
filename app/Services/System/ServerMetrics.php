<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Cache;

class ServerMetrics
{
    /**
     * @return array{
     *     cpu: array{usage_percent: int|null, cores: int},
     *     memory: array{used_bytes: int|null, total_bytes: int|null, usage_percent: int|null},
     *     network: array{received_bytes_per_second: int, transmitted_bytes_per_second: int, total_bytes_per_second: int},
     *     sampled_at: string
     * }
     */
    public function snapshot(): array
    {
        return [
            'cpu' => $this->cpu(),
            'memory' => $this->memory(),
            'network' => $this->network(),
            'sampled_at' => now()->toISOString(),
        ];
    }

    /**
     * @return array{usage_percent: int|null, cores: int}
     */
    private function cpu(): array
    {
        $stat = $this->read('/proc/stat');

        if ($stat === null || preg_match('/^cpu\s+(.+)$/m', $stat, $matches) !== 1) {
            return ['usage_percent' => null, 'cores' => 1];
        }

        $values = array_map('intval', preg_split('/\s+/', trim($matches[1])) ?: []);
        $idle = ($values[3] ?? 0) + ($values[4] ?? 0);
        $total = array_sum($values);
        $previous = Cache::get('server-metrics:cpu');

        Cache::put('server-metrics:cpu', [
            'idle' => $idle,
            'total' => $total,
        ], now()->addMinutes(5));

        preg_match_all('/^cpu\d+\s/m', $stat, $coreMatches);

        if (! is_array($previous)) {
            return [
                'usage_percent' => null,
                'cores' => max(1, count($coreMatches[0])),
            ];
        }

        $idleDelta = $idle - (int) ($previous['idle'] ?? 0);
        $totalDelta = $total - (int) ($previous['total'] ?? 0);

        return [
            'usage_percent' => $totalDelta > 0
                ? max(0, min(100, (int) round((1 - ($idleDelta / $totalDelta)) * 100)))
                : null,
            'cores' => max(1, count($coreMatches[0])),
        ];
    }

    /**
     * @return array{used_bytes: int|null, total_bytes: int|null, usage_percent: int|null}
     */
    private function memory(): array
    {
        $meminfo = $this->read('/proc/meminfo');

        if ($meminfo === null) {
            return ['used_bytes' => null, 'total_bytes' => null, 'usage_percent' => null];
        }

        $values = [];

        foreach (preg_split('/\r?\n/', $meminfo) ?: [] as $line) {
            if (preg_match('/^(MemTotal|MemAvailable):\s+(\d+)\s+kB$/', $line, $matches) === 1) {
                $values[$matches[1]] = (int) $matches[2] * 1024;
            }
        }

        $total = $values['MemTotal'] ?? null;
        $available = $values['MemAvailable'] ?? null;

        if ($total === null || $available === null || $total <= 0) {
            return ['used_bytes' => null, 'total_bytes' => $total, 'usage_percent' => null];
        }

        $used = max(0, $total - $available);

        return [
            'used_bytes' => $used,
            'total_bytes' => $total,
            'usage_percent' => max(0, min(100, (int) round(($used / $total) * 100))),
        ];
    }

    /**
     * @return array{received_bytes_per_second: int, transmitted_bytes_per_second: int, total_bytes_per_second: int}
     */
    private function network(): array
    {
        $totals = $this->networkTotals();
        $now = microtime(true);
        $previous = Cache::get('server-metrics:network');

        Cache::put('server-metrics:network', [
            'received_bytes' => $totals['received_bytes'],
            'transmitted_bytes' => $totals['transmitted_bytes'],
            'sampled_at' => $now,
        ], now()->addMinutes(5));

        if (! is_array($previous)) {
            return [
                'received_bytes_per_second' => 0,
                'transmitted_bytes_per_second' => 0,
                'total_bytes_per_second' => 0,
            ];
        }

        $elapsedSeconds = $now - (float) ($previous['sampled_at'] ?? 0);

        if ($elapsedSeconds <= 0) {
            return [
                'received_bytes_per_second' => 0,
                'transmitted_bytes_per_second' => 0,
                'total_bytes_per_second' => 0,
            ];
        }

        $received = max(0, (int) round(($totals['received_bytes'] - (int) ($previous['received_bytes'] ?? 0)) / $elapsedSeconds));
        $transmitted = max(0, (int) round(($totals['transmitted_bytes'] - (int) ($previous['transmitted_bytes'] ?? 0)) / $elapsedSeconds));

        return [
            'received_bytes_per_second' => $received,
            'transmitted_bytes_per_second' => $transmitted,
            'total_bytes_per_second' => $received + $transmitted,
        ];
    }

    /**
     * @return array{received_bytes: int, transmitted_bytes: int}
     */
    private function networkTotals(): array
    {
        $dev = $this->read('/proc/net/dev');

        if ($dev === null) {
            return ['received_bytes' => 0, 'transmitted_bytes' => 0];
        }

        $received = 0;
        $transmitted = 0;

        foreach (preg_split('/\r?\n/', $dev) ?: [] as $line) {
            if (preg_match('/^\s*([^:]+):\s*(.+)$/', $line, $matches) !== 1) {
                continue;
            }

            $interface = trim($matches[1]);

            if ($interface === 'lo') {
                continue;
            }

            $columns = array_values(array_filter(preg_split('/\s+/', trim($matches[2])) ?: [], fn (string $value): bool => $value !== ''));

            $received += (int) ($columns[0] ?? 0);
            $transmitted += (int) ($columns[8] ?? 0);
        }

        return [
            'received_bytes' => $received,
            'transmitted_bytes' => $transmitted,
        ];
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
