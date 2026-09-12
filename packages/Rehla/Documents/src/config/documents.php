<?php

declare(strict_types=1);

return [
    'disk' => 'private',
    'scan_lease_seconds' => (int) env('DOCUMENTS_SCAN_LEASE_SECONDS', 600),
    'cleanup_lease_seconds' => (int) env('DOCUMENTS_CLEANUP_LEASE_SECONDS', 600),
    'clamav' => [
        'endpoint' => env('CLAMAV_ENDPOINT', 'tcp://127.0.0.1:3310'),
        'timeout_seconds' => (float) env('CLAMAV_TIMEOUT_SECONDS', 10),
    ],
];
