<?php

namespace Pterodactyl\Services\HowToo\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Exceptions\DisplayException;
use Illuminate\Http\Client\ConnectionException;
use Pterodactyl\Services\HowToo\IntegrationCredentialStore;

final class OllamaModelDiscoveryService
{
    private const CACHE_KEY = 'howtoo:ollama:model-discovery';

    public function __construct(private IntegrationCredentialStore $credentials)
    {
    }

    public function refresh(): array
    {
        $baseUrl = $this->credentials->baseUrl('ollama');
        $secret = $this->credentials->secret('ollama');
        if (!$baseUrl || !$secret) {
            throw new DisplayException('Save the Ollama base URL and API key before refreshing models.');
        }

        try {
            $result = $this->discover($baseUrl, $secret);
        } catch (DisplayException $exception) {
            Cache::put(self::CACHE_KEY, ['connected' => false, 'models' => []], now()->addMinute());
            throw $exception;
        }

        Cache::put(self::CACHE_KEY, $result, now()->addMinutes(10));

        return $result;
    }

    public function discover(string $baseUrl, string $secret): array
    {
        try {
            $response = Http::acceptJson()
                ->withToken($secret)
                ->connectTimeout(5)
                ->timeout(15)
                ->get(OllamaEndpoint::url($baseUrl, 'tags'));
        } catch (ConnectionException|TransferException) {
            throw new DisplayException('Could not connect to the Ollama server.');
        } catch (\InvalidArgumentException) {
            throw new DisplayException('The configured Ollama base URL is invalid.');
        }

        if (!$response->successful()) {
            $message = match ($response->status()) {
                401, 403 => 'Ollama rejected the configured API key.',
                404 => 'The Ollama /api/tags endpoint was not found.',
                429 => 'Ollama is rate limiting model discovery. Try again shortly.',
                default => 'Ollama is temporarily unavailable.',
            };
            throw new DisplayException($message);
        }

        $models = collect($response->json('models', []))
            ->filter(fn ($model): bool => is_array($model))
            ->map(fn (array $model): string => trim((string) ($model['name'] ?? $model['model'] ?? '')))
            ->filter(fn (string $model): bool => preg_match('~^[A-Za-z0-9._:/-]{1,120}$~', $model) === 1)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($models === []) {
            throw new DisplayException('Ollama responded successfully, but no models are installed.');
        }

        return ['connected' => true, 'models' => $models, 'checked_at' => now()->toIso8601String()];
    }

    public function cached(): array
    {
        return Cache::get(self::CACHE_KEY, ['connected' => null, 'models' => [], 'checked_at' => null]);
    }

    public function forgetCachedModels(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
