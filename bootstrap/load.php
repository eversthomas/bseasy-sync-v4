<?php
/**
 * Lädt Plugin-Bootstrap: Includes, Module, Hooks (ohne Lifecycle-Registrierung).
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ------------------------------------------------------------
 *  🌍 INTERNATIONALIZATION
 * ------------------------------------------------------------
 */
add_action('plugins_loaded', function () {
    load_plugin_textdomain(
        BES_TEXT_DOMAIN,
        false,
        dirname(plugin_basename(BES_DIR . 'bseasy-sync.php')) . '/languages'
    );
});

/**
 * ------------------------------------------------------------
 *  🔧 UTILITIES & CONSTANTS
 * ------------------------------------------------------------
 */
if (file_exists(BES_DIR . 'includes/constants.php')) {
    require_once BES_DIR . 'includes/constants.php';
}
if (file_exists(BES_DIR . 'includes/error-handler.php')) {
    require_once BES_DIR . 'includes/error-handler.php';
}
if (file_exists(BES_DIR . 'includes/cache-utils.php')) {
    require_once BES_DIR . 'includes/cache-utils.php';
}

require_once BES_DIR . 'bootstrap/debug-log.php';
bes_bootstrap_register_admin_error_handlers();

require_once BES_DIR . 'bootstrap/legacy-data-paths.php';

if (file_exists(BES_DIR . 'includes/filters.php')) {
    require_once BES_DIR . 'includes/filters.php';
}
if (file_exists(BES_DIR . 'includes/hosting-compatibility.php')) {
    require_once BES_DIR . 'includes/hosting-compatibility.php';
}

/**
 * ------------------------------------------------------------
 *  🔩 CORE-FUNKTIONEN & CRON
 * ------------------------------------------------------------
 */
if (file_exists(BES_DIR . 'includes/constants-v3.php')) {
    require_once BES_DIR . 'includes/constants-v3.php';
}

// Lade V3-Module (nur im Admin oder bei Cron)
if (is_admin() || wp_doing_cron()) {
    if (file_exists(BES_DIR . 'sync/v3-helpers.php')) {
        require_once BES_DIR . 'sync/v3-helpers.php';
    }
    if (file_exists(BES_DIR . 'sync/api-explorer-v3.php')) {
        require_once BES_DIR . 'sync/api-explorer-v3.php';
    }
    if (file_exists(BES_DIR . 'sync/api-core-consent-v3.php')) {
        require_once BES_DIR . 'sync/api-core-consent-v3.php';
    }
    if (file_exists(BES_DIR . 'sync/cron-v3.php')) {
        require_once BES_DIR . 'sync/cron-v3.php';
    }
    if (file_exists(BES_DIR . 'admin/ajax/ajax-v3.php')) {
        require_once BES_DIR . 'admin/ajax/ajax-v3.php';
    }
    if (file_exists(BES_DIR . 'sync/v3-consent-audit.php')) {
        require_once BES_DIR . 'sync/v3-consent-audit.php';
    }
}

if (file_exists(BES_DIR . 'admin/calendar-handler.php')) {
    require_once BES_DIR . 'admin/calendar-handler.php';
}

/**
 * ------------------------------------------------------------
 *  🌐 FRONTEND (Fassade, Views, Shortcode, AJAX)
 * ------------------------------------------------------------
 */
if (file_exists(BES_DIR . 'frontend/includes/design-bridge.php')) {
    require_once BES_DIR . 'frontend/includes/design-bridge.php';
}
if (file_exists(BES_DIR . 'frontend/renderer.php')) {
    require_once BES_DIR . 'frontend/renderer.php';
}
if (file_exists(BES_DIR . 'frontend/map-render.php')) {
    require_once BES_DIR . 'frontend/map-render.php';
}
if (file_exists(BES_DIR . 'frontend/shortcode.php')) {
    require_once BES_DIR . 'frontend/shortcode.php';
}
if (file_exists(BES_DIR . 'frontend/ajax-endpoints.php')) {
    require_once BES_DIR . 'frontend/ajax-endpoints.php';
}

if (file_exists(BES_DIR . 'frontend/calendar-render.php')) {
    require_once BES_DIR . 'frontend/calendar-render.php';
}

/**
 * ------------------------------------------------------------
 *  🧩 ADMIN: Seite, Menü, Assets
 * ------------------------------------------------------------
 */
require_once BES_DIR . 'bootstrap/admin-page.php';
require_once BES_DIR . 'bootstrap/admin-menu.php';

/**
 * ------------------------------------------------------------
 *  🧠 FELDERVERWALTUNG (CustomField-Konfiguration)
 * ------------------------------------------------------------
 */
if (file_exists(BES_DIR . 'admin/fields/fields-handler.php')) {
    require_once BES_DIR . 'admin/fields/fields-handler.php';
}
if (file_exists(BES_DIR . 'admin/fields/includes/field-label-generator.php')) {
    require_once BES_DIR . 'admin/fields/includes/field-label-generator.php';
}
if (file_exists(BES_DIR . 'admin/fields/includes/fields-template.php')) {
    require_once BES_DIR . 'admin/fields/includes/fields-template.php';
}
if (file_exists(BES_DIR . 'admin/fields/includes/design-settings.php')) {
    require_once BES_DIR . 'admin/fields/includes/design-settings.php';
}

/**
 * ------------------------------------------------------------
 *  🔁 AJAX-HANDLER: Cache & Debug
 * ------------------------------------------------------------
 */
if (file_exists(BES_DIR . 'admin/ajax/ajax-cache.php')) {
    require_once BES_DIR . 'admin/ajax/ajax-cache.php';
}
if (file_exists(BES_DIR . 'admin/ajax/ajax-debug.php')) {
    require_once BES_DIR . 'admin/ajax/ajax-debug.php';
}

require_once BES_DIR . 'bootstrap/admin-assets.php';

/**
 * ------------------------------------------------------------
 *  🔌 Aktivierung / Deaktivierung / Uninstall (Funktionen)
 * ------------------------------------------------------------
 */
require_once BES_DIR . 'bootstrap/plugin-lifecycle.php';
