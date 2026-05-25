<?php
/**
 * Sync-Tab: Layout-Skelett.
 *
 * Bindet Datenvorbereitung und HTML-Partials ein.
 * JavaScript: admin/assets/sync/*.js (bootstrap/admin-assets.php).
 *
 * Architektur:
 *   sync-data-loader.php   — PHP-Datenvorbereitung (keine HTML-Ausgabe)
 *   sync-tab-explorer.php  — HTML: V3 Explorer + Feldauswahl
 *   sync-tab-sync.php      — HTML: V3 Sync-Karte
 *
 * @package BSEasySync
 * @author Tom Evers <https://bezugssysteme.de>
 */
// Kein schließendes PHP-Tag - WordPress Best Practice

if (!defined('ABSPATH')) exit;

// ── Datenvorbereitung (PHP, kein HTML) ───────────────────────────────────────
require_once BES_DIR . 'admin/views/partials/sync-data-loader.php';
?>

<?php require_once BES_DIR . 'admin/views/partials/sync-onboarding-hint.php'; ?>

<div class="bes-top-grid">

<?php require_once BES_DIR . 'admin/views/partials/sync-tab-cache-card.php'; ?>

    <!-- API-Zugangsdaten -->
    <div class="bes-card">
        <h3 class="bes-card-title">API-Zugangsdaten</h3>
        <div class="bes-card-block">
            <p class="bes-card-text" style="margin-bottom: 15px;">
                <strong>Zweck:</strong> Konfiguration der Verbindung zur EasyVerein API. Der API-Token authentifiziert die Anfragen, die Consent-Feld-ID bestimmt, welche Mitglieder synchronisiert werden.<br>
                <strong>Wann nutzen:</strong> Bei der ersten Einrichtung oder bei Token-Wechsel. Die Consent-Feld-ID kann auch aus dem V3 Explorer Feldkatalog (Custom Fields) entnommen werden.
            </p>
        </div>
        <form method="post" class="bes-sync-form">
            <?php wp_nonce_field('bes_admin_save', 'bes_admin_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="bes_token">API-Token</label></th>
                    <td>
                        <?php
                        $encrypted_token = get_option('bes_api_token', '');
                        $display_token = '';
                        if (!empty($encrypted_token)) {
                            if (function_exists('bes_decrypt_token')) {
                                $display_token = bes_decrypt_token($encrypted_token);
                            } else {
                                $display_token = $encrypted_token;
                            }
                        }
                        ?>
                        <input type="password" id="bes_token" name="bes_token"
                            value="<?php echo esc_attr($display_token); ?>"
                            style="width:400px;"
                            placeholder="API-Token eingeben">
                        <p class="description">Token wird verschlüsselt gespeichert. Leer lassen, um den aktuellen Token beizubehalten.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="bes_consent_field_id">Consent-Feld-ID</label></th>
                    <td>
                        <input type="number" id="bes_consent_field_id" name="bes_consent_field_id"
                            value="<?php echo esc_attr(get_option('bes_consent_field_id', '282018660')); ?>"
                            style="width:200px;">
                        <p class="description">ID des Custom Fields, das die Einwilligung zur Veröffentlichung bestimmt. Standard: 282018660</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="bes_sync_all_members">Sync-Modus</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="bes_sync_all_members" name="bes_sync_all_members" value="1"
                                <?php checked(get_option('bes_sync_all_members', false)); ?>>
                            <strong>Alle Mitglieder synchronisieren (ohne Consent-Filter)</strong>
                        </label>
                        <p class="description" style="margin-top: 10px;">
                            <span style="color: #d63638; font-weight: bold;">⚠️ WARNUNG:</span> Wenn aktiviert, werden <strong>alle Mitglieder</strong> synchronisiert, unabhängig von der Consent-Einstellung.
                            Dies kann datenschutzrechtliche Probleme verursachen. Nur verwenden, wenn Sie sicher sind, dass alle Mitglieder veröffentlicht werden dürfen.
                        </p>
                        <p class="description" style="margin-top: 5px;">
                            <strong>Standard:</strong> Nur Mitglieder mit aktiver Consent (Consent-Feld-ID = true) werden synchronisiert.
                        </p>
                    </td>
                </tr>
            </table>
            <p class="bes-action-row">
                <button type="submit" class="button">Einstellungen speichern</button>
            </p>
        </form>
    </div>
</div>

<?php require_once BES_DIR . 'admin/views/partials/sync-tab-explorer.php'; ?>

<?php require_once BES_DIR . 'admin/views/partials/sync-tab-sync.php'; ?>

<!-- V2-Progress-Box wurde entfernt - V3 verwendet eigene Statusleiste im V3-Bereich -->
