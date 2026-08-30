<?php

namespace Pterodactyl\Services\HowToo;

use Pterodactyl\Models\Server;

final class IntegrationCapabilities
{
    public function __construct(
        private IntegrationCredentialStore $credentials,
        private ServerGameContext $gameContext,
    ) {
    }

    public function for(Server $server): array
    {
        $providers = $this->credentials->status();
        $context = $this->gameContext->for($server);

        $available = static fn (array $provider): bool => $provider['enabled'] && $provider['configured'];

        return [
            'ai_assistant' => [
                'supported' => true,
                'available' => $available($providers['ollama']),
                'providers' => [
                    'ollama' => $available($providers['ollama']),
                ],
            ],
            'workshop' => [
                'supported' => $context['project_zomboid'],
                'available' => $context['project_zomboid'] && $available($providers['steam']),
            ],
            'curseforge' => [
                'supported' => $context['minecraft'],
                'available' => $context['minecraft'] && $available($providers['curseforge']),
                'game_version' => $context['minecraft_version'],
                'mod_loader' => $context['mod_loader'],
                'mod_loader_type' => $context['mod_loader_type'],
            ],
        ];
    }
}
