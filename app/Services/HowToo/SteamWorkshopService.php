<?php

namespace Pterodactyl\Services\HowToo;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Exceptions\DisplayException;
use Illuminate\Http\Client\ConnectionException;

final class SteamWorkshopService
{
    private const PROJECT_ZOMBOID_APP_ID = 108600;
    private const QUERY_TYPE_TEXT_SEARCH = 12;
    private const DEFAULT_PER_PAGE = 30;
    private const MAX_PER_PAGE = 50;

    public function __construct(
        private IntegrationCredentialStore $credentials,
        private ProjectZomboidModIdResolver $modIds,
    ) {
    }

    public function search(string $query, int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): array
    {
        $key = $this->requireCredential();
        $query = trim($query);
        $page = max(1, $page);
        $perPage = max(10, min($perPage, self::MAX_PER_PAGE));

        if ($workshopId = $this->directWorkshopId($query)) {
            $item = $this->details([$workshopId])[0] ?? null;
            if (!$item || $item['workshop_id'] !== $workshopId) {
                throw new DisplayException('The Steam Workshop item was not found or is private/deleted.');
            }

            return $this->result([$item], 1, 1, $perPage, true);
        }

        if ($this->looksLikeUrl($query)) {
            throw new DisplayException('The Steam Workshop URL is invalid.');
        }

        $cacheKey = sprintf('howtoo:steam:search:%s:%d:%d', sha1($query), $page, $perPage);
        $response = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($key, $query, $page, $perPage): array {
            try {
                $response = Http::baseUrl(config('howtoo.providers.steam.base_url'))
                    ->acceptJson()
                    ->connectTimeout(5)
                    ->timeout(20)
                    ->get('/IPublishedFileService/QueryFiles/v1/', $this->queryParameters($key, $query, $page, $perPage));
            } catch (ConnectionException) {
                throw new DisplayException('Steam Workshop is temporarily unavailable.');
            }

            $this->throwForSteamResponse($response->status());
            $payload = $response->json();
            if (!is_array($payload)) {
                throw new DisplayException('Steam Workshop returned an invalid response.');
            }

            return $payload;
        });

        $publishedFiles = data_get($response, 'response.publishedfiledetails', []);
        $publishedFiles = is_array($publishedFiles) ? $publishedFiles : [];
        $items = collect($publishedFiles)
            ->filter('is_array')
            ->map(fn (array $item): array => $this->transform($item))
            ->filter(fn (array $item): bool => $item['workshop_id'] !== '')
            ->unique('workshop_id')
            ->values()
            ->all();
        $total = max(0, (int) data_get($response, 'response.total', count($items)));

        return $this->result($this->rankResults($items, $query), $total, $page, $perPage, false);
    }

    public function details(array $workshopIds): array
    {
        $this->requireCredential();
        $ids = $this->workshopIds($workshopIds);
        if ($ids === []) {
            return [];
        }

        $cacheKey = 'howtoo:steam:details:' . sha1(implode(',', $ids));

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($ids): array {
            $payload = ['itemcount' => count($ids)];
            foreach ($ids as $index => $id) {
                $payload["publishedfileids[$index]"] = $id;
            }

            try {
                $response = Http::baseUrl(config('howtoo.providers.steam.base_url'))
                    ->acceptJson()
                    ->asForm()
                    ->connectTimeout(5)
                    ->timeout(20)
                    ->post('/ISteamRemoteStorage/GetPublishedFileDetails/v1/', $payload);
            } catch (ConnectionException) {
                throw new DisplayException('Steam Workshop details are temporarily unavailable.');
            }

            $this->throwForSteamResponse($response->status());
            $items = data_get($response->json(), 'response.publishedfiledetails', []);
            if (!is_array($items)) {
                throw new DisplayException('Steam Workshop returned an invalid details response.');
            }

            return collect($items)
                ->filter(fn ($item): bool => is_array($item) && (int) ($item['result'] ?? 1) === 1)
                ->map(fn (array $item): array => $this->transform($item))
                ->filter(fn (array $item): bool => $item['workshop_id'] !== '')
                ->values()
                ->all();
        });
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
            'description' => mb_substr($description, 0, 12000),
            'mod_ids' => $this->resolveModIds($item),
            'mod_id_source' => $this->modIdSource($item),
            'metadata' => $item['metadata'] ?? null,
            'kv_tags' => $item['kv_tags'] ?? [],
            'raw_description' => (string) ($item['description'] ?? ''),
            'content_url' => filter_var($item['file_url'] ?? null, FILTER_VALIDATE_URL) ?: null,
            'content_size' => isset($item['file_size']) ? (int) $item['file_size'] : null,
            'updated_at' => isset($item['time_updated']) ? (int) $item['time_updated'] : null,
        ];
    }

    private function resolveModIds(array $item): array
    {
        $metadata = $this->modIds->fromSteamMetadata($item);

        return $metadata !== [] ? $metadata : $this->modIds->fromDescription((string) ($item['description'] ?? ''));
    }

    private function modIdSource(array $item): ?string
    {
        if ($this->modIds->fromSteamMetadata($item) !== []) {
            return 'steam_metadata';
        }

        return $this->modIds->fromDescription((string) ($item['description'] ?? '')) !== []
            ? 'workshop_description'
            : null;
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

    private function queryParameters(string $key, string $query, int $page, int $perPage): array
    {
        return [
            'key' => $key,
            'query_type' => self::QUERY_TYPE_TEXT_SEARCH,
            'page' => $page,
            'numperpage' => $perPage,
            'creator_appid' => self::PROJECT_ZOMBOID_APP_ID,
            'appid' => self::PROJECT_ZOMBOID_APP_ID,
            'search_text' => $query,
            'cache_max_age_seconds' => 120,
            'return_details' => true,
            'return_metadata' => true,
            'return_kv_tags' => true,
            'return_tags' => true,
            'return_previews' => true,
        ];
    }

    private function result(array $items, int $total, int $page, int $perPage, bool $direct): array
    {
        $totalPages = $total === 0 ? 0 : (int) ceil($total / $perPage);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => !$direct && $page < $totalPages,
            ],
            'mode' => $direct ? 'direct' : 'text',
        ];
    }

    private function directWorkshopId(string $query): ?string
    {
        if (preg_match('/^\d{1,20}$/', $query) === 1) {
            return $query;
        }

        if (!$this->looksLikeUrl($query)) {
            return null;
        }

        $parts = parse_url($query);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        if (!in_array($host, ['steamcommunity.com', 'www.steamcommunity.com'], true)
            || $path !== '/sharedfiles/filedetails') {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $parameters);
        $id = trim((string) ($parameters['id'] ?? ''));

        return preg_match('/^\d{1,20}$/', $id) === 1 ? $id : null;
    }

    private function looksLikeUrl(string $query): bool
    {
        return preg_match('#^https?://#i', $query) === 1;
    }

    private function rankResults(array $items, string $query): array
    {
        $needle = $this->searchableText($query);
        $tokens = array_values(array_filter(explode(' ', $needle)));

        return collect($items)
            ->values()
            ->map(function (array $item, int $position) use ($needle, $tokens): array {
                $title = $this->searchableText((string) ($item['name'] ?? ''));
                $description = $this->searchableText((string) ($item['description'] ?? ''));
                $haystack = trim("$title $description");
                $rank = match (true) {
                    $title === $needle => 0,
                    $needle !== '' && str_starts_with($title, $needle) => 100,
                    $needle !== '' && str_contains($title, $needle) => 200,
                    $tokens !== [] && collect($tokens)->every(fn (string $token): bool => str_contains($title, $token)) => 300,
                    $needle !== '' && str_contains($description, $needle) => 400,
                    $tokens !== [] && collect($tokens)->every(fn (string $token): bool => str_contains($haystack, $token)) => 500,
                    default => 1000,
                };

                return ['item' => $item, 'rank' => $rank, 'position' => $position];
            })
            ->sortBy([['rank', 'asc'], ['position', 'asc']])
            ->pluck('item')
            ->values()
            ->all();
    }

    private function searchableText(string $value): string
    {
        $value = mb_strtolower(Str::ascii(trim($value)));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function throwForSteamResponse(int $status): void
    {
        if ($status >= 200 && $status < 300) {
            return;
        }

        throw new DisplayException(match ($status) {
            401, 403 => 'Steam Workshop authentication failed. Contact an administrator.', 404 => 'The Steam Workshop endpoint or item was not found.', 429 => 'Steam Workshop rate limit reached. Please try again shortly.', default => 'Steam Workshop is temporarily unavailable.',
        });
    }
}
