<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the interface language: the signed-in user's choice, then the language
 * cookie (set from the language menu when signed out), then the browser's
 * Accept-Language header, then English.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('locales'));
        $locale = $request->user()?->locale;

        if (! in_array($locale, $supported, true)) {
            $locale = $request->cookie('locale');
        }
        if (! in_array($locale, $supported, true)) {
            $locale = $request->getPreferredLanguage($supported) ?? config('app.locale');
            $locale = in_array($locale, $supported, true) ? $locale : 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
