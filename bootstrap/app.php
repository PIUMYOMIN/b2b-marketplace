<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        api: __DIR__ . '/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ── Global middleware (replaces Kernel.php $middleware array) ──────────
        // HandleCors MUST be global so it runs on every request, including
        // OPTIONS preflight requests that arrive before routing is resolved.
        $middleware->use([
            \Illuminate\Http\Middleware\TrustProxies::class,
            \Illuminate\Http\Middleware\HandleCors::class,
            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
            \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ]);

        // ── API middleware group ───────────────────────────────────────────────
        // Only SetLocale is prepended. EnsureFrontendRequestsAreStateful is NOT
        // used because Pyonea uses Bearer token auth, not cookie/session Sanctum.
        // Adding it causes CSRF token mismatch on every API login request.
        $middleware->api(prepend: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        // ── Middleware aliases ─────────────────────────────────────────────────
        // ── Rate limiters ───────────────────────────────────────────────────
        // Named limiters used on sensitive routes. Applied via ->middleware('throttle:reviews')
        $middleware->throttleWithRedis();  // use Redis if available, else DB

        \Illuminate\Support\Facades\RateLimiter::for('reviews', function ($request) {
            // Max 3 reviews per buyer per hour globally — prevents spam
            return \Illuminate\Cache\RateLimiting\Limit::perHour(3)
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn() => response()->json([
                    'success' => false,
                    'message' => 'Too many reviews. Please wait before submitting another.',
                ], 429));
        });

        \Illuminate\Support\Facades\RateLimiter::for('follows', function ($request) {
            // Max 20 follow/unfollow actions per minute — prevents bot follows
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(20)
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn() => response()->json([
                    'success' => false,
                    'message' => 'Too many follow actions. Slow down.',
                ], 429));
        });

        \Illuminate\Support\Facades\RateLimiter::for('checkout', function ($request) {
            // Max 5 order creation attempts per user per 10 minutes
            return \Illuminate\Cache\RateLimiting\Limit::perMinutes(10, 5)
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn() => response()->json([
                    'success' => false,
                    'message' => 'Too many order attempts. Please wait a moment.',
                ], 429));
        });

        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
            'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
            'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            // Spatie Permission
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();