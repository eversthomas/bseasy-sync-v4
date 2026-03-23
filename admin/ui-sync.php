<?php
if (!defined('ABSPATH')) exit;

/**
 * Sync-Tab: Verwaltung der BSEasy Sync-Synchronisierung
 * 
 * @package BSEasySync
 * @author Tom Evers <https://bezugssysteme.de>
 */
// Kein schließendes PHP-Tag - WordPress Best Practice

// Stelle sicher, dass BES_DIR definiert ist
if (!defined('BES_DIR')) {
    // Fallback: Versuche Plugin-Pfad zu ermitteln
    $plugin_file = __FILE__;
    $plugin_dir = dirname(dirname($plugin_file));
    define('BES_DIR', trailingslashit($plugin_dir));
}

// Stelle sicher, dass V3-Konstanten und Helper verfügbar sind
if (!defined('BES_V3_OPTION_PREFIX')) {
    if (defined('BES_DIR') && file_exists(BES_DIR . 'includes/constants-v3.php')) {
        require_once BES_DIR . 'includes/constants-v3.php';
    } elseif (file_exists(__DIR__ . '/../includes/constants-v3.php')) {
        require_once __DIR__ . '/../includes/constants-v3.php';
    }
}

// Lade V3-Helpers für bseasy_v3_read_json
if (!function_exists('bseasy_v3_read_json')) {
    if (file_exists(BES_DIR . 'sync/v3-helpers.php')) {
        require_once BES_DIR . 'sync/v3-helpers.php';
    }
}

// Lade V3-Core für bseasy_v3_load_selection
if (!function_exists('bseasy_v3_load_selection')) {
    if (file_exists(BES_DIR . 'sync/api-core-consent-v3.php')) {
        require_once BES_DIR . 'sync/api-core-consent-v3.php';
    }
}

// 🕐 Cache-Infos vorbereiten
?>
<?php
    // Cache-Infos vorbereiten
    $cache_stats = function_exists('bes_get_cache_stats') ? bes_get_cache_stats() : null;
    $cache_enabled = !(defined('BES_CACHE_DISABLED') && BES_CACHE_DISABLED);
    $dev_mode = defined('BES_DEV_MODE') && BES_DEV_MODE;
    $cache_duration = defined('BES_CACHE_DURATION') ? BES_CACHE_DURATION : HOUR_IN_SECONDS;
    $cache_duration_minutes = round($cache_duration / 60);
?>

<!-- Minibedienungsanleitung - Übersicht -->
<div class="bes-card bes-card-info" style="margin-bottom: 20px; border-left: 4px solid #2271b1;">
    <h3 class="bes-card-title">📖 Empfohlener Ablauf (Ersteinrichtung)</h3>
    <div class="bes-card-block">
        <ol style="margin-left: 20px; padding-left: 0;">
            <li style="margin-bottom: 8px;"><strong>API-Zugangsdaten</strong> konfigurieren (Token + Consent-Feld-ID)</li>
            <li style="margin-bottom: 8px;"><strong>V3 Explorer</strong> ausführen (erstellt Feldkatalog)</li>
            <li style="margin-bottom: 8px;"><strong>V3 Feldauswahl</strong> öffnen und gewünschte Felder auswählen</li>
            <li style="margin-bottom: 8px;"><strong>V3 Sync</strong> starten (synchronisiert die ausgewählten Felder)</li>
        </ol>
        <p class="bes-card-text" style="margin-top: 15px; margin-bottom: 0;">
            <strong>Hinweis:</strong> Detaillierte Erklärungen zu jeder Funktion finden Sie direkt in den jeweiligen Bereichen.
        </p>
    </div>
</div>

<div class="bes-top-grid">

    <!-- Cache-Verwaltung -->
    <div class="bes-card bes-card-muted">
        <h3 class="bes-card-title">Cache-Verwaltung</h3>
        <div class="bes-card-block">
            <p class="bes-card-text" style="margin-bottom: 15px;">
                <strong>Zweck:</strong> Verwaltet den lokalen Cache für bessere Performance. Der Cache speichert gerenderte Inhalte, Karten-Daten und API-Antworten temporär.<br>
                <strong>Wann nutzen:</strong> Bei Problemen mit veralteten Daten oder nach größeren Änderungen. Der Cache wird automatisch nach <?php echo esc_html($cache_duration_minutes); ?> Minuten erneuert.
            </p>
        </div>
        
        <?php if ($cache_stats): ?>
            <div class="bes-card-block">
                <p class="bes-card-text">
                    <strong>Status:</strong> 
                    <?php if ($cache_enabled): ?>
                        <span class="bes-text-success">Aktiv</span>
                        <?php if ($dev_mode): ?>
                            <span class="bes-text-warning">(Dev-Mode: <?php echo esc_html($cache_duration_minutes); ?> Min)</span>
                        <?php else: ?>
                            <span class="bes-text-muted">(<?php echo esc_html($cache_duration_minutes); ?> Min)</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="bes-text-error">Deaktiviert</span>
                    <?php endif; ?>
                </p>
                <p class="bes-card-text">
                    <strong>Einträge:</strong>
                    <span id="bes-cache-total"><?php echo esc_html($cache_stats['total_entries']); ?></span>
                    <span id="bes-cache-details">
                        (Renderer: <span id="bes-cache-render"><?php echo esc_html($cache_stats['render_cache']); ?></span>,
                        Map: <span id="bes-cache-map"><?php echo esc_html($cache_stats['map_cache']); ?></span><?php 
                        if (isset($cache_stats['select_options_cache']) && $cache_stats['select_options_cache'] > 0): 
                            ?>, Select-Options: <span id="bes-cache-select"><?php echo esc_html($cache_stats['select_options_cache']); ?></span><?php 
                        endif;
                        if (isset($cache_stats['rate_limit_cache']) && $cache_stats['rate_limit_cache'] > 0): 
                            ?>, Rate-Limit: <span id="bes-cache-rate-limit"><?php echo esc_html($cache_stats['rate_limit_cache']); ?></span><?php 
                        endif;
                        if (isset($cache_stats['other_cache']) && $cache_stats['other_cache'] > 0): 
                            ?>, Sonstige: <span id="bes-cache-other"><?php echo esc_html($cache_stats['other_cache']); ?></span><?php 
                        endif;
                        ?>)
                    </span>
                </p>
                <?php if ($cache_stats['total_size_mb'] > 0): ?>
                    <p class="bes-card-text">
                        <strong>Größe:</strong> <span id="bes-cache-size"><?php echo esc_html($cache_stats['total_size_mb']); ?></span> MB
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="bes-card-actions">
            <button type="button" id="bes-clear-cache" class="button button-secondary">
                <span class="bes-spinner" aria-hidden="true"></span>
                <span class="bes-btn-label">Cache leeren</span>
            </button>
            <?php if ($dev_mode): ?>
                <span class="bes-card-hint">
                    Dev-Mode aktiv: Cache-Dauer reduziert auf <?php echo esc_html($cache_duration_minutes); ?> Minuten
                </span>
            <?php endif; ?>
        </div>
        
        <div id="bes-cache-message" class="bes-card-message"></div>
    </div>

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

<?php
    // Stelle sicher, dass BES_DATA_V3 definiert ist
    if (!defined('BES_DATA_V3')) {
        if (file_exists(BES_DIR . 'includes/constants-v3.php')) {
            require_once BES_DIR . 'includes/constants-v3.php';
        }
    }
    
    // V3 Explorer Info
    $explorer_last_run = get_option(BES_V3_OPTION_PREFIX . 'explorer_last_run', false);
    $explorer_field_count = get_option(BES_V3_OPTION_PREFIX . 'explorer_field_count', 0);
    $explorer_running = get_option(BES_V3_OPTION_PREFIX . 'explorer_running', false);
    
    // Prüfe ob BES_DATA_V3 definiert ist, sonst verwende Fallback
    if (defined('BES_DATA_V3') && defined('BES_V3_FIELD_CATALOG')) {
        $catalog_file = BES_DATA_V3 . BES_V3_FIELD_CATALOG;
    } else {
        // Fallback: Verwende wp_upload_dir direkt
        $upload_dir = wp_upload_dir();
        $catalog_file = trailingslashit($upload_dir['basedir']) . 'bseasy-sync/v3/field_catalog_v3.json';
    }
    $catalog_exists = file_exists($catalog_file);
    
    // Zusätzliche Prüfung: Falls Datei nicht gefunden, prüfe alternative Pfade
    if (!$catalog_exists) {
        $upload_dir = wp_upload_dir();
        $alt_paths = [
            trailingslashit($upload_dir['basedir']) . 'bseasy-sync/v3/field_catalog_v3.json',
            WP_CONTENT_DIR . '/uploads/bseasy-sync/v3/field_catalog_v3.json',
        ];
        foreach ($alt_paths as $alt_path) {
            if (file_exists($alt_path)) {
                $catalog_file = $alt_path;
                $catalog_exists = true;
                break;
            }
        }
    }
    
    // V3 Sync Info
    $last_sync_time_v3 = get_option(BES_V3_OPTION_PREFIX . 'last_sync_time', false);
    $members_with_consent_v3 = get_option(BES_V3_OPTION_PREFIX . 'last_sync_members_with_consent', false);
    
    // Lade Explorer-Status falls vorhanden
    $explorer_status_file = BES_DATA_V3 . BES_V3_STATUS_FILE;
    $explorer_status = null;
    if (file_exists($explorer_status_file)) {
        $explorer_status = bseasy_v3_read_json($explorer_status_file);
    }
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
        // Prüfe ob BES_DATA_V3 definiert ist, sonst verwende Fallback
        if (defined('BES_DATA_V3') && defined('BES_V3_FIELD_CATALOG')) {
            $catalog_file = BES_DATA_V3 . BES_V3_FIELD_CATALOG;
        } else {
            // Fallback: Verwende wp_upload_dir direkt
            $upload_dir = wp_upload_dir();
            $catalog_file = trailingslashit($upload_dir['basedir']) . 'bseasy-sync/v3/field_catalog_v3.json';
        }
        $catalog_exists = file_exists($catalog_file);
        
        // Zusätzliche Prüfung: Falls Datei nicht gefunden, prüfe alternative Pfade
        if (!$catalog_exists) {
            $upload_dir = wp_upload_dir();
            $alt_paths = [
                trailingslashit($upload_dir['basedir']) . 'bseasy-sync/v3/field_catalog_v3.json',
                WP_CONTENT_DIR . '/uploads/bseasy-sync/v3/field_catalog_v3.json',
            ];
            foreach ($alt_paths as $alt_path) {
                if (file_exists($alt_path)) {
                    $catalog_file = $alt_path;
                    $catalog_exists = true;
                    break;
                }
            }
        }
        $selection = bseasy_v3_load_selection();
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

<!-- V2-Progress-Box wurde entfernt - V3 verwendet eigene Statusleiste im V3-Bereich -->

<script>
    jQuery(function($) {
        // V2-JavaScript-Handler wurden entfernt - nur noch V3 wird verwendet
        
        // Helper-Funktion für Button-States (wird von V3 verwendet)
        function setButtonState($btn, label, options = {}) {
            const opts = Object.assign({ loading: false, disabled: false }, options);
            $btn.toggleClass('is-bes-loading', !!opts.loading);
            $btn.prop('disabled', !!opts.disabled);
            const labelEl = $btn.find('.bes-btn-label');
            if (labelEl.length) {
                labelEl.text(label);
            } else {
                $btn.text(label);
            }
        }
        
        // V2-JavaScript-Handler wurden entfernt - nur noch V3 wird verwendet
        
        // ============================================================
        // V3 EXPLORER & SYNC
        // ============================================================
        
        // V3 Explorer Status Polling mit Statusleiste - Definition weiter unten (ab Zeile 828)
        
        
        // Neue Funktionen für Benachrichtigungen
        function showSyncCompleteNotification(message) {
            // Browser-Benachrichtigung (falls erlaubt)
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('BSEasy Sync abgeschlossen', {
                    body: message,
                    icon: '<?php echo esc_url(BES_URL . "img/logo-trans.800x0.webp"); ?>',
                    tag: 'bes-sync-complete',
                    requireInteraction: false
                });
            } else if ('Notification' in window && Notification.permission !== 'denied') {
                // Erlaube Benachrichtigungen beim ersten Mal
                Notification.requestPermission().then(function(permission) {
                    if (permission === 'granted') {
                        new Notification('BSEasy Sync abgeschlossen', {
                            body: message,
                            icon: '<?php echo esc_url(BES_URL . "img/logo-trans.800x0.webp"); ?>',
                            tag: 'bes-sync-complete'
                        });
                    }
                });
            }
            
            // Visuelle Toast-Benachrichtigung
            showToast('success', '✅ Sync abgeschlossen', message);
            
            // Seiten-Titel aktualisieren (falls Tab nicht aktiv)
            if (document.hidden) {
                const originalTitle = document.title;
                document.title = '✅ ' + message + ' - ' + originalTitle;
                
                // Zurücksetzen nach 5 Sekunden oder wenn Tab wieder aktiv wird
                const resetTitle = function() {
                    document.title = originalTitle;
                    document.removeEventListener('visibilitychange', resetTitle);
                };
                document.addEventListener('visibilitychange', resetTitle);
                setTimeout(resetTitle, 5000);
            }
        }
        
        function showSyncErrorNotification(message) {
            // Browser-Benachrichtigung für Fehler
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('BSEasy Sync Fehler', {
                    body: message,
                    icon: '<?php echo esc_url(BES_URL . "img/logo-trans.800x0.webp"); ?>',
                    tag: 'bes-sync-error',
                    requireInteraction: true // Fehler sollten Aufmerksamkeit erfordern
                });
            }
            
            // Visuelle Toast-Benachrichtigung
            showToast('error', '❌ Sync Fehler', message);
        }
        
        function showToast(type, title, message) {
            // Erstelle Toast-Element
            const toast = $('<div>')
                .addClass('bes-toast bes-toast-' + type)
                .html('<strong>' + title + '</strong><br>' + message)
                .css({
                    position: 'fixed',
                    top: '20px',
                    right: '20px',
                    background: type === 'success' ? '#00a32a' : '#d63638',
                    color: '#fff',
                    padding: '15px 20px',
                    borderRadius: '4px',
                    boxShadow: '0 2px 10px rgba(0,0,0,0.2)',
                    zIndex: 999999,
                    maxWidth: '400px',
                    animation: 'slideInRight 0.3s ease-out'
                });
            
            $('body').append(toast);
            
            // Auto-Entfernen nach 5 Sekunden
            setTimeout(function() {
                toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Klick zum Schließen
            toast.on('click', function() {
                $(this).fadeOut(300, function() {
                    $(this).remove();
                });
            });
        }
        
        function updateSyncStatistics(statusData) {
            // Aktualisiere die Statistiken oben auf der Seite ohne Seiten-Reload
            if (statusData.members_with_consent !== null && statusData.members_with_consent !== undefined) {
                $('#bes-sync-consent-count').text(statusData.members_with_consent.toLocaleString('de-DE'));
            }
            
            if (statusData.members_total !== null && statusData.members_total !== undefined) {
                $('#bes-sync-total-count').text(statusData.members_total.toLocaleString('de-DE'));
            }
        }


        // V2-Funktion startSync() wurde entfernt - V3 verwendet andere Funktionen

        // V2-Buttons (btnConsent, btnMerge) und V2-Merge-Handler wurden entfernt - V3 verwendet andere Buttons

        // V2-Stop- und Reset-Button Handler wurden entfernt - V3 verwendet andere Handler

        // V2-Autostart und Status-Polling wurde entfernt - V3 verwendet andere Funktionen
        
        // ============================================================
        // V3 EXPLORER & SYNC
        // ============================================================
        
        // V3 Explorer Status Polling mit Statusleiste
        let pollV3ExplorerTimer = null;
        const explorerBar = $('#besV3ExplorerBarInner');
        const explorerStatus = $('#besV3ExplorerStatus');
        const explorerBox = $('#besV3ExplorerProgress');
        
        function schedulePollV3Explorer(delayMs) {
            clearTimeout(pollV3ExplorerTimer);
            pollV3ExplorerTimer = setTimeout(pollV3ExplorerStatus, delayMs || 3000);
        }
        
        function pollV3ExplorerStatus() {
            const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;
            const messageEl = $('#bes-v3-explorer-message');
            
            $.get(ajaxurl, {
                action: 'bes_v3_status',
                _ajax_nonce: v3Nonce
            }, function(resp) {
                if (resp && resp.success && resp.data) {
                    const s = resp.data;
                    const progress = Math.max(0, Math.min(100, s.progress || 0));
                    const previousState = explorerBox.data('previous-state') || 'idle';
                    const stateChanged = previousState !== s.state;
                    
                    // Debug-Ausgabe
                    if (typeof console !== 'undefined' && console.log) {
                        console.log('Explorer Status:', { state: s.state, explorer_running: s.explorer_running, progress: progress, message: s.message });
                    }
                    
                    // Aktualisiere Statusleiste
                    explorerBar.css('width', progress + '%');
                    explorerStatus.text(s.message || 'Kein Status');
                    explorerBox.data('previous-state', s.state);
                    
                    if (s.explorer_running || s.state === 'running') {
                        // Explorer läuft
                        explorerBox.show();
                        explorerStatus.css('color', '');
                        explorerBox.removeClass('bes-sync-done bes-sync-error').addClass('bes-sync-running');
                        explorerStatus.text(s.message || 'API Explorer läuft...');
                        messageEl.html('<strong>🔄 ' + (s.message || 'API Explorer läuft...') + '</strong>');
                        
                        // Zeige Stop-Button falls vorhanden
                        $('#bes-v3-stop-explorer').show();
                        
                        schedulePollV3Explorer(3000);
                    } else if (s.state === 'done') {
                        // Explorer abgeschlossen
                        explorerBox.show();
                        const finalProgress = Math.max(0, Math.min(100, s.progress || 100));
                        explorerBar.css('width', finalProgress + '%');
                        explorerStatus.css('color', '#00a32a');
                        explorerBox.removeClass('bes-sync-running bes-sync-error').addClass('bes-sync-done');
                        
                        let successMsg = '✅ ' + (s.message || 'API Explorer abgeschlossen');
                        if (s.explorer_field_count !== null && s.explorer_field_count !== undefined) {
                            successMsg += ' (' + s.explorer_field_count.toLocaleString('de-DE') + ' Felder gefunden)';
                        }
                        explorerStatus.html('<strong style="color: #00a32a;">' + successMsg + '</strong>');
                        messageEl.html('<strong style="color: #00a32a;">✅ ' + (s.message || 'API Explorer abgeschlossen') + '</strong>').addClass('success');
                        
                        $('#bes-v3-run-explorer').find('.bes-btn-label').text('🔍 API Explorer ausführen');
                        $('#bes-v3-run-explorer').removeClass('is-bes-loading').prop('disabled', false);
                        $('#bes-v3-stop-explorer').hide();
                        
                        // Stoppe Polling komplett nach Abschluss
                        clearTimeout(pollV3ExplorerTimer);
                        
                        // Seite neu laden nach 2 Sekunden, damit Feldkatalog-Prüfung aktualisiert wird
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else if (s.state === 'error') {
                        // Fehler
                        explorerBox.show();
                        explorerStatus.css('color', '#d63638');
                        explorerBox.removeClass('bes-sync-running bes-sync-done').addClass('bes-sync-error');
                        explorerStatus.html('<strong style="color: #d63638;">❌ ' + (s.message || 'Fehler') + '</strong>');
                        messageEl.html('<strong style="color: #d63638;">❌ ' + (s.message || 'Fehler') + '</strong>').addClass('error');
                        
                        $('#bes-v3-run-explorer').find('.bes-btn-label').text('🔍 API Explorer ausführen');
                        $('#bes-v3-run-explorer').removeClass('is-bes-loading').prop('disabled', false);
                        $('#bes-v3-stop-explorer').hide();
                        
                        // Stoppe Polling bei Fehler
                        clearTimeout(pollV3ExplorerTimer);
                    } else {
                        // Explorer nicht mehr aktiv - verstecke Statusleiste und stoppe Polling komplett
                        if (typeof console !== 'undefined' && console.log) {
                            console.log('Explorer Status: nicht aktiv', { state: s.state, explorer_running: s.explorer_running });
                        }
                        explorerBox.hide();
                        messageEl.html('');
                        clearTimeout(pollV3ExplorerTimer);
                        pollV3ExplorerTimer = null; // Stelle sicher, dass Timer null ist
                    }
                } else {
                    // Response hat keine Daten
                    if (typeof console !== 'undefined' && console.warn) {
                        console.warn('Explorer Status-Response hat keine Daten:', resp);
                    }
                    // Retry nach 5 Sekunden
                    schedulePollV3Explorer(5000);
                }
            }).fail(function(xhr, statusText, error) {
                // Bei Fehler retry nach 5 Sekunden
                console.warn('Explorer Status-Abfrage fehlgeschlagen:', { xhr, statusText, error });
                messageEl.html('<strong style="color: #d63638;">⚠️ Status konnte nicht abgerufen werden</strong>');
                schedulePollV3Explorer(5000);
            });
        }
        
        // Prüfe Explorer-Status beim Seitenaufruf
        <?php if ($explorer_running): ?>
        setTimeout(function() {
            pollV3ExplorerStatus();
        }, 1000);
        <?php endif; ?>
        
        // Prüfe V3 Sync-Status beim Seitenaufruf
        const v3SyncNonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;
        $.get(ajaxurl, {
            action: 'bes_v3_status',
            _ajax_nonce: v3SyncNonce
        }, function(resp) {
            if (resp && resp.success && resp.data) {
                const s = resp.data;
                if (s.state === 'running' && !s.explorer_running) {
                    // V3 Sync läuft - starte Polling
                    syncBox.show();
                    $('#bes-v3-start-sync').find('.bes-btn-label').text('Läuft...');
                    $('#bes-v3-start-sync').addClass('is-bes-loading').prop('disabled', true);
                    $('#bes-v3-stop-sync').show();
                    $('#bes-v3-reset-sync').hide();
                    
                    if (s.current_part && s.total_parts) {
                        syncStatus.text('Durchlauf ' + s.current_part + ' von ' + s.total_parts + ': ' + (s.message || 'Läuft...'));
                    } else {
                        syncStatus.text(s.message || 'V3 Sync läuft...');
                    }
                    
                    pollV3Status();
                } else if (s.state === 'done' && !s.explorer_running) {
                    // V3 Sync ist fertig
                    syncBox.show();
                    const progress = Math.max(0, Math.min(100, s.progress || 100));
                    syncBar.css('width', progress + '%');
                    
                    let successMsg = '✅ ' + (s.message || 'V3 Sync erfolgreich abgeschlossen');
                    if (s.members_with_consent !== null && s.members_with_consent !== undefined) {
                        successMsg += ' (' + s.members_with_consent.toLocaleString('de-DE') + ' Mitglieder mit Consent)';
                    }
                    syncStatus.html('<strong style="color: #00a32a;">' + successMsg + '</strong>');
                    syncStatus.css('color', '#00a32a');
                    syncBox.removeClass('bes-sync-running bes-sync-error').addClass('bes-sync-done');
                    
                    $('#bes-v3-stop-sync').hide();
                    $('#bes-v3-reset-sync').show();
                    
                    // Stoppe Polling komplett nach Abschluss
                    clearTimeout(pollV3SyncTimer);
                    pollV3SyncTimer = null;
                } else if (s.state === 'error' && !s.explorer_running) {
                    // V3 Sync hat Fehler
                    syncBox.show();
                    syncStatus.html('<strong style="color: #d63638;">❌ ' + (s.message || 'Fehler') + '</strong>');
                    syncStatus.css('color', '#d63638');
                    syncBox.removeClass('bes-sync-running bes-sync-done').addClass('bes-sync-error');
                    
                    $('#bes-v3-stop-sync').hide();
                    $('#bes-v3-reset-sync').show();
                } else if (s.state === 'cancelled' && !s.explorer_running) {
                    // V3 Sync wurde gestoppt
                    syncBox.show();
                    syncStatus.html('<strong style="color: #d63638;">⏸️ ' + (s.message || 'Sync gestoppt') + '</strong>');
                    syncStatus.css('color', '#d63638');
                    syncBox.removeClass('bes-sync-running bes-sync-done').addClass('bes-sync-error');
                    
                    $('#bes-v3-stop-sync').hide();
                    $('#bes-v3-reset-sync').show();
                }
            }
        }).fail(function() {
            // Bei Fehler einfach verstecken
            syncBox.hide();
        });
        
        // V3 Explorer
        $('#bes-v3-run-explorer').on('click', function() {
            const btn = $(this);
            const sampleSize = parseInt($('#bes-v3-explorer-sample-size').val());
            const freshFromApi = $('#bes-v3-explorer-fresh').is(':checked');
            const messageEl = $('#bes-v3-explorer-message');
            
            setButtonState(btn, 'Starte...', { loading: true, disabled: true });
            messageEl.html('').removeClass('error success');
            
            const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;
            
            $.post(ajaxurl, {
                action: 'bes_v3_run_explorer',
                _ajax_nonce: v3Nonce,
                sample_size: sampleSize,
                fresh_from_api: freshFromApi ? 1 : 0
            }, function(resp) {
                if (resp && resp.success) {
                    messageEl.html('<strong style="color: #00a32a;">✅ ' + resp.data.msg + '</strong>').addClass('success');
                    // Zeige Statusleiste und starte Polling
                    explorerBox.show();
                    explorerStatus.text('API Explorer wird gestartet...');
                    setTimeout(function() {
                        pollV3ExplorerStatus();
                    }, 2000);
                } else {
                    messageEl.html('<strong style="color: #d63638;">❌ ' + (resp.data.error || 'Fehler') + '</strong>').addClass('error');
                    setButtonState(btn, '🔍 API Explorer ausführen', { loading: false, disabled: false });
                }
            }).fail(function() {
                messageEl.html('<strong style="color: #d63638;">❌ AJAX-Fehler</strong>').addClass('error');
                setButtonState(btn, '🔍 API Explorer ausführen', { loading: false, disabled: false });
            });
        });
        
        // V3 Sync starten
        $('#bes-v3-start-sync').on('click', function() {
            const btn = $(this);
            const batchSize = parseInt($('#bes-v3-batch-size').val());
            const autoContinue = $('#bes-v3-auto-continue').is(':checked');
            
            // Starte Sync
            setButtonState(btn, 'Starte...', { loading: true, disabled: true });
            
            const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;
            
            $.post(ajaxurl, {
                action: 'bes_v3_start_sync',
                _ajax_nonce: v3Nonce,
                batch_size: batchSize,
                auto_continue: autoContinue ? 1 : 0
            }, function(resp) {
                if (resp && resp.success) {
                    $('#bes-v3-sync-message').html('<strong style="color: #00a32a;">✅ ' + resp.data.msg + '</strong>');
                    // Zeige Statusleiste und starte Polling
                    syncBox.show();
                    if (resp.data.part && resp.data.total_parts) {
                        syncStatus.text('Starte Durchlauf ' + resp.data.part + ' von ' + resp.data.total_parts + '...');
                    } else {
                        syncStatus.text(resp.data.msg || 'V3 Sync wird gestartet...');
                    }
                    
                    // Setze Button-Text zurück (falls vorher "Fortsetzen" war)
                    $('#bes-v3-start-sync').find('.bes-btn-label').text('✅ V3 Sync starten');
                    
                    // Zeige Stop-Button, verstecke Reset-Button
                    $('#bes-v3-stop-sync').show();
                    $('#bes-v3-reset-sync').hide();
                    
                    // Starte Polling
                    setTimeout(function() {
                        pollV3Status();
                    }, 2000);
                } else {
                    $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ ' + (resp.data.error || 'Fehler') + '</strong>');
                    setButtonState(btn, '✅ V3 Sync starten', { loading: false, disabled: false });
                }
            }).fail(function() {
                $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ AJAX-Fehler</strong>');
                setButtonState(btn, '✅ V3 Sync starten', { loading: false, disabled: false });
            });
        });
        
        // V3 Merge
        $('#bes-v3-merge-parts').on('click', function() {
            const btn = $(this);
            setButtonState(btn, 'Führe zusammen...', { loading: true, disabled: true });
            
            const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;
            
            $.post(ajaxurl, {
                action: 'bes_v3_merge_parts',
                _ajax_nonce: v3Nonce
            }, function(resp) {
                if (resp && resp.success) {
                    $('#bes-v3-sync-message').html('<strong style="color: #00a32a;">✅ ' + resp.data.msg + '</strong>');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ ' + (resp.data.error || 'Fehler') + '</strong>');
                    setButtonState(btn, '🔗 V3 Teile zusammenführen', { loading: false, disabled: false });
                }
            }).fail(function() {
                $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ AJAX-Fehler</strong>');
                setButtonState(btn, '🔗 V3 Teile zusammenführen', { loading: false, disabled: false });
            });
        });
        
        // V3 Stop
        $('#bes-v3-stop-sync').on('click', function() {
            if (!confirm('Möchten Sie den V3 Sync wirklich stoppen?')) {
                return;
            }
            
            const btn = $(this);
            setButtonState(btn, 'Stoppe...', { loading: true, disabled: true });
            
            const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;
            
            $.post(ajaxurl, {
                action: 'bes_v3_stop_sync',
                _ajax_nonce: v3Nonce
            }, function(resp) {
                if (resp && resp.success) {
                    syncStatus.text(resp.data.message || 'Sync wurde gestoppt');
                    syncStatus.css('color', '#d63638');
                    syncBox.show();
                    
                    // Buttons zurücksetzen
                    $('#bes-v3-start-sync').find('.bes-btn-label').text('✅ V3 Sync starten');
                    $('#bes-v3-start-sync').removeClass('is-bes-loading').prop('disabled', false);
                    $('#bes-v3-stop-sync').hide();
                    $('#bes-v3-reset-sync').show();
                    
                    // Polling stoppen
                    clearTimeout(pollV3SyncTimer);
                    
                    // Status einmalig aktualisieren
                    setTimeout(pollV3Status, 1000);
                } else {
                    alert('Fehler: ' + (resp.data.error || 'Unbekannt'));
                    setButtonState(btn, '🛑 V3 Sync stoppen', { loading: false, disabled: false });
                }
            }).fail(function() {
                alert('AJAX-Fehler beim Stoppen');
                setButtonState(btn, '🛑 V3 Sync stoppen', { loading: false, disabled: false });
            });
        });
        
        // V3 Consent Audit
        let auditPollTimer = null;
        
        function pollAuditStatus() {
            const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bes_v3_audit_status',
                    _ajax_nonce: v3Nonce,
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        
                        if (data.running) {
                            // Audit läuft noch - Status anzeigen
                            if (data.status_message) {
                                $('#bes-v3-sync-message').html('<strong style="color: #2271b1;">🔄 ' + data.status_message + '</strong>');
                            }
                            // Nächsten Poll in 3 Sekunden
                            auditPollTimer = setTimeout(pollAuditStatus, 3000);
                        } else if (data.has_result && data.audit) {
                            // Audit abgeschlossen - Ergebnis anzeigen
                            clearTimeout(auditPollTimer);
                            const audit = data.audit;
                            let msg = '✅ Consent-Audit abgeschlossen\n\n';
                            msg += 'Consent-Feld:\n';
                            if (audit.consent_field_meta) {
                                msg += '- ID: ' + audit.consent_field_meta.id + '\n';
                                msg += '- Name: ' + (audit.consent_field_meta.name || 'N/A') + '\n';
                                msg += '- Type: ' + (audit.consent_field_meta.type || 'N/A') + '\n\n';
                            }
                            msg += 'Serverfilter:\n';
                            if (audit.server_filter_counts) {
                                msg += '- A (CURRENT): ' + audit.server_filter_counts.A_CURRENT.count + ' IDs\n';
                                msg += '- B (EXPANDED): ' + audit.server_filter_counts.B_EXPANDED_VALUES.count + ' IDs\n';
                                msg += '- C (NO ACTIVE): ' + audit.server_filter_counts.C_NO_ACTIVE_FILTER.count + ' IDs\n\n';
                            }
                            msg += 'Lokaler Check:\n';
                            if (audit.local_check_results) {
                                msg += '- CHECK_OLD: ' + audit.local_check_results.check_old_count + ' IDs\n';
                                msg += '- CHECK_NEW: ' + audit.local_check_results.check_new_count + ' IDs\n';
                                msg += '- CF nicht gefunden: ' + audit.local_check_results.cf_not_found_count + '\n';
                                msg += '- API-Fehler: ' + audit.local_check_results.member_cf_api_error_count + '\n\n';
                            }
                            msg += 'Differenzliste: ' + (audit.difference_list ? audit.difference_list.count : 0) + ' IDs\n\n';
                            msg += 'Ergebnis gespeichert in: ' + data.audit_file;
                            
                            alert(msg);
                            console.log('Audit-Ergebnis:', audit);
                            
                            $('#bes-v3-sync-message').html('<strong style="color: #00a32a;">✅ Consent-Audit abgeschlossen. Siehe Browser-Konsole für Details.</strong>');
                            $('#bes-v3-audit-consent').find('.bes-btn-label').text('🔍 Consent Audit');
                            $('#bes-v3-audit-consent').removeClass('is-bes-loading').prop('disabled', false);
                        } else if (data.state === 'error') {
                            // Fehler
                            clearTimeout(auditPollTimer);
                            $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ ' + (data.status_message || 'Fehler') + '</strong>');
                            $('#bes-v3-audit-consent').find('.bes-btn-label').text('🔍 Consent Audit');
                            $('#bes-v3-audit-consent').removeClass('is-bes-loading').prop('disabled', false);
                        } else {
                            // Noch kein Ergebnis - weiter pollen
                            auditPollTimer = setTimeout(pollAuditStatus, 3000);
                        }
                    }
                },
                error: function() {
                    clearTimeout(auditPollTimer);
                    $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ AJAX-Fehler beim Status-Abruf</strong>');
                    $('#bes-v3-audit-consent').find('.bes-btn-label').text('🔍 Consent Audit');
                    $('#bes-v3-audit-consent').removeClass('is-bes-loading').prop('disabled', false);
                }
            });
        }
        
        $('#bes-v3-audit-consent').on('click', function() {
            const $btn = $(this);
            setButtonState($btn, '🔍 Consent Audit läuft...', { loading: true, disabled: true });
            
            const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bes_v3_audit_consent',
                    _ajax_nonce: v3Nonce,
                },
                success: function(response) {
                    if (response.success) {
                        $('#bes-v3-sync-message').html('<strong style="color: #2271b1;">🔄 Consent-Audit gestartet – läuft im Hintergrund...</strong>');
                        // Starte Polling
                        setTimeout(function() {
                            pollAuditStatus();
                        }, 2000);
                    } else {
                        alert('❌ Fehler: ' + (response.data.error || 'Unbekannter Fehler'));
                        $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ ' + (response.data.error || 'Fehler') + '</strong>');
                        setButtonState($btn, '🔍 Consent Audit', { loading: false, disabled: false });
                    }
                },
                error: function() {
                    alert('❌ AJAX-Fehler beim Starten des Consent-Audits');
                    $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ AJAX-Fehler</strong>');
                    setButtonState($btn, '🔍 Consent Audit', { loading: false, disabled: false });
                }
            });
        });
        
        // V3 Reset
        $('#bes-v3-reset-sync').on('click', function() {
            if (!confirm('Möchten Sie den V3 Sync wirklich zurücksetzen? Alle Fortschritte gehen verloren.')) {
                return;
            }
            
            const btn = $(this);
            setButtonState(btn, 'Setze zurück...', { loading: true, disabled: true });
            
            const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;
            
            $.post(ajaxurl, {
                action: 'bes_v3_reset_sync',
                _ajax_nonce: v3Nonce
            }, function(resp) {
                if (resp && resp.success) {
                    syncStatus.text(resp.data.message || 'Sync wurde zurückgesetzt');
                    syncStatus.css('color', '');
                    syncBox.hide();
                    
                    // Buttons zurücksetzen
                    $('#bes-v3-start-sync').find('.bes-btn-label').text('✅ V3 Sync starten');
                    $('#bes-v3-start-sync').removeClass('is-bes-loading').prop('disabled', false);
                    $('#bes-v3-stop-sync').hide();
                    $('#bes-v3-reset-sync').hide();
                    
                    // Polling stoppen
                    clearTimeout(pollV3SyncTimer);
                    
                    // Seite neu laden für vollständige Aktualisierung
                    location.reload();
                } else {
                    alert('Fehler: ' + (resp.data.error || 'Unbekannt'));
                    setButtonState(btn, '🔄 V3 Sync zurücksetzen', { loading: false, disabled: false });
                }
            }).fail(function() {
                alert('AJAX-Fehler beim Zurücksetzen');
                setButtonState(btn, '🔄 V3 Sync zurücksetzen', { loading: false, disabled: false });
            });
        });
        
        // V3 Sync Status Polling mit Statusleiste
        let pollV3SyncTimer = null;
        const syncBar = $('#besV3SyncBarInner');
        const syncStatus = $('#besV3SyncStatus');
        const syncBox = $('#besV3SyncProgress');
        
        function schedulePollV3Sync(delayMs) {
            clearTimeout(pollV3SyncTimer);
            pollV3SyncTimer = setTimeout(pollV3Status, delayMs || 3000);
        }
        
        function pollV3Status() {
            const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;
            
            $.get(ajaxurl, {
                action: 'bes_v3_status',
                _ajax_nonce: v3Nonce
            }, function(resp) {
                if (resp && resp.success && resp.data) {
                    const s = resp.data;
                    const progress = Math.max(0, Math.min(100, s.progress || 0));
                    const previousState = syncBox.data('previous-state') || 'idle';
                    const stateChanged = previousState !== s.state;
                    
                    // Aktualisiere Statusleiste
                    syncBar.css('width', progress + '%');
                    syncStatus.text(s.message || 'Kein Status');
                    syncBox.data('previous-state', s.state);
                    
                    if (s.state === 'running') {
                        // Sync läuft
                        syncBox.show();
                        syncStatus.css('color', '');
                        syncBox.removeClass('bes-sync-done bes-sync-error').addClass('bes-sync-running');
                        
                        // Zeige detaillierte Informationen
                        if (s.current_part && s.total_parts) {
                            syncStatus.text('Durchlauf ' + s.current_part + ' von ' + s.total_parts + ': ' + (s.message || 'Läuft...'));
                        } else {
                            syncStatus.text(s.message || 'V3 Sync läuft...');
                        }
                        $('#bes-v3-sync-message').html('<strong>🔄 ' + (s.message || 'Sync läuft...') + '</strong>');
                        
                        // Zeige Stop-Button, verstecke Reset-Button
                        $('#bes-v3-stop-sync').show();
                        $('#bes-v3-reset-sync').hide();
                        
                        schedulePollV3Sync(3000);
                    } else if (s.state === 'done') {
                        // Sync abgeschlossen
                        syncBox.show();
                        const finalProgress = Math.max(0, Math.min(100, s.progress || 100));
                        syncBar.css('width', finalProgress + '%');
                        syncStatus.css('color', '#00a32a');
                        syncBox.removeClass('bes-sync-running bes-sync-error').addClass('bes-sync-done');
                        
                        let successMsg = '✅ ' + (s.message || 'V3 Sync erfolgreich abgeschlossen');
                        if (s.members_with_consent !== null && s.members_with_consent !== undefined) {
                            successMsg += ' (' + s.members_with_consent.toLocaleString('de-DE') + ' Mitglieder mit Consent)';
                        }
                        syncStatus.html('<strong style="color: #00a32a;">' + successMsg + '</strong>');
                        $('#bes-v3-sync-message').html('<strong style="color: #00a32a;">✅ ' + (s.message || 'Sync abgeschlossen') + '</strong>');
                        
                        // Zeige Reset-Button, verstecke Stop-Button
                        $('#bes-v3-stop-sync').hide();
                        $('#bes-v3-reset-sync').show();
                        
                        // Stoppe Polling komplett nach Abschluss
                        clearTimeout(pollV3SyncTimer);
                        pollV3SyncTimer = null;
                        
                        // Seite neu laden nach 2 Sekunden (nur wenn kein Modal offen ist)
                        setTimeout(function() {
                            // Prüfe ob Feldauswahl-Modal offen ist
                            if ($('#bes-v3-field-selector-modal').length === 0 || !$('#bes-v3-field-selector-modal').is(':visible')) {
                                location.reload();
                            }
                        }, 2000);
                    } else if (s.state === 'error') {
                        // Fehler
                        syncBox.show();
                        syncStatus.css('color', '#d63638');
                        syncBox.removeClass('bes-sync-running bes-sync-done').addClass('bes-sync-error');
                        syncStatus.html('<strong style="color: #d63638;">❌ ' + (s.message || 'Fehler') + '</strong>');
                        $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ ' + (s.message || 'Fehler') + '</strong>');
                        
                        // Zeige Reset-Button, verstecke Stop-Button
                        $('#bes-v3-stop-sync').hide();
                        $('#bes-v3-reset-sync').show();
                        
                        // Stoppe Polling bei Fehler
                        clearTimeout(pollV3SyncTimer);
                    } else if (s.state === 'cancelled') {
                        // Sync gestoppt
                        syncBox.show();
                        syncStatus.css('color', '#d63638');
                        syncBox.removeClass('bes-sync-running bes-sync-done').addClass('bes-sync-error');
                        syncStatus.html('<strong style="color: #d63638;">⏸️ ' + (s.message || 'Sync gestoppt') + '</strong>');
                        $('#bes-v3-sync-message').html('<strong style="color: #d63638;">⏸️ ' + (s.message || 'Sync gestoppt') + '</strong>');
                        
                        // Zeige Reset-Button, verstecke Stop-Button
                        $('#bes-v3-stop-sync').hide();
                        $('#bes-v3-reset-sync').show();
                        
                        // Stoppe Polling
                        clearTimeout(pollV3SyncTimer);
                    } else if (s.state === 'paused') {
                        // Sync pausiert (Zeitlimit erreicht)
                        syncBox.show();
                        syncStatus.css('color', '#dba617');
                        syncBox.removeClass('bes-sync-running bes-sync-done bes-sync-error').addClass('bes-sync-paused');
                        syncStatus.html('<strong style="color: #dba617;">⏸️ ' + (s.message || 'Sync pausiert (Zeitlimit erreicht)') + '</strong>');
                        $('#bes-v3-sync-message').html('<strong style="color: #dba617;">⏸️ ' + (s.message || 'Sync pausiert (Zeitlimit erreicht)') + '</strong>');
                        
                        // Zeige Start-Button als "Fortsetzen", verstecke Stop- und Reset-Button
                        $('#bes-v3-start-sync').find('.bes-btn-label').text('▶️ Sync fortsetzen');
                        $('#bes-v3-start-sync').removeClass('is-bes-loading').prop('disabled', false).show();
                        $('#bes-v3-stop-sync').hide();
                        $('#bes-v3-reset-sync').show();
                        
                        // Stoppe Polling (wird erst wieder gestartet, wenn Sync fortgesetzt wird)
                        clearTimeout(pollV3SyncTimer);
                    } else {
                        // Sync nicht aktiv - verstecke Statusleiste
                        syncBox.hide();
                        $('#bes-v3-sync-message').html('');
                        $('#bes-v3-stop-sync').hide();
                        $('#bes-v3-reset-sync').hide();
                        clearTimeout(pollV3SyncTimer);
                    }
                }
            }).fail(function() {
                // Bei Fehler retry nach 5 Sekunden
                schedulePollV3Sync(5000);
            });
        }
        
        // V3 Settings speichern (AJAX Handler fehlt noch - wird über POST gemacht)
        $('#bes-v3-batch-size, #bes-v3-auto-continue').on('change', function() {
            const batchSize = parseInt($('#bes-v3-batch-size').val());
            const autoContinue = $('#bes-v3-auto-continue').is(':checked');
            
            // Speichere via Option (temporär, sollte über AJAX gehen)
            // Wird beim Sync-Start gespeichert
        });
        
        // ============================================================
        // V3 FELDAUSWAHL
        // ============================================================
        const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;
        
        // Feldauswahl-Modal öffnen
        $('#bes-v3-open-field-selector').on('click', function() {
            const btn = $(this);
            setButtonState(btn, 'Lade Katalog...', { loading: true, disabled: true });
            $('#bes-v3-selection-message').html('');
            
            // Stoppe alle laufenden Polling-Timer, damit sie nicht stören
            clearTimeout(pollV3ExplorerTimer);
            clearTimeout(pollV3SyncTimer);
            
            // Lade Katalog und Selection parallel
            $.when(
                $.get(ajaxurl, { action: 'bes_v3_load_catalog', _ajax_nonce: v3Nonce }),
                $.get(ajaxurl, { action: 'bes_v3_load_selection', _ajax_nonce: v3Nonce })
            ).done(function(catalogResp, selectionResp) {
                setButtonState(btn, '📋 Feldauswahl öffnen', { loading: false, disabled: false });
                
                if (!catalogResp[0].success || !selectionResp[0].success) {
                    const errorMsg = catalogResp[0].data?.error || selectionResp[0].data?.error || 'Unbekannt';
                    console.error('V3 Feldauswahl Fehler:', catalogResp[0], selectionResp[0]);
                    $('#bes-v3-selection-message').html('<strong style="color: #d63638;">❌ Fehler beim Laden: ' + errorMsg + '</strong>');
                    return;
                }
                
                const catalog = catalogResp[0].data.catalog;
                const selection = selectionResp[0].data.selection;
                const selectedFields = selection.fields || [];
                
                // Validiere Katalog-Struktur
                if (!catalog || !catalog.fields) {
                    console.error('V3 Katalog ungültig:', catalog);
                    $('#bes-v3-selection-message').html('<strong style="color: #d63638;">❌ Katalog hat ungültige Struktur. Bitte Explorer erneut ausführen.</strong>');
                    return;
                }
                
                // Erstelle Modal
                try {
                    showV3FieldSelectorModal(catalog, selectedFields);
                } catch (e) {
                    console.error('Fehler beim Erstellen des Modals:', e);
                    $('#bes-v3-selection-message').html('<strong style="color: #d63638;">❌ Fehler beim Öffnen des Modals: ' + e.message + '</strong>');
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Fehler beim Laden:', textStatus, errorThrown, jqXHR);
                setButtonState(btn, '📋 Feldauswahl öffnen', { loading: false, disabled: false });
                $('#bes-v3-selection-message').html('<strong style="color: #d63638;">❌ AJAX-Fehler: ' + (errorThrown || textStatus) + '</strong>');
            });
        });
        
        // Selection zurücksetzen
        $('#bes-v3-reset-selection').on('click', function() {
            if (!confirm('Möchten Sie die Feldauswahl wirklich auf die Pflichtfelder zurücksetzen?')) {
                return;
            }
            
            const btn = $(this);
            setButtonState(btn, 'Setze zurück...', { loading: true, disabled: true });
            
            $.post(ajaxurl, {
                action: 'bes_v3_reset_selection',
                _ajax_nonce: v3Nonce
            }, function(resp) {
                if (resp && resp.success) {
                    $('#bes-v3-selection-message').html('<strong style="color: #00a32a;">✅ ' + resp.data.message + '</strong>');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $('#bes-v3-selection-message').html('<strong style="color: #d63638;">❌ ' + (resp.data?.error || 'Fehler') + '</strong>');
                    setButtonState(btn, '🔄 Auf Pflichtfelder zurücksetzen', { loading: false, disabled: false });
                }
            }).fail(function() {
                $('#bes-v3-selection-message').html('<strong style="color: #d63638;">❌ AJAX-Fehler</strong>');
                setButtonState(btn, '🔄 Auf Pflichtfelder zurücksetzen', { loading: false, disabled: false });
            });
        });
        
        // Modal für Feldauswahl
        function showV3FieldSelectorModal(catalog, selectedFields) {
            // Validiere Parameter
            if (!catalog || typeof catalog !== 'object') {
                throw new Error('Katalog ist ungültig oder leer');
            }
            if (!catalog.fields || typeof catalog.fields !== 'object') {
                throw new Error('Katalog enthält keine Felder-Struktur');
            }
            if (!Array.isArray(selectedFields)) {
                selectedFields = [];
            }
            
            // Entferne vorhandenes Modal falls vorhanden
            $('#bes-v3-field-selector-modal').remove();
            
            const selectedSet = new Set(selectedFields);
            // Lade requiredFields aus PHP (sollte ein Array sein)
            const requiredFieldsRaw = <?php 
                if (defined('BES_V3_REQUIRED_FIELDS')) {
                    echo json_encode(BES_V3_REQUIRED_FIELDS, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                } else {
                    echo '["member.id","member.membershipNumber","syncedAt"]';
                }
            ?>;
            // Stelle sicher, dass requiredFields ein Array ist
            let requiredFieldsArray;
            if (Array.isArray(requiredFieldsRaw)) {
                requiredFieldsArray = requiredFieldsRaw;
            } else if (requiredFieldsRaw && typeof requiredFieldsRaw === 'object') {
                // Falls es ein Objekt ist, konvertiere zu Array
                requiredFieldsArray = Object.values(requiredFieldsRaw);
            } else if (requiredFieldsRaw) {
                // Falls es ein einzelner Wert ist, mache ein Array daraus
                requiredFieldsArray = [requiredFieldsRaw];
            } else {
                // Fallback auf Standard-Pflichtfelder
                requiredFieldsArray = ['member.id', 'member.membershipNumber', 'syncedAt'];
            }
            const requiredSet = new Set(requiredFieldsArray);
            
            let html = '<div id="bes-v3-field-selector-modal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:100000;overflow-y:auto;padding:20px;">';
            html += '<div style="max-width:900px;margin:20px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">';
            html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:2px solid #ddd;padding-bottom:15px;">';
            html += '<h2 style="margin:0;">📋 V3 Feldauswahl</h2>';
            html += '<button type="button" id="bes-v3-close-modal" class="button" style="margin-left:20px;">✕ Schließen</button>';
            html += '</div>';
            
            // Statistik
            html += '<div style="background:#f0f0f1;padding:15px;border-radius:6px;margin-bottom:20px;">';
            html += '<strong>Ausgewählt:</strong> <span id="bes-v3-selected-count">' + selectedFields.length + '</span> Felder | ';
            html += '<strong>Pflichtfelder:</strong> ' + requiredFieldsArray.length + ' (immer enthalten)';
            html += '</div>';
            
            // Quick Actions
            html += '<div style="margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">';
            html += '<button type="button" class="button button-small" id="bes-v3-select-all">Alle auswählen</button>';
            html += '<button type="button" class="button button-small" id="bes-v3-deselect-all">Alle abwählen</button>';
            html += '<button type="button" class="button button-small" id="bes-v3-select-filled">Nur befüllte Felder auswählen</button>';
            html += '<button type="button" class="button button-small" id="bes-v3-toggle-filled-only">Nur Felder mit Inhalt anzeigen</button>';
            html += '<input type="text" id="bes-v3-filter-fields" placeholder="Felder filtern..." style="margin-left:auto;min-width:220px;width:250px;padding:5px;">';
            html += '</div>';
            
            // Felder-Gruppen in gewünschter Reihenfolge:
            // 1) Contact-Felder, 2) Member Custom Fields, 3) Member-Felder, 4) Contact Custom Fields
            const fieldGroups = [
                { key: 'contact', title: '📧 Contact-Felder', fields: catalog.fields?.contact || [] },
                { key: 'member_cf', title: '🏷️ Member Custom Fields', fields: catalog.fields?.member_cf || [] },
                { key: 'member', title: '👤 Member-Felder', fields: catalog.fields?.member || [] },
                { key: 'contact_cf', title: '🏷️ Contact Custom Fields', fields: catalog.fields?.contact_cf || [] }
            ];
            
            html += '<div style="max-height:500px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:15px;">';
            
            fieldGroups.forEach(function(group) {
                if (group.fields.length === 0) return;
                
                html += '<div class="bes-v3-field-group" style="margin-bottom:25px;">';
                html += '<h3 style="margin:0 0 10px 0;font-size:16px;color:#2271b1;border-bottom:1px solid #ddd;padding-bottom:5px;">' + group.title + ' (' + group.fields.length + ')</h3>';
                
                // Felder innerhalb der Gruppe sortieren:
                // 1) Pflichtfelder, 2) nach Befüllungsgrad (filled_pct) absteigend, 3) Felder mit Beispielen vor Feldern ohne Beispiele, 4) Name alphabetisch
                const sortedFields = (group.fields || []).slice().sort(function(a, b) {
                    const aKey = a.key;
                    const bKey = b.key;
                    const aRequired = requiredSet.has(aKey);
                    const bRequired = requiredSet.has(bKey);
                    if (aRequired !== bRequired) {
                        return aRequired ? -1 : 1;
                    }
                    const aFilled = a.filled_pct || 0;
                    const bFilled = b.filled_pct || 0;
                    if (aFilled !== bFilled) {
                        return bFilled - aFilled;
                    }
                    const aHasExample = Array.isArray(a.example_values) && a.example_values.length > 0;
                    const bHasExample = Array.isArray(b.example_values) && b.example_values.length > 0;
                    if (aHasExample !== bHasExample) {
                        return aHasExample ? -1 : 1;
                    }
                    const aName = (a.meta?.name || a.field_key || aKey || '').toLowerCase();
                    const bName = (b.meta?.name || b.field_key || bKey || '').toLowerCase();
                    if (aName < bName) return -1;
                    if (aName > bName) return 1;
                    return 0;
                });
                
                sortedFields.forEach(function(field) {
                    const fieldKey = field.key;
                    const isSelected = selectedSet.has(fieldKey);
                    const isRequired = requiredSet.has(fieldKey);
                    const filledPct = field.filled_pct || 0;
                    const fieldName = field.meta?.name || field.field_key || fieldKey;
                    
                    // Beispielwerte extrahieren
                    const exampleValues = field.example_values || [];
                    let exampleText = '';
                    if (exampleValues.length > 0) {
                        // Nimm ersten Beispielwert (oder mehrere, max 2)
                        const examples = exampleValues.slice(0, 2);
                        exampleText = examples.map(function(ex) {
                            // Kürze sehr lange Werte
                            const str = String(ex);
                            if (str.length > 80) {
                                return str.substring(0, 77) + '...';
                            }
                            return str;
                        }).join(' | ');
                    }
                    
                    html += '<div class="bes-v3-field-item" data-field-key="' + escapeHtml(fieldKey) + '" data-filled-pct="' + filledPct + '" style="padding:10px;border-bottom:1px solid #f0f0f1;">';
                    html += '<label style="cursor:pointer;display:flex;align-items:flex-start;">';
                    html += '<input type="checkbox" class="bes-v3-field-checkbox" value="' + escapeHtml(fieldKey) + '" ' + 
                        (isSelected ? 'checked' : '') + ' ' + (isRequired ? 'disabled' : '') + ' style="margin-right:10px;margin-top:3px;flex-shrink:0;">';
                    html += '<div style="flex:1;min-width:0;">';
                    // Feldname und ID (leicht ausgegraut)
                    html += '<div style="margin-bottom:4px;">';
                    html += '<span style="color:#666;font-weight:500;">' + escapeHtml(fieldName) + '</span> ';
                    html += '<code style="font-size:11px;color:#999;background:#f5f5f5;padding:2px 4px;border-radius:3px;">' + escapeHtml(fieldKey) + '</code>';
                    if (isRequired) {
                        html += ' <span style="color:#d63638;font-size:11px;margin-left:5px;">(Pflichtfeld)</span>';
                    }
                    html += '</div>';
                    // Beispielinhalt (fett, schwarz)
                    if (exampleText) {
                        html += '<div style="font-weight:bold;color:#000;font-size:13px;margin-top:3px;line-height:1.4;">' + escapeHtml(exampleText) + '</div>';
                    } else {
                        html += '<div style="color:#999;font-size:12px;font-style:italic;margin-top:3px;">Kein Beispiel verfügbar</div>';
                    }
                    // Befüllungsprozentsatz
                    html += '<div style="font-size:11px;color:#666;margin-top:4px;">' + filledPct + '% befüllt</div>';
                    html += '</div>';
                    html += '</label>';
                    html += '</div>';
                });
                
                html += '</div>';
            });
            
            html += '</div>'; // End scrollable area
            
            html += '<div style="margin-top:20px;padding-top:15px;border-top:2px solid #ddd;display:flex;justify-content:space-between;align-items:center;">';
            html += '<div><strong id="bes-v3-final-count">' + selectedFields.length + '</strong> Felder ausgewählt</div>';
            html += '<div>';
            html += '<button type="button" id="bes-v3-cancel-selection" class="button" style="margin-right:10px;">Abbrechen</button>';
            html += '<button type="button" id="bes-v3-save-selection" class="button button-primary">💾 Auswahl speichern</button>';
            html += '</div>';
            html += '</div>';
            
            html += '</div></div>';
            
            $('body').append(html);
            
            // Event Handler
            $('#bes-v3-close-modal, #bes-v3-cancel-selection').on('click', function() {
                $('#bes-v3-field-selector-modal').remove();
            });
            
            $('#bes-v3-select-all').on('click', function() {
                $('.bes-v3-field-checkbox:not(:disabled)').prop('checked', true);
                updateSelectionCount();
            });
            
            $('#bes-v3-deselect-all').on('click', function() {
                $('.bes-v3-field-checkbox:not(:disabled)').prop('checked', false);
                updateSelectionCount();
            });
            
            $('#bes-v3-select-filled').on('click', function() {
                $('.bes-v3-field-checkbox:not(:disabled)').each(function() {
                    const $item = $(this).closest('.bes-v3-field-item');
                    const filledAttr = $item.data('filled-pct');
                    const filledPct = typeof filledAttr === 'number' ? filledAttr : parseInt(filledAttr, 10) || 0;
                    $(this).prop('checked', filledPct > 0);
                });
                updateSelectionCount();
            });
            
            function applyFieldVisibility() {
                const filter = $('#bes-v3-filter-fields').val().toLowerCase();
                const filledOnly = $('#bes-v3-toggle-filled-only').data('filledOnly') === true;
                
                $('.bes-v3-field-item').each(function() {
                    const $item = $(this);
                    const text = $item.text().toLowerCase();
                    const matchesText = !filter || text.includes(filter);
                    
                    const filledAttr = $item.data('filled-pct');
                    const filledPct = typeof filledAttr === 'number' ? filledAttr : parseInt(filledAttr, 10) || 0;
                    const hasContent = filledPct > 0;
                    
                    const visible = matchesText && (!filledOnly || hasContent);
                    $item.toggle(visible);
                });
            }

            $('#bes-v3-filter-fields').on('input', function() {
                applyFieldVisibility();
            });

            $('#bes-v3-toggle-filled-only').on('click', function() {
                const $btn = $(this);
                const active = $btn.data('filledOnly') === true;
                const makeActive = !active;
                $btn.data('filledOnly', makeActive);
                $btn.toggleClass('button-primary', makeActive);
                applyFieldVisibility();
            });
            
            $('.bes-v3-field-checkbox').on('change', updateSelectionCount);
            
            function updateSelectionCount() {
                const count = $('.bes-v3-field-checkbox:checked').length;
                $('#bes-v3-selected-count, #bes-v3-final-count').text(count);
            }
            
            $('#bes-v3-save-selection').on('click', function() {
                const btn = $(this);
                const selected = $('.bes-v3-field-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();
                
                setButtonState(btn, 'Speichere...', { loading: true, disabled: true });
                
                $.post(ajaxurl, {
                    action: 'bes_v3_save_selection',
                    _ajax_nonce: v3Nonce,
                    fields: selected
                }, function(resp) {
                    if (resp && resp.success) {
                        $('#bes-v3-selection-message').html('<strong style="color: #00a32a;">✅ ' + resp.data.message + '</strong>');
                        $('#bes-v3-field-selector-modal').remove();
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        alert('Fehler: ' + (resp.data?.error || 'Unbekannt'));
                        setButtonState(btn, '💾 Auswahl speichern', { loading: false, disabled: false });
                    }
                }).fail(function() {
                    alert('AJAX-Fehler beim Speichern');
                    setButtonState(btn, '💾 Auswahl speichern', { loading: false, disabled: false });
                });
            });
        }
        
        function escapeHtml(text) {
            if (text === null || text === undefined) {
                return '';
            }
            const div = document.createElement('div');
            div.textContent = String(text);
            return div.innerHTML;
        }
    });
</script>
<?php
// Kein schließendes PHP-Tag - WordPress Best Practice