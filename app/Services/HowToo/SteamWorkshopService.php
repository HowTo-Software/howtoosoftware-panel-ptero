<?php

namespace Pterodactyl\Services\HowToo;

use Illuminate\Support\Facades\Http;
use Pterodactyl\Exceptions\DisplayException;

final class SteamWorkshopService
{
    private const PROJECT_ZOMBOID_APP_ID = 108600;

    public function __construct(private IntegrationCredentialStore $credentials)
    {
    }

    public function search(string $query, int $page = 1): array
    {
        $key = $this->requireCredential();

        try {
            $response = Http::baseUrl(config('howtoo.providers.steam.base_url'))
                ->acceptJson()
                ->timeout(20)
                ->retry(2, 150)
                ->get('/IPublishedFileService/QueryFiles/v1/', [
                    'key' => $key,
                    'query_type' => 11,
                    'page' => max(1, min($page, 1000)),
                    'numperpage' => 20,
                    'creator_appid' => self::PROJECT_ZOMBOID_APP_ID,
                    'appid' => self::PROJECT_ZOMBOID_APP_ID,
                    'search_text' => $query,
                    'return_details' => true,
                    'return_metadata' => true,
                ])
                ->throw()
                ->json();
        } catch (\Throwable) {
            throw new DisplayException('Steam Workshop search is temporarily unavailable.');
        }

        $items = collect(data_get($response, 'response.publishedfiledetails', []))
            ->map(fn (array $item): array => $this->transform($item))
            ->values()
            ->all();

        return [
            'items' => $items,
            'total' => (int) data_get($response, 'response.total', count($items)),
        ];
    }

    public function details(array $workshopIds): array
    {
        $this->requireCredential();
        $ids = $this->workshopIds($workshopIds);
        if ($ids === []) {
            return [];
        }

        $payload = ['itemcount' => count($ids)];
        foreach ($ids as $index => $id) {
            $payload["publishedfileids[$index]"] = $id;
        }

        try {
            $response = Http::baseUrl(config('howtoo.providers.steam.base_url'))
                ->acceptJson()
                ->asForm()
                ->timeout(20)
                ->retry(2, 150)
                ->post('/ISteamRemoteStorage/GetPublishedFileDetails/v1/', $payload)
                ->throw()
                ->json();
        } catch (\Throwable) {
            throw new DisplayException('Steam Workshop details are temporarily unavailable.');
        }

        return collect(data_get($response, 'response.publishedfiledetails', []))
            ->map(fn (array $item): array => $this->transform($item))
            ->values()
            ->all();
    }

    private function requireCredential(): string
    {
        $key = $this->credentials->secret('steam');
        if (!$this->credentials->isEnabled('steam') || !$key) {
            throw new DisplayException('Steam Workshop is not configured by the administrator.');
        }

        return $key;
    }

    private function transform(array $item): array
    {
        $description = $this->plainText((string) ($item['description'] ?? $item['short_description'] ?? ''));

        return [
            'workshop_id' => (string) ($item['publishedfileid'] ?? ''),
            'name' => mb_substr(trim((string) ($item['title'] ?? 'Untitled mod')), 0, 180),
            'image' => filter_var($item['preview_url'] ?? null, FILTER_VALIDATE_URL) ?: null,
            'description' => mb_substr($description, 0, 1000),
            'mod_ids' => $this->resolveModIds((string) ($item['description'] ?? '')),
            'updated_at' => isset($item['time_updated']) ? (int) $item['time_updated'] : null,
        ];
    }

    private function resolveModIds(string $description): array
    {
        preg_match_all('/\bMod\s*IDs?\s*[:=]\s*([^\r\n]+)/iu', $description, $matches);

        return collect($matches[1])
            ->flatMap(fn (string $value): array => preg_split('/\s*[;,]\s*/', trim($value)) ?: [])
            ->map(fn (string $value): string => trim($value))
            ->filter(fn (string $value): bool => preg_match('/^[A-Za-z0-9_.-]{1,128}$/', $value) === 1)
            ->unique()
            ->values()
            ->all();
    }

    private function plainText(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/\[(?:\/?)[A-Za-z][^\]]*\]/', '', $value) ?? '';
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function workshopIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id): string => trim((string) $id))
            ->filter(fn (string $id): bool => preg_match('/^\d{1,20}$/', $id) === 1)
            ->unique()
            ->take(100)
            ->values()
            ->all();
    }
}
