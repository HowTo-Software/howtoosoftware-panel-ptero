<?php

namespace Pterodactyl\Tests\Unit\Services\HowToo;

use Pterodactyl\Models\User;
use Illuminate\Routing\Router;
use Pterodactyl\Models\Server;
use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\HowToo\PanelCapabilityRegistry;

class PanelCapabilityRegistryTest extends TestCase
{
    public function testItUsesRealRoutesPermissionsAndGameIntegrations(): void
    {
        $server = \Mockery::mock(Server::class);
        $user = \Mockery::mock(User::class);
        $granted = ['file.read', 'startup.read', 'integration.ai', 'integration.workshop'];
        $user->shouldReceive('can')->andReturnUsing(
            fn (string $permission, Server $target): bool => $target === $server && in_array($permission, $granted, true),
        );
        $routes = [
            'api/client/servers/{server}/resources',
            'api/client/servers/{server}/files/list',
            'api/client/servers/{server}/startup',
            'api/client/servers/{server}/howtoo/assistant/stream',
            'api/client/servers/{server}/howtoo/workshop',
        ];
        $integrations = [
            'ai_assistant' => ['supported' => true, 'available' => true],
            'workshop' => ['supported' => true, 'available' => true],
            'curseforge' => ['supported' => false, 'available' => false],
        ];

        $context = (new PanelCapabilityRegistry(\Mockery::mock(Router::class)))
            ->for($server, $user, $integrations, $routes);
        $ids = collect($context['capabilities'])->pluck('id')->all();

        $this->assertSame(['console', 'files', 'startup', 'ai_assistant', 'workshop_mods'], $ids);
        $this->assertNotContains('curseforge_mods', $ids);
        $this->assertContains('startup.read', $context['granted_permissions']);
        $this->assertSame('/startup', collect($context['capabilities'])->firstWhere('id', 'startup')['path']);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
