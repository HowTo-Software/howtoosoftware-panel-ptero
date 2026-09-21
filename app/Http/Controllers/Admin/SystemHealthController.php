<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\HowToo\SystemHealthService;

class SystemHealthController extends Controller
{
    public function __construct(private SystemHealthService $health)
    {
    }

    /**
     * Returns the live CPU, memory and storage consumption of the panel host.
     */
    public function __invoke(): JsonResponse
    {
        return new JsonResponse($this->health->metrics());
    }
}
