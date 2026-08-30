<?php

namespace Pterodactyl\Http\ViewComposers;

use Illuminate\View\View;
use Pterodactyl\Services\Helpers\AssetHashService;

class AssetComposer
{
    /**
     * AssetComposer constructor.
     */
    public function __construct(private AssetHashService $assetHashService)
    {
    }

    /**
     * Provide access to the asset service in the views.
     */
    public function compose(View $view): void
    {
        $authentik = config('services.authentik');
        $ssoEnabled = !empty($authentik['client_id']) && !empty($authentik['base_url']);

        $view->with('asset', $this->assetHashService);
        $view->with('siteConfiguration', [
            'name' => config('app.name') ?? 'Pterodactyl',
            'locale' => config('app.locale') ?? 'en',
            'recaptcha' => [
                'enabled' => config('recaptcha.enabled', false),
                'siteKey' => config('recaptcha.website_key') ?? '',
            ],
            'sso' => [
                'enabled' => $ssoEnabled,
                // Passwords live in Active Directory, so recovery is Authentik's flow, not ours.
                'recoveryUrl' => $ssoEnabled
                    ? rtrim($authentik['base_url'], '/') . '/if/flow/' . trim($authentik['recovery_flow'], '/') . '/'
                    : '',
            ],
        ]);
    }
}
