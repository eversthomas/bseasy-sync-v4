<?php
/**
 * BSEasy Sync V3 - Konstanten
 * 
 * V3-spezifische Konstanten, komplett getrennt von V2
 * 
 * @package BSEasySync
 * @author Tom Evers <https://bezugssysteme.de>
 * @since 3.0.0
 */

if (!defined('ABSPATH')) exit;

// ============================================================
// V3 VERZEICHNISSE
// ============================================================
if (!defined('BES_DATA_V3')) {
    if (function_exists('wp_upload_dir')) {
        $upload_dir = wp_upload_dir();
        if (isset($upload_dir['basedir']) && isset($upload_dir['baseurl'])) {
            define('BES_DATA_V3', trailingslashit($upload_dir['basedir']) . 'bseasy-sync/v3/');
            define('BES_DATA_V3_URL', trailingslashit($upload_dir['baseurl']) . 'bseasy-sync/v3/');
        } else {
            define('BES_DATA_V3', WP_CONTENT_DIR . '/uploads/bseasy-sync/v3/');
            define('BES_DATA_V3_URL', (function_exists('content_url') ? content_url('/uploads/bseasy-sync/v3/') : ''));
        }
    } else {
        define('BES_DATA_V3', WP_CONTENT_DIR . '/uploads/bseasy-sync/v3/');
        define('BES_DATA_V3_URL', '');
    }
}

// ============================================================
// V3 DATEINAMEN
// ============================================================
if (!defined('BES_V3_FIELD_CATALOG')) {
    define('BES_V3_FIELD_CATALOG', 'field_catalog_v3.json');
}

if (!defined('BES_V3_SELECTION')) {
    define('BES_V3_SELECTION', 'selection_v3.json');
}

if (!defined('BES_V3_MEMBERS_FILE')) {
    define('BES_V3_MEMBERS_FILE', 'members_consent_v3.json');
}

if (!defined('BES_V3_STATUS_FILE')) {
    define('BES_V3_STATUS_FILE', 'status_v3.json');
}

if (!defined('BES_V3_HISTORY_FILE')) {
    define('BES_V3_HISTORY_FILE', 'sync_history_v3.json');
}

if (!defined('BES_V3_LOG_FILE')) {
    define('BES_V3_LOG_FILE', 'sync-v3.log');
}

if (!defined('BES_V3_DEBUG_LOG_FILE')) {
    define('BES_V3_DEBUG_LOG_FILE', 'debug-v3.log');
}

// ============================================================
// V3 SYNC SETTINGS
// ============================================================
if (!defined('BES_V3_BATCH_SIZE_DEFAULT')) {
    define('BES_V3_BATCH_SIZE_DEFAULT', 200);
}

if (!defined('BES_V3_BATCH_SIZE_MIN')) {
    define('BES_V3_BATCH_SIZE_MIN', 50);
}

if (!defined('BES_V3_BATCH_SIZE_MAX')) {
    define('BES_V3_BATCH_SIZE_MAX', 500);
}

if (!defined('BES_V3_EXPLORER_SAMPLE_MIN')) {
    define('BES_V3_EXPLORER_SAMPLE_MIN', 1);
}

if (!defined('BES_V3_EXPLORER_SAMPLE_MAX')) {
    define('BES_V3_EXPLORER_SAMPLE_MAX', 200);
}

if (!defined('BES_V3_EXPLORER_SAMPLE_DEFAULT')) {
    define('BES_V3_EXPLORER_SAMPLE_DEFAULT', 100);
}

// ============================================================
// V3 CRON HOOKS
// ============================================================
if (!defined('BES_V3_CRON_HOOK')) {
    define('BES_V3_CRON_HOOK', 'bes_run_consent_v3_single');
}

if (!defined('BES_V3_EXPLORER_CRON_HOOK')) {
    define('BES_V3_EXPLORER_CRON_HOOK', 'bes_run_explorer_v3');
}

// ============================================================
// V3 OPTION KEYS
// ============================================================
if (!defined('BES_V3_OPTION_PREFIX')) {
    define('BES_V3_OPTION_PREFIX', 'bes_v3_');
}

// ============================================================
// V3 PII MASKING
// ============================================================
if (!defined('BES_V3_PII_PATTERNS')) {
    define('BES_V3_PII_PATTERNS', [
        'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
        'phone' => '/[\+]?[0-9\s\-\(\)]{8,}/',
        'address' => '/\b\d{1,5}\s+[a-zA-ZäöüÄÖÜß\s]+(?:straße|str\.|Strasse|Str\.|weg|Weg|platz|Platz|allee|Allee|ring|Ring)\b/i',
    ]);
}

// ============================================================
// V3 REQUIRED FIELDS (Pflichtkern)
// ============================================================
if (!defined('BES_V3_REQUIRED_FIELDS')) {
    define('BES_V3_REQUIRED_FIELDS', [
        'member.id',
        'member.membershipNumber', // Identifier
        'syncedAt', // Timestamp
    ]);
}
