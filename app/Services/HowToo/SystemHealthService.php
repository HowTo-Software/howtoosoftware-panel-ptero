<?php

namespace Pterodactyl\Services\HowToo;

use Pterodactyl\Models\Node;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Contracts\Repository\NodeRepositoryInterface;

final class SystemHealthService
{
    private const CPU_SAMPLE_KEY = 'howtoo:system-health:cpu-sample';

    /**
     * Live resource consumption for the machine running the panel.
     */
    public function metrics(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'uptime_seconds' => $this->uptimeSeconds(),
            'cpu' => $this->cpu(),
            'memory' => $this->memory(),
            'storage' => $this->storage(),
        ];
    }

    /**
     * Memory and disk committed to servers on every node, read from the database only
     * so that an unreachable daemon can never stall the admin overview.
     */
    public function nodes(): array
    {
        $usage = DB::table('servers')
            ->selectRaw('node_id, COUNT(*) as servers, COALESCE(SUM(memory), 0) as memory, COALESCE(SUM(disk), 0) as disk')
            ->groupBy('node_id')
            ->get()
            ->keyBy('node_id');

        return Node::query()
            ->orderBy('name')
            ->get()
            ->map(function (Node $node) use ($usage): array {
                $allocated = $usage->get($node->id);

                return [
                    'id' => $node->id,
                    'name' => $node->name,
                    'servers' => (int) ($allocated->servers ?? 0),
                    'maintenance' => (bool) $node->maintenance_mode,
                    'memory' => $this->nodeResource((int) ($allocated->memory ?? 0), $node->memory, $node->memory_overallocate),
                    'disk' => $this->nodeResource((int) ($allocated->disk ?? 0), $node->disk, $node->disk_overallocate),
                ];
            })
            ->all();
    }

    private function nodeResource(int $allocated, int $capacity, int $overallocate): array
    {
        $max = $overallocate > 0 ? $capacity * (1 + ($overallocate / 100)) : $capacity;
        $percent = $this->percentage($allocated, $max);

        return [
            'used_mib' => $allocated,
            'total_mib' => (int) round($max),
            'percent' => $percent,
            'status' => $this->status($percent),
        ];
    }

    private function cpu(): array
    {
        $cores = $this->cores();
        $load = $this->loadAverage();
        $percent = $this->utilisation();

        if ($percent === null && $load !== null) {
            $percent = $this->percentage($load[0], $cores);
        }

        return [
            'percent' => $percent === null ? null : min(100.0, $percent),
            'cores' => $cores,
            'load' => $load,
            'status' => $this->status($percent),
        ];
    }

    /**
     * Percentage of non-idle CPU time since the previous poll. Returns null until a
     * second sample is available, which is why the caller falls back to load average.
     */
    private function utilisation(): ?float
    {
        $stat = $this->read('/proc/stat');
        if ($stat === null || !preg_match('/^cpu\s+(.*)$/m', $stat, $matches)) {
            return null;
        }

        $fields = array_map('intval', preg_split('/\s+/', trim($matches[1])) ?: []);
        if (count($fields) < 5) {
            return null;
        }

        $sample = ['total' => array_sum($fields), 'idle' => $fields[3] + $fields[4]];
        $previous = Cache::get(self::CPU_SAMPLE_KEY);
        Cache::put(self::CPU_SAMPLE_KEY, $sample, now()->addMinutes(5));

        if (!is_array($previous) || !isset($previous['total'], $previous['idle'])) {
            return null;
        }

        $total = $sample['total'] - $previous['total'];
        if ($total <= 0) {
            return null;
        }

        return $this->percentage($total - ($sample['idle'] - $previous['idle']), $total);
    }

    private function cores(): int
    {
        $cpuinfo = $this->read('/proc/cpuinfo');
        $count = $cpuinfo === null ? 0 : preg_match_all('/^processor\s*:/m', $cpuinfo);

        return max(1, (int) $count);
    }

    private function loadAverage(): ?array
    {
        $load = function_exists('sys_getloadavg') ? @sys_getloadavg() : false;

        return is_array($load) && count($load) === 3
            ? array_map(fn ($value): float => round((float) $value, 2), array_values($load))
            : null;
    }

    private function memory(): array
    {
        $host = $this->hostMemory();
        $cgroup = $this->cgroupMemory();
        $usage = $cgroup !== null && ($host === null || $cgroup['total'] < $host['total']) ? $cgroup : $host;

        if ($usage === null) {
            return ['percent' => null, 'used' => null, 'total' => null, 'status' => 'unknown'];
        }

        $percent = $this->percentage($usage['used'], $usage['total']);

        return [
            'percent' => $percent,
            'used' => $usage['used'],
            'total' => $usage['total'],
            'status' => $this->status($percent),
        ];
    }

    private function hostMemory(): ?array
    {
        $meminfo = $this->read('/proc/meminfo');
        if ($meminfo === null) {
            return null;
        }

        $total = $this->meminfoValue($meminfo, 'MemTotal');
        $available = $this->meminfoValue($meminfo, 'MemAvailable');
        if ($total === null || $available === null || $total <= 0) {
            return null;
        }

        return ['total' => $total, 'used' => max(0, $total - $available)];
    }

    private function meminfoValue(string $meminfo, string $key): ?int
    {
        return preg_match('/^' . preg_quote($key, '/') . ':\s+(\d+) kB$/m', $meminfo, $matches)
            ? (int) $matches[1] * 1024
            : null;
    }

    /**
     * Container memory limit and usage, so a capped panel container is not measured
     * against the whole host. Page cache is excluded because it is reclaimable.
     */
    private function cgroupMemory(): ?array
    {
        $limit = $this->read('/sys/fs/cgroup/memory.max') ?? $this->read('/sys/fs/cgroup/memory/memory.limit_in_bytes');
        $current = $this->read('/sys/fs/cgroup/memory.current') ?? $this->read('/sys/fs/cgroup/memory/memory.usage_in_bytes');
        if ($limit === null || $current === null) {
            return null;
        }

        $total = trim($limit) === 'max' ? 0 : (int) trim($limit);
        // cgroup v1 reports a sentinel close to PHP_INT_MAX when no limit is set.
        if ($total <= 0 || $total >= PHP_INT_MAX / 2) {
            return null;
        }

        $stat = $this->read('/sys/fs/cgroup/memory.stat') ?? $this->read('/sys/fs/cgroup/memory/memory.stat') ?? '';
        $reclaimable = preg_match('/^(?:total_)?inactive_file\s+(\d+)$/m', $stat, $matches) ? (int) $matches[1] : 0;

        return ['total' => $total, 'used' => max(0, (int) trim($current) - $reclaimable)];
    }

    private function storage(): array
    {
if (!function_exists('disk_total_space') || !function_exists('disk_free_space')) {
            return ['percent' => null, 'used' => null, 'total' => null, 'status' => 'unknown'];
        }

        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());

        if (!is_float($total) || !is_float($free) || $total <= 0) {
            return ['percent' => null, 'used' => null, 'total' => null, 'status' => 'unknown'];
        }

        $used = (int) max(0, $total - $free);
        $percent = $this->percentage($used, $total);

        return [
            'percent' => $percent,
            'used' => $used,
            'total' => (int) $total,
            'status' => $this->status($percent),
        ];
    }

    private function uptimeSeconds(): ?int
    {
        $uptime = $this->read('/proc/uptime');

        return $uptime === null ? null : (int) (float) explode(' ', trim($uptime))[0];
    }

    private function status(?float $percent): string
    {
        return match (true) {
            $percent === null => 'unknown',
            $percent > NodeRepositoryInterface::THRESHOLD_PERCENTAGE_MEDIUM => 'critical',
            $percent > NodeRepositoryInterface::THRESHOLD_PERCENTAGE_LOW => 'warning',
            default => 'ok',
        };
    }

    private function percentage(float $used, float $total): ?float
    {
        return $total <= 0 ? null : round(max(0, $used / $total * 100), 1);
    }

    private function read(string $path): ?string
    {
        if (!function_exists('is_readable') || !function_exists('file_get_contents')) {
            return null;
        }

        if (!@is_readable($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        return is_string($contents) && trim($contents) !== '' ? $contents : null;
    }
}
