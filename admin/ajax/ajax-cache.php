<?php
/**
 * AJAX-Handler für Cache-Operationen
 * 
 * Enthält alle Cache-bezogenen AJAX-Endpunkte:
 * - bes_clear_cache: Leert Render-Cache
 * - bes_cache_stats: Gibt Cache-Statistiken zurück
 * 
 * @package BSEasySync
 * @since 3.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * ------------------------------------------------------------
 *  🗑️ AJAX: CACHE LEEREN
 * ------------------------------------------------------------
 * Original: bseasy-sync.php Zeilen 1098-1133
 * 
 * Handler 1: bes_clear_cache
 * Action: wp_ajax_bes_clear_cache
 * Funktion: Leert Render-Cache
 * Sicherheit:
 * - Nonce: bes_admin_nonce
 * - Permission: manage_options
 * - Rate-Limiting: 30 Requests/Minute
 */
add_action('wp_ajax_bes_clear_cache', function () {
    // Nonce-Prüfung für Sicherheit
    if (!isset($_REQUEST['_ajax_nonce']) || !wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bes_admin_nonce')) {
        wp_send_json_error(['error' => esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => esc_html__('Keine Berechtigung.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    // Rate-Limiting (30 Requests pro Minute - Cache-Operationen sind teurer)
    if (!function_exists('bes_check_rate_limit') || !bes_check_rate_limit('bes_clear_cache', 30, 60)) {
        wp_send_json_error(['error' => esc_html__('Zu viele Anfragen. Bitte warten Sie einen Moment.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!function_exists('bes_clear_render_cache')) {
        wp_send_json_error(['error' => esc_html__('Cache-Funktion nicht verfügbar.', BES_TEXT_DOMAIN)]);
    }
    
    $deleted = bes_clear_render_cache();
    
    // Cache-Statistiken nach dem Löschen
    $stats = function_exists('bes_get_cache_stats') ? bes_get_cache_stats() : null;
    
    wp_send_json_success([
        'message' => sprintf(
            esc_html__('Cache geleert: %d Einträge gelöscht.', BES_TEXT_DOMAIN),
            $deleted
        ),
        'deleted' => $deleted,
        'stats' => $stats
    ]);
});

/**
 * ------------------------------------------------------------
 *  📊 AJAX: CACHE-STATISTIKEN
 * ------------------------------------------------------------
 * Original: bseasy-sync.php Zeilen 1197-1224
 * 
 * Handler 2: bes_cache_stats
 * Action: wp_ajax_bes_cache_stats
 * Funktion: Gibt Cache-Statistiken zurück
 * Sicherheit:
 * - Nonce: bes_admin_nonce
 * - Permission: manage_options
 * - Rate-Limiting: 60 Requests/Minute
 */
add_action('wp_ajax_bes_cache_stats', function () {
    // Nonce-Prüfung für Sicherheit
    if (!isset($_REQUEST['_ajax_nonce']) || !wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bes_admin_nonce')) {
        wp_send_json_error(['error' => esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => esc_html__('Keine Berechtigung.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    // Rate-Limiting (60 Requests pro Minute)
    if (!function_exists('bes_check_rate_limit') || !bes_check_rate_limit('bes_cache_stats', 60, 60)) {
        wp_send_json_error(['error' => esc_html__('Zu viele Anfragen. Bitte warten Sie einen Moment.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!function_exists('bes_get_cache_stats')) {
        wp_send_json_error(['error' => esc_html__('Cache-Statistik-Funktion nicht verfügbar.', BES_TEXT_DOMAIN)]);
    }
    
    $stats = bes_get_cache_stats();
    
    wp_send_json_success([
        'stats' => $stats
    ]);
});

