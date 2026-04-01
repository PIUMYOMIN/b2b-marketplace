<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Resolve the request locale and set it on the application.
     *
     * Priority:
     *   1. ?lang= query parameter  (explicit override — useful for testing)
     *   2. Accept-Language header  (standard browser/axios header set by the frontend)
     *   3. Default to 'en'
     *
     * Supported locales: en, my
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = ['en', 'my'];

        // 1. Explicit query param takes priority
        $locale = $request->query('lang');

        // 2. Fall back to Accept-Language header
        if (!$locale || !in_array($locale, $supported)) {
            $locale = $request->getPreferredLanguage($supported);
        }

        // 3. Hard fallback
        if (!in_array($locale, $supported)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
