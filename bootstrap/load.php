<?php
/**
 * Plugin-Bootstrap: Ladereihenfolge aller Module.
 *
 * Architekturregeln (V4):
 *  - includes/   → immer geladen (Infrastruktur, geteilt)
 *  - sync/        → nur in Admin/Cron; Einstiegspunkt: sync/sync-service.php
 *  - admin/       → nur in Admin/Cron
 *  - frontend/    → immer geladen (Shortcode, AJAX, Rendering)
 *
 * Kopplungsregeln:
 *  - admin/ und frontend/ dürfen NUR sync/sync-service.php einbinden, keine
 *    sync-internen Dateien direkt.
 *  - Mitgliederdaten werden ausschließlich über includes/data/member-repository.php
 *    gelesen.
 *  - Design-Einstellungen liegen in includes/design/design-settings.php (geteilt).
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

// hosting-compatibility VOR debug-log: bes_write_debug_log() ruft bes_ensure_writable_directory(),
// das muss verfügbar sein, bevor der Admin-Error-Handler einen Log-Eintrag schreibt.
if (file_exists(BES_DIR . 'includes/hosting-compatibility.php')) {
    require_once BES_DIR . 'includes/hosting-compatibility.php';
}

require_once BES_DIR . 'bootstrap/debug-log.php';
bes_bootstrap_register_admin_error_handlers();

require_once BES_DIR . 'bootstrap/legacy-data-paths.php';

if (file_exists(BES_DIR . 'includes/filters.php')) {
    require_once BES_DIR . 'includes/filters.php';
}

/**
 * ------------------------------------------------------------
 *  🗂️ GEMEINSAME RESSOURCEN (immer laden)
 *  Konstanten, Repository, Design – werden von allen Modulen benötigt.
 * ------------------------------------------------------------
 */
if (file_exists(BES_DIR . 'includes/constants-v3.php')) {
    require_once BES_DIR . 'includes/constants-v3.php';
}

// Datenzugriffsschicht: Mitgliederdaten (Frontend + Admin + AJAX)
if (file_exists(BES_DIR . 'includes/data/member-repository.php')) {
    require_once BES_DIR . 'includes/data/member-repository.php';
}

// Design-Einstellungen (Frontend + Admin)
if (file_exists(BES_DIR . 'includes/design/design-settings.php')) {
    require_once BES_DIR . 'includes/design/design-settings.php';
}

// Admin-Hinweise bei API-/Token-Fehlern (EasyVerein)
if (file_exists(BES_DIR . 'includes/api-error-user-hints.php')) {
    require_once BES_DIR . 'includes/api-error-user-hints.php';
}

/**
 * ------------------------------------------------------------
 *  🔩 SYNC-MODUL & ADMIN-AJAX (nur in Admin-Kontext oder WP-Cron)
 *  Einstiegspunkt: sync/sync-service.php (kapselt alle internen Dateien).
 * ------------------------------------------------------------
 */
if (is_admin() || wp_doing_cron()) {
    if (file_exists(BES_DIR . 'sync/sync-service.php')) {
        require_once BES_DIR . 'sync/sync-service.php';
    }
    if (file_exists(BES_DIR . 'sync/runtime/cron-v3.php')) {
        require_once BES_DIR . 'sync/runtime/cron-v3.php';
    }
    if (file_exists(BES_DIR . 'admin/ajax/ajax-v3.php')) {
        require_once BES_DIR . 'admin/ajax/ajax-v3.php';
    }
    if (file_exists(BES_DIR . 'admin/calendar-handler.php')) {
        require_once BES_DIR . 'admin/calendar-handler.php';
    }
}

/**
 * ------------------------------------------------------------
 *  🌐 FRONTEND (Fassade, Views, Shortcode, AJAX)
 * ------------------------------------------------------------
 */
if (file_exists(BES_DIR . 'frontend/includes/design-bridge.php')) {
    require_once BES_DIR . 'frontend/includes/design-bridge.php';
}
if (file_exists(BES_DIR . 'frontend/includes/schema.php')) {
    require_once BES_DIR . 'frontend/includes/schema.php';
}
if (file_exists(BES_DIR . 'frontend/includes/geo-utils.php')) {
    require_once BES_DIR . 'frontend/includes/geo-utils.php';
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
 *  🧩 ADMIN: Seite, Menü, Felder, AJAX, Assets (nur WP-Admin)
 *  Cron-relevante Module (sync/, ajax-v3, calendar-handler) liegen
 *  im Block oben (is_admin() || wp_doing_cron()).
 * ------------------------------------------------------------
 */
if (is_admin()) {
    require_once BES_DIR . 'bootstrap/admin-page.php';
    require_once BES_DIR . 'bootstrap/admin-menu.php';

    if (file_exists(BES_DIR . 'admin/fields/fields-handler.php')) {
        require_once BES_DIR . 'admin/fields/fields-handler.php';
    }
    if (file_exists(BES_DIR . 'admin/fields/includes/field-label-generator.php')) {
        require_once BES_DIR . 'admin/fields/includes/field-label-generator.php';
    }
    if (file_exists(BES_DIR . 'admin/fields/includes/fields-template.php')) {
        require_once BES_DIR . 'admin/fields/includes/fields-template.php';
    }
    // design-settings.php wird weiter oben als gemeinsame Ressource geladen
    // (includes/design/design-settings.php). Stub: admin/fields/includes/design-settings.php

    if (file_exists(BES_DIR . 'admin/ajax/ajax-cache.php')) {
        require_once BES_DIR . 'admin/ajax/ajax-cache.php';
    }
    if (file_exists(BES_DIR . 'admin/ajax/ajax-debug.php')) {
        require_once BES_DIR . 'admin/ajax/ajax-debug.php';
    }

    require_once BES_DIR . 'bootstrap/admin-assets.php';
}

/**
 * ------------------------------------------------------------
 *  🔌 Aktivierung / Deaktivierung / Uninstall (Funktionen)
 * ------------------------------------------------------------
 */
require_once BES_DIR . 'bootstrap/plugin-lifecycle.php';
