<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configurado para permitir el frontend React (appweb-erp) en desarrollo.
    | Las rutas tenant siguen el patron /{tenant}/api/v1/...
    |
    */

    'paths' => [
        'api/*',
        '*/api/v1/*',
        'system/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [
        '#^http://(localhost|127\.0\.0\.1)(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
