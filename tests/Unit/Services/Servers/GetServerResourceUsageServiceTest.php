<?php

namespace Pterodactyl\Tests\Unit\Services\Servers;

use GuzzleHttp\Psr7\Request;
use Pterodactyl\Models\Server;
use Pterodactyl\Tests\TestCase;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as Cache;
use GuzzleHttp\Exception\ConnectException;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Services\Servers\GetServerResourceUsageService;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class GetServerResourceUsageServiceTest extends TestCase
{
    public function testResourceUsageIsCachedPerServer(): void
    {
        $cache = new Cache(new ArrayStore());
        $repository = \Mockery::mock(DaemonServerRepository::class);
        $selectedUuid = null;
        $serverOne = $this->server('server-one');
        $serverTwo = $this->server('server-two');
        $usageByServer = [
            'server-one' => ['utilization' => ['cpu_absolute' => 12.5]],
            'server-two' => ['utilization' => ['cpu_absolute' => 76.0]],
        ];

        $repository->shouldReceive('setServer')
            ->twice()
            ->andReturnUsing(function (Server $server) use (&$selectedUuid, $repository) {
                $selectedUuid = $server->uuid;

                return $repository;
            });
        $repository->shouldReceive('getDetails')
            ->twice()
            ->andReturnUsing(function () use (&$selectedUuid, $usageByServer) {
                return $usageByServer[$selectedUuid];
            });

        $service = new GetServerResourceUsageService($cache, $repository);

        $this->assertSame($usageByServer['server-one'], $service->handle($serverOne));
        $this->assertSame($usageByServer['server-one'], $service->handle($serverOne));
        $this->assertSame($usageByServer['server-two'], $service->handle($serverTwo));
    }

    public function testServiceResolvesWithTheConfiguredCacheStore(): void
    {
        $this->app->instance(DaemonServerRepository::class, \Mockery::mock(DaemonServerRepository::class));

        $this->assertInstanceOf(
            GetServerResourceUsageService::class,
            $this->app->make(GetServerResourceUsageService::class),
        );
    }

    public function testConcurrentCacheMissDoesNotIssueAnotherWingsRequest(): void
    {
        $cache = new Cache(new ArrayStore());
        $server = $this->server('server-one');
        $this->assertTrue($cache->getStore()->lock("resources:$server->uuid:refresh-lock", 60)->get());

        $repository = \Mockery::mock(DaemonServerRepository::class);
        $repository->shouldNotReceive('setServer');

        $service = new GetServerResourceUsageService($cache, $repository, 0);

        try {
            $service->handle($server);
            $this->fail('A contended cache refresh should return a temporary 503.');
        } catch (ServiceUnavailableHttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
            $this->assertStringContainsString('temporarily unavailable', $exception->getMessage());
        }
    }

    public function testWingsFailureIsNotRetriedForEveryConcurrentCacheWaiter(): void
    {
        $cache = new Cache(new ArrayStore());
        $server = $this->server('server-one');
        $repository = \Mockery::mock(DaemonServerRepository::class);
        $exception = new DaemonConnectionException(new ConnectException('Wings unavailable.', new Request('GET', '/')));
        $repository->shouldReceive('setServer')->once()->andReturnSelf();
        $repository->shouldReceive('getDetails')->once()->andThrow($exception);

        $service = new GetServerResourceUsageService($cache, $repository);

        try {
            $service->handle($server);
            $this->fail('The first Wings failure should be preserved.');
        } catch (DaemonConnectionException $thrown) {
            $this->assertSame($exception, $thrown);
        }

        try {
            $service->handle($server);
            $this->fail('A concurrent cache waiter should receive a temporary 503.');
        } catch (ServiceUnavailableHttpException $thrown) {
            $this->assertSame(503, $thrown->getStatusCode());
        }
    }

    private function server(string $uuid): Server
    {
        $server = new Server();
        $server->uuid = $uuid;

        return $server;
    }
}
