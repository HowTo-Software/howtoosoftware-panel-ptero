<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\HowToo;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\HowToo\CurseForgeService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\HowToo\CurseForgeReadRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\HowToo\CurseForgeSearchRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\HowToo\CurseForgeInstallRequest;

class CurseForgeController extends ClientApiController
{
    public function __construct(private CurseForgeService $curseForge)
    {
        parent::__construct();
    }

    public function installed(CurseForgeReadRequest $request, Server $server): JsonResponse
    {
        return new JsonResponse(['items' => $this->curseForge->installed($server)]);
    }

    public function search(CurseForgeSearchRequest $request, Server $server): JsonResponse
    {
        return new JsonResponse($this->curseForge->search(
            $server,
            $request->string('query')->toString(),
            $request->integer('index', 0),
        ));
    }

    public function show(CurseForgeReadRequest $request, Server $server, int $modId): JsonResponse
    {
        return new JsonResponse($this->curseForge->mod($server, $modId));
    }

    public function files(CurseForgeReadRequest $request, Server $server, int $modId): JsonResponse
    {
        return new JsonResponse(['items' => $this->curseForge->compatibleFiles($server, $modId)]);
    }

    public function install(CurseForgeInstallRequest $request, Server $server): JsonResponse
    {
        $result = $this->curseForge->install($server, $request->integer('mod_id'), $request->integer('file_id'));

        Activity::event('server:integration.curseforge-install')
            ->property('mod_id', $request->integer('mod_id'))
            ->property('file_id', $request->integer('file_id'))
            ->property('file_name', $result['file_name'])
            ->log();

        return new JsonResponse($result);
    }
}
