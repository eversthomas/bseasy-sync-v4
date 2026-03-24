<?php
/**
 * Registrierung des Admin-Menüs.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fügt die Hauptmenü-Seite für BSEasy Sync hinzu.
 */
function bes_bootstrap_register_admin_menu() {
    add_menu_page(
        __('BSEasy Sync', BES_TEXT_DOMAIN),
        __('BSEasy Sync', BES_TEXT_DOMAIN),
        'manage_options',
        'bseasy-sync',
        'bes_admin_page',
        'dashicons-update-alt',
        80
    );
}

add_action('admin_menu', 'bes_bootstrap_register_admin_menu');
