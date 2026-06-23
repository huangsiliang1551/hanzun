<?php

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'type' => 'mysql',
            'hostname' => env('DB_HOST', '127.0.0.1'),
            'hostport' => (int) env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'hanzun_cms'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'prefix' => env('DB_PREFIX', ''),
            'connect_timeout' => (int) env('DB_CONNECT_TIMEOUT', 1),
        ],
    ],
];
