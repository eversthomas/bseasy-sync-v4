<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap bes-fields">

  <!-- 🔹 LINKER SIDEBAR-BEREICH WIRD PER JS EINGEFÜGT -->

  <div class="bes-mainarea">
      
      <!-- Kopfbereich gehört in den Mainarea -->
      <div class="bes-header">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:15px;">
          <div>
            <h2 style="margin:0 0 5px 0;">EasyVerein – Feldverwaltung</h2>
            <p style="margin:0;">Shortcode: <strong>[bes_members]</strong></p>
            <p style="margin:5px 0 0 0;">Hier kannst du festlegen, welche Felder im Frontend angezeigt werden sollen, und ob sie oberhalb oder unterhalb des „Weiterlesen"-Buttons erscheinen.</p>
          </div>
        </div>
        
        <!-- JSON-Quelle (V3 ist die einzige Quelle) -->
        <div style="background:#f0f0f1;border:1px solid #dcdcde;border-radius:8px;padding:15px;margin:15px 0;">
            <h3 style="margin:0 0 10px 0;font-size:14px;">📊 JSON-Quelle für Felder</h3>
            <p style="margin:0 0 10px 0;font-size:13px;">
                <strong>Quelle:</strong> <span style="color: #00a32a; font-weight: bold;">V3 (members_consent_v3.json)</span>
            </p>
            <?php
            // Stelle sicher, dass V3 als Quelle gesetzt ist
            if (get_option('bes_json_source', 'v2') !== 'v3') {
                update_option('bes_json_source', 'v3');
            }
            
            $v3_file = BES_DATA_V3 . BES_V3_MEMBERS_FILE;
            $v3_exists = file_exists($v3_file) && filesize($v3_file) > 0;
            
            if (!$v3_exists):
            ?>
                <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:10px;margin-top:10px;">
                    <strong>⚠️ Warnung:</strong> V3-Datei fehlt oder ist leer. Bitte führe zuerst einen V3 Sync im Sync-Tab durch.
                </div>
            <?php endif; ?>
        </div>
        
        <div id="bes-message" class="notice-inline"></div>
      </div>

      <!-- Arbeitsleiste (vereinfacht) -->
      <div class="bes-toolbar">
        <div class="bes-toolbar-main">
          <input type="text" id="bes-search" class="bes-search-input" placeholder="🔍 Feld suchen..." />
          <select id="bes-view-filter" class="bes-view-select">
            <option value="all">Alle</option>
            <option value="configured">Konfigurierte</option>
            <option value="unconfigured">Unkonfigurierte</option>
          </select>
          <label class="bes-toggle-label">
            <input type="checkbox" id="bes-toggle-frontend-only" />
            <span>Nur Frontend-Felder</span>
          </label>
          <button type="button" class="button button-small" id="bes-reset-filters">🔄 Reset</button>
        </div>
        <details class="bes-toolbar-advanced">
          <summary>Erweitert</summary>
          <div class="bes-toolbar-advanced-content">
            <div class="bes-stats">
              <span id="bes-stat-total">0 Felder</span>
              <span id="bes-stat-configured">0 konfiguriert</span>
              <span id="bes-stat-in-use">0 in Verwendung</span>
            </div>
            <div class="bes-action-buttons">
              <button type="button" class="button button-small" id="bes-collapse-all">Alle einklappen</button>
              <button type="button" class="button button-small" id="bes-expand-all">Alle ausklappen</button>
              <button type="button" class="button button-small" id="bes-toggle-unused" title="Übrige Felder ein- oder ausblenden">Übrige Felder ein-/ausblenden</button>
              <button type="button" class="button button-small" id="bes-toggle-ignored" title="Ausgeblendete Felder ein-/ausblenden">👁️‍🗨️ Ausgeblendete zeigen</button>
              <button type="button" class="button button-small button-primary" id="bes-auto-generate-labels">✨ Labels automatisch generieren</button>
              <button type="button" class="button button-small" id="bes-hide-unconfigured">🙈 Unkonfigurierte ausblenden</button>
              <button type="button" class="button button-small" id="bes-show-hidden">👁️ Ausgeblendete anzeigen</button>
            </div>
          </div>
        </details>
      </div>

      <!-- 2-Spalten Layout -->
      <div class="bes-field-admin">
        <div class="bes-panes">
          <!-- LINKE SPALTE: Frontend-Felder -->
          <div class="bes-pane-left">
            <section class="bes-section" data-area="above">
              <h3>🟩 Über dem Button</h3>
              <div id="bes-above" class="bes-sortable"></div>
            </section>
            <section class="bes-section" data-area="below">
              <h3>🟦 Unter dem Button</h3>
              <div id="bes-below" class="bes-sortable"></div>
            </section>
          </div>
          <!-- RECHTE SPALTE: Palette (unused) -->
          <div class="bes-pane-right">
            <section class="bes-section" data-area="unused">
              <h3>⬜ Alle übrigen Felder</h3>
              <div id="bes-unused" class="bes-sortable"></div>
            </section>
          </div>
        </div>
      </div>

      <!-- Design-Einstellungen -->
      <div class="bes-design-section" style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e2e4e7;">
        <h2 style="margin-bottom: 1rem;">🎨 Frontend-Design</h2>
        <p style="margin-bottom: 1.5rem; color: #646970;">Passe das Aussehen der Mitglieder-Cards im Frontend an.</p>
        
        <div class="bes-design-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
          
          <!-- Card-Farben -->
          <div class="bes-design-group" style="background: #f8f9fa; padding: 1.25rem; border-radius: 8px; border: 1px solid #e2e4e7;">
            <h3 style="margin-top: 0; margin-bottom: 1rem; font-size: 14px; font-weight: 600;">Card-Farben</h3>
            
            <div style="margin-bottom: 1rem;">
              <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 13px;">Hintergrundfarbe</label>
              <div style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="color" id="bes-design-card-bg" value="<?php echo esc_attr(bes_get_design_settings()['card_bg']); ?>" style="width: 60px; height: 40px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;" />
                <input type="text" id="bes-design-card-bg-text" value="<?php echo esc_attr(bes_get_design_settings()['card_bg']); ?>" style="flex: 1; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px;" placeholder="#ffffff" />
              </div>
            </div>
            
            <div style="margin-bottom: 1rem;">
              <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 13px;">Rahmenfarbe</label>
              <div style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="color" id="bes-design-card-border" value="<?php echo esc_attr(bes_get_design_settings()['card_border']); ?>" style="width: 60px; height: 40px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;" />
                <input type="text" id="bes-design-card-border-text" value="<?php echo esc_attr(bes_get_design_settings()['card_border']); ?>" style="flex: 1; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px;" placeholder="rgba(0,0,0,0.10)" />
              </div>
            </div>
            
            <div style="margin-bottom: 1rem;">
              <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 13px;">Schriftfarbe</label>
              <div style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="color" id="bes-design-card-text" value="<?php echo esc_attr(bes_get_design_settings()['card_text']); ?>" style="width: 60px; height: 40px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;" />
                <input type="text" id="bes-design-card-text-text" value="<?php echo esc_attr(bes_get_design_settings()['card_text']); ?>" style="flex: 1; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px;" placeholder="#1c1c1c" />
              </div>
            </div>
            
            <div style="margin-bottom: 1rem;">
              <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 13px;">Linkfarbe</label>
              <div style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="color" id="bes-design-card-link" value="<?php echo esc_attr(bes_get_design_settings()['card_link']); ?>" style="width: 60px; height: 40px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;" />
                <input type="text" id="bes-design-card-link-text" value="<?php echo esc_attr(bes_get_design_settings()['card_link']); ?>" style="flex: 1; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px;" placeholder="#D99400" />
              </div>
            </div>
            
            <div style="margin-bottom: 1rem;">
              <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 13px;">Linker Rahmen (Stripe)</label>
              <div style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="color" id="bes-design-card-stripe" value="<?php echo esc_attr(bes_get_design_settings()['card_stripe']); ?>" style="width: 60px; height: 40px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;" />
                <input type="text" id="bes-design-card-stripe-text" value="<?php echo esc_attr(bes_get_design_settings()['card_stripe']); ?>" style="flex: 1; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px;" placeholder="#F7A600" />
              </div>
            </div>
            
            <div>
              <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" id="bes-design-image-shadow" <?php checked(bes_get_design_settings()['image_shadow'], true); ?> style="width: 18px; height: 18px; cursor: pointer;" />
                <span style="font-weight: 500; font-size: 13px;">Bild-Schatten aktivieren</span>
              </label>
              <p style="margin: 0.5rem 0 0 0; font-size: 12px; color: #646970;">Zeigt einen leichten Schatteneffekt um die Bilder, damit sie über dem Hintergrund schweben.</p>
            </div>
          </div>
          
          <!-- Button-Farben -->
          <div class="bes-design-group" style="background: #f8f9fa; padding: 1.25rem; border-radius: 8px; border: 1px solid #e2e4e7;">
            <h3 style="margin-top: 0; margin-bottom: 1rem; font-size: 14px; font-weight: 600;">Button-Farben</h3>
            
            <div style="margin-bottom: 1rem;">
              <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 13px;">Hintergrundfarbe</label>
              <div style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="color" id="bes-design-button-bg" value="<?php echo esc_attr(bes_get_design_settings()['button_bg']); ?>" style="width: 60px; height: 40px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;" />
                <input type="text" id="bes-design-button-bg-text" value="<?php echo esc_attr(bes_get_design_settings()['button_bg']); ?>" style="flex: 1; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px;" placeholder="#F7A600" />
              </div>
            </div>
            
            <div style="margin-bottom: 1rem;">
              <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 13px;">Hover-Hintergrund</label>
              <div style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="color" id="bes-design-button-bg-hover" value="<?php echo esc_attr(bes_get_design_settings()['button_bg_hover']); ?>" style="width: 60px; height: 40px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;" />
                <input type="text" id="bes-design-button-bg-hover-text" value="<?php echo esc_attr(bes_get_design_settings()['button_bg_hover']); ?>" style="flex: 1; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px;" placeholder="#D99400" />
              </div>
            </div>
            
            <div>
              <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 13px;">Schriftfarbe</label>
              <div style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="color" id="bes-design-button-text" value="<?php echo esc_attr(bes_get_design_settings()['button_text']); ?>" style="width: 60px; height: 40px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;" />
                <input type="text" id="bes-design-button-text-text" value="<?php echo esc_attr(bes_get_design_settings()['button_text']); ?>" style="flex: 1; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px;" placeholder="#000000" />
              </div>
            </div>
          </div>
          
        </div>
        
        <div class="bes-design-actions" style="display: flex; gap: 0.75rem; align-items: center;">
          <button type="button" id="bes-save-design" class="button button-primary">💾 Design speichern</button>
          <button type="button" id="bes-reset-design" class="button">🔄 Auf Standard zurücksetzen</button>
          <span id="bes-design-message" style="margin-left: 1rem; color: #00a32a; font-weight: 500; display: none;"></span>
        </div>
      </div>

      <!-- Aktionen -->
      <div class="bes-actions">
        <button id="bes-save-fields" class="button button-primary">💾 Änderungen speichern</button>
        <button id="bes-reload-fields" class="button">🔄 Neu laden</button>
        <button id="bes-import-template" class="button" title="Template-Konfiguration importieren (nur wenn noch keine Config existiert)">📥 Template importieren</button>
        <button id="bes-export-template" class="button" title="Aktuelle Konfiguration als Template für neue Installationen speichern">📦 Als Template exportieren</button>
      </div>

  </div> <!-- END .bes-mainarea -->

</div> <!-- END .wrap -->

<script>
jQuery(function($) {
    // V2/V3-Auswahl wurde entfernt - V3 ist die einzige Quelle
    // Keine JavaScript-Handler mehr nötig
});
</script>
