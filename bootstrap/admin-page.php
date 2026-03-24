<?php
/**
 * Admin-Hauptseite: POST-Verarbeitung und Einbindung der Tab-UI.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

function bes_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Debug: Initial-Log beim Seitenaufruf
    bes_write_debug_log("Admin-Seite geladen - bes_admin_page() aufgerufen", "INFO", "bes_admin_page");
    bes_write_debug_log("BES_DIR: " . (defined('BES_DIR') ? BES_DIR : 'NICHT DEFINIERT'), "INFO", "bes_admin_page");
    bes_write_debug_log("PHP Version: " . PHP_VERSION, "INFO", "bes_admin_page");
    bes_write_debug_log("WordPress Version: " . get_bloginfo('version'), "INFO", "bes_admin_page");

    // Nonce-Verifizierung für alle POST-Requests
    if (isset($_POST['bes_token']) || isset($_POST['bes_consent_field_id']) ||
        isset($_POST['bes_batch_size']) || isset($_POST['bes_auto_continue']) || isset($_POST['bes_sync_all_members'])) {
        if (!isset($_POST['bes_admin_nonce']) || !wp_verify_nonce($_POST['bes_admin_nonce'], 'bes_admin_save')) {
            wp_die(esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN));
        }
    }

    // Token speichern (verschlüsselt)
    if (isset($_POST['bes_token'])) {
        $token = sanitize_text_field($_POST['bes_token']);
        // Nur speichern wenn Token nicht leer ist (leer = beibehalten)
        if (!empty($token)) {
            // Verschlüssele Token vor dem Speichern
            if (function_exists('bes_encrypt_token')) {
                $encrypted_token = bes_encrypt_token($token);
                update_option('bes_api_token', $encrypted_token);
                echo '<div class="updated"><p>' . esc_html__('Token gespeichert.', BES_TEXT_DOMAIN) . '</p></div>';
            } else {
                // Fallback: Unverschlüsselt speichern (für Migration)
                update_option('bes_api_token', $token);
                echo '<div class="updated"><p>' . esc_html__('Token gespeichert (unverschlüsselt - Migration).', BES_TEXT_DOMAIN) . '</p></div>';
            }
        }
    }

    // Consent-Feld-ID speichern mit Validierung
    if (isset($_POST['bes_consent_field_id'])) {
        $consent_id = intval($_POST['bes_consent_field_id']);
        // Validierung: zwischen 1 und 999999999 (sinnvolle Obergrenze)
        if ($consent_id > 0 && $consent_id <= BES_CONSENT_FIELD_ID_MAX) {
            update_option('bes_consent_field_id', $consent_id);
            echo '<div class="updated"><p>' . esc_html__('Consent-Feld-ID gespeichert.', BES_TEXT_DOMAIN) . '</p></div>';
        } else {
            echo '<div class="error"><p>' . sprintf(
                esc_html__('Ungültige Consent-Feld-ID. Bitte einen Wert zwischen %d und %d eingeben.', BES_TEXT_DOMAIN),
                BES_CONSENT_FIELD_ID_MIN,
                BES_CONSENT_FIELD_ID_MAX
            ) . '</p></div>';
        }
    }

    // Batch-Größe speichern
    if (isset($_POST['bes_batch_size'])) {
        $batch_size = intval($_POST['bes_batch_size']);
        if ($batch_size >= BES_BATCH_SIZE_MIN && $batch_size <= BES_BATCH_SIZE_MAX) {
            update_option('bes_batch_size', $batch_size);
            echo '<div class="updated"><p>' . esc_html__('Batch-Größe gespeichert.', BES_TEXT_DOMAIN) . '</p></div>';
        } else {
            echo '<div class="error"><p>' . sprintf(
                esc_html__('Batch-Größe muss zwischen %d und %d liegen.', BES_TEXT_DOMAIN),
                BES_BATCH_SIZE_MIN,
                BES_BATCH_SIZE_MAX
            ) . '</p></div>';
        }
    }

    // V2-Sync-Methode wurde entfernt - nur noch V3 wird verwendet

    // Automatische Fortsetzung speichern
    if (isset($_POST['bes_auto_continue'])) {
        update_option('bes_auto_continue', true);
    } else {
        update_option('bes_auto_continue', false);
    }

    // Sync-alle-Mitglieder-Option speichern
    if (isset($_POST['bes_sync_all_members'])) {
        update_option('bes_sync_all_members', true);
        echo '<div class="updated"><p>' . esc_html__('Sync-Modus gespeichert: Alle Mitglieder werden synchronisiert (ohne Consent-Filter).', BES_TEXT_DOMAIN) . '</p></div>';
    } else {
        update_option('bes_sync_all_members', false);
        echo '<div class="updated"><p>' . esc_html__('Sync-Modus gespeichert: Nur Mitglieder mit Consent werden synchronisiert.', BES_TEXT_DOMAIN) . '</p></div>';
    }

    // Prüfe und bereinige abgebrochene Syncs beim Speichern (verhindert falsche "running"-Anzeige)
    if (isset($_POST['bes_sync_all_members']) || isset($_POST['bes_consent_field_id'])) {
        if (defined('BES_DATA_V3') && defined('BES_V3_STATUS_FILE')) {
            $status_file = BES_DATA_V3 . BES_V3_STATUS_FILE;
            if (file_exists($status_file)) {
                $status_data = json_decode(file_get_contents($status_file), true);
                if (is_array($status_data) && isset($status_data['state']) && $status_data['state'] === 'cancelled') {
                    // Setze Status auf idle und explorer_running auf false
                    $status_data['state'] = 'idle';
                    $status_data['progress'] = 0;
                    $status_data['message'] = '';
                    file_put_contents($status_file, json_encode($status_data, JSON_PRETTY_PRINT));

                    if (defined('BES_V3_OPTION_PREFIX')) {
                        update_option(BES_V3_OPTION_PREFIX . 'explorer_running', false);
                    }
                }
            }
        }
    }

    // Admin-UI laden
    require_once BES_DIR . 'admin/views/ui-main.php';
}
