<?php

namespace Pterodactyl\Services\HowToo;

use Pterodactyl\Models\Node;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

final class SystemHealthService
{
    private const CPU_SAMPLE_KEY = 'howtoo:system-health:cpu-sample';
    private const CPU_VALUE_KEY = 'howtoo:system-health:cpu-percent';

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
     * The node rows for the overview table. Live consumption is fetched separately by
     * NodeUtilizationService so that an unreachable daemon can never stall the page load.
     */
    public function nodes(): array
    {
        $counts = DB::table('servers')
            ->selectRaw('node_id, COUNT(*) as total')
            ->groupBy('node_id')
            ->pluck('total', 'node_id');

        return Node::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Node $node): array => [
                'id' => $node->id,
                'name' => $node->name,
                'servers' => (int) ($counts[$node->id] ?? 0),
                'maintenance' => (bool) $node->maintenance_mode,
            ])
            ->all();
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

        if (!is_array($previous) || !isset($previous['total'], $previous['idle'])) {
            Cache::put(self::CPU_SAMPLE_KEY, $sample, now()->addMinutes(5));

            return null;
        }

        // Two dashboards polling at once can land in the same tick; reuse the last
        // figure rather than dividing by a window too short to mean anything.
        $total = $sample['total'] - $previous['total'];
        if ($total < 50) {
            $last = Cache::get(self::CPU_VALUE_KEY);

            return is_float($last) ? $last : null;
        }

        Cache::put(self::CPU_SAMPLE_KEY, $sample, now()->addMinutes(5));
        $percent = $this->percentage($total - ($sample['idle'] - $previous['idle']), $total);
        Cache::put(self::CPU_VALUE_KEY, $percent, now()->addMinutes(5));

        return $percent;
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
        return ResourceStatus::fromPercent($percent);
    }

    private function percentage(float $used, float $total): ?float
    {
        return ResourceStatus::percentage($used, $total);
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
