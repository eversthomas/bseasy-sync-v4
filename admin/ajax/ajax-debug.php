<?php
/**
 * AJAX-Handler für Debug-Operationen
 * 
 * Enthält Debug-bezogene AJAX-Endpunkte:
 * - bes_debug_log: Schreibt Debug-Log-Eintrag
 * 
 * @package BSEasySync
 * @since 3.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * ------------------------------------------------------------
 *  🐛 AJAX: DEBUG-LOG SCHREIBEN
 * ------------------------------------------------------------
 * Original: bseasy-sync.php Zeilen 1140-1190
 * 
 * Handler 1: bes_debug_log
 * Action: wp_ajax_bes_debug_log
 * Funktion: Schreibt Debug-Log-Eintrag
 * Sicherheit:
 * - Nonce: bes_debug_nonce
 * - Permission: manage_options
 * - Rate-Limiting: 100 Requests/Minute
 */
add_action('wp_ajax_bes_debug_log', function () {
    // Nonce-Prüfung für Sicherheit
    if (!isset($_REQUEST['_ajax_nonce']) || !wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bes_debug_nonce')) {
        wp_send_json_error(['error' => esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => esc_html__('Keine Berechtigung.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    // Rate-Limiting (100 Requests pro Minute - Debug-Logs sind häufig)
    if (!function_exists('bes_check_rate_limit') || !bes_check_rate_limit('bes_debug_log', 100, 60)) {
        wp_send_json_error(['error' => esc_html__('Zu viele Anfragen. Bitte warten Sie einen Moment.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
    $level = isset($_POST['level']) ? sanitize_text_field($_POST['level']) : 'INFO';
    $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'ui.js';
    
    if (empty($message)) {
        wp_send_json_error(['error' => esc_html__('Keine Nachricht übermittelt.', BES_TEXT_DOMAIN)]);
    }
    
    // Debug-Verzeichnis erstellen falls nicht vorhanden
    $debug_dir = BES_DIR . 'debug/';
    if (!file_exists($debug_dir)) {
        wp_mkdir_p($debug_dir);
        @chmod($debug_dir, 0755);
    }
    
    // Debug-Log-Datei
    $log_file = $debug_dir . 'debug.log';
    
    // Timestamp
    $timestamp = date('Y-m-d H:i:s');
    
    // Log-Eintrag formatieren
    $log_entry = "[{$timestamp}] [{$level}] [{$context}] {$message}\n";
    
    // In Datei schreiben (append mode)
    $result = @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Debug-Log geschrieben', 'file' => $log_file]);
    } else {
        wp_send_json_error(['error' => 'Fehler beim Schreiben der Debug-Log-Datei']);
    }
});

