<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\HowToo\IntegrationCredentialStore;
use Pterodactyl\Http\Requests\Admin\Settings\IntegrationSettingsFormRequest;

class IntegrationController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private IntegrationCredentialStore $credentials,
    ) {
    }

    public function index(): View
    {
        return view('admin.settings.integrations', ['providers' => $this->credentials->status()]);
    }

    public function update(IntegrationSettingsFormRequest $request): RedirectResponse
    {
        $this->credentials->update($request->validated('providers'));
        $this->alert->success(__('Integration settings were updated securely.'))->flash();

        return redirect()->route('admin.settings.integrations');
    }
}
