<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    | Configuração otimizada para projeto Laravel + Vue desacoplado
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'broadcasting/auth',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // Desenvolvimento local (frontend)
        'http://localhost:3000',
        'http://127.0.0.1:3000',

        // Desenvolvimento local (backend)
        'http://localhost:8000',
        'http://127.0.0.1:8000',

        // Outras URLs de desenvolvimento
        'http://localhost',
        'http://localhost:5173',
        'http://127.0.0.1:5173',

        // Produção (ajustar conforme necessário)
        'https://meusite.com',
        'https://app.meusite.com',
    ],

    'allowed_origins_patterns' => [
        // Permite qualquer subdomínio de meusite.com
        '/^https:\/\/.*\.meusite\.com$/',

        // Permite qualquer porta localhost/127.0.0.1
        '/^http:\/\/localhost:\d+$/',
        '/^http:\/\/127\.0\.0\.1:\d+$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | CRUCIAL para autenticação SPA com Laravel Sanctum
    |--------------------------------------------------------------------------
    */
    'supports_credentials' => true,
];
