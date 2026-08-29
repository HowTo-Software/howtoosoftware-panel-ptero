<?php

namespace Pterodactyl\Services\HowToo\Ai;

interface StreamingAiProviderAdapter extends AiProviderAdapter
{
    /** @throws AiProviderException */
    public function stream(AiProviderCredential $credential, AiProviderPrompt $prompt, callable $onDelta): string;
}
