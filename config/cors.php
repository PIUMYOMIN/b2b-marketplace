<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Frontend: https://pyonea.com  (and www.)
    | Backend:  https://api.pyonea.com
    |
    | The 'paths' list must cover every route prefix the browser can hit.
    | HandleCors middleware is registered as global in bootstrap/app.php,
    | so OPTIONS preflight requests are handled before routing runs.
    |
    */

    // Cover all API routes + storage (for signed URLs)
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // ORIGIN is always the FRONTEND, never the API server itself.
    'allowed_origins' => [
        'https://pyonea.com',
        'https://www.pyonea.com',

        // Local dev
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:3000',
        'http://127.0.0.1:5173',
    ],

    // Also allow any localhost port (catches Vite port changes during dev)
    'allowed_origins_patterns' => [
        '^http://localhost:\d+$',
        '^http://127\.0\.0\.1:\d+$',
    ],

    // Allow all headers — includes Authorization, Content-Type, X-Idempotency-Key etc.
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'Accept',
        'X-Requested-With',
        'X-Idempotency-Key',
        'X-CSRF-TOKEN',
    ],

    'exposed_headers' => [],

    // Cache preflight response for 24 hours
    'max_age' => 86400,

    // false for Bearer token auth — true only needed for cookie/session Sanctum
    'supports_credentials' => false,

];