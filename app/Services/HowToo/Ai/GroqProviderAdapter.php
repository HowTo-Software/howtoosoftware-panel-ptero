<?php

namespace Pterodactyl\Services\HowToo\Ai;

use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;

final class GroqProviderAdapter implements StreamingAiProviderAdapter
{
    public function name(): string
    {
        return 'groq';
    }

    public function generate(AiProviderCredential $credential, AiProviderPrompt $prompt): string
    {
        try {
            $response = Http::baseUrl(config('howtoo.providers.groq.base_url'))
                ->acceptJson()
                ->asJson()
                ->withToken($credential->secret)
                ->connectTimeout(5)
                ->timeout($credential->timeoutSeconds)
                ->post('/chat/completions', $this->payload($credential, $prompt));
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

    public function stream(AiProviderCredential $credential, AiProviderPrompt $prompt, callable $onDelta): string
    {
        try {
            $response = Http::baseUrl(config('howtoo.providers.groq.base_url'))
                ->accept('text/event-stream')
                ->asJson()
                ->withToken($credential->secret)
                ->connectTimeout(5)
                ->timeout($credential->timeoutSeconds)
                ->withOptions(['stream' => true])
                ->post('/chat/completions', array_merge($this->payload($credential, $prompt), ['stream' => true]));

            if (!$response->successful()) {
                throw AiProviderException::fromResponse($response);
            }

            try {
                $answer = (new AiSseStreamDecoder())->consume(
                    $response->toPsrResponse()->getBody(),
                    static function (string $payload): ?string {
                        if ($payload === '[DONE]') {
                            return null;
                        }
                        $data = json_decode($payload, true);

                        return is_array($data) ? data_get($data, 'choices.0.delta.content') : null;
                    },
                    $onDelta,
                );
            } finally {
                $response->close();
            }
        } catch (AiProviderException $exception) {
            throw $exception;
        } catch (ConnectionException|TransferException $exception) {
            throw AiProviderException::fromTransport($exception);
        }

        if ($answer === '') {
            throw new AiProviderException(AiProviderException::INVALID_RESPONSE, $response->status());
        }

        return $answer;
    }

    private function payload(AiProviderCredential $credential, AiProviderPrompt $prompt): array
    {
        $this->validateModel($credential);

        return [
            'model' => $credential->model,
            'messages' => array_merge([['role' => 'system', 'content' => $prompt->system]], $prompt->messages),
            'temperature' => 0.15,
            'max_completion_tokens' => max(256, min((int) config('howtoo.assistant.max_output_tokens', 1000), 2048)),
            'tool_choice' => 'none',
        ];
    }

    private function validateModel(AiProviderCredential $credential): void
    {
        if (preg_match('/^[A-Za-z0-9._:\/-]{1,120}$/', $credential->model) !== 1) {
            throw new AiProviderException(AiProviderException::REQUEST_REJECTED);
        }
    }
}
