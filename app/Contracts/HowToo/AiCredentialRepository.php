<?php

namespace Pterodactyl\Contracts\HowToo;

use Pterodactyl\Services\HowToo\Ai\AiProviderCredential;

interface AiCredentialRepository
{
    public function orderedAiProviders(): array;

    /** @return AiProviderCredential[] */
    public function availableAiCredentials(string $provider, string $model): array;

    public function putOnCooldown(AiProviderCredential $credential, int $seconds, string $reason): void;

    public function markHealthy(AiProviderCredential $credential): void;
}
