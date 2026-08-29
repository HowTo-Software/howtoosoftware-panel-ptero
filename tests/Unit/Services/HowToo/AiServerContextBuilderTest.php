<?php

namespace Pterodactyl\Tests\Unit\Services\HowToo;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use PHPUnit\Framework\TestCase;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\ActivityLog;
use Illuminate\Database\Eloquent\Collection;
use Pterodactyl\Services\HowToo\ServerGameContext;
use Pterodactyl\Services\HowToo\AiServerContextBuilder;

class AiServerContextBuilderTest extends TestCase
{
    public function testItIncludesOnlyVisibleNonSensitiveVariablesAndRedactsStartupSecrets(): void
    {
        $server = \Mockery::mock(Server::class)->makePartial();
        $server->setRawAttributes([
            'name' => 'Community',
            'memory' => 8192,
            'disk' => 40960,
            'cpu' => 400,
            'startup' => 'start --token=very-secret --password "quoted secret" --name={{SERVER_NAME}}',
            'status' => null,
        ]);
        $server->setRelation('egg', (object) ['name' => 'Project Zomboid', 'description' => 'Project Zomboid']);
        $server->setRelation('nest', (object) ['name' => 'Steam Games']);
        $server->setRelation('node', (object) ['name' => 'EUA-01']);
        $server->setRelation('variables', new Collection([
            $this->variable('SERVER_NAME', 'Community', true),
            $this->variable('PUBLIC_OPTION', 'AIza012345678901234567890123456789', true),
            $this->variable('DB_PASSWORD', 'must-not-leak', true),
            $this->variable('INTERNAL_OPTION', 'hidden', false),
        ]));
        $activity = (new \ReflectionClass(ActivityLog::class))->newInstanceWithoutConstructor();
        $activity->setRawAttributes([
            'event' => 'server:power.restart',
            'timestamp' => CarbonImmutable::parse('2026-08-29T10:00:00Z'),
        ]);
        $server->setRelation('activity', new Collection([$activity]));
        $user = \Mockery::mock(User::class);
        $user->shouldReceive('can')->with(Permission::ACTION_STARTUP_READ, $server)->andReturnTrue();
        $user->shouldReceive('can')->with(Permission::ACTION_ACTIVITY_READ, $server)->andReturnTrue();
        $panelContext = [
            'capabilities' => [['id' => 'startup', 'available' => true]],
            'granted_permissions' => [Permission::ACTION_STARTUP_READ],
        ];

        $context = (new AiServerContextBuilder(new ServerGameContext()))
            ->build(
                $server,
                $user,
                'running',
                'console',
                'Authorization: Bearer abcdefghijklmnop token=another-secret',
                $panelContext,
            );
        $encoded = json_encode($context);

        $this->assertSame('running', $context['status']);
        $this->assertSame([
            ['name' => 'SERVER_NAME', 'value' => 'Community'],
            ['name' => 'PUBLIC_OPTION', 'value' => '[redacted]'],
        ], $context['visible_variables']);
        $this->assertStringNotContainsString('very-secret', $encoded);
        $this->assertStringNotContainsString('quoted secret', $encoded);
        $this->assertStringNotContainsString('abcdefghijklmnop', $encoded);
        $this->assertStringNotContainsString('must-not-leak', $encoded);
        $this->assertStringNotContainsString('another-secret', $encoded);
        $this->assertStringContainsString('[redacted]', $encoded);
        $this->assertSame('server:power.restart', $context['recent_events'][0]['event']);
        $this->assertSame([Permission::ACTION_STARTUP_READ], $context['granted_permissions']);
    }

    public function testItOmitsStartupVariablesAndActivityWithoutUserPermissions(): void
    {
        $server = \Mockery::mock(Server::class)->makePartial();
        $server->setRawAttributes([
            'name' => 'Restricted',
            'memory' => 4096,
            'disk' => 40960,
            'cpu' => 200,
            'startup' => 'start --token=do-not-send',
            'status' => null,
        ]);
        $server->setRelation('egg', (object) ['name' => 'Project Zomboid', 'description' => 'Project Zomboid']);
        $server->setRelation('nest', (object) ['name' => 'Steam Games']);
        $server->setRelation('node', (object) ['name' => 'EUA-01']);
        $server->setRelation('variables', new Collection([
            $this->variable('SERVER_NAME', 'Restricted', true),
        ]));
        $user = \Mockery::mock(User::class);
        $user->shouldReceive('can')->with(Permission::ACTION_STARTUP_READ, $server)->andReturnFalse();
        $user->shouldReceive('can')->with(Permission::ACTION_ACTIVITY_READ, $server)->andReturnFalse();

        $context = (new AiServerContextBuilder(new ServerGameContext()))
            ->build($server, $user, 'offline', null, null, [
                'capabilities' => [],
                'granted_permissions' => [],
            ]);

        $this->assertNull($context['startup_command_template']);
        $this->assertSame([], $context['visible_variables']);
        $this->assertSame([], $context['recent_events']);
        $this->assertStringNotContainsString('do-not-send', json_encode($context));
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    private function variable(string $name, string $value, bool $visible): object
    {
        return (object) [
            'env_variable' => $name,
            'default_value' => '',
            'server_value' => $value,
            'user_viewable' => $visible,
        ];
    }
}
