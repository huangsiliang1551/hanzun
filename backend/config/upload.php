<?php

declare(strict_types=1);

return [
    'disk' => env('UPLOAD_DISK', 'local'),
    'root' => env('UPLOAD_ROOT', 'public/uploads'),
    'allowed_extensions' => [
        'image' => ['jpg', 'jpeg', 'png', 'webp'],
        'video' => ['mp4', 'webm'],
        'pdf' => ['pdf'],
    ],
    'blocked_extensions' => [
        'svg',
        'php',
        'php3',
        'php4',
        'php5',
        'php7',
        'php8',
        'phtml',
        'phar',
        'exe',
        'js',
        'jsp',
        'asp',
        'aspx',
        'sh',
        'bash',
        'bat',
        'cmd',
        'com',
        'dll',
        'cgi',
        'pl',
        'py',
    ],
    'limits' => [
        'image' => (int) env('UPLOAD_MAX_IMAGE_SIZE', 5 * 1024 * 1024),
        'video' => (int) env('UPLOAD_MAX_VIDEO_SIZE', 50 * 1024 * 1024),
        'pdf' => (int) env('UPLOAD_MAX_PDF_SIZE', 20 * 1024 * 1024),
    ],
    'mime_types' => [
        'image' => ['image/jpeg', 'image/png', 'image/webp'],
        'video' => ['video/mp4', 'video/webm'],
        'pdf' => ['application/pdf'],
    ],
];
