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
        public int $timeoutSeconds = 25,
    ) {
    }

    public function withTimeout(int $timeoutSeconds): self
    {
        return new self(
            $this->provider,
            $this->model,
            $this->secret,
            $this->keyId,
            $this->environment,
            $timeoutSeconds,
        );
    }
}
