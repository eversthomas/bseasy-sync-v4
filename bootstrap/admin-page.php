<?php
/**
 * Admin-Hauptseite: POST-Verarbeitung und Einbindung der Tab-UI.
 *
 * Verwendet das PRG-Pattern (Post/Redirect/Get): Nach erfolgreichem Speichern
 * wird ein Redirect ausgeführt, sodass Browser-Reloads keine Duplikate erzeugen.
 * Fehlermeldungen werden über settings_errors() / add_settings_error() übergeben.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Verarbeitet POST-Requests der Admin-Seite.
 * Gibt kein HTML aus – setzt nur Settings-Errors und leitet danach weiter.
 */
function bes_admin_handle_post(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    // Nur bei relevanten POST-Feldern handeln
    $has_post = isset($_POST['bes_token'])
        || isset($_POST['bes_consent_field_id'])
        || isset($_POST['bes_batch_size'])
        || isset($_POST['bes_auto_continue'])
        || isset($_POST['bes_sync_all_members']);

    if (!$has_post) {
        return;
    }

    if (!isset($_POST['bes_admin_nonce']) || !wp_verify_nonce($_POST['bes_admin_nonce'], 'bes_admin_save')) {
        wp_die(esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN));
    }

    // Token speichern (verschlüsselt)
    if (isset($_POST['bes_token'])) {
        $token = sanitize_text_field($_POST['bes_token']);
        if (!empty($token)) {
            if (function_exists('bes_encrypt_token')) {
                $encrypted_token = bes_encrypt_token($token);
                update_option('bes_api_token', $encrypted_token);
                add_settings_error('bes_settings', 'bes_token_saved', __('Token gespeichert.', BES_TEXT_DOMAIN), 'updated');
            } else {
                update_option('bes_api_token', $token);
                add_settings_error('bes_settings', 'bes_token_saved', __('Token gespeichert (unverschlüsselt – Migration).', BES_TEXT_DOMAIN), 'updated');
            }
        }
    }

    // Consent-Feld-ID speichern mit Validierung
    if (isset($_POST['bes_consent_field_id'])) {
        $consent_id = intval($_POST['bes_consent_field_id']);
        if ($consent_id > 0 && $consent_id <= BES_CONSENT_FIELD_ID_MAX) {
            update_option('bes_consent_field_id', $consent_id);
            add_settings_error('bes_settings', 'bes_consent_saved', __('Consent-Feld-ID gespeichert.', BES_TEXT_DOMAIN), 'updated');
        } else {
            add_settings_error('bes_settings', 'bes_consent_invalid', sprintf(
                __('Ungültige Consent-Feld-ID. Bitte einen Wert zwischen %d und %d eingeben.', BES_TEXT_DOMAIN),
                BES_CONSENT_FIELD_ID_MIN,
                BES_CONSENT_FIELD_ID_MAX
            ), 'error');
        }
    }

    // Batch-Größe speichern
    if (isset($_POST['bes_batch_size'])) {
        $batch_size = intval($_POST['bes_batch_size']);
        if ($batch_size >= BES_BATCH_SIZE_MIN && $batch_size <= BES_BATCH_SIZE_MAX) {
            update_option('bes_batch_size', $batch_size);
            add_settings_error('bes_settings', 'bes_batch_saved', __('Batch-Größe gespeichert.', BES_TEXT_DOMAIN), 'updated');
        } else {
            add_settings_error('bes_settings', 'bes_batch_invalid', sprintf(
                __('Batch-Größe muss zwischen %d und %d liegen.', BES_TEXT_DOMAIN),
                BES_BATCH_SIZE_MIN,
                BES_BATCH_SIZE_MAX
            ), 'error');
        }
    }

    // Automatische Fortsetzung speichern
    update_option('bes_auto_continue', isset($_POST['bes_auto_continue']));

    // Sync-alle-Mitglieder-Option speichern
    if (isset($_POST['bes_sync_all_members'])) {
        update_option('bes_sync_all_members', true);
        add_settings_error('bes_settings', 'bes_sync_mode_saved', __('Sync-Modus gespeichert: Alle Mitglieder werden synchronisiert (ohne Consent-Filter).', BES_TEXT_DOMAIN), 'updated');
    } else {
        update_option('bes_sync_all_members', false);
        add_settings_error('bes_settings', 'bes_sync_mode_saved', __('Sync-Modus gespeichert: Nur Mitglieder mit Consent werden synchronisiert.', BES_TEXT_DOMAIN), 'updated');
    }

    // Prüfe und bereinige abgebrochene Syncs beim Speichern
    if (isset($_POST['bes_sync_all_members']) || isset($_POST['bes_consent_field_id'])) {
        if (defined('BES_DATA_V3') && defined('BES_V3_STATUS_FILE')) {
            $status_file = BES_DATA_V3 . BES_V3_STATUS_FILE;
            if (file_exists($status_file)) {
                $status_data = json_decode(file_get_contents($status_file), true);
                if (is_array($status_data) && isset($status_data['state']) && $status_data['state'] === 'cancelled') {
                    $status_data['state']    = 'idle';
                    $status_data['progress'] = 0;
                    $status_data['message']  = '';
                    file_put_contents($status_file, json_encode($status_data, JSON_PRETTY_PRINT));

                    if (defined('BES_V3_OPTION_PREFIX')) {
                        update_option(BES_V3_OPTION_PREFIX . 'explorer_running', false);
                    }
                }
            }
        }
    }

    // PRG: Nach dem Speichern weiterleiten, damit Browser-Reload keine Duplikate erzeugt.
    // Fehler-Meldungen überstehen keinen Redirect mit set_transient().
    set_transient('settings_errors', get_settings_errors('bes_settings'), 30);
    wp_safe_redirect(add_query_arg(['page' => 'bseasy-sync', 'settings-updated' => 'true'], admin_url('admin.php')));
    exit;
}

// POST-Verarbeitung früh in init ausführen (vor jeder HTML-Ausgabe)
add_action('admin_init', function () {
    if (!is_admin() || !isset($_POST['bes_admin_nonce'])) {
        return;
    }
    if (isset($_GET['page']) && $_GET['page'] === 'bseasy-sync') {
        bes_admin_handle_post();
    }
});

function bes_admin_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    bes_write_debug_log("Admin-Seite geladen - bes_admin_page() aufgerufen", "INFO", "bes_admin_page");
    bes_write_debug_log("BES_DIR: " . (defined('BES_DIR') ? BES_DIR : 'NICHT DEFINIERT'), "INFO", "bes_admin_page");
    bes_write_debug_log("PHP Version: " . PHP_VERSION, "INFO", "bes_admin_page");
    bes_write_debug_log("WordPress Version: " . get_bloginfo('version'), "INFO", "bes_admin_page");

    // Gespeicherte Fehlermeldungen aus Transient wiederherstellen (nach PRG-Redirect)
    $transient_errors = get_transient('settings_errors');
    if ($transient_errors) {
        foreach ($transient_errors as $error) {
            add_settings_error($error['setting'], $error['code'], $error['message'], $error['type']);
        }
        delete_transient('settings_errors');
    }

    // Admin-UI laden – settings_errors() wird in ui-main.php ausgegeben
    require_once BES_DIR . 'admin/views/ui-main.php';
}
