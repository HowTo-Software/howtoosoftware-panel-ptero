<?php

namespace Pterodactyl\Services\HowToo\Ai;

interface AiProviderAdapter
{
    public function name(): string;

    /** @throws AiProviderException */
    public function generate(AiProviderCredential $credential, AiProviderPrompt $prompt): string;
}
