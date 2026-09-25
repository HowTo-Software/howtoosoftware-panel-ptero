<?php

namespace Pterodactyl\Tests\Unit\Services\HowToo;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\Server;
use PHPUnit\Framework\TestCase;
use Illuminate\Database\Eloquent\Collection;
use Pterodactyl\Services\HowToo\ServerGameContext;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Services\HowToo\ProjectZomboidSaveWipeService;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ProjectZomboidSaveWipeServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function testItDeletesOnlyAllowlistedNonSymlinkDirectoriesWhenFullyOffline(): void
    {
        $server = $this->projectZomboidServer();
        $server->shouldReceive('validateCurrentState')->twice();

        $daemon = \Mockery::mock(DaemonServerRepository::class);
        $daemon->shouldReceive('setServer')->twice()->with($server)->andReturnSelf();
        $daemon->shouldReceive('getDetails')->twice()->andReturn(['state' => 'offline']);

        $files = \Mockery::mock(DaemonFileRepository::class);
        $files->shouldReceive('setServer')->twice()->with($server)->andReturnSelf();
        $files->shouldReceive('getDirectory')->once()->with('/.cache')->andReturn([
            ['name' => 'db', 'file' => false, 'symlink' => false],
            ['name' => 'logs', 'file' => false, 'symlink' => false],
            ['name' => 'saves', 'file' => false, 'symlink' => true],
            ['name' => 'Logs', 'file' => false, 'symlink' => false],
            ['name' => 'db', 'file' => true, 'symlink' => false],
            ['name' => 'config', 'file' => false, 'symlink' => false],
        ]);
        $files->shouldReceive('deleteFiles')->once()->with('/.cache', ['db', 'logs']);

        $service = new ProjectZomboidSaveWipeService(new ServerGameContext(), $files, $daemon);

        $this->assertSame(['db', 'logs'], $service->handle($server));
    }

    public function testItRefusesToWipeWhenTheDaemonReportsAnOnlineServer(): void
    {
        $server = $this->projectZomboidServer();
        $server->shouldReceive('validateCurrentState')->once();

        $daemon = \Mockery::mock(DaemonServerRepository::class);
        $daemon->shouldReceive('setServer')->once()->with($server)->andReturnSelf();
        $daemon->shouldReceive('getDetails')->once()->andReturn(['state' => 'running']);

        $files = \Mockery::mock(DaemonFileRepository::class);
        $files->shouldNotReceive('getDirectory');
        $files->shouldNotReceive('deleteFiles');

        $service = new ProjectZomboidSaveWipeService(new ServerGameContext(), $files, $daemon);

        $this->expectException(ConflictHttpException::class);
        $service->handle($server);
    }

    public function testItRefusesToWipeServersThatAreNotProjectZomboid(): void
    {
        $server = $this->projectZomboidServer();
        $server->setRelation('egg', (new Egg())->forceFill(['name' => 'Minecraft']));

        $daemon = \Mockery::mock(DaemonServerRepository::class);
        $daemon->shouldNotReceive('setServer');
        $files = \Mockery::mock(DaemonFileRepository::class);
        $files->shouldNotReceive('setServer');

        $service = new ProjectZomboidSaveWipeService(new ServerGameContext(), $files, $daemon);

        $this->expectException(BadRequestHttpException::class);
        $service->handle($server);
    }

    private function projectZomboidServer(): Server
    {
        $server = \Mockery::mock(Server::class)->makePartial();
        $server->setRelation('egg', (new Egg())->forceFill(['name' => 'Project Zomboid']));
        $server->setRelation('nest', (new Nest())->forceFill(['name' => 'Games']));
        $server->setRelation('variables', new Collection());

        return $server;
    }
}
