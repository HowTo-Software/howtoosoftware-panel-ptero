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
            $rules["providers.$provider.secret"] = ['nullable', 'string', 'max:512'];
            $rules["providers.$provider.model"] = ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9._:\/-]+$/'];
        }

        return $rules;
    }
}
