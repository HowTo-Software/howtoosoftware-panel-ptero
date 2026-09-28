<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class GetServerResourceUsageService
{
    private const CACHE_SECONDS = 20;
    private const FAILURE_COOLDOWN_SECONDS = 2;
    private const LOCK_WAIT_SECONDS = 5;

    public function __construct(
        private CacheRepository $cache,
        private DaemonServerRepository $repository,
        private int $lockWaitSeconds = self::LOCK_WAIT_SECONDS,
    ) {
    }

    /**
     * Return cached resource usage, coalescing simultaneous cache misses per server.
     *
     * @throws DaemonConnectionException
     */
    public function handle(Server $server): array
    {
        $key = "resources:$server->uuid";
        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $failureKey = "$key:refresh-failure";
        if ($this->cache->get($failureKey) === true) {
            throw new ServiceUnavailableHttpException(2, 'Resource data is temporarily unavailable while the server daemon recovers.');
        }

        $store = $this->cache->getStore();
        if (!$store instanceof LockProvider) {
            return $this->cache->remember($key, now()->addSeconds(self::CACHE_SECONDS), function () use ($failureKey, $server): array {
                try {
                    $stats = $this->repository->setServer($server)->getDetails();
                } catch (DaemonConnectionException $exception) {
                    $this->cache->put($failureKey, true, now()->addSeconds(self::FAILURE_COOLDOWN_SECONDS));

                    throw $exception;
                }

                $this->cache->forget($failureKey);

                return $stats;
            });
        }

        // Keep the lock alive longer than the configured Wings request timeout so a slow
        // request cannot outlive its lock and allow a second request to hit Wings.
        $lockSeconds = max(10, (int) config('pterodactyl.guzzle.timeout', 30) + 5);
        $lockWaitSeconds = min(
            $this->lockWaitSeconds,
            max(1, (int) config('pterodactyl.guzzle.timeout', 30) - 1),
        );

        try {
            return $store->lock("$key:refresh-lock", $lockSeconds)->block(
                $lockWaitSeconds,
                function () use ($key, $failureKey, $server): array {
                    // Another request may have populated the cache while this request waited.
                    $cached = $this->cache->get($key);
                    if (is_array($cached)) {
                        return $cached;
                    }

                    if ($this->cache->get($failureKey) === true) {
                        throw new ServiceUnavailableHttpException(2, 'Resource data is temporarily unavailable while the server daemon recovers.');
                    }

                    try {
                        $stats = $this->repository->setServer($server)->getDetails();
                    } catch (DaemonConnectionException $exception) {
                        $this->cache->put($failureKey, true, now()->addSeconds(self::FAILURE_COOLDOWN_SECONDS));

                        throw $exception;
                    }

                    $this->cache->forget($failureKey);
                    $this->cache->put($key, $stats, now()->addSeconds(self::CACHE_SECONDS));

                    return $stats;
                }
            );
        } catch (LockTimeoutException $exception) {
            // A cold cache with a slow Wings response has no real snapshot to serve. Return
            // a clear transient failure instead of inventing metrics or making another call.
            throw new ServiceUnavailableHttpException(2, 'Resource data is temporarily unavailable while a fresh snapshot is being fetched.', $exception);
        }
    }
}
