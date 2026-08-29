<?php

namespace Pterodactyl\Services\HowToo;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\HowTooIntegration;
use Pterodactyl\Models\HowTooIntegrationKey;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Pterodactyl\Contracts\HowToo\AiCredentialRepository;
use Pterodactyl\Services\HowToo\Ai\AiProviderCredential;

final class IntegrationCredentialStore implements AiCredentialRepository
{
    public const PROVIDERS = ['gemini', 'groq', 'steam', 'curseforge'];
    public const AI_PROVIDERS = ['gemini', 'groq'];

    public function __construct(private Encrypter $encrypter)
    {
    }

    public function status(): array
    {
        $records = HowTooIntegration::query()
            ->with(['keys' => fn ($query) => $query->orderBy('priority')->orderBy('id')])
            ->get()
            ->keyBy('provider');

        return collect(self::PROVIDERS)->mapWithKeys(function (string $provider) use ($records) {
            /** @var HowTooIntegration|null $record */
            $record = $records->get($provider);
            $hasRecord = $record instanceof HowTooIntegration;
            $environmentConfigured = filled(config("howtoo.providers.$provider.secret"));
            $environmentEnabled = $hasRecord ? (bool) $record->environment_key_enabled : true;
            $keys = $hasRecord ? $record->keys : collect();
            $databaseConfigured = $keys->contains(fn (HowTooIntegrationKey $key): bool => $key->enabled && filled($key->getRawOriginal('secret')));

            return [$provider => [
                'enabled' => $hasRecord ? $record->enabled : (bool) config("howtoo.providers.$provider.enabled", false),
                'configured' => $databaseConfigured
                    || ($environmentEnabled && $environmentConfigured)
                    || ($hasRecord && filled($record->getRawOriginal('secret'))),
                'model' => $hasRecord && filled($record->model) ? $record->model : config("howtoo.providers.$provider.model"),
                'priority' => $hasRecord ? $record->priority : (int) config("howtoo.providers.$provider.priority", 100),
                'timeout_seconds' => $hasRecord
                    ? $record->timeout_seconds
                    : (int) config("howtoo.providers.$provider.timeout_seconds", 25),
                'environment_configured' => $environmentConfigured,
                'environment_key_enabled' => $environmentEnabled,
                'keys' => $keys->map(fn (HowTooIntegrationKey $key): array => [
                    'id' => $key->id,
                    'name' => $key->name,
                    'enabled' => $key->enabled,
                    'priority' => $key->priority,
                    'cooldown_until' => $key->cooldown_until?->toIso8601String(),
                    'cooling_down' => $key->cooldown_until?->isFuture() ?? false,
                    'failure_count' => $key->failure_count,
                    'last_failure_reason' => $key->last_failure_reason,
                ])->values()->all(),
            ]];
        })->all();
    }

    public function secret(string $provider): ?string
    {
        $this->assertProvider($provider);
        $record = HowTooIntegration::query()->where('provider', $provider)->first();
        $keys = HowTooIntegrationKey::query()
            ->where('provider', $provider)
            ->where('enabled', true)
            ->where(function ($query) {
                $query->whereNull('cooldown_until')->orWhere('cooldown_until', '<=', now());
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
        $secret = null;

        foreach ($keys as $key) {
            try {
                $secret = $this->decryptSecret($key->getRawOriginal('secret'));
            } catch (DecryptException) {
                $this->quarantineUndecryptableKey($key);
                continue;
            }

            if (filled($secret)) {
                break;
            }
        }

        if (!$secret && $record instanceof HowTooIntegration) {
            try {
                $secret = $this->decryptSecret($record->getRawOriginal('secret'));
            } catch (DecryptException) {
                $secret = null;
            }
        }

        $environmentEnabled = $record instanceof HowTooIntegration ? $record->environment_key_enabled : true;
        if (!$secret && $environmentEnabled) {
            $secret = config("howtoo.providers.$provider.secret");
        }

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

    public function orderedAiProviders(): array
    {
        $records = HowTooIntegration::query()->whereIn('provider', self::AI_PROVIDERS)->get()->keyBy('provider');

        return collect(self::AI_PROVIDERS)
            ->map(function (string $provider) use ($records): ?array {
                /** @var HowTooIntegration|null $record */
                $record = $records->get($provider);
                $enabled = (bool) ($record->enabled ?? config("howtoo.providers.$provider.enabled", false));
                $model = $record?->model ?: config("howtoo.providers.$provider.model");
                if (!$enabled || !is_string($model) || $model === '') {
                    return null;
                }

                return [
                    'name' => $provider,
                    'model' => $model,
                    'priority' => (int) ($record->priority ?? config("howtoo.providers.$provider.priority", 100)),
                    'timeout_seconds' => max(5, min(
                        (int) ($record->timeout_seconds ?? config("howtoo.providers.$provider.timeout_seconds", 25)),
                        55,
                    )),
                ];
            })
            ->filter()
            ->sortBy([['priority', 'asc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    public function availableAiCredentials(string $provider, string $model, int $timeoutSeconds): array
    {
        $this->assertAiProvider($provider);
        $record = HowTooIntegration::query()->where('provider', $provider)->first();
        $keys = HowTooIntegrationKey::query()
            ->where('provider', $provider)
            ->where('enabled', true)
            ->where(function ($query) {
                $query->whereNull('cooldown_until')->orWhere('cooldown_until', '<=', now());
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
        $credentials = collect();

        foreach ($keys as $key) {
            try {
                $secret = $this->decryptSecret($key->getRawOriginal('secret'));
            } catch (DecryptException) {
                $this->quarantineUndecryptableKey($key);
                continue;
            }

            if (filled($secret)) {
                $credentials->push(new AiProviderCredential(
                    $provider,
                    $model,
                    $secret,
                    $key->id,
                    false,
                    $timeoutSeconds,
                ));
            }
        }

        $environmentSecret = config("howtoo.providers.$provider.secret");
        $environmentEnabled = $record instanceof HowTooIntegration ? $record->environment_key_enabled : true;
        if ($environmentEnabled
            && filled($environmentSecret)
            && !Cache::has($this->environmentCooldownKey($provider))) {
            $credentials->push(new AiProviderCredential(
                $provider,
                $model,
                $environmentSecret,
                null,
                true,
                $timeoutSeconds,
            ));
        }

        return $credentials->all();
    }

    public function putOnCooldown(AiProviderCredential $credential, int $seconds, string $reason): void
    {
        $seconds = max(30, min($seconds, 3600));
        if ($credential->keyId) {
            HowTooIntegrationKey::query()->whereKey($credential->keyId)->update([
                'failure_count' => DB::raw('failure_count + 1'),
                'last_failure_reason' => mb_substr($reason, 0, 32),
                'last_failed_at' => now(),
                'cooldown_until' => now()->addSeconds($seconds),
                'updated_at' => now(),
            ]);

            return;
        }

        Cache::put($this->environmentCooldownKey($credential->provider), $reason, now()->addSeconds($seconds));
    }

    public function markHealthy(AiProviderCredential $credential): void
    {
        if ($credential->keyId) {
            HowTooIntegrationKey::query()
                ->whereKey($credential->keyId)
                ->where(function ($query) {
                    $query->where('failure_count', '>', 0)->orWhereNotNull('cooldown_until');
                })
                ->update([
                    'failure_count' => 0,
                    'last_failure_reason' => null,
                    'last_failed_at' => null,
                    'cooldown_until' => null,
                    'updated_at' => now(),
                ]);

            return;
        }

        Cache::forget($this->environmentCooldownKey($credential->provider));
    }

    public function update(array $providers): void
    {
        DB::transaction(function () use ($providers): void {
            foreach (self::PROVIDERS as $provider) {
                $input = Arr::get($providers, $provider, []);
                $record = HowTooIntegration::query()->firstOrNew(['provider' => $provider]);
                $record->enabled = (bool) ($input['enabled'] ?? false);
                $record->priority = (int) ($input['priority'] ?? config("howtoo.providers.$provider.priority", 100));
                $record->environment_key_enabled = (bool) ($input['environment_key_enabled'] ?? false);
                $record->timeout_seconds = max(5, min((int) ($input['timeout_seconds'] ?? 25), 55));
                $record->model = filled($input['model'] ?? null) ? trim($input['model']) : null;
                $record->save();

                if (filled($input['secret'] ?? null)) {
                    HowTooIntegrationKey::query()->create([
                        'provider' => $provider,
                        'name' => 'Primary',
                        'enabled' => true,
                        'priority' => 10,
                        'secret' => trim($input['secret']),
                    ]);
                }

                foreach (($input['keys'] ?? []) as $keyInput) {
                    $this->updateKey($provider, $keyInput);
                }
            }
        });
    }

    private function updateKey(string $provider, array $input): void
    {
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id !== false) {
            $key = HowTooIntegrationKey::query()->where('provider', $provider)->whereKey($id)->first();
            if (!$key) {
                throw ValidationException::withMessages(['providers' => 'An integration key does not belong to this provider.']);
            }

            if ((bool) ($input['delete'] ?? false)) {
                $key->delete();

                return;
            }
        } else {
            if ((bool) ($input['delete'] ?? false)) {
                return;
            }
            if (!filled($input['secret'] ?? null)) {
                throw ValidationException::withMessages(['providers' => 'A secret is required for each new integration key.']);
            }

            $key = new HowTooIntegrationKey(['provider' => $provider]);
        }

        $shouldResetCooldown = $id !== false && !$key->enabled && (bool) ($input['enabled'] ?? false);
        $key->name = trim((string) ($input['name'] ?? 'API Key'));
        $key->enabled = (bool) ($input['enabled'] ?? false);
        $key->priority = (int) ($input['priority'] ?? 100);

        if (filled($input['secret'] ?? null)) {
            $key->secret = trim($input['secret']);
            $key->failure_count = 0;
            $key->last_failure_reason = null;
            $key->last_failed_at = null;
            $key->cooldown_until = null;
        } elseif ($shouldResetCooldown) {
            $key->failure_count = 0;
            $key->last_failure_reason = null;
            $key->last_failed_at = null;
            $key->cooldown_until = null;
        }

        $key->save();
    }

    private function environmentCooldownKey(string $provider): string
    {
        return "howtoo:ai:environment-cooldown:$provider";
    }

    private function quarantineUndecryptableKey(HowTooIntegrationKey $key): void
    {
        HowTooIntegrationKey::query()->whereKey($key->id)->update([
            'failure_count' => DB::raw('failure_count + 1'),
            'last_failure_reason' => 'invalid_credential',
            'last_failed_at' => now(),
            'cooldown_until' => now()->addHour(),
            'updated_at' => now(),
        ]);
    }

    private function decryptSecret(mixed $encrypted): ?string
    {
        if (!is_string($encrypted) || $encrypted === '') {
            return null;
        }

        $secret = $this->encrypter->decrypt($encrypted, false);

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    private function assertProvider(string $provider): void
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new \InvalidArgumentException('Unsupported integration provider.');
        }
    }

    private function assertAiProvider(string $provider): void
    {
        if (!in_array($provider, self::AI_PROVIDERS, true)) {
            throw new \InvalidArgumentException('Unsupported AI provider.');
        }
    }
}
