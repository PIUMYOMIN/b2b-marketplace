<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        'https://api.pyonea.com',
        'http://localhost:5173',
        'http://localhost:5174',
        'https://pyonea.com',
        'https://www.pyonea.com',
    ],

    'allowed_origins_patterns' => ['^http://localhost:\d+$', '^http://127\.0\.0\.1:\d+$'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
