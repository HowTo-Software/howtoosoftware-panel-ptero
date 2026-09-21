<?php

namespace Pterodactyl\Services\HowToo;

use GuzzleHttp\Client;
use Pterodactyl\Models\Node;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Foundation\Application;

final class NodeUtilizationService
{
    private const CACHE_KEY = 'howtoo:node-utilization';
    private const CACHE_SECONDS = 4;
    private const CONNECT_TIMEOUT = 2;
    private const TIMEOUT = 5;

    public function __construct(private Application $app)
    {
    }

    /**
     * What every node is actually consuming right now, as reported by its daemon.
     * Cached briefly so several open dashboards do not multiply the load on Wings.
     */
    public function all(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            return Cache::lock(self::CACHE_KEY . ':lock', self::TIMEOUT)->block(
                self::CONNECT_TIMEOUT,
                function (): array {
                    $cached = Cache::get(self::CACHE_KEY);

                    if (is_array($cached)) {
                        return $cached;
                    }

                    $fresh = $this->collect();
                    Cache::put(self::CACHE_KEY, $fresh, now()->addSeconds(self::CACHE_SECONDS));

                    return $fresh;
                },
            );
        } catch (LockTimeoutException) {
            return Cache::get(self::CACHE_KEY) ?? $this->collect();
        }
    }

    private function collect(): array
    {
        $nodes = Node::query()->orderBy('name')->get();
        $requests = [];

        foreach ($nodes as $node) {
            $client = $this->client($node);
            $requests["servers:$node->id"] = $client->getAsync('/api/servers');
            $requests["system:$node->id"] = $client->getAsync('/api/system?v=2');
        }

        $responses = Utils::settle($requests)->wait();

        return [
            'generated_at' => now()->toIso8601String(),
            'nodes' => $nodes->map(fn (Node $node): array => [
                'id' => $node->id,
                ...$this->summarise(
                    $node,
                    $this->decode($responses["servers:$node->id"] ?? null),
                    $this->decode($responses["system:$node->id"] ?? null),
                ),
            ])->all(),
        ];
    }

    private function summarise(Node $node, ?array $servers, ?array $system): array
    {
        if ($servers === null) {
            return [
                'reachable' => false,
                'online' => null,
                'cpu' => $this->unavailable(),
                'memory' => $this->unavailable(),
                'disk' => $this->unavailable(),
            ];
        }

        $cpu = 0.0;
        $memory = 0;
        $disk = 0;
        $online = 0;

        foreach ($servers as $server) {
            $utilization = (array) ($server['utilization'] ?? []);
            $cpu += (float) ($utilization['cpu_absolute'] ?? 0);
            $memory += (int) ($utilization['memory_bytes'] ?? 0);
            $disk += (int) ($utilization['disk_bytes'] ?? 0);

            if (($server['state'] ?? null) === 'running') {
                ++$online;
            }
        }

        $host = $this->host($system);

        return [
            'reachable' => true,
            'online' => $online,
            'cpu' => $this->cpu(round($cpu, 1), $host['threads']),
            // Servers here are mostly uncapped, so the daemon's real total beats the configured one.
            'memory' => $host['memory'] > 0
                ? $this->resource($memory, $host['memory'], 'daemon')
                : $this->resource($memory, $node->memory * 1024 * 1024, 'configured'),
            // Wings reports disk usage but no filesystem total, so only the node config is left.
            'disk' => $this->resource($disk, $node->disk * 1024 * 1024, 'configured'),
        ];
    }

    /**
     * Wings answers ?v=2 with the host's own totals and older builds return the flat v1 shape.
     */
    private function host(?array $system): array
    {
        $inner = (array) ($system['system'] ?? []);

        return [
            'threads' => (int) ($inner['cpu_threads'] ?? $system['cpu_count'] ?? 0),
            'memory' => (int) ($inner['memory_bytes'] ?? 0),
        ];
    }

    /**
     * cpu_absolute is a percentage of a single thread, so full capacity is threads * 100.
     * A thread count of zero means the daemon never answered, not that CPU is uncapped.
     */
    private function cpu(float $used, int $threads): array
    {
        return $threads > 0
            ? $this->resource($used, $threads * 100, 'daemon')
            : ['used' => $used, 'total' => null, 'percent' => null, 'source' => null, 'status' => 'unknown'];
    }

    private function resource(int|float $used, int|float $total, string $source): array
    {
        // A configured capacity of zero means unlimited, so there is nothing to measure against.
        if ($total <= 0) {
return ['used' => $used, 'total' => null, 'percent' => null, 'source' => $source, 'status' => 'unlimited'];
        }

        $percent = ResourceStatus::percentage($used, $total);

        return [
            'used' => $used,
            'total' => $total,
            'percent' => $percent,
            'source' => $source,
            'status' => ResourceStatus::fromPercent($percent),
        ];
    }

    private function unavailable(): array
    {
        return ['used' => null, 'total' => null, 'percent' => null, 'source' => null, 'status' => 'unknown'];
    }

    private function decode(?array $settled): ?array
    {
        if (($settled['state'] ?? null) !== 'fulfilled') {
            return null;
        }

        $decoded = json_decode($settled['value']->getBody()->__toString(), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function client(Node $node): Client
    {
        return new Client([
            'base_uri' => $node->getConnectionAddress(),
            'timeout' => self::TIMEOUT,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'verify' => $this->app->environment('production'),
            'headers' => [
                'Authorization' => 'Bearer ' . $node->getDecryptedKey(),
                'Accept' => 'application/json',
            ],
        ]);
    }
}
