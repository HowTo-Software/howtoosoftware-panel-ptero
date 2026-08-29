<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\HowToo;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class CurseForgeInstallRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_INTEGRATION_CURSEFORGE_INSTALL;
    }

    public function rules(): array
    {
        return [
            'mod_id' => 'required|integer|min:1',
            'file_id' => 'required|integer|min:1',
        ];
    }
}
