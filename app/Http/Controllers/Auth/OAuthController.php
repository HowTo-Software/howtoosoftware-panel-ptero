<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Pterodactyl\Events\Auth\DirectLogin;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\User;

/**
 * Single sign-on against Authentik, which federates Active Directory.
 *
 * Two rules make this safe to run beside non-directory accounts:
 *
 *  1. An SSO login may only ever land on an account that Active Directory
 *     owns, i.e. one stamped by panel-ad-sync.py with an "ad-" external_id.
 *     Accounts without an AD-owned external_id are unreachable through this
 *     route even if someone contrives a matching email address.
 *  2. Two-factor is NOT bypassed. When the account has TOTP enabled this
 *     hands off to the existing checkpoint flow rather than logging the user
 *     straight in, so the same verification code path is used as for a
 *     password login.
 */
class OAuthController extends AbstractLoginController
{
    private const DRIVER = 'authentik';

    /** Only accounts owned by Active Directory may authenticate this way. */
    private const AD_PREFIX = 'ad-';

    public function redirect(): RedirectResponse
    {
        $this->assertConfigured();

        // setScopes replaces the provider defaults, which otherwise include
        // goauthentik.io/api. Identity is all this route needs.
        return Socialite::driver(self::DRIVER)
            ->setScopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * @throws DisplayException
     */
    public function callback(Request $request): RedirectResponse
    {
        $this->assertConfigured();

        // Socialite validates the state parameter here, which is what protects
        // the callback against CSRF. Any failure must abort the login.
        try {
            $oauthUser = Socialite::driver(self::DRIVER)->user();
        } catch (\Throwable $e) {
            Activity::event('auth:fail')->withRequestMetadata()
                ->property('oauth_error', $e->getMessage())->log();

            return redirect()->route('auth.login')
                ->withErrors(['sso' => trans('auth.sso.failed')]);
        }

        $email = $oauthUser->getEmail();
        if (empty($email)) {
            return $this->reject($request, 'no email claim returned by the identity provider');
        }

        // Note there is deliberately no email_verified check. Authentik reports
        // that claim as false for directory-sourced users, because it only
        // records whether the address was confirmed through an Authentik email
        // stage. Here the address is mastered in Active Directory, and users
        // cannot edit their own email or username in Authentik, so the claim
        // carries no signal and would reject every legitimate login.

        /** @var User|null $user */
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
            ->where('external_id', 'LIKE', self::AD_PREFIX . '%')
            ->first();

        if (!$user) {
            // Deliberately identical whether the account is absent or simply
            // not AD-owned, so this cannot be used to probe for accounts.
            return $this->reject($request, "no AD-owned account for {$email}");
        }

        if (!$user->use_totp) {
            $request->session()->remove('auth_confirmation_token');
            $request->session()->regenerate();
            $this->auth->guard()->login($user, true);

            Event::dispatch(new DirectLogin($user, true));
            Activity::event('auth:success')->withRequestMetadata()->subject($user)
                ->property('method', 'sso')->log();

            return redirect('/');
        }

        // Same session structure and expiry the password flow produces, so the
        // existing LoginCheckpointController validates it unchanged.
        Activity::event('auth:checkpoint')->withRequestMetadata()->subject($user)
            ->property('method', 'sso')->log();

        $request->session()->put('auth_confirmation_token', [
            'user_id' => $user->id,
            'token_value' => $token = Str::random(64),
            'expires_at' => CarbonImmutable::now()->addMinutes(5),
        ]);

        // Carried in the fragment, which browsers never send to a server and
        // never place in a Referer header.
        return redirect('/auth/login/checkpoint#token=' . $token);
    }

    private function reject(Request $request, string $reason): RedirectResponse
    {
        Activity::event('auth:fail')->withRequestMetadata()
            ->property('method', 'sso')->property('reason', $reason)->log();

        return redirect()->route('auth.login')
            ->withErrors(['sso' => trans('auth.sso.not_permitted')]);
    }

    /**
     * @throws DisplayException
     */
    private function assertConfigured(): void
    {
        foreach (['base_url', 'client_id', 'client_secret'] as $key) {
            if (empty(config("services.authentik.$key"))) {
                throw new DisplayException(trans('auth.sso.not_configured'));
            }
        }
    }
}
