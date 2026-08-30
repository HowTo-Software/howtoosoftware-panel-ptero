<?php

namespace Pterodactyl\Services\HowToo\Ai;

use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;

final class OllamaProviderAdapter implements StreamingAiProviderAdapter
{
    public function name(): string
    {
        return 'ollama';
    }

    public function generate(AiProviderCredential $credential, AiProviderPrompt $prompt): string
    {
        try {
            $response = $this->request($credential)
                ->acceptJson()
                ->post(OllamaEndpoint::url($credential->baseUrl, 'chat'), $this->payload($credential, $prompt, false));
        } catch (ConnectionException|TransferException $exception) {
            throw AiProviderException::fromTransport($exception);
        } catch (\InvalidArgumentException) {
            throw new AiProviderException(AiProviderException::REQUEST_REJECTED);
        }

        if (!$response->successful()) {
            throw AiProviderException::fromResponse($response);
        }

        $answer = trim((string) data_get($response->json(), 'message.content', ''));
        if ($answer === '') {
            throw new AiProviderException(AiProviderException::INVALID_RESPONSE, $response->status());
        }

        return $answer;
    }

    public function stream(AiProviderCredential $credential, AiProviderPrompt $prompt, callable $onDelta): string
    {
        try {
            $response = $this->request($credential)
                ->accept('application/x-ndjson')
                ->withOptions(['stream' => true])
                ->post(OllamaEndpoint::url($credential->baseUrl, 'chat'), $this->payload($credential, $prompt, true));

            if (!$response->successful()) {
                throw AiProviderException::fromResponse($response);
            }

            try {
                $answer = (new OllamaNdjsonStreamDecoder())->consume($response->toPsrResponse()->getBody(), $onDelta);
            } finally {
                $response->close();
            }
        } catch (AiStreamCancelledException|AiProviderException $exception) {
            throw $exception;
        } catch (ConnectionException|TransferException $exception) {
            throw AiProviderException::fromTransport($exception);
        } catch (\InvalidArgumentException) {
            throw new AiProviderException(AiProviderException::REQUEST_REJECTED);
        }

        if ($answer === '') {
            throw new AiProviderException(AiProviderException::INVALID_RESPONSE, $response->status());
        }

        return $answer;
    }

    private function request(AiProviderCredential $credential): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::asJson()
            ->connectTimeout(5)
            ->timeout($credential->timeoutSeconds);

        return $credential->secret !== '' ? $request->withToken($credential->secret) : $request;
    }

    private function payload(AiProviderCredential $credential, AiProviderPrompt $prompt, bool $stream): array
    {
        if (preg_match('~^[A-Za-z0-9._:/-]{1,120}$~', $credential->model) !== 1) {
            throw new AiProviderException(AiProviderException::REQUEST_REJECTED);
        }

        return [
            'model' => $credential->model,
            'messages' => array_merge([['role' => 'system', 'content' => $prompt->system]], $prompt->messages),
            'stream' => $stream,
            'options' => [
                'temperature' => 0.2,
                'num_predict' => max(256, min((int) config('howtoo.assistant.max_output_tokens', 1000), 4096)),
            ],
        ];
    }
}
