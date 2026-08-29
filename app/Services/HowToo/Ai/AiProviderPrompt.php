<?php

namespace Pterodactyl\Services\HowToo\Ai;

final readonly class AiProviderPrompt
{
    public function __construct(
        public string $system,
        public array $messages,
    ) {
    }
}
