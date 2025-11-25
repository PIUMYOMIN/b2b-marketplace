<?php

return [
    'paths' => ['api/v1/*', 'sanctum/csrf-cookie','storage/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['https://b2b.piueducation.org','http://localhost:5173'],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];