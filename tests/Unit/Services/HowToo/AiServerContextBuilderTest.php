<?php

namespace Pterodactyl\Tests\Unit\Services\HowToo;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\Server;
use PHPUnit\Framework\TestCase;
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
            $this->variable('DB_PASSWORD', 'must-not-leak', true),
            $this->variable('INTERNAL_OPTION', 'hidden', false),
        ]));
        $activity = (new \ReflectionClass(ActivityLog::class))->newInstanceWithoutConstructor();
        $activity->setRawAttributes([
            'event' => 'server:power.restart',
            'timestamp' => CarbonImmutable::parse('2026-08-29T10:00:00Z'),
        ]);
        $server->setRelation('activity', new Collection([$activity]));

        $context = (new AiServerContextBuilder(new ServerGameContext()))
            ->build($server, 'running', 'console', 'Authorization: Bearer abcdefghijklmnop token=another-secret', true);
        $encoded = json_encode($context);

        $this->assertSame('running', $context['status']);
        $this->assertSame([['name' => 'SERVER_NAME', 'value' => 'Community']], $context['visible_variables']);
        $this->assertStringNotContainsString('very-secret', $encoded);
        $this->assertStringNotContainsString('quoted secret', $encoded);
        $this->assertStringNotContainsString('abcdefghijklmnop', $encoded);
        $this->assertStringNotContainsString('must-not-leak', $encoded);
        $this->assertStringNotContainsString('another-secret', $encoded);
        $this->assertStringContainsString('[redacted]', $encoded);
        $this->assertSame('server:power.restart', $context['recent_events'][0]['event']);
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
