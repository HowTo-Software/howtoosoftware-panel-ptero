<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\HowToo;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\HowToo\ServerGameContext;
use Pterodactyl\Services\HowToo\SteamWorkshopService;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Services\HowToo\ZomboidWorkshopConfigService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Http\Requests\Api\Client\Servers\HowToo\WorkshopReadRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\HowToo\WorkshopSearchRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\HowToo\WorkshopUpdateRequest;

class WorkshopController extends ClientApiController
{
    public function __construct(
        private ZomboidWorkshopConfigService $config,
        private SteamWorkshopService $steam,
        private ServerGameContext $gameContext,
        private DaemonPowerRepository $power,
    ) {
        parent::__construct();
    }

    public function index(WorkshopReadRequest $request, Server $server): JsonResponse
    {
        $configuration = $this->config->read($server);
        $configuration['details'] = [];
        $configuration['details_error'] = null;

        try {
            $configuration['details'] = $this->steam->details($configuration['workshop_items']);
        } catch (DisplayException $exception) {
            $configuration['details_error'] = $exception->getMessage();
        }

        return new JsonResponse($configuration);
    }

    public function search(WorkshopSearchRequest $request, Server $server): JsonResponse
    {
        if (!$this->gameContext->for($server)['project_zomboid']) {
            throw new DisplayException('Workshop Manager is only available for Project Zomboid servers.');
        }

        return new JsonResponse($this->steam->search(
            $request->string('query')->toString(),
            $request->integer('page', 1),
        ));
    }

    public function update(WorkshopUpdateRequest $request, Server $server): JsonResponse
    {
        $configuration = $this->config->save(
            $server,
            $request->input('workshop_items'),
            $request->input('mods'),
            $request->string('revision')->toString(),
        );

        Activity::event('server:integration.workshop-update')
            ->property('workshop_items', $configuration['workshop_items'])
            ->property('mods', $configuration['mods'])
            ->log();

        $restarted = $request->input('action') === 'restart';
        $restartError = null;
        if ($restarted) {
            try {
                $this->power->setServer($server)->send('restart');
                Activity::event('server:power.restart')->log();
            } catch (DaemonConnectionException) {
                $restarted = false;
                $restartError = 'Changes were saved, but Wings could not restart the server.';
            }
        }

        return new JsonResponse(array_merge($configuration, [
            'restarted' => $restarted,
            'restart_error' => $restartError,
        ]));
    }
}
