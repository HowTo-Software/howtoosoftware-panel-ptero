<?php

namespace Pterodactyl\Services\HowToo;

use Pterodactyl\Models\Server;

final class ServerGameContext
{
    private const VERSION_VARIABLES = [
        'MINECRAFT_VERSION',
        'MC_VERSION',
        'MINECRAFT_JAR_VERSION',
        'SERVER_VERSION',
        'VERSION',
    ];

    private const LOADER_VARIABLES = ['MOD_LOADER', 'MODLOADER', 'LOADER_TYPE', 'SERVER_TYPE', 'TYPE'];

    public function for(Server $server): array
    {
        $relations = collect(['egg', 'nest', 'variables'])
            ->reject(fn (string $relation): bool => $server->relationLoaded($relation))
            ->all();
        if ($relations !== []) {
            $server->loadMissing($relations);
        }

        $identity = mb_strtolower(implode(' ', array_filter([
            $server->egg?->name,
            $server->egg?->description,
            $server->nest->name,
        ])));

        $variables = $server->variables->mapWithKeys(function ($variable) {
            $value = filled($variable->server_value) ? $variable->server_value : $variable->default_value;

            return [strtoupper($variable->env_variable) => trim((string) $value)];
        })->all();

        $projectZomboid = str_contains($identity, 'project zomboid') || str_contains($identity, 'zomboid');
        $minecraft = str_contains($identity, 'minecraft') || $this->detectLoader($identity, $variables) !== null;
        $version = $minecraft ? $this->firstVariable($variables, self::VERSION_VARIABLES) : null;
        $loader = $minecraft ? $this->detectLoader($identity, $variables) : null;

        return [
            'game' => $projectZomboid ? 'project_zomboid' : ($minecraft ? 'minecraft' : 'other'),
            'project_zomboid' => $projectZomboid,
            'minecraft' => $minecraft,
            'minecraft_version' => $this->validVersion($version) ? $version : null,
            'mod_loader' => $loader?->name,
            'mod_loader_type' => $loader?->value,
            'zomboid_server_name' => $this->zomboidServerName($variables),
        ];
    }

    private function firstVariable(array $variables, array $names): ?string
    {
        foreach ($names as $name) {
            if (filled($variables[$name] ?? null)) {
                return $variables[$name];
            }
        }

        return null;
    }

    private function detectLoader(string $identity, array $variables): ?CurseForgeLoader
    {
        $value = mb_strtolower((string) ($this->firstVariable($variables, self::LOADER_VARIABLES) ?? ''));
        $haystack = "$identity $value";

        foreach ([
            'neoforge' => CurseForgeLoader::NeoForge,
            'fabric' => CurseForgeLoader::Fabric,
            'quilt' => CurseForgeLoader::Quilt,
            'forge' => CurseForgeLoader::Forge,
        ] as $name => $loader) {
            if (str_contains($haystack, $name)) {
                return $loader;
            }
        }

        return null;
    }

    private function validVersion(?string $version): bool
    {
        return is_string($version) && preg_match('/^\d+(?:\.\d+){1,3}(?:[-+._a-zA-Z0-9]*)?$/', $version) === 1;
    }

    private function zomboidServerName(array $variables): string
    {
        $name = $this->firstVariable($variables, ['SERVER_NAME', 'PZ_SERVER_NAME']) ?? 'servertest';

        return preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $name) === 1 ? $name : 'servertest';
    }
}

enum CurseForgeLoader: int
{
    case Forge = 1;
    case Fabric = 4;
    case Quilt = 5;
    case NeoForge = 6;
}
