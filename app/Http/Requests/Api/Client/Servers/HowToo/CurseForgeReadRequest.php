<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\HowToo;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class CurseForgeReadRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_INTEGRATION_CURSEFORGE_READ;
    }
}
