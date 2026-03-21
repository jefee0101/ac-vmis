<?php

return [
    'profiles' => [
        'avatar' => [
            'max_kb' => 2048,
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        ],
        'team_avatar' => [
            'max_kb' => 4096,
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        ],
        'medical_certificate' => [
            'max_kb' => 5120,
            'mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
        ],
        'academic_document' => [
            'max_kb' => 5120,
            'mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
        ],
    ],

    'antivirus' => [
        'enabled' => env('UPLOAD_AV_SCAN_ENABLED', false),
        'binary' => env('UPLOAD_AV_BINARY', 'clamscan'),
        'timeout_seconds' => env('UPLOAD_AV_TIMEOUT', 20),
        'fail_open' => env('UPLOAD_AV_FAIL_OPEN', true),
    ],
];
