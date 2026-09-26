<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\HowToo;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Services\HowToo\ServerCoverService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Settings\StoreServerCoverRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Settings\DeleteServerCoverRequest;

class ServerCoverController extends ClientApiController
{
    public function __construct(private ServerCoverService $covers)
    {
        parent::__construct();
    }

    public function show(Server $server): BinaryFileResponse
    {
        $path = $server->cover_image;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(StoreServerCoverRequest $request, Server $server): JsonResponse
    {
        $this->covers->store($server, $request->file('cover'));

        return new JsonResponse(['cover_image_url' => $this->url($server)]);
    }

    public function destroy(DeleteServerCoverRequest $request, Server $server): JsonResponse
    {
        $this->covers->delete($server);

        return new JsonResponse(['cover_image_url' => null]);
    }

    private function url(Server $server): ?string
    {
        if (!$server->cover_image) {
            return null;
        }

        return route('api:client:server.settings.cover', ['server' => $server->uuid])
            . '?v=' . substr(hash('sha256', $server->cover_image), 0, 12);
    }
}
