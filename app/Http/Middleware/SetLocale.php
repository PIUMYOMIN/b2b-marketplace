<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Accept-Language');
        $locale = $request->query('lang', $request->getPreferredLanguage(['en', 'my']));

        \Log::info('========== SET LOCALE MIDDLEWARE ==========');
        \Log::info('Accept-Language header: ' . ($header ?? 'null'));
        \Log::info('lang query param: ' . ($request->query('lang') ?? 'null'));
        \Log::info('Computed locale: ' . $locale);
        \Log::info('Setting locale to: ' . $locale);

        if (in_array($locale, ['en', 'my'])) {
            app()->setLocale($locale);
        }

        \Log::info('Locale after set: ' . app()->getLocale());
        \Log::info('==========================================');

        return $next($request);
    }
}