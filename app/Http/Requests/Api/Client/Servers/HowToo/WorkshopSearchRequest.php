<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\HowToo;

class WorkshopSearchRequest extends WorkshopReadRequest
{
    public function rules(): array
    {
        return [
            'query' => 'required|string|min:2|max:300',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:10|max:50',
        ];
    }
}
