<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\HowToo;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class AssistantRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_INTEGRATION_AI;
    }

    public function rules(): array
    {
        return [
            'provider' => 'required|string|in:gemini,groq',
            'message' => 'required|string|max:3000',
            'history' => 'array|max:10',
            'history.*.role' => 'required_with:history|string|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:3000',
            'section' => 'nullable|string|max:80',
            'error' => 'nullable|string|max:2500',
        ];
    }
}
