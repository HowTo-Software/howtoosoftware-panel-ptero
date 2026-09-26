<?php

namespace Pterodactyl\Services\HowToo;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Client\PendingRequest;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CurseForgeService
{
    private const MINECRAFT_GAME_ID = 432;
    private const MINECRAFT_MOD_CLASS_ID = 6;
    private const MINECRAFT_MODPACK_CLASS_ID = 4471;

    public function __construct(
        private IntegrationCredentialStore $credentials,
        private ServerGameContext $gameContext,
        private DaemonFileRepository $files,
        private DaemonServerRepository $daemon,
    ) {
    }

    public function search(Server $server, string $query = '', int $index = 0, string $sort = 'downloads'): array
    {
        $context = $this->compatibleContext($server);
        $this->requireModdedLoader($context);

        return $this->searchProjects($query, $context, self::MINECRAFT_MOD_CLASS_ID, $index, $sort, true);
    }

    public function searchModpacks(Server $server, string $query = '', int $index = 0, string $sort = 'downloads'): array
    {
        $context = $this->compatibleContext($server, false, false);
        $query = trim($query);
        $slug = $this->curseForgeModpackSlug($query);

        return $this->searchProjects($slug === null ? $query : '', $context, self::MINECRAFT_MODPACK_CLASS_ID, $index, $sort, false, $slug);
    }

    private function searchProjects(
        string $query,
        array $context,
        int $classId,
        int $index,
        string $sort,
        bool $filterLoader,
        ?string $slug = null,
    ): array {
        $params = [
            'gameId' => self::MINECRAFT_GAME_ID,
            'classId' => $classId,
            'sortField' => $this->sortField($sort),
            'sortOrder' => 'desc',
            'index' => max(0, min($index, 9980)),
            'pageSize' => 20,
        ];
        if ($query !== '') {
            $params['searchFilter'] = $query;
        }
        if ($slug !== null) {
            $params['slug'] = $slug;
        }
        if ($context['minecraft_version'] !== null) {
            $params['gameVersion'] = $context['minecraft_version'];
        }
        if ($filterLoader && $context['mod_loader_type'] > 0) {
            $params['modLoaderType'] = $context['mod_loader_type'];
        }

        try {
            $response = $this->client()->get('/v1/mods/search', $params)->throw()->json();
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
        $this->requireModdedLoader($context);

        try {
            $params = [
                'modLoaderType' => $context['mod_loader_type'],
                'pageSize' => 50,
            ];
            if ($context['minecraft_version'] !== null) {
                $params['gameVersion'] = $context['minecraft_version'];
            }
            $response = $this->client()->get("/v1/mods/$modId/files", $params)->throw()->json();
        } catch (\Throwable) {
            throw new DisplayException('Could not load compatible CurseForge files.');
        }

        return collect($response['data'] ?? [])
            ->filter(fn (array $file): bool => ($file['isAvailable'] ?? false) && $this->matchesVersion($file, $context))
            ->map(fn (array $file): array => $this->transformFile($file))
            ->values()
            ->all();
    }

    public function compatibleServerPackFiles(Server $server, int $modId): array
    {
        $context = $this->compatibleContext($server, false, false);
        $this->assertMinecraftModpack($modId);

        try {
            $params = ['pageSize' => 50, 'index' => 0];
            if ($context['minecraft_version'] !== null) {
                $params['gameVersion'] = $context['minecraft_version'];
            }
            $response = $this->client()->get("/v1/mods/$modId/files", $params)->throw()->json();
        } catch (\Throwable) {
            throw new DisplayException('Could not load CurseForge server pack files.');
        }

        $projectFiles = collect($response['data'] ?? [])
            ->filter(fn (array $file): bool => ($file['isAvailable'] ?? false) && $this->matchesVersion($file, $context));
        $linkedServerPackIds = $projectFiles
            ->map(fn (array $file): int => (int) ($file['serverPackFileId'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $serverPackIds = $projectFiles
            ->filter(fn (array $file): bool => (bool) ($file['isServerPack'] ?? false))
            ->map(fn (array $file): int => (int) ($file['id'] ?? 0))
            ->merge($linkedServerPackIds)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($serverPackIds->isEmpty()) {
            return [];
        }

        try {
            $files = $this->client()->post('/v1/mods/files', ['fileIds' => $serverPackIds->all()])->throw()->json();
        } catch (\Throwable) {
            throw new DisplayException('Could not load CurseForge server pack files.');
        }

        $explicitlyLinked = $linkedServerPackIds->flip();

        return collect($files['data'] ?? [])
            ->filter(fn (array $file): bool => ($file['isAvailable'] ?? false)
                && ((bool) ($file['isServerPack'] ?? false) || $explicitlyLinked->has((int) ($file['id'] ?? 0)))
                && ($this->matchesVersion($file, $context) || $explicitlyLinked->has((int) ($file['id'] ?? 0))))
            ->map(fn (array $file): array => array_merge($this->transformFile($file), ['is_server_pack' => true]))
            ->sortByDesc('file_date')
            ->values()
            ->all();
    }

    public function installed(Server $server): array
    {
        $this->compatibleContext($server, false, false);

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

    public function installServerPack(Server $server, int $modId, int $fileId): array
    {
        $this->compatibleContext($server, false, false);
        $file = collect($this->compatibleServerPackFiles($server, $modId))->firstWhere('id', $fileId);
        if (!$file) {
            throw new DisplayException('The selected file is not a verified CurseForge server pack for this Minecraft version.');
        }
        $url = $file['download_url'] ?: $this->downloadUrl($modId, $fileId);
        if (!$url || !$this->isTrustedDownloadUrl($url)) {
            throw new DisplayException('This author does not provide a supported server download for this file.');
        }

        $details = $this->daemon->setServer($server)->getDetails();
        if (($details['state'] ?? null) !== 'offline') {
            throw new ConflictHttpException('Stop the server completely before installing a modpack server pack.');
        }

        $filename = sprintf('hts-curseforge-server-pack-%d-%d.zip', $modId, $fileId);
        $repository = $this->files->setServer($server);
        $entries = $repository->getDirectory('/');
        if (collect($entries)->contains(fn (array $entry): bool => strcasecmp((string) ($entry['name'] ?? ''), $filename) === 0)) {
            throw new ConflictHttpException('A temporary file for this server pack already exists in the server root. Remove it and try again.');
        }

        try {
            $repository->pull($url, '/', [
                'filename' => $filename,
                'foreground' => true,
            ]);
            $repository->decompressFile('/', $filename);
        } catch (\Throwable $exception) {
            $this->removeTemporaryPack($repository, $filename);
            throw $exception;
        }

        $this->removeTemporaryPack($repository, $filename);

        return ['file_name' => $filename, 'installed' => true];
    }

    private function compatibleContext(Server $server, bool $requireVersion = true, bool $requireLoader = true): array
    {
        $context = $this->gameContext->for($server);
        if (!$context['minecraft_java']) {
            if ($context['minecraft_edition'] === 'bedrock') {
                throw new DisplayException('Minecraft Bedrock was detected. This installer handles Java mods and Java server packs only.');
            }

            throw new DisplayException('CurseForge content is currently available for Minecraft servers only.');
        }
        if ($requireVersion && !$context['minecraft_version']) {
            throw new DisplayException('The Minecraft version could not be read from this server egg. Set its version variable to an exact version such as 1.20.1.');
        }
        if ($requireLoader && $context['mod_loader_type'] === null) {
            throw new DisplayException('The Java mod loader could not be read from this server egg. Set the server egg to Forge, Fabric, Quilt, or NeoForge.');
        }

        return $context;
    }

    private function requireModdedLoader(array $context): void
    {
        if ($context['mod_loader_type'] === 0) {
            throw new DisplayException('This server uses Vanilla. Individual CurseForge mods require Forge, Fabric, Quilt, or NeoForge; use a server pack that includes its own mod loader.');
        }
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
        return $context['minecraft_version'] === null
            || in_array($context['minecraft_version'], $file['gameVersions'] ?? [], true);
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

    private function sortField(string $sort): int
    {
        return match ($sort) {
            'updated' => 3,
            'popular' => 2,
            default => 6,
        };
    }

    private function curseForgeModpackSlug(string $query): ?string
    {
        if (!preg_match('/^https?:\/\//i', $query)) {
            return null;
        }

        $parts = parse_url($query);
        $host = mb_strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        if (!in_array($host, ['curseforge.com', 'www.curseforge.com'], true)
            || ($parts['scheme'] ?? '') !== 'https'
            || preg_match('~^/minecraft/modpacks/([a-z0-9][a-z0-9-]{0,99})(?:/.*)?$~i', $path, $matches) !== 1) {
            throw new DisplayException('Paste a CurseForge Minecraft modpack link, for example https://www.curseforge.com/minecraft/modpacks/example.');
        }

        return mb_strtolower($matches[1]);
    }

    private function removeTemporaryPack(DaemonFileRepository $repository, string $filename): void
    {
        try {
            $repository->deleteFiles('/', [$filename]);
        } catch (\Throwable $exception) {
            Log::warning('Unable to remove the temporary CurseForge server pack archive.', [
                'file' => $filename,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function downloadUrl(int $modId, int $fileId): ?string
    {
        try {
            $response = $this->client()->get("/v1/mods/$modId/files/$fileId/download-url")->throw()->json();
        } catch (\Throwable) {
            return null;
        }

        return $this->httpsUrl(data_get($response, 'data'));
    }

    private function assertMinecraftModpack(int $modId): void
    {
        try {
            $response = $this->client()->get("/v1/mods/$modId")->throw()->json();
        } catch (\Throwable) {
            throw new DisplayException('Could not load this CurseForge project.');
        }

        $project = $response['data'] ?? [];
        if ((int) ($project['gameId'] ?? 0) !== self::MINECRAFT_GAME_ID
            || (int) ($project['classId'] ?? 0) !== self::MINECRAFT_MODPACK_CLASS_ID) {
            throw new DisplayException('The selected CurseForge project is not a Minecraft modpack.');
        }
    }
}
