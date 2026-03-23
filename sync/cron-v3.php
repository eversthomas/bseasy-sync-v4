<?php
/**
 * BSEasy Sync V3 - Cron System
 * 
 * Verwaltet asynchrone V3 Sync-Durchläufe über WP-Cron
 * Komplett getrennt von V2
 * 
 * @package BSEasySync
 * @subpackage V3
 * @author Tom Evers <https://bezugssysteme.de>
 * @since 3.0.0
 */

if (!defined('ABSPATH')) exit;

// Lade Basis-Konstanten
if (!defined('BES_DATA')) {
    $main_file = plugin_dir_path(__DIR__) . 'bseasy-sync.php';
    if (file_exists($main_file)) {
        require_once $main_file;
    }
}

// Lade V3-Helpers
require_once BES_DIR . 'sync/v3-helpers.php';

// Lade V3 Sync
require_once BES_DIR . 'sync/api-core-consent-v3.php';

// Lade Explorer
require_once BES_DIR . 'sync/api-explorer-v3.php';

// Lade Audit-Funktionen
require_once BES_DIR . 'sync/v3-consent-audit.php';

/**
 * V3 Explorer (für WP-Cron)
 * 
 * @param int $sample_size Sample-Größe
 * @param bool $fresh_from_api Ob frische Daten von API geholt werden sollen
 */
add_action('bes_run_explorer_v3', function ($sample_size = 100, $fresh_from_api = true) {
    try {
        // Timeout-Setting
        if (function_exists('bes_safe_set_time_limit')) {
            bes_safe_set_time_limit(0);
        } else {
            @set_time_limit(0);
        }
        
        if (function_exists('bes_safe_increase_memory')) {
            bes_safe_increase_memory('512M');
        }
        
        bseasy_v3_update_status(0, 100, "API Explorer läuft... (Sample: $sample_size)", 'running');
        
        // Führe Explorer aus
        $result = bseasy_v3_run_explorer($sample_size, $fresh_from_api);
        
        // Lösche Running-Flag
        delete_option(BES_V3_OPTION_PREFIX . 'explorer_running');
        
        if ($result['success']) {
            bseasy_v3_update_status(100, 100, $result['message'], 'done');
            bseasy_v3_log("API Explorer erfolgreich abgeschlossen", 'INFO');
        } else {
            bseasy_v3_update_status(0, 100, "Fehler: " . ($result['error'] ?? 'Unbekannter Fehler'), 'error');
            bseasy_v3_log("API Explorer fehlgeschlagen: " . ($result['error'] ?? 'Unbekannter Fehler'), 'ERROR');
        }
        
    } catch (Throwable $e) {
        delete_option(BES_V3_OPTION_PREFIX . 'explorer_running');
        $error_msg = $e->getMessage();
        bseasy_v3_update_status(0, 100, "Kritischer Fehler: $error_msg", 'error');
        bseasy_v3_log(
            "V3 Explorer Fehler: $error_msg in " . $e->getFile() . ":" . $e->getLine(),
            'ERROR'
        );
    }
}, 10, 2);

/**
 * V3 Sync-Durchlauf (für WP-Cron)
 * 
 * @param int $offset Start-Offset
 * @param int $limit Batch-Größe
 * @param int $part Aktuelle Durchlauf-Nummer
 */
add_action(BES_V3_CRON_HOOK, function ($offset = 0, $limit = 200, $part = 1) {
    try {
        // Prüfe ob Sync gestoppt wurde
        $status_file = BES_DATA_V3 . BES_V3_STATUS_FILE;
        if (file_exists($status_file)) {
            $status_data = bseasy_v3_read_json($status_file);
            if (isset($status_data['state']) && $status_data['state'] === 'cancelled') {
                bseasy_v3_log("V3 Sync-Durchlauf $part wurde übersprungen - Sync wurde gestoppt", 'INFO');
                return;
            }
        }
        
        $batch_size = (int) get_option(BES_V3_OPTION_PREFIX . 'batch_size', $limit);
        $auto_continue = (bool) get_option(BES_V3_OPTION_PREFIX . 'auto_continue', false);
        $total_members = (int) get_option(BES_V3_OPTION_PREFIX . 'total_members', 0);
        
        // Timeout-Setting
        if (function_exists('bes_safe_set_time_limit')) {
            bes_safe_set_time_limit(0);
        } else {
            @set_time_limit(0);
        }
        
        // Speichere current_part in Option
        update_option(BES_V3_OPTION_PREFIX . 'current_part', $part);
        
        $total_parts_estimated = $total_members > 0 ? ceil($total_members / $batch_size) : 0;
        
        bseasy_v3_update_status(0, 0, "Starte Durchlauf $part (Offset: $offset, Limit: $limit, Methode: V3) ...", 'running', [
            'current_part' => $part,
            'total_parts' => $total_parts_estimated
        ]);
        
        // Sync ausführen
        $result = bseasy_v3_run_sync($offset, $batch_size);
        $total_members = (int) get_option(BES_V3_OPTION_PREFIX . 'total_members', 0);
        
        $is_success = isset($result['success']) && $result['success'] === true;
        
        if ($is_success) {
            // Gesamtanzahl speichern
            $actual_total = 0;
            if (isset($result['meta']['total_members']) && $result['meta']['total_members'] > 0) {
                $actual_total = (int)$result['meta']['total_members'];
            } elseif (isset($result['members_total']) && $result['members_total'] > 0) {
                $actual_total = (int)$result['members_total'];
            }
            
            if ($actual_total > 0) {
                update_option(BES_V3_OPTION_PREFIX . 'total_members', $actual_total);
                $total_members = $actual_total;
            }
            
            $next = $part + 1;
            $total_parts = $actual_total > 0 ? ceil($actual_total / $batch_size) : 0;
            
            // Prüfe ob noch weitere Durchläufe nötig sind
            $needs_more = false;
            if (isset($result['incomplete']) && $result['incomplete'] === true) {
                $needs_more = true;
            } elseif (isset($result['needs_more'])) {
                $needs_more = $result['needs_more'];
            } else {
                $needs_more = $actual_total > 0 && ($offset + $batch_size) < $actual_total;
            }
            
            if ($needs_more) {
                // Prüfe ob Sync gestoppt wurde
                $status_file = BES_DATA_V3 . BES_V3_STATUS_FILE;
                $sync_cancelled = false;
                if (file_exists($status_file)) {
                    $status_data = bseasy_v3_read_json($status_file);
                    if (isset($status_data['state']) && $status_data['state'] === 'cancelled') {
                        $sync_cancelled = true;
                    }
                }
                
                if ($sync_cancelled) {
                    bseasy_v3_update_status(100, 100, "⏸️ Durchlauf $part abgeschlossen – Sync wurde gestoppt", 'cancelled');
                    delete_option(BES_V3_OPTION_PREFIX . 'current_part');
                    return;
                }
                
                update_option(BES_V3_OPTION_PREFIX . 'current_part', $next);
                
                if ($auto_continue) {
                    // Prüfe nochmal ob Sync gestoppt wurde
                    $status_file = BES_DATA_V3 . BES_V3_STATUS_FILE;
                    $sync_cancelled = false;
                    if (file_exists($status_file)) {
                        $status_data = bseasy_v3_read_json($status_file);
                        if (isset($status_data['state']) && $status_data['state'] === 'cancelled') {
                            $sync_cancelled = true;
                        }
                    }
                    
                    if ($sync_cancelled) {
                        bseasy_v3_update_status(100, 100, "⏸️ Durchlauf $part abgeschlossen – Sync wurde gestoppt", 'cancelled');
                        delete_option(BES_V3_OPTION_PREFIX . 'current_part');
                        return;
                    }
                    
                    // Plane nächsten Durchlauf
                    $next_offset = $offset + $batch_size;
                    $next_hook = BES_V3_CRON_HOOK;
                    $next_args = [$next_offset, $batch_size, $next];
                    
                    wp_clear_scheduled_hook($next_hook, $next_args);
                    $scheduled = wp_schedule_single_event(time() + 2, $next_hook, $next_args);
                    
                    if ($scheduled !== false) {
                        bseasy_v3_update_status(100, 100, "✅ Durchlauf $part abgeschlossen – Starte Durchlauf $next in 2 Sekunden...", 'running', [
                            'current_part' => $next,
                            'total_parts' => $total_parts
                        ]);
                        spawn_cron();
                    } else {
                        bseasy_v3_update_status(100, 100, "✅ Durchlauf $part abgeschlossen – Fehler beim Planen des nächsten Durchlaufs", 'error', [
                            'current_part' => $part,
                            'total_parts' => $total_parts
                        ]);
                    }
                } else {
                    bseasy_v3_update_status(100, 100, "✅ Durchlauf $part abgeschlossen – Bitte Sync erneut starten für Durchlauf $next" . ($total_parts > 0 ? " von $total_parts" : ""), 'done', [
                        'current_part' => $part,
                        'total_parts' => $total_parts
                    ]);
                }
            } else {
                // Alle Durchläufe abgeschlossen
                delete_option(BES_V3_OPTION_PREFIX . 'current_part');
                
                // Prüfe ob merged Datei existiert
                $merged_file = BES_DATA_V3 . BES_V3_MEMBERS_FILE;
                $merge_was_successful = file_exists($merged_file);
                
                if (!$merge_was_successful) {
                    // Merge wurde nicht automatisch durchgeführt - versuche es jetzt
                    bseasy_v3_log("⚠️ Merge wurde nicht automatisch durchgeführt - starte manuell...", 'WARN');
                    $merge_result = bseasy_v3_merge_parts();
                    if ($merge_result['success']) {
                        $merge_was_successful = true;
                        update_option(BES_V3_OPTION_PREFIX . 'last_sync_time', current_time('mysql'));
                        update_option(BES_V3_OPTION_PREFIX . 'last_sync_members_with_consent', (int)($merge_result['members_count'] ?? 0));
                        bseasy_v3_update_status(
                            100,
                            100,
                            "✅ Alle Durchläufe abgeschlossen und zusammengeführt: {$merge_result['members_count']} Mitglieder",
                            'done'
                        );
                    } else {
                        bseasy_v3_update_status(
                            100,
                            100,
                            "✅ Alle Durchläufe abgeschlossen - Merge fehlgeschlagen: " . ($merge_result['error'] ?? 'Unbekannt'),
                            'error'
                        );
                    }
                } else {
                    bseasy_v3_update_status(100, 100, "✅ Alle Durchläufe abgeschlossen und zusammengeführt.", 'done');
                }
                
                // Lösche total_members nur wenn Merge erfolgreich war
                if ($merge_was_successful) {
                    delete_option(BES_V3_OPTION_PREFIX . 'total_members');
                }
            }
        } else {
            // Fehler
            $error = $result['error'] ?? 'Unbekannter Fehler';
            bseasy_v3_log("V3 Sync-Durchlauf $part fehlgeschlagen: $error", 'ERROR');
            
            bseasy_v3_update_status(0, 0, "Fehler: $error", 'error');
            update_option(BES_V3_OPTION_PREFIX . 'last_error', [
                'part' => $part,
                'offset' => $offset,
                'error' => $error,
                'timestamp' => time()
            ]);
        }
        
    } catch (Throwable $e) {
        $error_msg = $e->getMessage();
        bseasy_v3_update_status(0, 0, "Kritischer Fehler: $error_msg", 'error');
        
        bseasy_v3_log(
            "V3 Cron Sync Fehler: $error_msg in " . $e->getFile() . ":" . $e->getLine(),
            'ERROR'
        );
        
        update_option(BES_V3_OPTION_PREFIX . 'last_error', [
            'part' => $part,
            'offset' => $offset,
            'error' => $error_msg,
            'timestamp' => time()
        ]);
    }
}, 10, 3);

/**
 * V3 Consent Audit (für WP-Cron)
 */
add_action('bes_run_audit_consent_v3', function () {
    try {
        // Timeout-Setting
        if (function_exists('bes_safe_set_time_limit')) {
            bes_safe_set_time_limit(0);
        } else {
            @set_time_limit(0);
        }
        
        if (function_exists('bes_safe_increase_memory')) {
            bes_safe_increase_memory('512M');
        }
        
        bseasy_v3_update_status(0, 100, "Consent-Audit läuft...", 'running');
        
        // Token prüfen
        $encrypted_token = get_option('bes_api_token', '');
        if (empty($encrypted_token)) {
            bseasy_v3_update_status(0, 100, "Fehler: Kein API-Token konfiguriert", 'error');
            delete_option(BES_V3_OPTION_PREFIX . 'audit_running');
            return;
        }
        
        $token = function_exists('bes_decrypt_token') ? bes_decrypt_token($encrypted_token) : $encrypted_token;
        if (empty($token)) {
            bseasy_v3_update_status(0, 100, "Fehler: Token konnte nicht entschlüsselt werden", 'error');
            delete_option(BES_V3_OPTION_PREFIX . 'audit_running');
            return;
        }
        
        $baseUsed = null;
        
        // Führe Audit aus
        bseasy_v3_log("AUDIT: Consent-Audit gestartet (via Cron)", 'INFO');
        $audit_result = bseasy_v3_audit_consent($token, $baseUsed);
        
        // Lösche Running-Flag
        delete_option(BES_V3_OPTION_PREFIX . 'audit_running');
        
        // Speichere Audit-Ergebnis
        $audit_file = BES_DATA_V3 . 'audit_consent_v3.json';
        bseasy_v3_safe_write_json($audit_file, json_encode($audit_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        if (!empty($audit_result['success'])) {
            $summary = "Audit abgeschlossen";
            if (isset($audit_result['local_check_results'])) {
                $summary .= " - CHECK_OLD: " . $audit_result['local_check_results']['check_old_count'] . 
                           ", CHECK_NEW: " . $audit_result['local_check_results']['check_new_count'];
            }
            bseasy_v3_update_status(100, 100, $summary, 'done');
            bseasy_v3_log("AUDIT: Consent-Audit erfolgreich abgeschlossen - Ergebnis gespeichert in $audit_file", 'INFO');
        } else {
            $error_msg = !empty($audit_result['errors']) ? implode(', ', $audit_result['errors']) : 'Unbekannter Fehler';
            bseasy_v3_update_status(0, 100, "Fehler: $error_msg", 'error');
            bseasy_v3_log("AUDIT: Consent-Audit fehlgeschlagen: $error_msg", 'ERROR');
        }
        
    } catch (Throwable $e) {
        delete_option(BES_V3_OPTION_PREFIX . 'audit_running');
        $error_msg = $e->getMessage();
        bseasy_v3_update_status(0, 100, "Kritischer Fehler: $error_msg", 'error');
        bseasy_v3_log(
            "V3 Audit Fehler: $error_msg in " . $e->getFile() . ":" . $e->getLine(),
            'ERROR'
        );
    }
});
