<?php
/**
 * Admin-Assets (Styles & Scripts) für die Plugin-Admin-Seite.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lädt CSS/JS nur auf den BSEasy-Sync-Admin-Screens.
 *
 * @param string $hook Aktueller Admin-Hook
 */
function bes_bootstrap_admin_enqueue_scripts($hook) {
    // Debug: Logge Hook-Namen
    if (function_exists('bes_write_debug_log')) {
        bes_write_debug_log("admin_enqueue_scripts Hook aufgerufen - hook: " . $hook, "INFO", "admin_enqueue_scripts");
    }

    // Nur auf der Plugin-Seite laden
    // Hook-Format: {menu_slug} oder {menu_slug}_{submenu_slug}
    if (strpos($hook, 'bseasy-sync') === false) {
        if (function_exists('bes_write_debug_log')) {
            bes_write_debug_log("Hook '" . $hook . "' entspricht nicht 'bseasy-sync' - Scripts werden nicht geladen", "INFO", "admin_enqueue_scripts");
        }
        return;
    }

    if (function_exists('bes_write_debug_log')) {
        bes_write_debug_log("Hook '" . $hook . "' entspricht 'bseasy-sync' - Scripts werden geladen", "INFO", "admin_enqueue_scripts");
    }

    /**
     * 🧩 Basis-CSS für das Admin-UI
     */
    wp_enqueue_style(
        'bes-admin-style',
        BES_URL . 'admin/assets/admin.css',
        [],
        BES_VERSION
    );

    /**
     * 🧩 Neue Sidebar-CSS
     */
    wp_enqueue_style(
        'bes-sidebar-style',
        BES_URL . 'admin/assets/bes-sidebar.css',
        [],
        BES_VERSION
    );

    /**
     * 🧩 Felderverwaltung CSS
     */
    wp_enqueue_style(
        'bes-fields-style',
        BES_URL . 'admin/assets/ui-felder.css',
        ['bes-sidebar-style'],
        BES_VERSION
    );

    /**
     * 🧩 Haupt-JS (Admin Tabs, UI etc.)
     * WICHTIG: false = im Header laden (für frühe Verfügbarkeit)
     */
    wp_enqueue_script(
        'bes-admin-script',
        BES_URL . 'admin/assets/ui.js',
        ['jquery'],
        BES_VERSION,
        false  // Im Header laden statt Footer
    );

    /**
     * 🧩 Sortable + Felderverwaltung
     */
    wp_enqueue_script(
        'bes-sortable',
        BES_URL . 'admin/vendor/Sortable.min.js',
        [],
        '1.15',
        true
    );

    /**
     * 🧩 Neue Sidebar-Felderverwaltung (Sidebar, Filter, Suche)
     */
    wp_enqueue_script(
        'bes-fields-sidebar',
        BES_URL . 'admin/assets/ui-felder-sidebar.js',
        ['jquery', 'bes-sortable'],
        BES_VERSION,
        true
    );

    wp_localize_script('bes-fields-sidebar', 'bes_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('bes_felder_nonce'),
    ]);

    /**
     * 🔑 AJAX-Variablen für Cache-Verwaltung
     */
    wp_localize_script('bes-admin-script', 'bes_cache_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('bes_admin_nonce'),
    ]);

    /**
     * 🔑 AJAX-Variablen für Debug-Logging
     */
    wp_localize_script('bes-admin-script', 'bes_debug_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('bes_debug_nonce'),
    ]);

    /**
     * 🔑 AJAX-Variablen für Sync-Operationen (Consent-Sync, Merge, Status)
     */
    wp_localize_script('bes-admin-script', 'bes_sync_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('bes_admin_nonce'),
    ]);

    /**
     * 🔑 AJAX-Variablen für V3-Operationen (Explorer, V3 Sync)
     */
    wp_localize_script('bes-admin-script', 'bes_v3_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('bes_admin_nonce'),
    ]);

    /**
     * Sync-Tab JavaScript (ausgelagert aus admin/views/ui-sync.php)
     */
    if (!defined('BES_V3_OPTION_PREFIX')) {
        if (defined('BES_DIR') && file_exists(BES_DIR . 'includes/constants-v3.php')) {
            require_once BES_DIR . 'includes/constants-v3.php';
        }
    }

    $bes_sync_data = [
        'logoUrl'         => esc_url(BES_URL . 'img/logo-trans.800x0.webp'),
        'explorerRunning' => (bool) get_option(
            (defined('BES_V3_OPTION_PREFIX') ? BES_V3_OPTION_PREFIX : 'bes_v3_') . 'explorer_running',
            false
        ),
        'requiredFields'  => defined('BES_V3_REQUIRED_FIELDS')
            ? array_values(BES_V3_REQUIRED_FIELDS)
            : ['member.id', 'member.membershipNumber', 'syncedAt'],
    ];

    wp_enqueue_script(
        'bes-sync-shared',
        BES_URL . 'admin/assets/sync/sync-shared.js',
        ['jquery', 'bes-admin-script'],
        BES_VERSION,
        true
    );
    wp_localize_script('bes-sync-shared', 'besSyncData', $bes_sync_data);

    wp_enqueue_script(
        'bes-sync-notifications',
        BES_URL . 'admin/assets/sync/sync-notifications.js',
        ['bes-sync-shared'],
        BES_VERSION,
        true
    );

    wp_enqueue_script(
        'bes-sync-explorer',
        BES_URL . 'admin/assets/sync/sync-explorer.js',
        ['bes-sync-notifications'],
        BES_VERSION,
        true
    );

    wp_enqueue_script(
        'bes-sync-controller',
        BES_URL . 'admin/assets/sync/sync-controller.js',
        ['bes-sync-explorer'],
        BES_VERSION,
        true
    );

    wp_enqueue_script(
        'bes-sync-audit',
        BES_URL . 'admin/assets/sync/sync-audit.js',
        ['bes-sync-controller'],
        BES_VERSION,
        true
    );

    wp_enqueue_script(
        'bes-sync-field-selector',
        BES_URL . 'admin/assets/sync/sync-field-selector.js',
        ['bes-sync-audit'],
        BES_VERSION,
        true
    );

    wp_enqueue_script(
        'bes-sync-init',
        BES_URL . 'admin/assets/sync/sync-init.js',
        ['bes-sync-field-selector'],
        BES_VERSION,
        true
    );

    // ui-felder.js wurde entfernt - ui-felder-sidebar.js ist die aktuelle Version
    // Die alte Datei wurde gelöscht, da sie doppelte IDs verursachte und nicht mehr benötigt wird
}

add_action('admin_enqueue_scripts', 'bes_bootstrap_admin_enqueue_scripts');
