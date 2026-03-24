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

    // ui-felder.js wurde entfernt - ui-felder-sidebar.js ist die aktuelle Version
    // Die alte Datei wurde gelöscht, da sie doppelte IDs verursachte und nicht mehr benötigt wird
}

add_action('admin_enqueue_scripts', 'bes_bootstrap_admin_enqueue_scripts');
