<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie','storage/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['http://localhost:5173', 'https://b2b.piueducation.org'], // Specify domains instead of *
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];