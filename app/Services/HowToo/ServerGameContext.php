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
        'VANILLA_VERSION',
        'BEDROCK_VERSION',
        'GAME_VERSION',
        'VERSION',
    ];

    private const LOADER_VARIABLES = [
        'MOD_LOADER',
        'MOD_LOADER_TYPE',
        'MODLOADER',
        'MINECRAFT_MOD_LOADER',
        'LOADER_TYPE',
        'SERVER_TYPE',
        'SERVER_SOFTWARE',
        'TYPE',
    ];

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
        $loaderIdentity = implode(' ', array_map(
            fn (string $name): string => $variables[$name] ?? '',
            self::LOADER_VARIABLES,
        ));
        $bedrock = array_key_exists('BEDROCK_VERSION', $variables)
            || preg_match('/\b(bedrock|pocketmine|nukkit)\b/', "$identity $loaderIdentity") === 1;
        $minecraft = str_contains($identity, 'minecraft') || $bedrock || $this->detectLoader($identity, $variables) !== null;
        $minecraftJava = $minecraft && !$bedrock;
        $version = $minecraft ? $this->firstMinecraftVersion($variables) : null;
        $loader = $minecraftJava ? $this->detectLoader($identity, $variables) : null;

        // Vanilla Minecraft eggs do not have a loader variable. Their version is
        // still useful for identifying and browsing server packs, while the
        // CurseForge API's loader value 0 means "Any", not "Vanilla".
        if ($minecraftJava && $loader === null && $this->isVanillaJavaEgg($identity)) {
            $loader = CurseForgeLoader::Vanilla;
        }

        return [
            'game' => $projectZomboid ? 'project_zomboid' : ($minecraft ? 'minecraft' : 'other'),
            'project_zomboid' => $projectZomboid,
            'minecraft' => $minecraft,
            'minecraft_java' => $minecraftJava,
            'minecraft_edition' => !$minecraft ? null : ($bedrock ? 'bedrock' : 'java'),
            'minecraft_version' => $this->normalizeVersion($version),
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

    private function firstMinecraftVersion(array $variables): ?string
    {
        foreach (self::VERSION_VARIABLES as $name) {
            $version = $this->normalizeVersion($variables[$name] ?? null);
            if ($version !== null) {
                return $version;
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
            'vanilla' => CurseForgeLoader::Vanilla,
        ] as $name => $loader) {
            if (str_contains($haystack, $name)) {
                return $loader;
            }
        }

        return null;
    }

    private function isVanillaJavaEgg(string $identity): bool
    {
        return !preg_match('/\b(bungee(?:cord)?|waterfall|velocity|paper|spigot|purpur|folia|bukkit|sponge)\b/', $identity)
            && (str_contains($identity, 'minecraft') || str_contains($identity, 'vanilla'));
    }

    private function normalizeVersion(?string $version): ?string
    {
        if (!is_string($version) || preg_match('/^(\d+\.\d+(?:\.\d+){0,2})(?:[-+._a-zA-Z0-9]*)?$/', trim($version), $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function zomboidServerName(array $variables): string
    {
        $name = $this->firstVariable($variables, ['SERVER_NAME', 'PZ_SERVER_NAME']) ?? 'servertest';

        return preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $name) === 1 ? $name : 'servertest';
    }
}

enum CurseForgeLoader: int
{
    case Vanilla = 0;
    case Forge = 1;
    case Fabric = 4;
    case Quilt = 5;
    case NeoForge = 6;
}
