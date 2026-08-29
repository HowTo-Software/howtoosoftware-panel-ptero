<?php

namespace Pterodactyl\Http\Requests\Admin\Settings;

use Illuminate\Validation\Rule;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;
use Pterodactyl\Services\HowToo\IntegrationCredentialStore;

class IntegrationSettingsFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        $rules = ['providers' => ['required', 'array']];

        foreach (IntegrationCredentialStore::PROVIDERS as $provider) {
            $rules["providers.$provider"] = ['required', 'array'];
            $rules["providers.$provider.enabled"] = ['nullable', Rule::in(['0', '1'])];
            $rules["providers.$provider.priority"] = ['required', 'integer', 'min:1', 'max:1000'];
            $rules["providers.$provider.timeout_seconds"] = ['required', 'integer', 'min:5', 'max:55'];
            $rules["providers.$provider.environment_key_enabled"] = ['nullable', Rule::in(['0', '1'])];
            $rules["providers.$provider.secret"] = ['nullable', 'string', 'max:512'];
            $rules["providers.$provider.model"] = ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9._:\/-]+$/'];
            $rules["providers.$provider.keys"] = ['nullable', 'array', 'max:20'];
            $rules["providers.$provider.keys.*.id"] = ['nullable', 'integer', 'min:1'];
            $rules["providers.$provider.keys.*.name"] = ['required', 'string', 'max:80'];
            $rules["providers.$provider.keys.*.enabled"] = ['nullable', Rule::in(['0', '1'])];
            $rules["providers.$provider.keys.*.priority"] = ['required', 'integer', 'min:1', 'max:1000'];
            $rules["providers.$provider.keys.*.secret"] = ['nullable', 'string', 'max:512'];
            $rules["providers.$provider.keys.*.delete"] = ['nullable', Rule::in(['0', '1'])];
        }

        return $rules;
    }
}
