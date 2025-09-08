<?php

return [
    'paths' => ['api/v1/*', 'sanctum/csrf-cookie','storage/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [], // leave empty
'allowed_origins_patterns' => ['/https?:\/\/localhost:5173/', '/https?:\/\/b2b\.piueducation\.org/'],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];