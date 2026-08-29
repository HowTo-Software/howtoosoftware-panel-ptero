<?php

namespace Pterodactyl\Tests\Unit\Services\HowToo;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Pterodactyl\Models\Server;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Exception\ServerException;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\HowToo\ServerGameContext;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Services\HowToo\ZomboidWorkshopConfigService;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

class ZomboidWorkshopConfigServiceTest extends TestCase
{
    private ZomboidWorkshopConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ZomboidWorkshopConfigService(
            \Mockery::mock(DaemonFileRepository::class),
            new ServerGameContext(),
        );
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function testItParsesOrderedSemicolonListsAndRemovesDuplicates(): void
    {
        $result = $this->service->parseContent(implode("\r\n", [
            'PublicName=Example',
            'WorkshopItems=123;456;123;;789',
            'Mods=Alpha;Beta;Alpha',
        ]));

        $this->assertSame(['123', '456', '789'], $result['workshop_items']);
        $this->assertSame(['Alpha', 'Beta'], $result['mods']);
    }

    public function testItUpdatesOnlyWorkshopKeysAndPreservesLineEndings(): void
    {
        $content = "PublicName=Example\r\nWorkshopItems=1;2\r\nMods=Old\r\nWorkshopItems=duplicate\r\n";
        $updated = $this->service->updateContent($content, ['9', '8', '9'], ['New', 'Other', 'New']);

        $this->assertSame(
            "PublicName=Example\r\nWorkshopItems=9;8\r\nMods=New;Other\r\n",
            $updated,
        );
    }

    public function testItAppendsMissingKeysWithoutChangingOtherSettings(): void
    {
        $updated = $this->service->updateContent("PublicName=Example\nPauseEmpty=true", ['42'], ['ExampleMod']);

        $this->assertSame(
            "PublicName=Example\nPauseEmpty=true\nWorkshopItems=42\nMods=ExampleMod",
            $updated,
        );
    }

    public function testItPrioritizesTheDynamicCacheConfigurationPath(): void
    {
        $server = \Mockery::mock(Server::class);
        $repository = \Mockery::mock(DaemonFileRepository::class);
        $repository->shouldReceive('setServer')->once()->with($server)->andReturnSelf();
        $repository->shouldReceive('getContent')
            ->once()
            ->with('/.cache/Server/Community.ini', 2 * 1024 * 1024)
            ->andReturn('WorkshopItems=123');

        $result = $this->locate($repository, $server, 'Community');

        $this->assertSame('/.cache/Server/Community.ini', $result[0]);
        $this->assertSame('WorkshopItems=123', $result[1]);
    }

    public function testItContinuesToLegacyPathsForWingsMissingFileResponsesUsingHttp500(): void
    {
        $server = \Mockery::mock(Server::class);
        $repository = \Mockery::mock(DaemonFileRepository::class);
        $repository->shouldReceive('setServer')->once()->with($server)->andReturnSelf();
        $repository->shouldReceive('getContent')
            ->once()
            ->ordered()
            ->with('/.cache/Server/Legacy.ini', 2 * 1024 * 1024)
            ->andThrow($this->wingsError(500, '{"error":"stat /.cache/Server/Legacy.ini: no such file or directory"}'));
        $repository->shouldReceive('getContent')
            ->once()
            ->ordered()
            ->with('/Zomboid/Server/Legacy.ini', 2 * 1024 * 1024)
            ->andReturn("WorkshopItems=456\nMods=LegacyMod");

        $result = $this->locate($repository, $server, 'Legacy');

        $this->assertSame('/Zomboid/Server/Legacy.ini', $result[0]);
    }

    public function testItDoesNotIgnoreUnrelatedWingsHttp500Errors(): void
    {
        $server = \Mockery::mock(Server::class);
        $repository = \Mockery::mock(DaemonFileRepository::class);
        $repository->shouldReceive('setServer')->once()->with($server)->andReturnSelf();
        $repository->shouldReceive('getContent')
            ->once()
            ->with('/.cache/Server/Broken.ini', 2 * 1024 * 1024)
            ->andThrow($this->wingsError(500, '{"error":"internal filesystem failure"}'));

        $this->expectException(DisplayException::class);
        $this->expectExceptionMessage('Could not read the Project Zomboid configuration from Wings.');

        $this->locate($repository, $server, 'Broken');
    }

    private function locate(DaemonFileRepository $repository, Server $server, string $serverName): array
    {
        $service = new ZomboidWorkshopConfigService($repository, new ServerGameContext());
        $method = new \ReflectionMethod($service, 'locate');
        $method->setAccessible(true);

        return $method->invoke($service, $server, $serverName);
    }

    private function wingsError(int $status, string $body): DaemonConnectionException
    {
        $request = new Request('GET', 'http://wings.test/api/servers/example/files/contents');
        $response = new Response($status, ['Content-Type' => 'application/json'], $body);

        return new DaemonConnectionException(new ServerException('Wings request failed.', $request, $response));
    }
}
