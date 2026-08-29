<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\HowToo;

class CurseForgeSearchRequest extends CurseForgeReadRequest
{
    public function rules(): array
    {
        return [
            'query' => 'required|string|min:2|max:100',
            'index' => 'sometimes|integer|min:0|max:9980',
        ];
    }
}
