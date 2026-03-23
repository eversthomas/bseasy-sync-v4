<?php
/**
 * AJAX-Handler für V3-Operationen (Explorer & Sync)
 * 
 * @package BSEasySync
 * @subpackage V3
 * @since 3.0.0
 */

if (!defined('ABSPATH')) exit;

// Lade V3-Module
require_once BES_DIR . 'sync/v3-helpers.php';
// Stelle sicher, dass api-core-consent.php geladen wird (für bes_consent_norm_list)
if (!function_exists('bes_consent_norm_list')) {
    // Lade api-core-consent.php direkt, falls es noch nicht geladen wurde
    if (!function_exists('bes_consent_api_get')) {
        require_once BES_DIR . 'sync/api-core-consent-requests.php';
    } else {
        // bes_consent_api_get existiert bereits, aber bes_consent_norm_list fehlt
        // Lade api-core-consent.php direkt
        require_once BES_DIR . 'sync/api-core-consent.php';
    }
}
require_once BES_DIR . 'sync/api-explorer-v3.php';
require_once BES_DIR . 'sync/api-core-consent-v3.php';

/**
 * AJAX: API Explorer ausführen (asynchron über WP-Cron)
 */
add_action('wp_ajax_bes_v3_run_explorer', function () {
    // Nonce-Prüfung
    if (!isset($_REQUEST['_ajax_nonce']) || !wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bes_admin_nonce')) {
        wp_send_json_error(['error' => esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => esc_html__('Keine Berechtigung', BES_TEXT_DOMAIN)]);
        return;
    }
    
    // Rate-Limiting
    if (!function_exists('bes_check_rate_limit') || !bes_check_rate_limit('bes_v3_run_explorer', 5, 60)) {
        wp_send_json_error(['error' => esc_html__('Zu viele Anfragen. Bitte warten Sie einen Moment.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    $sample_size = isset($_POST['sample_size']) ? (int)$_POST['sample_size'] : BES_V3_EXPLORER_SAMPLE_DEFAULT;
    $fresh_from_api = isset($_POST['fresh_from_api']) ? (bool)$_POST['fresh_from_api'] : true;
    
    // Validiere Sample-Größe
    if ($sample_size < BES_V3_EXPLORER_SAMPLE_MIN || $sample_size > BES_V3_EXPLORER_SAMPLE_MAX) {
        $sample_size = BES_V3_EXPLORER_SAMPLE_DEFAULT;
    }
    
    // Verhindere parallelen Lauf mit V3 Sync/Explorer
    $status_file = BES_DATA_V3 . BES_V3_STATUS_FILE;
    if (file_exists($status_file)) {
        $status_data = bseasy_v3_read_json($status_file);
        if (!empty($status_data['state']) && $status_data['state'] === 'running') {
            wp_send_json_error([
                'error' => esc_html__(
                    'Es läuft bereits ein V3 Prozess (Sync oder Explorer). Bitte warten Sie, bis dieser abgeschlossen ist.',
                    BES_TEXT_DOMAIN
                ),
            ]);
            return;
        }
    }
    
    // Setze Explorer-Status auf "running"
    bseasy_v3_update_status(0, 100, "API Explorer wird gestartet...", 'running');
    update_option(BES_V3_OPTION_PREFIX . 'explorer_running', true);
    update_option(BES_V3_OPTION_PREFIX . 'explorer_sample_size', $sample_size);
    update_option(BES_V3_OPTION_PREFIX . 'explorer_fresh_from_api', $fresh_from_api);
    
    // Plane WP-Cron-Job für sofortige Ausführung
    $hook = 'bes_run_explorer_v3';
    $args = [$sample_size, $fresh_from_api];
    
    // Lösche eventuell vorhandene alte Jobs
    wp_clear_scheduled_hook($hook, $args);
    
    // Plane neuen Job für sofortige Ausführung
    $scheduled = wp_schedule_single_event(time(), $hook, $args);
    
    if ($scheduled === false) {
        bseasy_v3_update_status(0, 100, 'Fehler: Konnte Explorer-Job nicht planen', 'error');
        wp_send_json_error(['error' => esc_html__('Konnte Explorer nicht starten. Bitte WP-Cron prüfen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    // SOFORT antworten (Explorer läuft jetzt asynchron über WP-Cron)
    wp_send_json_success([
        'msg' => "API Explorer gestartet – läuft im Hintergrund (Sample: $sample_size)",
        'async' => true
    ]);
    
    // Trigger WP-Cron sofort
    if (function_exists('spawn_cron')) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            spawn_cron();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
            spawn_cron();
        } else {
            spawn_cron();
        }
    }
    
    return;
});

/**
 * AJAX: V3 Sync starten
 */
add_action('wp_ajax_bes_v3_start_sync', function () {
    // Nonce-Prüfung
    if (!isset($_REQUEST['_ajax_nonce']) || !wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bes_admin_nonce')) {
        wp_send_json_error(['error' => esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => esc_html__('Keine Berechtigung', BES_TEXT_DOMAIN)]);
        return;
    }
    
    // Rate-Limiting
    if (!function_exists('bes_check_rate_limit') || !bes_check_rate_limit('bes_v3_start_sync', 5, 60)) {
        wp_send_json_error(['error' => esc_html__('Zu viele Anfragen. Bitte warten Sie einen Moment.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    // Verhindere parallelen Lauf mit Explorer
    $explorer_running = get_option(BES_V3_OPTION_PREFIX . 'explorer_running', false);
    if ($explorer_running) {
        wp_send_json_error([
            'error' => esc_html__(
                'Der API Explorer läuft aktuell. Bitte warten Sie, bis dieser abgeschlossen ist.',
                BES_TEXT_DOMAIN
            ),
        ]);
        return;
    }
    
    // Speichere Einstellungen falls übergeben
    if (isset($_POST['batch_size'])) {
        $batch_size = (int)$_POST['batch_size'];
        if ($batch_size >= BES_V3_BATCH_SIZE_MIN && $batch_size <= BES_V3_BATCH_SIZE_MAX) {
            update_option(BES_V3_OPTION_PREFIX . 'batch_size', $batch_size);
        }
    } else {
        $batch_size = (int) get_option(BES_V3_OPTION_PREFIX . 'batch_size', BES_V3_BATCH_SIZE_DEFAULT);
    }
    
    if (isset($_POST['auto_continue'])) {
        update_option(BES_V3_OPTION_PREFIX . 'auto_continue', (bool)$_POST['auto_continue']);
    }
    
    $auto_continue = (bool) get_option(BES_V3_OPTION_PREFIX . 'auto_continue', false);
    $total_members = (int) get_option(BES_V3_OPTION_PREFIX . 'total_members', 0);
    
    $part = get_option(BES_V3_OPTION_PREFIX . 'current_part', 1);
    $offset = ($part - 1) * $batch_size;
    
    $estimated_parts = $total_members > 0 ? ceil($total_members / $batch_size) : 0;
    
    // Wenn ein neuer Sync startet (part === 1), lösche alle alten Part-Dateien
    // Dies verhindert, dass alte Daten aus vorherigen Syncs (z.B. "Alle Mitglieder") mit neuen Syncs vermischt werden
    // Die finale JSON-Datei bleibt erhalten, damit während des Syncs weiterhin Mitglieder im Frontend angezeigt werden
    // Sie wird beim Merge automatisch überschrieben
    // Dynamische Lösung: Findet alle Part-Dateien unabhängig von der Batch-Größe
    if ($part === 1 && defined('BES_DATA_V3')) {
        // Stelle sicher, dass das Verzeichnis existiert
        if (file_exists(BES_DATA_V3)) {
            // Finde alle Part-Dateien dynamisch (unabhängig von Batch-Größe)
            $part_files = glob(BES_DATA_V3 . 'members_consent_v3_part*.json');
            $deleted_count = 0;
            if ($part_files && is_array($part_files)) {
                foreach ($part_files as $part_file) {
                    if (file_exists($part_file) && @unlink($part_file)) {
                        $deleted_count++;
                    }
                }
            }
            
            if ($deleted_count > 0) {
                bseasy_v3_log("Bereinigung: $deleted_count alte Part-Dateien gelöscht (neuer Sync startet)", 'INFO');
            }
        }
    }
    
    // Speichere current_part in Option
    update_option(BES_V3_OPTION_PREFIX . 'current_part', $part);
    
    // Status setzen
    bseasy_v3_update_status(0, 0, "Starte Durchlauf $part" . ($estimated_parts > 0 ? " von ~$estimated_parts" : "") . " ...", 'running', [
        'current_part' => $part,
        'total_parts' => $estimated_parts
    ]);
    
    // WP-Cron-Job planen
    $hook = BES_V3_CRON_HOOK;
    $args = [$offset, $batch_size, $part];
    
    wp_clear_scheduled_hook($hook, $args);
    $scheduled = wp_schedule_single_event(time(), $hook, $args);
    
    if ($scheduled === false) {
        bseasy_v3_update_status(0, 0, 'Fehler: Konnte Cron-Job nicht planen', 'error');
        wp_send_json_error(['error' => esc_html__('Konnte Sync nicht starten. Bitte WP-Cron prüfen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    wp_send_json_success([
        'msg' => "V3 Sync gestartet – Durchlauf $part" . ($estimated_parts > 0 ? " von ~$estimated_parts" : ""),
        'part' => $part,
        'total_parts' => $estimated_parts,
        'async' => true
    ]);
    
    // Trigger WP-Cron
    if (function_exists('spawn_cron')) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            spawn_cron();
        } else {
            spawn_cron();
        }
    }
});

/**
 * AJAX: V3 Status abrufen (Sync + Explorer)
 */
add_action('wp_ajax_bes_v3_status', function () {
    if (!isset($_REQUEST['_ajax_nonce']) || !wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bes_admin_nonce')) {
        wp_send_json_error(['error' => esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => esc_html__('Keine Berechtigung', BES_TEXT_DOMAIN)]);
        return;
    }
    
    $file = BES_DATA_V3 . BES_V3_STATUS_FILE;
    $data = ['state' => 'idle', 'progress' => 0];
    
    if (file_exists($file)) {
        $data = bseasy_v3_read_json($file) ?: $data;
    }
    
    // Berechne Progress als Prozent (0-100)
    if (isset($data['progress']) && isset($data['total']) && $data['total'] > 0) {
        $data['progress'] = (int)min(100, max(0, ($data['progress'] / $data['total']) * 100));
    } elseif (isset($data['progress'])) {
        // Falls total nicht gesetzt ist, verwende progress direkt (für Explorer mit total=100)
        $data['progress'] = (int)min(100, max(0, $data['progress']));
    } else {
        $data['progress'] = 0;
    }
    
    // Prüfe ob Explorer läuft
    $explorer_running = get_option(BES_V3_OPTION_PREFIX . 'explorer_running', false);
    
    // Wenn Status "cancelled" ist, setze explorer_running zurück (verhindert falsche "running"-Anzeige)
    if (isset($data['state']) && $data['state'] === 'cancelled') {
        update_option(BES_V3_OPTION_PREFIX . 'explorer_running', false);
        $explorer_running = false;
    }
    
    if ($explorer_running && (!isset($data['state']) || ($data['state'] !== 'done' && $data['state'] !== 'cancelled'))) {
        // Explorer läuft - aktualisiere Status falls vorhanden
        if (file_exists($file)) {
            $explorer_data = bseasy_v3_read_json($file);
            if ($explorer_data && isset($explorer_data['state'])) {
                $data = $explorer_data;
                // Berechne Progress erneut für Explorer-Daten
                if (isset($data['progress']) && isset($data['total']) && $data['total'] > 0) {
                    $data['progress'] = (int)min(100, max(0, ($data['progress'] / $data['total']) * 100));
                } elseif (isset($data['progress'])) {
                    $data['progress'] = (int)min(100, max(0, $data['progress']));
                }
            } else {
                $data['state'] = 'running';
                $data['message'] = $data['message'] ?? 'API Explorer läuft...';
            }
        } else {
            $data['state'] = 'running';
            $data['message'] = 'API Explorer läuft...';
        }
    }
    
    // Erweitere mit Optionen
    if (!isset($data['members_with_consent']) || $data['members_with_consent'] === null) {
        $data['members_with_consent'] = get_option(BES_V3_OPTION_PREFIX . 'last_sync_members_with_consent', null);
    }
    if (!isset($data['members_total']) || $data['members_total'] === null) {
        $data['members_total'] = get_option(BES_V3_OPTION_PREFIX . 'total_members', null);
    }
    if (!isset($data['last_sync_time']) || $data['last_sync_time'] === null) {
        $data['last_sync_time'] = get_option(BES_V3_OPTION_PREFIX . 'last_sync_time', null);
    }
    
    // Sync-Part-Informationen (für Statusleiste)
    if (!isset($data['current_part']) || $data['current_part'] === null) {
        $data['current_part'] = get_option(BES_V3_OPTION_PREFIX . 'current_part', null);
    }
    if (!isset($data['total_parts']) || $data['total_parts'] === null) {
        $total_members = (int) get_option(BES_V3_OPTION_PREFIX . 'total_members', 0);
        $batch_size = (int) get_option(BES_V3_OPTION_PREFIX . 'batch_size', BES_V3_BATCH_SIZE_DEFAULT);
        $data['total_parts'] = $total_members > 0 ? ceil($total_members / $batch_size) : null;
    }
    
    // Explorer-Info
    $data['explorer_last_run'] = get_option(BES_V3_OPTION_PREFIX . 'explorer_last_run', null);
    $data['explorer_field_count'] = get_option(BES_V3_OPTION_PREFIX . 'explorer_field_count', null);
    $data['explorer_running'] = $explorer_running;
    
    wp_send_json_success($data);
});

/**
 * AJAX: V3 Sync stoppen
 */
add_action('wp_ajax_bes_v3_stop_sync', function () {
    if (!isset($_REQUEST['_ajax_nonce']) || !wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bes_admin_nonce')) {
        wp_send_json_error(['error' => esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => esc_html__('Keine Berechtigung', BES_TEXT_DOMAIN)]);
        return;
    }
    
    $hook = BES_V3_CRON_HOOK;
    bseasy_v3_update_status(0, 0, 'Sync wurde gestoppt', 'cancelled');
    delete_option(BES_V3_OPTION_PREFIX . 'current_part');
    
    if (function_exists('wp_unschedule_hook')) {
        wp_unschedule_hook($hook);
    } else {
        wp_clear_scheduled_hook($hook);
    }
    
    wp_send_json_success(['message' => esc_html__('Sync wurde gestoppt.', BES_TEXT_DOMAIN)]);
});

/**
 * AJAX: V3 Explorer stoppen
 */
add_action('wp_ajax_bes_v3_stop_explorer', function () {
    if (!isset($_REQUEST['_ajax_nonce']) || !wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bes_admin_nonce')) {
        wp_send_json_error(['error' => esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => esc_html__('Keine Berechtigung', BES_TEXT_DOMAIN)]);
        return;
    }
    
    // Setze Explorer-Status auf "cancelled"
    bseasy_v3_update_status(0, 100, 'API Explorer wurde gestoppt', 'cancelled');
    delete_option(BES_V3_OPTION_PREFIX . 'explorer_running');
    
    // Geplanten Explorer-Cron-Job entfernen
    $hook = 'bes_run_explorer_v3';
    if (function_exists('wp_unschedule_hook')) {
        wp_unschedule_hook($hook);
    } else {
        wp_clear_scheduled_hook($hook);
    }
    
    wp_send_json_success([
        'message' => esc_html__('API Explorer wurde gestoppt.', BES_TEXT_DOMAIN),
    ]);
});

/**
 * AJAX: V3 Sync zurücksetzen
 */
add_action('wp_ajax_bes_v3_reset_sync', function () {
    if (!isset($_REQUEST['_ajax_nonce']) || !wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bes_admin_nonce')) {
        wp_send_json_error(['error' => esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => esc_html__('Keine Berechtigung', BES_TEXT_DOMAIN)]);
        return;
    }
    
    $hook = BES_V3_CRON_HOOK;
    wp_clear_scheduled_hook($hook);
    
    $status_file = BES_DATA_V3 . BES_V3_STATUS_FILE;
    if (file_exists($status_file)) {
        @unlink($status_file);
    }
    
    delete_option(BES_V3_OPTION_PREFIX . 'current_part');
    delete_option(BES_V3_OPTION_PREFIX . 'total_members');
    delete_option(BES_V3_OPTION_PREFIX . 'last_error');
    
    wp_send_json_success(['message' => esc_html__('Sync wurde zurückgesetzt.', BES_TEXT_DOMAIN)]);
});

/**
 * AJAX: V3 Parts zusammenführen
 */
add_action('wp_ajax_bes_v3_merge_parts', function () {
    if (!isset($_REQUEST['_ajax_nonce']) || !wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bes_admin_nonce')) {
        wp_send_json_error(['error' => esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => esc_html__('Keine Berechtigung', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!function_exists('bes_check_rate_limit') || !bes_check_rate_limit('bes_v3_merge_parts', 5, 60)) {
        wp_send_json_error(['error' => esc_html__('Zu viele Anfragen. Bitte warten Sie einen Moment.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (function_exists('bes_clear_render_cache')) {
        bes_clear_render_cache();
    }
    
    $result = bseasy_v3_merge_parts();
    
    if ($result['success']) {
        update_option(BES_V3_OPTION_PREFIX . 'last_sync_time', current_time('mysql'));
        update_option(BES_V3_OPTION_PREFIX . 'last_sync_members_with_consent', (int)($result['members_count'] ?? 0));
        
        if (function_exists('bes_clear_render_cache')) {
            bes_clear_render_cache();
        }
        
        wp_send_json_success([
            'msg' => "✅ Zusammenführung erfolgreich: {$result['members_count']} Mitglieder aus {$result['parts_count']} Teilen",
            'count' => $result['members_count']
        ]);
    } else {
        wp_send_json_error(['error' => esc_html($result['error'] ?? __('Unbekannter Fehler', BES_TEXT_DOMAIN))]);
    }
});

/**
 * AJAX: Feldkatalog laden
 */
add_action('wp_ajax_bes_v3_load_catalog', function () {
    if (!isset($_REQUEST['_ajax_nonce']) || !wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bes_admin_nonce')) {
        wp_send_json_error(['error' => esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => esc_html__('Keine Berechtigung', BES_TEXT_DOMAIN)]);
        return;
    }
    
    $catalog_file = BES_DATA_V3 . BES_V3_FIELD_CATALOG;
    if (!file_exists($catalog_file)) {
        wp_send_json_error(['error' => esc_html__('Feldkatalog nicht gefunden. Bitte führe zuerst den API Explorer aus.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    $catalog = bseasy_v3_read_json($catalog_file);
    if (!$catalog) {
        wp_send_json_error(['error' => esc_html__('Konnte Feldkatalog nicht laden.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    wp_send_json_success(['catalog' => $catalog]);
});

/**
 * AJAX: Aktuelle Selection laden
 */
add_action('wp_ajax_bes_v3_load_selection', function () {
    if (!isset($_REQUEST['_ajax_nonce']) || !wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bes_admin_nonce')) {
        wp_send_json_error(['error' => esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => esc_html__('Keine Berechtigung', BES_TEXT_DOMAIN)]);
        return;
    }
    
    $selection = bseasy_v3_load_selection();
    wp_send_json_success(['selection' => $selection]);
});

/**
 * AJAX: V3 Selection speichern
 */
add_action('wp_ajax_bes_v3_save_selection', function () {
    if (!isset($_REQUEST['_ajax_nonce']) || !wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bes_admin_nonce')) {
        wp_send_json_error(['error' => esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => esc_html__('Keine Berechtigung', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!isset($_POST['fields']) || !is_array($_POST['fields'])) {
        wp_send_json_error(['error' => esc_html__('Ungültige Feldauswahl.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    // Validiere Pflichtfelder
    $fields = array_map('sanitize_text_field', $_POST['fields']);
    $required_fields = BES_V3_REQUIRED_FIELDS;
    $fields = array_unique(array_merge($required_fields, $fields));
    
    $selection = [
        'fields' => $fields,
        'source' => 'manual',
        'updated_at' => date('c'),
    ];
    
    if (bseasy_v3_save_selection($selection)) {
        wp_send_json_success([
            'message' => esc_html__('Feldauswahl gespeichert.', BES_TEXT_DOMAIN),
            'field_count' => count($fields)
        ]);
    } else {
        wp_send_json_error(['error' => esc_html__('Konnte Feldauswahl nicht speichern.', BES_TEXT_DOMAIN)]);
    }
});

/**
 * AJAX: V3 Selection zurücksetzen
 */
add_action('wp_ajax_bes_v3_reset_selection', function () {
    if (!isset($_REQUEST['_ajax_nonce']) || !wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bes_admin_nonce')) {
        wp_send_json_error(['error' => esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => esc_html__('Keine Berechtigung', BES_TEXT_DOMAIN)]);
        return;
    }
    
    $selection = [
        'fields' => BES_V3_REQUIRED_FIELDS,
        'source' => 'default',
        'updated_at' => date('c'),
    ];
    
    if (bseasy_v3_save_selection($selection)) {
        wp_send_json_success([
            'message' => esc_html__('Feldauswahl auf Pflichtfelder zurückgesetzt.', BES_TEXT_DOMAIN),
            'field_count' => count(BES_V3_REQUIRED_FIELDS)
        ]);
    } else {
        wp_send_json_error(['error' => esc_html__('Konnte Feldauswahl nicht zurücksetzen.', BES_TEXT_DOMAIN)]);
    }
});

/**
 * AJAX: V3 Consent Audit starten (asynchron über WP-Cron)
 */
add_action('wp_ajax_bes_v3_audit_consent', function () {
    if (!isset($_REQUEST['_ajax_nonce']) || !wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bes_admin_nonce')) {
        wp_send_json_error(['error' => esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => esc_html__('Keine Berechtigung', BES_TEXT_DOMAIN)]);
        return;
    }
    
    // Rate-Limiting
    if (!function_exists('bes_check_rate_limit') || !bes_check_rate_limit('bes_v3_audit_consent', 2, 300)) {
        wp_send_json_error(['error' => esc_html__('Zu viele Anfragen. Bitte warten Sie einige Minuten.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    // Setze Audit-Status auf "running"
    bseasy_v3_update_status(0, 100, "Consent-Audit wird gestartet...", 'running');
    update_option(BES_V3_OPTION_PREFIX . 'audit_running', true);
    
    // Plane WP-Cron-Job für sofortige Ausführung
    $hook = 'bes_run_audit_consent_v3';
    
    // Lösche eventuell vorhandene alte Jobs
    wp_clear_scheduled_hook($hook);
    
    // Plane neuen Job für sofortige Ausführung
    $scheduled = wp_schedule_single_event(time(), $hook);
    
    if ($scheduled === false) {
        bseasy_v3_update_status(0, 100, 'Fehler: Konnte Audit-Job nicht planen', 'error');
        delete_option(BES_V3_OPTION_PREFIX . 'audit_running');
        wp_send_json_error(['error' => esc_html__('Konnte Audit nicht starten. Bitte WP-Cron prüfen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    // SOFORT antworten (Audit läuft jetzt asynchron über WP-Cron)
    wp_send_json_success([
        'msg' => "Consent-Audit gestartet – läuft im Hintergrund",
        'async' => true
    ]);
    
    // Trigger WP-Cron sofort
    if (function_exists('spawn_cron')) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            spawn_cron();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
            spawn_cron();
        } else {
            spawn_cron();
        }
    }
    
    return;
});

/**
 * AJAX: V3 Audit Status abrufen
 */
add_action('wp_ajax_bes_v3_audit_status', function () {
    if (!isset($_REQUEST['_ajax_nonce']) || !wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bes_admin_nonce')) {
        wp_send_json_error(['error' => esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN)]);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['error' => esc_html__('Keine Berechtigung', BES_TEXT_DOMAIN)]);
        return;
    }
    
    $audit_running = get_option(BES_V3_OPTION_PREFIX . 'audit_running', false);
    $audit_file = BES_DATA_V3 . 'audit_consent_v3.json';
    
    $result = [
        'running' => $audit_running,
        'has_result' => file_exists($audit_file),
        'audit_file' => $audit_file,
    ];
    
    // Lade Status-Datei falls vorhanden
    $status_file = BES_DATA_V3 . BES_V3_STATUS_FILE;
    if (file_exists($status_file)) {
        $status_data = bseasy_v3_read_json($status_file);
        if ($status_data && isset($status_data['message'])) {
            $result['status_message'] = $status_data['message'];
            $result['state'] = $status_data['state'] ?? 'unknown';
        }
    }
    
    // Lade Audit-Ergebnis falls vorhanden
    if (file_exists($audit_file)) {
        $audit_data = bseasy_v3_read_json($audit_file);
        if ($audit_data) {
            $result['audit'] = $audit_data;
        }
    }
    
    wp_send_json_success($result);
});
