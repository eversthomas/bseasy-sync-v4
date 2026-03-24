<?php
/**
 * BSEasy Sync - Zentrale Konstanten
 * 
 * @package BSEasySync
 * @author Tom Evers <https://bezugssysteme.de>
 * @since 3.0.0
 */

if (!defined('ABSPATH')) exit;

// ============================================================
// DEBUG & LOGGING
// ============================================================
if (!defined('BES_DEBUG_MODE')) {
    define('BES_DEBUG_MODE', defined('WP_DEBUG') && WP_DEBUG);
}

if (!defined('BES_DEBUG_VERBOSE')) {
    define('BES_DEBUG_VERBOSE', defined('WP_DEBUG_LOG') && WP_DEBUG_LOG && BES_DEBUG_MODE);
}

// ============================================================
// CACHE SETTINGS
// ============================================================

// Entwicklungsumgebungs-Erkennung
if (!defined('BES_DEV_MODE')) {
    // Automatische Erkennung: WP_DEBUG aktiv = Dev-Mode
    define('BES_DEV_MODE', defined('WP_DEBUG') && WP_DEBUG);
}

// Cache-Dauer basierend auf Umgebung
if (!defined('BES_CACHE_DURATION')) {
    if (BES_DEV_MODE) {
        // Entwicklung: 60 Sekunden oder deaktiviert
        define('BES_CACHE_DURATION', 60);
    } elseif (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'staging') {
        // Staging: 5 Minuten
        define('BES_CACHE_DURATION', 5 * MINUTE_IN_SECONDS);
    } else {
        // Production: 1 Stunde
        define('BES_CACHE_DURATION', HOUR_IN_SECONDS);
    }
}

// Cache komplett deaktivieren in Dev-Mode (optional)
if (!defined('BES_CACHE_DISABLED')) {
    // Kann über wp-config.php überschrieben werden: define('BES_CACHE_DISABLED', true);
    define('BES_CACHE_DISABLED', BES_DEV_MODE && defined('BES_DISABLE_CACHE') && BES_DISABLE_CACHE);
}

if (!defined('BES_CACHE_GROUP')) {
    define('BES_CACHE_GROUP', 'bes_render');
}

// ============================================================
// API SETTINGS
// ============================================================
if (!defined('BES_API_VERSION')) {
    define('BES_API_VERSION', 'v2.0');
}

if (!defined('BES_API_BASES')) {
    define('BES_API_BASES', ['https://hexa.easyverein.com/api', 'https://easyverein.com/api']);
}

if (!defined('BES_API_TIMEOUT')) {
    define('BES_API_TIMEOUT', 45);
}

if (!defined('BES_CONSENT_FIELD_ID_DEFAULT')) {
    define('BES_CONSENT_FIELD_ID_DEFAULT', 282018660);
}

// ============================================================
// SYNC SETTINGS
// ============================================================
if (!defined('BES_BATCH_SIZE_MIN')) {
    define('BES_BATCH_SIZE_MIN', 50);
}

if (!defined('BES_BATCH_SIZE_MAX')) {
    define('BES_BATCH_SIZE_MAX', 500);
}

if (!defined('BES_BATCH_SIZE_DEFAULT')) {
    define('BES_BATCH_SIZE_DEFAULT', 200);
}

// ============================================================
// FILE SETTINGS
// ============================================================
if (!defined('BES_MAX_FILE_SIZE')) {
    define('BES_MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
}

if (!defined('BES_JSON_DEPTH')) {
    define('BES_JSON_DEPTH', 512);
}

// ============================================================
// RECURSION LIMITS
// ============================================================
if (!defined('BES_MAX_RECURSION_DEPTH')) {
    define('BES_MAX_RECURSION_DEPTH', 5);
}

// ============================================================
// VALIDATION LIMITS
// ============================================================
if (!defined('BES_CONSENT_FIELD_ID_MIN')) {
    define('BES_CONSENT_FIELD_ID_MIN', 1);
}

if (!defined('BES_CONSENT_FIELD_ID_MAX')) {
    define('BES_CONSENT_FIELD_ID_MAX', 999999999);
}

