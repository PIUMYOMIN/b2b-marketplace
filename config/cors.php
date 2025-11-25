<?php

return [
    'paths' => ['api/v1/*', 'sanctum/csrf-cookie','storage/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['https://b2b.piueducation.org', 'b2b.education.org', 'http://localhost:5173'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];