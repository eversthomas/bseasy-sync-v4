<?php
/**
 * Sync-Tab: V3 Explorer + Feldauswahl (HTML-Partial).
 *
 * Erwartet Variablen aus sync-data-loader.php:
 *   $explorer_running, $explorer_last_run, $explorer_field_count,
 *   $catalog_file, $catalog_exists, $explorer_status
 *
 * @package BSEasySync
 */
if (!defined('ABSPATH')) exit;
?>

<div class="bes-sync-grid">
    <!-- V3 Explorer -->
    <div class="bes-card bes-card-info" style="border-left: 4px solid #2271b1;">
        <h3 class="bes-card-title">V3 Explorer</h3>
        <div class="bes-card-block">
        <h4 style="margin-top: 0;">API Explorer</h4>
        <p class="bes-card-text">
            <strong>Zweck:</strong> Katalogisiert alle verfügbaren Felder aus der EasyVerein API und erstellt einen Feldkatalog für die Feldauswahl.<br>
            <strong>Wann nutzen:</strong> Bei der ersten Einrichtung oder nach API-Updates, wenn neue Felder verfügbar sein könnten. Muss vor der ersten Feldauswahl ausgeführt werden.<br>
            <strong>Hinweis:</strong> Der Explorer funktioniert ohne Consent-Feld-ID. Die Consent-ID kann anschließend aus dem Feldkatalog (Custom Fields) entnommen werden.
        </p>

        <?php if ($explorer_running): ?>
            <p class="bes-card-text">
                <strong style="color: #2271b1;">🔄 Explorer läuft...</strong><br>
                <?php if ($explorer_status && isset($explorer_status['message'])): ?>
                    <?php echo esc_html($explorer_status['message']); ?>
                <?php endif; ?>
            </p>
        <?php elseif ($explorer_last_run): ?>
            <p class="bes-card-text">
                <strong>Letzter Lauf:</strong> <?php echo esc_html(wp_date('d.m.Y H:i:s', $explorer_last_run)); ?><br>
                <strong>Gefundene Felder:</strong> <?php echo esc_html(number_format($explorer_field_count, 0, ',', '.')); ?><br>
                <?php if ($catalog_exists): ?>
                    <strong>Katalog:</strong> <code><?php echo esc_html(str_replace(ABSPATH, '', $catalog_file)); ?></code>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="bes-card-text">
                <em>Noch kein Explorer-Lauf durchgeführt.</em>
            </p>
        <?php endif; ?>

        <div class="bes-card-actions">
            <label>
                <strong>Sample-Größe:</strong>
                <select id="bes-v3-explorer-sample-size" style="margin-left: 10px;">
                    <option value="1">1 (Referenz)</option>
                    <option value="50">50</option>
                    <option value="100" selected>100</option>
                    <option value="200">200</option>
                </select>
            </label>
            <label style="margin-left: 20px;">
                <input type="checkbox" id="bes-v3-explorer-fresh" checked>
                Fresh from API
            </label>
            <button type="button" id="bes-v3-run-explorer" class="button button-primary" style="margin-left: 20px;">
                <span class="bes-spinner" aria-hidden="true"></span>
                <span class="bes-btn-label">🔍 API Explorer ausführen</span>
            </button>
        </div>
        <div id="bes-v3-explorer-message" class="bes-card-message"></div>

        <!-- V3 Explorer Statusleiste -->
        <div id="besV3ExplorerProgress" class="bes-progress" style="margin-top:20px;padding:10px;border:1px solid #ccc;display:none;" aria-live="polite">
            <div style="margin-bottom:8px;">API Explorer Fortschritt:</div>
            <div id="besV3ExplorerBar" style="height:20px;background:#eee;border-radius:5px;margin-bottom:10px;">
                <div id="besV3ExplorerBarInner" style="height:20px;width:0;background:#2271b1;border-radius:5px;"></div>
            </div>
            <p id="besV3ExplorerStatus" style="margin-top:8px;font-weight:bold;">Bereit.</p>
        </div>
        </div>
    </div>

    <!-- V3 Feldauswahl -->
    <div class="bes-card">
        <h3 class="bes-card-title">V3 Feldauswahl</h3>
        <div class="bes-card-block">
        <p class="bes-card-text">
            <strong>Zweck:</strong> Auswahl der Felder, die beim V3 Sync synchronisiert werden sollen. Nur ausgewählte Felder werden von der API geladen und in WordPress gespeichert.<br>
            <strong>Wann nutzen:</strong> Nach dem Explorer-Lauf oder wenn Sie andere Felder synchronisieren möchten. Pflichtfelder (ID, Mitgliedsnummer, syncedAt) sind immer enthalten und können nicht abgewählt werden.
        </p>

        <?php
        $selection      = bseasy_v3_load_selection();
        $selected_fields = isset($selection['fields']) ? $selection['fields'] : BES_V3_REQUIRED_FIELDS;
        $selected_count = count($selected_fields);
        ?>

        <?php if (!$catalog_exists): ?>
            <div class="bes-card-message" style="background: #fff3cd; border-color: #ffc107;">
                <strong>Feldkatalog fehlt:</strong> Bitte führe zuerst den API Explorer aus, um den Feldkatalog zu erstellen.
            </div>
        <?php else: ?>
            <div class="bes-card-text">
                <strong>Aktuell ausgewählt:</strong> <?php echo esc_html($selected_count); ?> Felder<br>
                <?php if (isset($selection['updated_at'])): ?>
                    <strong>Zuletzt aktualisiert:</strong> <?php echo esc_html(wp_date('d.m.Y H:i:s', strtotime($selection['updated_at']))); ?>
                <?php endif; ?>
            </div>

            <div class="bes-card-actions">
                <button type="button" id="bes-v3-open-field-selector" class="button button-primary">
                    Feldauswahl öffnen
                </button>
                <button type="button" id="bes-v3-reset-selection" class="button button-secondary" style="margin-left: 10px;">
                    Auf Pflichtfelder zurücksetzen
                </button>
            </div>
        <?php endif; ?>

        <div id="bes-v3-selection-message" class="bes-card-message"></div>
        </div>
    </div>
</div>
