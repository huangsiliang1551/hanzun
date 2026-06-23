<?php

declare(strict_types=1);

return [
    'access_ttl' => (int) env('AUTH_ACCESS_TTL', 7200),
    'refresh_ttl' => (int) env('AUTH_REFRESH_TTL', 2592000),
    'jwt_secret' => env('AUTH_JWT_SECRET', ''),
    'login_max_attempts' => (int) env('AUTH_LOGIN_MAX_ATTEMPTS', 5),
    'password_algo' => PASSWORD_BCRYPT,
];
