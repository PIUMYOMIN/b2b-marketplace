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
        // 1. Explicit query param takes priority
        $locale = $this->normalizeLocale($request->query('lang'));

        // 2. Fall back to Accept-Language header
        if (!$locale) {
            foreach ($request->getLanguages() as $acceptedLanguage) {
                $locale = $this->normalizeLocale($acceptedLanguage);

                if ($locale) {
                    break;
                }
            }
        }

        // 3. Hard fallback
        $locale = $locale ?: 'en';

        app()->setLocale($locale);

        return $next($request);
    }

    private function normalizeLocale(?string $locale): ?string
    {
        $locale = strtolower(str_replace('_', '-', trim((string) $locale)));

        if (str_starts_with($locale, 'my') || str_starts_with($locale, 'mm')) {
            return 'my';
        }

        if (str_starts_with($locale, 'en')) {
            return 'en';
        }

        return null;
    }
}
