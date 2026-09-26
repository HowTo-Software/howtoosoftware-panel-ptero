<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\HowToo;

class CurseForgeSearchRequest extends CurseForgeReadRequest
{
    public function rules(): array
    {
        return [
            'query' => 'nullable|string|min:2|max:300',
            'index' => 'sometimes|integer|min:0|max:9980',
            'sort' => 'sometimes|in:downloads,popular,updated',
        ];
    }
}
