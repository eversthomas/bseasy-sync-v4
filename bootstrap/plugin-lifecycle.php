<?php
/**
 * Aktivierung, Deaktivierung, Deinstallation, Migration.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin-Aktivierung
 */
function bes_plugin_activate() {
    // Speichere aktuellen Error-Reporting-Level
    $error_level = error_reporting();

    // Unterdrücke nur Warnungen und Notices während der Aktivierung
    // Aber behalte Error-Reporting für kritische Fehler
    error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR);

    // KEIN Output-Buffering-Manipulation mehr!
    // WordPress verwaltet das selbst - wir sollten es nicht stören

    // Stelle sicher, dass Konstanten definiert sind
    if (!defined('BES_DATA')) {
        if (function_exists('wp_upload_dir')) {
            $upload_dir = wp_upload_dir();
            if (isset($upload_dir['basedir']) && isset($upload_dir['baseurl'])) {
                define('BES_UPLOADS_DIR', trailingslashit($upload_dir['basedir']) . 'bseasy-sync/');
                define('BES_UPLOADS_URL', trailingslashit($upload_dir['baseurl']) . 'bseasy-sync/');
            } else {
                define('BES_UPLOADS_DIR', WP_CONTENT_DIR . '/uploads/bseasy-sync/');
                define('BES_UPLOADS_URL', (function_exists('content_url') ? content_url('/uploads/bseasy-sync/') : ''));
            }
        } else {
            define('BES_UPLOADS_DIR', WP_CONTENT_DIR . '/uploads/bseasy-sync/');
            define('BES_UPLOADS_URL', '');
        }
        define('BES_DATA', BES_UPLOADS_DIR);
        define('BES_IMG', trailingslashit(BES_UPLOADS_DIR) . 'img/');
    }

    // Stelle sicher, dass Standard-Konstanten definiert sind
    if (!defined('BES_BATCH_SIZE_DEFAULT')) {
        define('BES_BATCH_SIZE_DEFAULT', 200);
    }
    if (!defined('BES_CONSENT_FIELD_ID_DEFAULT')) {
        define('BES_CONSENT_FIELD_ID_DEFAULT', 282018660);
    }

    // Erstelle notwendige Verzeichnisse
    if (function_exists('wp_mkdir_p') && defined('BES_DATA') && BES_DATA) {
        if (!file_exists(BES_DATA)) {
            wp_mkdir_p(BES_DATA);
            @chmod(BES_DATA, 0755);
        }
        if (defined('BES_IMG') && BES_IMG && !file_exists(BES_IMG)) {
            wp_mkdir_p(BES_IMG);
            @chmod(BES_IMG, 0755);
        }
        // V2-Verzeichnisse werden nicht mehr erstellt (nur noch V3)
    }

    // Setze Standard-Optionen (autoload=false für Performance)
    if (!get_option('bes_batch_size')) {
        update_option('bes_batch_size', BES_BATCH_SIZE_DEFAULT, false);
    }
    if (!get_option('bes_consent_field_id')) {
        update_option('bes_consent_field_id', BES_CONSENT_FIELD_ID_DEFAULT, false);
    }

    // Flush Rewrite Rules falls nötig
    flush_rewrite_rules(false);

    // Migration zu getrennten Verzeichnissen (einmalig)
    bes_migrate_to_versioned_dirs();

    // Stelle Error-Reporting wieder her
    error_reporting($error_level);
}

/**
 * Plugin-Deaktivierung
 */
function bes_plugin_deactivate() {
    // Legacy-Cleanup: Lösche alte V2-Status-Dateien (falls noch vorhanden)
    // V2 wurde entfernt, aber alte Dateien könnten noch existieren
    $status_file = BES_DATA . 'status.json';
    if (file_exists($status_file)) {
        @unlink($status_file);
    }

    // Lösche Render-Cache
    if (function_exists('bes_clear_render_cache')) {
        bes_clear_render_cache();
    }

    // Lösche temporäre Part-Dateien (optional - nur wenn gewünscht)
    // $part_files = glob(BES_DATA . 'members_consent_part*.json');
}

/**
 * Migration zu V2-Verzeichnis
 * Verschiebt bestehende V2-Dateien ins v2/ Verzeichnis (falls noch nicht geschehen)
 */
function bes_migrate_to_versioned_dirs() {
    // Nur einmalig ausführen
    if (get_option('bes_versioned_dirs_migration_completed', false)) {
        return;
    }

    // Stelle sicher, dass V2-Verzeichnis existiert
    if (defined('BES_DATA_V2') && !file_exists(BES_DATA_V2)) {
        wp_mkdir_p(BES_DATA_V2);
        @chmod(BES_DATA_V2, 0755);
    }

    $migration_performed = false;

    // Verschiebe V2-Dateien (members_consent_v2*.json) ins v2/ Verzeichnis
    $v2_files = glob(BES_DATA . 'members_consent_v2*.json');
    if ($v2_files !== false) {
        foreach ($v2_files as $file) {
            $filename = basename($file);
            $new_path = BES_DATA_V2 . $filename;
            if (file_exists($file) && !file_exists($new_path)) {
                if (@rename($file, $new_path)) {
                    $migration_performed = true;
                }
            }
        }
    }

    if ($migration_performed) {
        update_option('bes_versioned_dirs_migration_completed', true, false);
    }
}

/**
 * Plugin-Deinstallation (nur wenn explizit gelöscht)
 */
function bes_plugin_uninstall() {
    // Lösche alle Plugin-Optionen
    delete_option('bes_api_token');
    delete_option('bes_consent_field_id');
    delete_option('bes_batch_size');
    delete_option('bes_auto_continue');
    delete_option('bes_last_sync_time');
    delete_option('bes_last_sync_members_with_consent');
    delete_option('bes_last_sync_members_total');
    delete_option('bes_total_members');
    delete_option('bes_current_part');
    delete_option('bes_last_error');
    delete_option('bes_calendars');
    delete_option('bes_map_enabled');
    delete_option('bes_map_style');
    delete_option('bes_map_zoom');
    delete_option('bes_map_center_lat');
    delete_option('bes_map_center_lng');
    delete_option('bes_migration_completed');

    // Lösche alle Transients
    if (function_exists('bes_clear_render_cache')) {
        bes_clear_render_cache();
    }

    // WICHTIG: Lösche KEINE Daten-Dateien (members_consent.json, etc.)
    // Diese sollen erhalten bleiben, falls Plugin wieder installiert wird
}
