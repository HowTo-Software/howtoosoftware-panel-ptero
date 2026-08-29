<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\HowToo;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Services\HowToo\AiAssistantService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\HowToo\AssistantRequest;

class AssistantController extends ClientApiController
{
    public function __construct(private AiAssistantService $assistant)
    {
        parent::__construct();
    }

    public function __invoke(AssistantRequest $request, Server $server): JsonResponse
    {
        return new JsonResponse($this->assistant->ask(
            $server,
            $request->string('message')->toString(),
            $request->input('history', []),
            $request->input('section'),
            $request->input('error'),
        ));
    }
}
