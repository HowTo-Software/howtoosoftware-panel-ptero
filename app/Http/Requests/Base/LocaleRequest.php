<?php

namespace Pterodactyl\Http\Requests\Base;

use Illuminate\Foundation\Http\FormRequest;

class LocaleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', 'max:64', 'regex:/^(?:en|pt)(?:[+\s]+(?:en|pt))*$/'],
            'namespace' => ['required', 'string', 'max:512', 'regex:/^[a-z]{1,64}(?:[+\s]+[a-z]{1,64})*$/'],
        ];
    }
}
