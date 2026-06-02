<?php
/**
 * Sync-Lab Konfiguration — wird NIE vom Plugin-Bootstrap geladen.
 */

declare(strict_types=1);

return [
    'api_version' => 'v2.0',
    'api_bases' => [
        'https://hexa.easyverein.com/api',
        'https://easyverein.com/api',
    ],
    'timeout' => 45,
    'rate_limit_per_minute' => 90,
    'request_pause_us' => 200000,
    'consent_field_id_default' => 282018660,
    'consent_values' => ['true', 'True'],
    'paths' => [
        'output' => __DIR__ . '/output',
        'cache' => __DIR__ . '/cache',
    ],
    'production_members_file' => null, // wird in bootstrap.php gesetzt
];
