<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration — Pyonea
    | Frontend: https://pyonea.com
    | Backend:  https://api.pyonea.com
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        'https://pyonea.com',
        'https://www.pyonea.com',
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:3000',
        'http://127.0.0.1:5173',
    ],

    'allowed_origins_patterns' => [
        '^http://localhost:\d+$',
        '^http://127\.0\.0\.1:\d+$',
    ],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'Accept',
        'X-Requested-With',
        'X-Idempotency-Key',
        'X-CSRF-TOKEN',
    ],

    'exposed_headers' => [],

    'max_age' => 86400,

    /*
    |--------------------------------------------------------------------------
    | IMPORTANT: supports_credentials MUST be false for Bearer token auth.
    |
    | true  = cookie/session Sanctum (SPA mode) — requires CSRF token
    | false = Bearer token Sanctum (API mode)   — Pyonea uses this
    |
    | When true + cross-origin: browser enforces strict origin matching
    | and CSRF which blocks all API requests from pyonea.com → api.pyonea.com.
    |--------------------------------------------------------------------------
    */
    'supports_credentials' => false,

];