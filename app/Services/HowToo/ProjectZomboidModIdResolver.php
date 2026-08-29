<?php

namespace Pterodactyl\Services\HowToo;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\RequestException;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

final class ProjectZomboidModIdResolver
{
    private const MAX_DEPTH = 5;
    private const MAX_ENTRIES_PER_ITEM = 500;
    private const MAX_MOD_INFO_SIZE = 256 * 1024;
    private const MAX_REMOTE_ARCHIVE_SIZE = 64 * 1024 * 1024;
    private const WORKSHOP_ROOTS = [
        '/.cache/Steam/steamapps/workshop/content/108600',
        '/.cache/steam/steamapps/workshop/content/108600',
        '/.steam/steamapps/workshop/content/108600',
        '/steamapps/workshop/content/108600',
        '/Steam/steamapps/workshop/content/108600',
        '/workshop/content/108600',
        '/steamcmd/steamapps/workshop/content/108600',
    ];

    public function __construct(
        private DaemonFileRepository $files,
        private CacheRepository $cache,
    ) {
    }

    public function resolve(Server $server, string $workshopId, array $steamItem = []): array
    {
        $installed = $this->resolveInstalled($server, [$workshopId])[$workshopId] ?? [];
        if ($installed !== []) {
            return ['mod_ids' => $installed, 'source' => 'mod_info'];
        }

        return $this->resolveSteamItem($steamItem, true);
    }

    public function resolveSteamItem(array $steamItem, bool $allowRemoteArchive = false): array
    {
        $metadata = $this->fromSteamMetadata($steamItem);
        if ($metadata !== []) {
            return ['mod_ids' => $metadata, 'source' => 'steam_metadata'];
        }

        if ($allowRemoteArchive) {
            $remote = $this->fromRemoteArchive($steamItem);
            if ($remote !== []) {
                return ['mod_ids' => $remote, 'source' => 'remote_mod_info'];
            }
        }

        $description = $this->fromDescription((string) ($steamItem['raw_description'] ?? ''));

        return [
            'mod_ids' => $description,
            'source' => $description === [] ? null : 'workshop_description',
        ];
    }

    /** @return array<string, string[]> */
    public function resolveInstalled(Server $server, array $workshopIds): array
    {
        $ids = collect($workshopIds)
            ->map(fn ($id): string => trim((string) $id))
            ->filter(fn (string $id): bool => preg_match('/^\d{1,20}$/', $id) === 1)
            ->unique()
            ->take(100)
            ->values();
        $resolved = [];
        $pending = [];

        foreach ($ids as $id) {
            $cached = $this->cache->get($this->cacheKey($server, $id));
            if (is_array($cached) && $cached !== []) {
                $resolved[$id] = $cached;
            } else {
                $pending[] = $id;
            }
        }

        if ($pending === []) {
            return $resolved;
        }

        $repository = $this->files->setServer($server);
        foreach (self::WORKSHOP_ROOTS as $root) {
            try {
                $entries = $repository->getDirectory($root);
            } catch (DaemonConnectionException $exception) {
                if ($this->isMissingPath($exception)) {
                    continue;
                }

                continue;
            }

            $available = collect($entries)
                ->filter(fn (array $entry): bool => !($entry['file'] ?? true) && !($entry['symlink'] ?? false))
                ->map(fn (array $entry): string => (string) ($entry['name'] ?? ''))
                ->filter(fn (string $name): bool => in_array($name, $pending, true))
                ->values();

            foreach ($available as $id) {
                $modIds = $this->scanWorkshopItem($repository, "$root/$id");
                if ($modIds !== []) {
                    $resolved[$id] = $modIds;
                    $this->cache->put($this->cacheKey($server, $id), $modIds, CarbonImmutable::now()->addMinutes(5));
                    $pending = array_values(array_diff($pending, [$id]));
                }
            }

            if ($pending === []) {
                break;
            }
        }

        return $resolved;
    }

    public function fromSteamMetadata(array $item): array
    {
        $values = [];
        $metadata = $item['metadata'] ?? null;
        if (is_string($metadata) && $metadata !== '') {
            $decoded = json_decode($metadata, true);
            if (is_array($decoded)) {
                array_walk_recursive($decoded, function ($value, $key) use (&$values): void {
                    if (preg_match('/^(?:mod_?)?ids?$/i', (string) $key) === 1) {
                        $values[] = (string) $value;
                    }
                });
            }
            preg_match_all('/(?:^|[\r\n;,])\s*(?:mod_?)?ids?\s*[:=]\s*([^\r\n;,]+)/i', $metadata, $matches);
            $values = array_merge($values, $matches[1]);
        }

        foreach (($item['kv_tags'] ?? []) as $tag) {
            if (is_array($tag) && preg_match('/(?:mod_?)?ids?/i', (string) ($tag['key'] ?? '')) === 1) {
                $values[] = (string) ($tag['value'] ?? '');
            }
        }

        return $this->normalize($values);
    }

    public function fromDescription(string $description): array
    {
        preg_match_all('/\bMod\s*IDs?\s*[:=]\s*([^\r\n\[<]+)/iu', $description, $matches);

        return $this->normalize($matches[1]);
    }

    public function parseModInfo(string $content): array
    {
        preg_match_all('/^\s*id\s*=\s*([^\r\n#;]+)\s*$/im', $content, $matches);

        return $this->normalize($matches[1]);
    }

    private function fromRemoteArchive(array $item): array
    {
        $url = $item['content_url'] ?? null;
        $size = (int) ($item['content_size'] ?? 0);
        if (!is_string($url) || !$this->trustedSteamContentUrl($url)) {
            return [];
        }
        if ($size > self::MAX_REMOTE_ARCHIVE_SIZE || !class_exists(\ZipArchive::class)) {
            return [];
        }

        $temporary = tempnam(storage_path('framework/cache'), 'howtoo-pz-');
        if (!is_string($temporary)) {
            return [];
        }

        try {
            $response = Http::accept('*/*')
                ->connectTimeout(5)
                ->timeout(35)
                ->withOptions([
                    'sink' => $temporary,
                    'allow_redirects' => false,
                    'on_headers' => function ($response): void {
                        $length = (int) $response->getHeaderLine('Content-Length');
                        if ($length > self::MAX_REMOTE_ARCHIVE_SIZE) {
                            throw new \RuntimeException('Steam Workshop archive exceeds the metadata limit.');
                        }
                    },
                    'progress' => function ($downloadTotal, $downloadedBytes): void {
                        if ($downloadedBytes > self::MAX_REMOTE_ARCHIVE_SIZE) {
                            throw new \RuntimeException('Steam Workshop archive exceeded the download limit.');
                        }
                    },
                ])
                ->get($url);

            clearstatcache(true, $temporary);
            if (!$response->successful() || filesize($temporary) > self::MAX_REMOTE_ARCHIVE_SIZE) {
                return [];
            }

            $archive = new \ZipArchive();
            if ($archive->open($temporary) !== true) {
                return [];
            }

            try {
                $modIds = [];
                $files = min($archive->numFiles, 5000);
                for ($index = 0; $index < $files; ++$index) {
                    $stat = $archive->statIndex($index);
                    $path = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
                    if (basename($path) !== 'mod.info' || str_contains($path, '../')) {
                        continue;
                    }
                    if ((int) ($stat['size'] ?? 0) > self::MAX_MOD_INFO_SIZE) {
                        continue;
                    }

                    $content = $archive->getFromIndex($index, self::MAX_MOD_INFO_SIZE);
                    if (is_string($content)) {
                        $modIds = array_merge($modIds, $this->parseModInfo($content));
                    }
                }

                return $this->normalize($modIds);
            } finally {
                $archive->close();
            }
        } catch (\Throwable) {
            return [];
        } finally {
            @unlink($temporary);
        }
    }

    private function scanWorkshopItem(DaemonFileRepository $repository, string $root): array
    {
        $queue = [[$root, 0]];
        $visited = 0;
        $modIds = [];

        while ($queue !== [] && $visited < self::MAX_ENTRIES_PER_ITEM) {
            [$directory, $depth] = array_shift($queue);

            try {
                $entries = $repository->getDirectory($directory);
            } catch (DaemonConnectionException) {
                continue;
            }

            foreach ($entries as $entry) {
                if (++$visited > self::MAX_ENTRIES_PER_ITEM || ($entry['symlink'] ?? false)) {
                    continue;
                }

                $name = (string) ($entry['name'] ?? '');
                if ($name === '' || basename($name) !== $name || in_array($name, ['.', '..'], true)) {
                    continue;
                }

                $path = "$directory/$name";
                if (!($entry['file'] ?? true)) {
                    if ($depth < self::MAX_DEPTH) {
                        $queue[] = [$path, $depth + 1];
                    }
                    continue;
                }

                if (strcasecmp($name, 'mod.info') !== 0) {
                    continue;
                }

                try {
                    $modIds = array_merge($modIds, $this->parseModInfo(
                        $repository->getContent($path, self::MAX_MOD_INFO_SIZE),
                    ));
                } catch (\Throwable) {
                    // A broken mod.info must not prevent other mods in the item from being resolved.
                }
            }
        }

        return $this->normalize($modIds);
    }

    private function normalize(array $values): array
    {
        return collect($values)
            ->flatMap(fn ($value): array => preg_split('/\s*[;,]\s*/', trim((string) $value)) ?: [])
            ->map(fn (string $value): string => trim($value))
            ->filter(fn (string $value): bool => preg_match('/^[A-Za-z0-9_.-]{1,128}$/', $value) === 1)
            ->unique()
            ->take(100)
            ->values()
            ->all();
    }

    private function cacheKey(Server $server, string $workshopId): string
    {
        return "howtoo:pz-mod-ids:{$server->uuid}:$workshopId";
    }

    private function trustedSteamContentUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || !is_string($parts['host'] ?? null)) {
            return false;
        }

        $host = mb_strtolower($parts['host']);

        return $host === 'steamusercontent.com'
            || str_ends_with($host, '.steamusercontent.com')
            || $host === 'steamcontent.com'
            || str_ends_with($host, '.steamcontent.com')
            || $host === 'steamusercontent-a.akamaihd.net';
    }

    private function isMissingPath(DaemonConnectionException $exception): bool
    {
        $previous = $exception->getPrevious();
        if (!$previous instanceof RequestException || !$previous->hasResponse()) {
            return false;
        }

        $response = $previous->getResponse();
        if ($response->getStatusCode() === 404) {
            return true;
        }

        if ($response->getStatusCode() !== 500) {
            return false;
        }

        $body = mb_strtolower($response->getBody()->__toString());

        return str_contains($body, 'no such file or directory')
            || str_contains($body, 'file does not exist')
            || str_contains($body, 'path does not exist')
            || str_contains($body, 'not found');
    }
}
