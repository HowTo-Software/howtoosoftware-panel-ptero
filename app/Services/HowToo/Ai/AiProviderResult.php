<?php

namespace Pterodactyl\Services\HowToo\Ai;

final readonly class AiProviderResult
{
    public function __construct(
        public string $provider,
        public string $answer,
    ) {
    }
}
