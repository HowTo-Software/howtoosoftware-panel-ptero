<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\HowToo\NodeUtilizationService;

class NodeUtilizationController extends Controller
{
    public function __construct(private NodeUtilizationService $utilization)
    {
    }

    /**
     * Returns what every node is currently consuming, as reported by its daemon.
     */
    public function __invoke(): JsonResponse
    {
        return new JsonResponse($this->utilization->all());
    }
}
