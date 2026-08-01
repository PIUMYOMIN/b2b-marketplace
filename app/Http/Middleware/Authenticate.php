<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string[]  ...$guards
     * @return mixed
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, Closure $next, ...$guards)
    {
        // Laravel decides whether to render AuthenticationException as JSON
        // before it evaluates redirectTo(). Expo requests can omit Accept, so
        // mark API and bearer-token requests as JSON up front.
        if ($request->is('api/*') || $request->bearerToken()) {
            $request->headers->set('Accept', 'application/json');
        }

        $this->authenticate($request, $guards);

        return $next($request);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Mobile clients do not always send an Accept: application/json header.
        // Returning the web login route for an API request makes Laravel try to
        // generate a route that does not exist, turning an authentication failure
        // into a 500 response instead of the expected 401 JSON response.
        if ($request->expectsJson() || $request->is('api/*') || $request->bearerToken()) {
            return null;
        }

        return route('login');
    }
}
