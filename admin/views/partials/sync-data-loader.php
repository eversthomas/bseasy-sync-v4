<?php
/**
 * Sync-Tab: PHP-Datenvorbereitung (kein HTML).
 *
 * Wird von admin/views/ui-sync.php ganz oben eingebunden.
 * Stellt alle PHP-Variablen bereit, die von den Sync-Tab-Partials benötigt werden.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) exit;

// Stelle sicher, dass BES_DIR definiert ist
if (!defined('BES_DIR')) {
    // Fallback: Plugin-Root (Datei liegt unter admin/views/partials/)
    $plugin_file = __FILE__;
    $plugin_dir = dirname(dirname(dirname(dirname($plugin_file))));
    define('BES_DIR', trailingslashit($plugin_dir));
}

// Stelle sicher, dass V3-Konstanten und Helper verfügbar sind
if (!defined('BES_V3_OPTION_PREFIX')) {
    if (defined('BES_DIR') && file_exists(BES_DIR . 'includes/constants-v3.php')) {
        require_once BES_DIR . 'includes/constants-v3.php';
    } elseif (file_exists(__DIR__ . '/../../../includes/constants-v3.php')) {
        require_once __DIR__ . '/../../../includes/constants-v3.php';
    }
}

// Sync-Modul nur über die öffentliche Fassade laden (bseasy_v3_read_json etc.)
if (!function_exists('bseasy_v3_read_json')) {
    if (defined('BES_DIR') && file_exists(BES_DIR . 'sync/sync-service.php')) {
        require_once BES_DIR . 'sync/sync-service.php';
    }
}

// ── Cache-Infos ──────────────────────────────────────────────────────────────
$cache_stats            = function_exists('bes_get_cache_stats') ? bes_get_cache_stats() : null;
$cache_enabled          = !(defined('BES_CACHE_DISABLED') && BES_CACHE_DISABLED);
$dev_mode               = defined('BES_DEV_MODE') && BES_DEV_MODE;
$cache_duration         = defined('BES_CACHE_DURATION') ? BES_CACHE_DURATION : HOUR_IN_SECONDS;
$cache_duration_minutes = round($cache_duration / 60);

// ── V3 Explorer-Status ───────────────────────────────────────────────────────
// Stelle sicher, dass BES_DATA_V3 definiert ist
if (!defined('BES_DATA_V3')) {
    if (file_exists(BES_DIR . 'includes/constants-v3.php')) {
        require_once BES_DIR . 'includes/constants-v3.php';
    }
}

$explorer_last_run   = get_option(BES_V3_OPTION_PREFIX . 'explorer_last_run', false);
$explorer_field_count = get_option(BES_V3_OPTION_PREFIX . 'explorer_field_count', 0);
$explorer_running    = get_option(BES_V3_OPTION_PREFIX . 'explorer_running', false);

// Feldkatalog-Pfad ermitteln
if (defined('BES_DATA_V3') && defined('BES_V3_FIELD_CATALOG')) {
    $catalog_file = BES_DATA_V3 . BES_V3_FIELD_CATALOG;
} else {
    $upload_dir   = wp_upload_dir();
    $catalog_file = trailingslashit($upload_dir['basedir']) . 'bseasy-sync/v3/field_catalog_v3.json';
}
$catalog_exists = file_exists($catalog_file);

// Zusätzliche Prüfung: alternative Pfade falls Datei nicht gefunden
if (!$catalog_exists) {
    $upload_dir = wp_upload_dir();
    $alt_paths  = [
        trailingslashit($upload_dir['basedir']) . 'bseasy-sync/v3/field_catalog_v3.json',
        WP_CONTENT_DIR . '/uploads/bseasy-sync/v3/field_catalog_v3.json',
    ];
    foreach ($alt_paths as $alt_path) {
        if (file_exists($alt_path)) {
            $catalog_file   = $alt_path;
            $catalog_exists = true;
            break;
        }
    }
}

// ── V3 Sync-Status ───────────────────────────────────────────────────────────
$last_sync_time_v3       = get_option(BES_V3_OPTION_PREFIX . 'last_sync_time', false);
$members_with_consent_v3 = get_option(BES_V3_OPTION_PREFIX . 'last_sync_members_with_consent', false);

// Explorer-Status-Datei (für laufenden Explorer-Fortschritt)
$explorer_status_file = BES_DATA_V3 . BES_V3_STATUS_FILE;
$explorer_status      = null;
if (file_exists($explorer_status_file)) {
    $explorer_status = bseasy_v3_read_json($explorer_status_file);
}
