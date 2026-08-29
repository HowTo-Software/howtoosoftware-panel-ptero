<?php

namespace Pterodactyl\Services\HowToo\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

final class GroqProviderAdapter implements AiProviderAdapter
{
    public function name(): string
    {
        return 'groq';
    }

    public function generate(AiProviderCredential $credential, AiProviderPrompt $prompt): string
    {
        if (preg_match('/^[A-Za-z0-9._:\/-]{1,120}$/', $credential->model) !== 1) {
            throw new AiProviderException(AiProviderException::REQUEST_REJECTED);
        }

        try {
            $response = Http::baseUrl(config('howtoo.providers.groq.base_url'))
                ->acceptJson()
                ->asJson()
                ->withToken($credential->secret)
                ->connectTimeout(5)
                ->timeout(20)
                ->post('/chat/completions', [
                    'model' => $credential->model,
                    'messages' => array_merge([['role' => 'system', 'content' => $prompt->system]], $prompt->messages),
                    'temperature' => 0.15,
                    'max_completion_tokens' => 700,
                    'tool_choice' => 'none',
                ]);
        } catch (ConnectionException) {
            throw new AiProviderException(AiProviderException::TIMEOUT);
        }

        if (!$response->successful()) {
            throw AiProviderException::fromResponse($response);
        }

        $answer = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
        if ($answer === '') {
            throw new AiProviderException(AiProviderException::INVALID_RESPONSE, $response->status());
        }

        return $answer;
    }
}
