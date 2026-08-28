<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Foundation\Application;

class LanguageMiddleware
{
    /**
     * LanguageMiddleware constructor.
     */
    public function __construct(private Application $app)
    {
    }

    /**
     * Handle an incoming request and set the user's preferred language.
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        // The product currently supports Portuguese and English. Derive the
        // locale from the browser for every request so a stale account default
        // cannot force the wrong language on shared or newly-created accounts.
        $locale = $request->getPreferredLanguage(['pt', 'en']) ?? config('app.locale', 'en');
        $this->app->setLocale(in_array($locale, ['pt', 'en'], true) ? $locale : 'en');

        return $next($request);
    }
}
