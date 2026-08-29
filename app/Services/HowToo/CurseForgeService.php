<?php

namespace Pterodactyl\Services\HowToo;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Client\PendingRequest;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

final class CurseForgeService
{
    private const MINECRAFT_GAME_ID = 432;
    private const MINECRAFT_MOD_CLASS_ID = 6;

    public function __construct(
        private IntegrationCredentialStore $credentials,
        private ServerGameContext $gameContext,
        private DaemonFileRepository $files,
    ) {
    }

    public function search(Server $server, string $query, int $index = 0): array
    {
        $context = $this->compatibleContext($server);

        try {
            $response = $this->client()->get('/v1/mods/search', [
                'gameId' => self::MINECRAFT_GAME_ID,
                'classId' => self::MINECRAFT_MOD_CLASS_ID,
                'searchFilter' => $query,
                'gameVersion' => $context['minecraft_version'],
                'modLoaderType' => $context['mod_loader_type'],
                'sortField' => 2,
                'sortOrder' => 'desc',
                'index' => max(0, min($index, 9980)),
                'pageSize' => 20,
            ])->throw()->json();
        } catch (\Throwable) {
            throw new DisplayException('CurseForge search is temporarily unavailable.');
        }

        return [
            'items' => collect($response['data'] ?? [])->map(fn (array $mod): array => $this->transformMod($mod))->all(),
            'pagination' => $response['pagination'] ?? null,
            'compatibility' => $this->publicCompatibility($context),
        ];
    }

    public function mod(Server $server, int $modId): array
    {
        $this->compatibleContext($server);

        try {
            $response = $this->client()->get("/v1/mods/$modId")->throw()->json();
            $description = $this->client()->get("/v1/mods/$modId/description")->throw()->json();
        } catch (\Throwable) {
            throw new DisplayException('Could not load this CurseForge project.');
        }

        return array_merge($this->transformMod((array) ($response['data'] ?? [])), [
            'description' => mb_substr(trim(strip_tags((string) ($description['data'] ?? ''))), 0, 4000),
        ]);
    }

    public function compatibleFiles(Server $server, int $modId): array
    {
        $context = $this->compatibleContext($server);

        try {
            $response = $this->client()->get("/v1/mods/$modId/files", [
                'gameVersion' => $context['minecraft_version'],
                'modLoaderType' => $context['mod_loader_type'],
                'pageSize' => 50,
            ])->throw()->json();
        } catch (\Throwable) {
            throw new DisplayException('Could not load compatible CurseForge files.');
        }

        return collect($response['data'] ?? [])
            ->filter(fn (array $file): bool => ($file['isAvailable'] ?? false) && $this->matchesVersion($file, $context))
            ->map(fn (array $file): array => $this->transformFile($file))
            ->values()
            ->all();
    }

    public function installed(Server $server): array
    {
        $this->compatibleContext($server);

        try {
            $entries = $this->files->setServer($server)->getDirectory('/mods');
        } catch (DaemonConnectionException $exception) {
            if ($this->isNotFound($exception)) {
                return [];
            }

            throw new DisplayException('Could not read the server mods directory.');
        }

        return collect($entries)
            ->filter(fn (array $entry): bool => ($entry['file'] ?? true) && preg_match('/\.(jar|zip)$/i', (string) ($entry['name'] ?? '')) === 1)
            ->map(fn (array $entry): array => [
                'name' => (string) $entry['name'],
                'size' => (int) ($entry['size'] ?? 0),
                'modified_at' => $entry['modified'] ?? null,
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    public function install(Server $server, int $modId, int $fileId): array
    {
        $files = $this->compatibleFiles($server, $modId);
        $file = collect($files)->firstWhere('id', $fileId);
        if (!$file) {
            throw new DisplayException('The selected file is not compatible with this server version and mod loader.');
        }

        $url = $file['download_url'];
        if (!$url || !$this->isTrustedDownloadUrl($url)) {
            throw new DisplayException('This author does not provide a supported server download for this file.');
        }

        $filename = $this->safeFilename($file['file_name']);
        $repository = $this->files->setServer($server);
        try {
            $entries = $repository->getDirectory('/mods');
        } catch (DaemonConnectionException $exception) {
            if (!$this->isNotFound($exception)) {
                throw new DisplayException('Could not access the server mods directory.');
            }

            $repository->createDirectory('mods', '/');
            $entries = [];
        }

        if (collect($entries)->contains(fn (array $entry): bool => strcasecmp((string) ($entry['name'] ?? ''), $filename) === 0)) {
            throw new DisplayException('This mod file is already installed.');
        }

        $repository->pull($url, '/mods', [
            'filename' => $filename,
            'foreground' => true,
        ]);

        return ['file_name' => $filename, 'installed' => true];
    }

    private function compatibleContext(Server $server): array
    {
        $context = $this->gameContext->for($server);
        if (!$context['minecraft']) {
            throw new DisplayException('CurseForge Mods is currently available for Minecraft servers only.');
        }
        if (!$context['minecraft_version'] || !$context['mod_loader_type']) {
            throw new DisplayException('The Minecraft version or mod loader could not be detected from this server egg.');
        }

        return $context;
    }

    private function client(): PendingRequest
    {
        $key = $this->credentials->secret('curseforge');
        if (!$this->credentials->isEnabled('curseforge') || !$key) {
            throw new DisplayException('CurseForge is not configured by the administrator.');
        }

        return Http::baseUrl(config('howtoo.providers.curseforge.base_url'))
            ->acceptJson()
            ->withHeaders(['x-api-key' => $key])
            ->timeout(25)
            ->retry(2, 200);
    }

    private function transformMod(array $mod): array
    {
        return [
            'id' => (int) ($mod['id'] ?? 0),
            'name' => mb_substr(trim((string) ($mod['name'] ?? 'Unknown mod')), 0, 180),
            'summary' => mb_substr(trim(strip_tags((string) ($mod['summary'] ?? ''))), 0, 600),
            'image' => $this->httpsUrl(data_get($mod, 'logo.thumbnailUrl')),
            'website' => $this->httpsUrl(data_get($mod, 'links.websiteUrl')),
            'download_count' => (int) ($mod['downloadCount'] ?? 0),
            'updated_at' => $mod['dateModified'] ?? null,
        ];
    }

    private function transformFile(array $file): array
    {
        return [
            'id' => (int) ($file['id'] ?? 0),
            'display_name' => mb_substr((string) ($file['displayName'] ?? $file['fileName'] ?? ''), 0, 220),
            'file_name' => (string) ($file['fileName'] ?? ''),
            'file_date' => $file['fileDate'] ?? null,
            'file_length' => (int) ($file['fileLength'] ?? 0),
            'release_type' => (int) ($file['releaseType'] ?? 0),
            'game_versions' => array_values(array_filter($file['gameVersions'] ?? [], 'is_string')),
            'download_url' => $this->httpsUrl($file['downloadUrl'] ?? null),
        ];
    }

    private function matchesVersion(array $file, array $context): bool
    {
        return in_array($context['minecraft_version'], $file['gameVersions'] ?? [], true);
    }

    private function publicCompatibility(array $context): array
    {
        return [
            'game_version' => $context['minecraft_version'],
            'mod_loader' => $context['mod_loader'],
            'mod_loader_type' => $context['mod_loader_type'],
        ];
    }

    private function safeFilename(string $filename): string
    {
        $filename = trim($filename);
        if ($filename !== basename($filename) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._ -]{0,190}\.(jar|zip)$/i', $filename) !== 1) {
            throw new DisplayException('CurseForge returned an unsafe file name.');
        }

        return $filename;
    }

    private function isTrustedDownloadUrl(string $url): bool
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_starts_with($url, 'https://') && ($host === 'forgecdn.net' || str_ends_with($host, '.forgecdn.net'));
    }

    private function httpsUrl(mixed $url): ?string
    {
        return is_string($url) && str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function isNotFound(DaemonConnectionException $exception): bool
    {
        $previous = $exception->getPrevious();

        return $previous instanceof ClientException && $previous->getResponse()->getStatusCode() === 404;
    }
}
