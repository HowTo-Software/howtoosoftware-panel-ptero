<?php

namespace Pterodactyl\Services\HowToo;

use Illuminate\Support\Arr;
use Pterodactyl\Models\HowTooIntegration;

final class IntegrationCredentialStore
{
    public const PROVIDERS = ['gemini', 'groq', 'steam', 'curseforge'];

    public function status(): array
    {
        $records = HowTooIntegration::query()->get()->keyBy('provider');

        return collect(self::PROVIDERS)->mapWithKeys(function (string $provider) use ($records) {
            /** @var HowTooIntegration|null $record */
            $record = $records->get($provider);

            return [$provider => [
                'enabled' => $record->enabled ?? (bool) config("howtoo.providers.$provider.enabled", false),
                'configured' => $this->hasSecret($provider, $record),
                'model' => $record->model ?? config("howtoo.providers.$provider.model"),
            ]];
        })->all();
    }

    public function secret(string $provider): ?string
    {
        $this->assertProvider($provider);
        $record = HowTooIntegration::query()->where('provider', $provider)->first();
        $secret = $record?->secret ?: config("howtoo.providers.$provider.secret");

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    public function isEnabled(string $provider): bool
    {
        $this->assertProvider($provider);
        $record = HowTooIntegration::query()->where('provider', $provider)->first();

        return (bool) ($record->enabled ?? config("howtoo.providers.$provider.enabled", false));
    }

    public function model(string $provider): ?string
    {
        $this->assertProvider($provider);
        $record = HowTooIntegration::query()->where('provider', $provider)->first();
        $model = $record?->model ?: config("howtoo.providers.$provider.model");

        return is_string($model) && $model !== '' ? $model : null;
    }

    public function update(array $providers): void
    {
        foreach (self::PROVIDERS as $provider) {
            $input = Arr::get($providers, $provider, []);
            $record = HowTooIntegration::query()->firstOrNew(['provider' => $provider]);
            $record->enabled = (bool) ($input['enabled'] ?? false);
            $record->model = filled($input['model'] ?? null) ? trim($input['model']) : null;

            // A blank secret means "keep the existing value". It is never rendered
            // back into the form and therefore never enters an HTML response.
            if (filled($input['secret'] ?? null)) {
                $record->secret = trim($input['secret']);
            }

            $record->save();
        }
    }

    private function hasSecret(string $provider, ?HowTooIntegration $record): bool
    {
        return filled($record?->secret) || filled(config("howtoo.providers.$provider.secret"));
    }

    private function assertProvider(string $provider): void
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new \InvalidArgumentException('Unsupported integration provider.');
        }
    }
}
