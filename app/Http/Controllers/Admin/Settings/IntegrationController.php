<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\HowTooIntegration;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\HowToo\Ai\OllamaEndpoint;
use Pterodactyl\Services\HowToo\IntegrationCredentialStore;
use Pterodactyl\Services\HowToo\Ai\OllamaModelDiscoveryService;
use Pterodactyl\Http\Requests\Admin\Settings\IntegrationSettingsFormRequest;

class IntegrationController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private IntegrationCredentialStore $credentials,
        private OllamaModelDiscoveryService $ollama,
    ) {
    }

    public function index(): View
    {
        return view('admin.settings.integrations', [
            'providers' => $this->credentials->status(),
            'ollamaDiscovery' => $this->ollama->cached(),
        ]);
    }

    public function update(IntegrationSettingsFormRequest $request): RedirectResponse
    {
        $providers = $request->validated('providers');
        $previous = $this->credentials->status()['ollama'];
        $ollama = $providers['ollama'];
        $baseUrl = filled($ollama['base_url'] ?? null) ? OllamaEndpoint::normalize($ollama['base_url']) : null;
        $credentialsChanged = filled($ollama['secret'] ?? null)
            || $baseUrl !== ($previous['base_url'] ?: null)
            || (bool) ($ollama['environment_key_enabled'] ?? false) !== $previous['environment_key_enabled'];

        $this->credentials->update($providers);
        if ($credentialsChanged) {
            $this->ollama->forgetCachedModels();
        }
        $this->alert->success(__('Integration settings were updated securely.'))->flash();

        return redirect()->route('admin.settings.integrations');
    }

    public function refreshOllamaModels(): RedirectResponse
    {
        try {
            $result = $this->ollama->refresh();
            if (count($result['models']) === 1 && !$this->credentials->model('ollama')) {
                HowTooIntegration::query()->where('provider', 'ollama')->update([
                    'model' => $result['models'][0],
                    'updated_at' => now(),
                ]);
            }
            $this->alert->success(__('Ollama connected. :count model(s) found.', ['count' => count($result['models'])]))->flash();
        } catch (\Pterodactyl\Exceptions\DisplayException $exception) {
            $this->alert->danger($exception->getMessage())->flash();
        }

        return redirect()->route('admin.settings.integrations');
    }
}
