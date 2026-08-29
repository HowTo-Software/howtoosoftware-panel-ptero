<?php

namespace Pterodactyl\Services\HowToo\Ai;

use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;

final class GeminiProviderAdapter implements StreamingAiProviderAdapter
{
    public function name(): string
    {
        return 'gemini';
    }

    public function generate(AiProviderCredential $credential, AiProviderPrompt $prompt): string
    {
        $model = $this->model($credential);

        try {
            $response = Http::baseUrl(config('howtoo.providers.gemini.base_url'))
                ->acceptJson()
                ->asJson()
                ->withHeaders(['x-goog-api-key' => $credential->secret])
                ->connectTimeout(5)
                ->timeout($credential->timeoutSeconds)
                ->post("/v1beta/models/{$model}:generateContent", $this->payload($prompt));
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

    public function stream(AiProviderCredential $credential, AiProviderPrompt $prompt, callable $onDelta): string
    {
        $model = $this->model($credential);

        try {
            $response = Http::baseUrl(config('howtoo.providers.gemini.base_url'))
                ->accept('text/event-stream')
                ->asJson()
                ->withHeaders(['x-goog-api-key' => $credential->secret])
                ->connectTimeout(5)
                ->timeout($credential->timeoutSeconds)
                ->withOptions(['stream' => true])
                ->post("/v1beta/models/{$model}:streamGenerateContent?alt=sse", $this->payload($prompt));

            if (!$response->successful()) {
                throw AiProviderException::fromResponse($response);
            }

            try {
                $answer = (new AiSseStreamDecoder())->consume(
                    $response->toPsrResponse()->getBody(),
                    static function (string $payload): ?string {
                        $data = json_decode($payload, true);
                        $parts = is_array($data) ? data_get($data, 'candidates.0.content.parts', []) : [];

                        return is_array($parts)
                            ? collect($parts)->pluck('text')->filter('is_string')->implode('')
                            : null;
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

    private function model(AiProviderCredential $credential): string
    {
        $model = preg_replace('#^models/#', '', $credential->model);
        if (!is_string($model) || preg_match('/^[A-Za-z0-9._-]{1,120}$/', $model) !== 1) {
            throw new AiProviderException(AiProviderException::REQUEST_REJECTED);
        }

        return $model;
    }

    private function payload(AiProviderPrompt $prompt): array
    {
        return [
            'systemInstruction' => ['parts' => [['text' => $prompt->system]]],
            'contents' => array_map(static fn (array $message): array => [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content']]],
            ], $prompt->messages),
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 900,
            ],
        ];
    }
}
