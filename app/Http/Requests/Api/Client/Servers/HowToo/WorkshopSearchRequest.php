<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\HowToo;

class WorkshopSearchRequest extends WorkshopReadRequest
{
    public function rules(): array
    {
        return [
            'mode' => 'sometimes|string|in:search,trending,most_subscribed,recent',
            'query' => 'required_without:mode|required_if:mode,search|nullable|string|min:2|max:300',
            'tags' => 'sometimes|array|max:8',
            'tags.*' => 'required|string|max:64',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:10|max:50',
        ];
    }
}
