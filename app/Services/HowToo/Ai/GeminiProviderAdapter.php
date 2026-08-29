<?php

namespace Pterodactyl\Services\HowToo\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

final class GeminiProviderAdapter implements AiProviderAdapter
{
    public function name(): string
    {
        return 'gemini';
    }

    public function generate(AiProviderCredential $credential, AiProviderPrompt $prompt): string
    {
        $model = preg_replace('#^models/#', '', $credential->model);
        if (!is_string($model) || preg_match('/^[A-Za-z0-9._-]{1,120}$/', $model) !== 1) {
            throw new AiProviderException(AiProviderException::REQUEST_REJECTED);
        }

        $contents = array_map(static fn (array $message): array => [
            'role' => $message['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $message['content']]],
        ], $prompt->messages);

        try {
            $response = Http::baseUrl(config('howtoo.providers.gemini.base_url'))
                ->acceptJson()
                ->asJson()
                ->withHeaders(['x-goog-api-key' => $credential->secret])
                ->connectTimeout(5)
                ->timeout($credential->timeoutSeconds)
                ->post("/v1beta/models/{$model}:generateContent", [
                    'systemInstruction' => ['parts' => [['text' => $prompt->system]]],
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 900,
                    ],
                ]);
        } catch (ConnectionException) {
            throw new AiProviderException(AiProviderException::TIMEOUT);
        }

        if (!$response->successful()) {
            throw AiProviderException::fromResponse($response);
        }

        $answer = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));
        if ($answer === '') {
            throw new AiProviderException(AiProviderException::INVALID_RESPONSE, $response->status());
        }

        return $answer;
    }
}
