<?php

namespace Pterodactyl\Services\HowToo;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Exceptions\DisplayException;

final class SteamWorkshopService
{
    private const PROJECT_ZOMBOID_APP_ID = 108600;
    private const QUERY_TYPE_TEXT_SEARCH = 12;
    private const SEARCH_RESULTS_PER_PAGE = 50;
    private const SEARCH_PAGE_DEPTH = 3;
    private const SEARCH_RESULT_LIMIT = 60;

    public function __construct(
        private IntegrationCredentialStore $credentials,
        private ProjectZomboidModIdResolver $modIds,
    ) {
    }

    public function search(string $query, int $page = 1): array
    {
        $key = $this->requireCredential();
        $query = trim($query);
        $page = max(1, min($page, 1000));
        $items = [];
        $total = 0;

        try {
            for ($offset = 0; $offset < self::SEARCH_PAGE_DEPTH && $page + $offset <= 1000; ++$offset) {
                $response = Http::baseUrl(config('howtoo.providers.steam.base_url'))
                    ->acceptJson()
                    ->timeout(20)
                    ->retry(2, 150)
                    ->get('/IPublishedFileService/QueryFiles/v1/', $this->queryParameters($key, $query, $page + $offset))
                    ->throw()
                    ->json();

                $publishedFiles = data_get($response, 'response.publishedfiledetails', []);
                if (!is_array($publishedFiles)) {
                    $publishedFiles = [];
                }

                if ($offset === 0) {
                    $total = (int) data_get($response, 'response.total', count($publishedFiles));
                }

                foreach ($publishedFiles as $publishedFile) {
                    if (!is_array($publishedFile)) {
                        continue;
                    }

                    $item = $this->transform($publishedFile);
                    if ($item['workshop_id'] !== '') {
                        $items[$item['workshop_id']] = $item;
                    }
                }

                if ($this->containsExactTitle($items, $query) || count($publishedFiles) < self::SEARCH_RESULTS_PER_PAGE) {
                    break;
                }
            }
        } catch (\Throwable) {
            throw new DisplayException('Steam Workshop search is temporarily unavailable.');
        }

        return [
            'items' => array_slice($this->rankResults(array_values($items), $query), 0, self::SEARCH_RESULT_LIMIT),
            'total' => $total,
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

        return $metadata !== []
            ? $metadata
            : $this->modIds->fromDescription((string) ($item['description'] ?? ''));
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

    private function queryParameters(string $key, string $query, int $page): array
    {
        return [
            'key' => $key,
            'query_type' => self::QUERY_TYPE_TEXT_SEARCH,
            'page' => $page,
            'numperpage' => self::SEARCH_RESULTS_PER_PAGE,
            'creator_appid' => self::PROJECT_ZOMBOID_APP_ID,
            'appid' => self::PROJECT_ZOMBOID_APP_ID,
            'search_text' => $query,
            'cache_max_age_seconds' => 0,
            'return_details' => true,
            'return_metadata' => true,
            'return_kv_tags' => true,
            'return_tags' => true,
            'return_previews' => true,
        ];
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

    private function containsExactTitle(array $items, string $query): bool
    {
        $needle = $this->searchableText($query);
        if ($needle === '') {
            return false;
        }

        return collect($items)->contains(
            fn (array $item): bool => $this->searchableText((string) ($item['name'] ?? '')) === $needle,
        );
    }

    private function searchableText(string $value): string
    {
        $value = mb_strtolower(Str::ascii(trim($value)));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
