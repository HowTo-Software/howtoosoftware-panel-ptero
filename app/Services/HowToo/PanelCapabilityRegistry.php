<?php

namespace Pterodactyl\Services\HowToo;

use Pterodactyl\Models\User;
use Illuminate\Routing\Router;
use Pterodactyl\Models\Server;

final class PanelCapabilityRegistry
{
    private const DEFINITIONS = [
        'console' => [
            'label' => 'Console',
            'path' => '/',
            'route' => 'api/client/servers/{server}/resources',
            'summary' => 'View live output, resource usage and server state. Commands require control.console.',
            'permissions' => ['websocket.connect', 'control.console', 'control.start', 'control.stop', 'control.restart'],
            'always_visible' => true,
        ],
        'files' => [
            'label' => 'Files',
            'path' => '/files',
            'route' => 'api/client/servers/{server}/files/list',
            'summary' => 'Browse and manage server files, archives, uploads and SFTP access.',
            'permissions' => ['file.read', 'file.read-content', 'file.create', 'file.update', 'file.delete', 'file.archive', 'file.sftp'],
        ],
        'databases' => [
            'label' => 'Databases',
            'path' => '/databases',
            'route' => 'api/client/servers/{server}/databases',
            'summary' => 'View and manage databases assigned to this server.',
            'permissions' => ['database.read', 'database.create', 'database.update', 'database.delete'],
        ],
        'schedules' => [
            'label' => 'Schedules',
            'path' => '/schedules',
            'route' => 'api/client/servers/{server}/schedules',
            'summary' => 'Create timed tasks for commands, power actions and backups.',
            'permissions' => ['schedule.read', 'schedule.create', 'schedule.update', 'schedule.delete'],
        ],
        'users' => [
            'label' => 'Users',
            'path' => '/users',
            'route' => 'api/client/servers/{server}/users',
            'summary' => 'Manage subusers and their server permissions.',
            'permissions' => ['user.read', 'user.create', 'user.update', 'user.delete'],
        ],
        'backups' => [
            'label' => 'Backups',
            'path' => '/backups',
            'route' => 'api/client/servers/{server}/backups',
            'summary' => 'Create, download, restore and manage server backups.',
            'permissions' => ['backup.read', 'backup.create', 'backup.download', 'backup.restore', 'backup.delete'],
        ],
        'network' => [
            'label' => 'Network',
            'path' => '/network',
            'route' => 'api/client/servers/{server}/network/allocations',
            'summary' => 'View and manage the network allocations assigned to the server.',
            'permissions' => ['allocation.read', 'allocation.create', 'allocation.update', 'allocation.delete'],
        ],
        'startup' => [
            'label' => 'Startup',
            'path' => '/startup',
            'route' => 'api/client/servers/{server}/startup',
            'summary' => 'View or change user-editable startup variables and the server image.',
            'permissions' => ['startup.read', 'startup.update', 'startup.docker-image'],
        ],
        'settings' => [
            'label' => 'Settings',
            'path' => '/settings',
            'route' => 'api/client/servers/{server}/settings/rename',
            'summary' => 'Rename or reinstall the server and view SFTP connection details.',
            'permissions' => ['settings.rename', 'settings.reinstall', 'file.sftp'],
        ],
        'activity' => [
            'label' => 'Activity',
            'path' => '/activity',
            'route' => 'api/client/servers/{server}/activity',
            'summary' => 'Review recent actions recorded for this server.',
            'permissions' => ['activity.read'],
        ],
        'ai_assistant' => [
            'label' => 'AI Assistant',
            'path' => '/assistant',
            'route' => 'api/client/servers/{server}/howtoo/assistant/stream',
            'summary' => 'Get read-only contextual help about this server and the HowToo panel.',
            'permissions' => ['integration.ai'],
            'integration' => 'ai_assistant',
        ],
        'workshop_mods' => [
            'label' => 'Workshop Mods',
            'path' => '/workshop-mods',
            'route' => 'api/client/servers/{server}/howtoo/workshop',
            'summary' => 'Search and configure Project Zomboid Steam Workshop items and Mod IDs, then save or save and restart.',
            'permissions' => ['integration.workshop', 'integration.workshop-update'],
            'integration' => 'workshop',
        ],
        'curseforge_mods' => [
            'label' => 'CurseForge Mods',
            'path' => '/curseforge-mods',
            'route' => 'api/client/servers/{server}/howtoo/curseforge/search',
            'summary' => 'Browse compatible Minecraft CurseForge files and install supported mods.',
            'permissions' => ['integration.curseforge', 'integration.curseforge-install'],
            'integration' => 'curseforge',
        ],
    ];

    public function __construct(private Router $router)
    {
    }

    public function for(Server $server, User $user, array $integrations, ?array $routeUris = null): array
    {
        $routeUris ??= collect($this->router->getRoutes()->getRoutes())
            ->map(fn ($route): string => $route->uri())
            ->all();
        $granted = $this->grantedPermissions($server, $user);

        $capabilities = collect(self::DEFINITIONS)
            ->filter(fn (array $definition): bool => in_array($definition['route'], $routeUris, true))
            ->filter(function (array $definition) use ($integrations): bool {
                $integration = $definition['integration'] ?? null;

                return !$integration || (bool) ($integrations[$integration]['supported'] ?? false);
            })
            ->filter(function (array $definition) use ($granted): bool {
                if ($definition['always_visible'] ?? false) {
                    return true;
                }
                if (($definition['permissions'] ?? []) === []) {
                    return true;
                }

                return collect($definition['permissions'])->contains(fn (string $permission): bool => in_array($permission, $granted, true));
            })
            ->map(function (array $definition, string $id) use ($integrations, $granted): array {
                $integration = $definition['integration'] ?? null;

                return [
                    'id' => $id,
                    'label' => $definition['label'],
                    'path' => $definition['path'],
                    'summary' => $definition['summary'],
                    'available' => !$integration || (bool) ($integrations[$integration]['available'] ?? false),
                    'granted_permissions' => collect($definition['permissions'] ?? [])
                        ->filter(fn (string $permission): bool => in_array($permission, $granted, true))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'capabilities' => $capabilities,
            'granted_permissions' => $granted,
        ];
    }

    private function grantedPermissions(Server $server, User $user): array
    {
        return collect(self::DEFINITIONS)
            ->flatMap(fn (array $definition): array => $definition['permissions'] ?? [])
            ->unique()
            ->filter(fn (string $permission): bool => $user->can($permission, $server))
            ->values()
            ->all();
    }
}
