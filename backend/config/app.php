<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', '涵尊 CMS 后端'),
    'debug' => (bool) env('APP_DEBUG', false),
    'default_timezone' => env('APP_TIMEZONE', 'Asia/Shanghai'),
    'admin_guard' => 'adminapi',
];
