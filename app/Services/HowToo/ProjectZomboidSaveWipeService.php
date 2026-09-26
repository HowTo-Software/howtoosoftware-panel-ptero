<?php

namespace Pterodactyl\Services\HowToo;

use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ProjectZomboidSaveWipeService
{
    private const CACHE_ROOT = '/.cache';

    private const TARGETS = ['db', 'logs', 'saves'];

    public function __construct(
        private ServerGameContext $gameContext,
        private DaemonFileRepository $files,
        private DaemonServerRepository $daemon,
    ) {
    }

    /**
     * Removes only the known Project Zomboid save data directories under .cache.
     * Paths are intentionally not accepted from the caller.
     */
    public function handle(Server $server): array
    {
        if (!$this->gameContext->for($server)['project_zomboid']) {
            throw new BadRequestHttpException('This action is only available for Project Zomboid servers.');
        }

        $this->assertServerCanBeWiped($server);

        $entries = $this->files->setServer($server)->getDirectory(self::CACHE_ROOT);
        $targets = collect($entries)
            ->filter(fn ($entry): bool => is_array($entry) && in_array($entry['name'] ?? null, self::TARGETS, true))
            ->filter(fn (array $entry): bool => ($entry['file'] ?? true) === false && !($entry['symlink'] ?? false))
            ->pluck('name')
            ->values()
            ->all();

        if ($targets !== []) {
            // Recheck immediately before the destructive Wings call; the browser status is not trusted.
            $this->assertServerCanBeWiped($server);
            $this->files->setServer($server)->deleteFiles(self::CACHE_ROOT, $targets);
        }

        return $targets;
    }

    private function assertServerCanBeWiped(Server $server): void
    {
        $server->validateCurrentState();

        $details = $this->daemon->setServer($server)->getDetails();
        if (($details['state'] ?? null) !== 'offline') {
            throw new ConflictHttpException('The server must be fully offline before its Project Zomboid save can be wiped.');
        }
    }
}
