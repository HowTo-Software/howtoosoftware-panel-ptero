<?php

namespace Pterodactyl\Services\HowToo\Ai;

final readonly class AiProviderCredential
{
    public function __construct(
        public string $provider,
        public string $model,
        public string $secret,
        public ?int $keyId,
        public bool $environment,
    ) {
    }
}
