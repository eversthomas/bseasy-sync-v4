<?php
/**
 * Sync-Tab: V3 Sync-Karte (HTML-Partial).
 *
 * Erwartet Variablen aus sync-data-loader.php:
 *   $last_sync_time_v3, $members_with_consent_v3
 *
 * @package BSEasySync
 */
if (!defined('ABSPATH')) exit;
?>

<!-- V3 Sync -->
<div class="bes-card bes-card-info" style="border-left: 4px solid #2271b1; margin-top: 20px;">
    <h3 class="bes-card-title">V3 Sync</h3>
    <div class="bes-card-block">
        <p class="bes-card-text">
            <strong>Zweck:</strong> Synchronisiert die ausgewählten Felder von EasyVerein nach WordPress.
            <?php if (get_option('bes_sync_all_members', false)): ?>
                <span style="color: #d63638; font-weight: bold;">⚠️ Aktuell: Alle Mitglieder werden synchronisiert (ohne Consent-Filter).</span>
            <?php else: ?>
                Nur Mitglieder mit aktiver Consent (Einwilligung zur Veröffentlichung) werden synchronisiert.
            <?php endif; ?>
            <br>
            <strong>Wann nutzen:</strong> Regelmäßig, um die Daten aktuell zu halten. Kann auch manuell nach Änderungen in EasyVerein gestartet werden.<br>
            <strong>Voraussetzung:</strong>
            <?php if (!get_option('bes_sync_all_members', false)): ?>
                Consent-Feld-ID muss oben konfiguriert sein.
            <?php else: ?>
                <span style="color: #d63638;">Consent-Feld-ID wird ignoriert (alle Mitglieder werden synchronisiert).</span>
            <?php endif; ?>
            Die Feldauswahl sollte vorher durchgeführt werden.
        </p>

        <?php if ($last_sync_time_v3): ?>
            <p class="bes-card-text">
                <strong>Letzter erfolgreicher Sync:</strong> <?php echo esc_html(wp_date('d.m.Y H:i:s', strtotime($last_sync_time_v3))); ?><br>
                <?php if ($members_with_consent_v3 !== false): ?>
                    <strong>Mitglieder mit Consent:</strong> <?php echo esc_html(number_format($members_with_consent_v3, 0, ',', '.')); ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <div class="bes-card-actions">
            <label>
                <strong>V3 Batch-Größe:</strong>
                <input type="number" id="bes-v3-batch-size" name="bes_v3_batch_size" min="<?php echo BES_V3_BATCH_SIZE_MIN; ?>" max="<?php echo BES_V3_BATCH_SIZE_MAX; ?>"
                    value="<?php echo esc_attr(get_option(BES_V3_OPTION_PREFIX . 'batch_size', BES_V3_BATCH_SIZE_DEFAULT)); ?>"
                    style="width:100px; margin-left: 10px;">
            </label>
            <label style="margin-left: 20px;">
                <input type="checkbox" id="bes-v3-auto-continue" value="1"
                    <?php checked(get_option(BES_V3_OPTION_PREFIX . 'auto_continue', false)); ?>>
                V3 Auto-Fortsetzung
            </label>
            <button type="button" id="bes-v3-start-sync" class="button button-primary" style="margin-left: 20px;">
                <span class="bes-spinner" aria-hidden="true"></span>
                <span class="bes-btn-label">V3 Sync starten</span>
            </button>
            <button type="button" id="bes-v3-merge-parts" class="button" style="margin-left: 10px;">
                <span class="bes-spinner" aria-hidden="true"></span>
                <span class="bes-btn-label">V3 Teile zusammenführen</span>
            </button>
            <button type="button" id="bes-v3-reset-sync" class="button button-secondary" style="margin-left: 10px; display:none;">
                <span class="bes-btn-label">V3 Sync zurücksetzen</span>
            </button>
            <button type="button" id="bes-v3-stop-sync" class="button button-secondary" style="margin-left: 10px; display:none;">
                <span class="bes-spinner" aria-hidden="true"></span>
                <span class="bes-btn-label">V3 Sync stoppen</span>
            </button>
            <button type="button" id="bes-v3-audit-consent" class="button button-secondary" style="margin-left: 10px;">
                <span class="bes-spinner" aria-hidden="true"></span>
                <span class="bes-btn-label">Consent Audit</span>
            </button>
        </div>
        <div id="bes-v3-sync-message" class="bes-card-message"></div>

        <!-- V3 Sync Statusleiste -->
        <div id="besV3SyncProgress" class="bes-progress" style="margin-top:20px;padding:10px;border:1px solid #ccc;display:none;" aria-live="polite">
            <div style="margin-bottom:8px;">V3 Sync Fortschritt:</div>
            <div id="besV3SyncBar" style="height:20px;background:#eee;border-radius:5px;margin-bottom:10px;">
                <div id="besV3SyncBarInner" style="height:20px;width:0;background:#0073aa;border-radius:5px;"></div>
            </div>
            <p id="besV3SyncStatus" style="margin-top:8px;font-weight:bold;">Bereit.</p>
        </div>
    </div>
</div>
