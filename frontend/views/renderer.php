<?php
if (!defined('ABSPATH')) exit;

// ------------------------------------------------------------
// 🔧 BASIS-PFADE AUS HAUPTPLUGIN LADEN (auch bei WP-Cron nutzbar)
// ------------------------------------------------------------
if (!defined('BES_DATA')) {
  $main_file = plugin_dir_path(dirname(__DIR__)) . 'bseasy-sync.php';
  if (file_exists($main_file)) {
    require_once $main_file; // zentrale Konstanten laden
  } else {
    // Fallback, falls Hauptplugin nicht geladen ist (z. B. bei direkter Einbindung)
    $upload_dir = wp_upload_dir();
    define('BES_UPLOADS_DIR', trailingslashit($upload_dir['basedir']) . 'bseasy-sync/');
    define('BES_UPLOADS_URL', trailingslashit($upload_dir['baseurl']) . 'bseasy-sync/');
    define('BES_DATA', BES_UPLOADS_DIR);
    define('BES_IMG', BES_UPLOADS_DIR . 'img/');
  }
}

require_once dirname(__DIR__) . '/includes/filter-helpers.php';


/**
 * Rendert Mitgliederkarten im Frontend
 * 
 * @return string HTML-Output der Mitgliederkarten
 */
function bes_render_members(): string
{
  // Mitgliederdaten über Repository prüfen (keine direkte Abhängigkeit zu sync/)
  if (!function_exists('bes_members_file_exists') || !bes_members_file_exists()) {
    return '';
  }
  
  $config_file  = BES_DATA . 'fields-config.json';
  $img_dir      = BES_DATA . 'img/';

  // ============================================================
  // RENDER-TRANSIENT DEAKTIVIERT (kein get_transient / bes_get_cached)
  // ============================================================
  // Pro Seitenaufruf soll u. a. shuffle($members) greifen; ein HTML-Transient würde die
  // Reihenfolge bis zur Cache-Laufzeit festhalten. ~155 Mitglieder sind ohne diesen Cache unkritisch.

  bes_debug_log('Renderer: generiere HTML (Render-Transient aus)', 'DEBUG', 'bes_render_members');

  // ============================================================
  // VALIDIERUNG
  // ============================================================
  if (!file_exists($config_file)) {
    $error_msg = '<div style="padding: 20px; background: #fee; border: 2px solid red; color: #333; margin: 20px;">';
    $error_msg .= '<p style="font-weight: bold; color: red;">❌ Fehler: Konfigurationsdatei nicht gefunden!</p>';
    $error_msg .= '<p style="font-size: 12px; color: #666;">';
    $error_msg .= '<strong>config_file:</strong> ' . esc_html($config_file) . ' → ❌ MISSING<br>';
    $error_msg .= '</p>';
    $error_msg .= '<p style="font-size: 11px; color: #999; margin-top: 10px;">Bitte prüfen Sie die Plugin-Einstellungen.</p>';
    $error_msg .= '</div>';

    if (function_exists('bes_debug_log')) {
      bes_debug_log('Konfigurationsdatei nicht gefunden: ' . $config_file, 'ERROR', 'bes_render_members');
    }
    return $error_msg;
  }

  // ============================================================
  // DATEN LADEN
  // ============================================================
  // Mitgliederdaten über Repository laden (keine direkte Abhängigkeit zu sync/)
  $members = bes_members_get_all();

  if (empty($members)) {
    return '<p>Keine Mitgliederdaten gefunden.</p>';
  }

  // ✅ Konfiguration laden
  if (function_exists('bes_safe_file_get_contents')) {
    $config_raw = bes_safe_file_get_contents($config_file, BES_DATA);
  } else {
    $config_raw = file_get_contents($config_file);
  }

  if ($config_raw === false || $config_raw === null) {
    if (function_exists('bes_debug_log')) {
      bes_debug_log('Konfigurationsdatei konnte nicht gelesen werden', 'ERROR', 'bes_render_members');
    }
    return '<p>Fehler beim Laden der Konfiguration.</p>';
  }

  // ✅ JSON-Decode mit sofortiger Fehlerprüfung
  try {
    if (function_exists('bes_safe_json_decode')) {
      $config_data = bes_safe_json_decode($config_raw, true);
    } else {
      $config_data = json_decode($config_raw, true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON-Decode-Fehler: ' . json_last_error_msg());
      }
    }
  } catch (Exception $e) {
    $error_msg = 'JSON-Decode-Fehler: ' . $e->getMessage();
    if (function_exists('bes_debug_log')) {
      bes_debug_log($error_msg, 'ERROR', 'bes_render_members');
    }
    return '<p>' . esc_html($error_msg) . '</p>';
  }

  bes_debug_log(
    sprintf('Daten geladen: %d Mitglieder, %d Config-Items',
      count($members),
      count($config_data ?? [])
    ),
    'DEBUG',
    'bes_render_members'
  );
  $config  = is_array($config_data) ? $config_data : [];
  
  // Filter: Erlaube Entwicklern, Mitglieder-Daten zu modifizieren
  if (function_exists('bes_filter_members_data')) {
      $members = bes_filter_members_data($members);
  }

  // Zufällige Kartenreihenfolge pro Seitenaufruf (serverseitig; DOM bleibt für applyFilters stabil)
  if (!empty($members)) {
    shuffle($members);
  }

  // ----------------------------------------------------------
  // 🔢 Sortierung nach order (globale Grundsortierung)
  // ----------------------------------------------------------
  usort($config, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

  // ----------------------------------------------------------
  // 🧹 Globale Hilfsfunktion: Werte bereinigen (HTML entfernen, normalisieren)
  // ----------------------------------------------------------
  if (!function_exists('bes_clean_value')) {
    function bes_clean_value($value) {
      // Prüfe nur null und '', nicht empty() (damit "0" und false erhalten bleiben)
      if ($value === null || $value === '') return '';
      
      // Wenn es ein Array ist, rekursiv bereinigen
      if (is_array($value)) {
        return array_map('bes_clean_value', $value);
      }
      
      // Objekte und Ressourcen nicht unterstützen
      if (is_object($value) || is_resource($value)) {
        return '';
      }
      
      // String konvertieren
      $value = (string) $value;
      
      // HTML-Tags entfernen
      $value = strip_tags($value);
      
      // HTML-Entities dekodieren
      $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      
      // Trimmen
      $value = trim($value);
      
      return $value;
    }
  }
  
  // Alias für Kompatibilität
  $clean_value = 'bes_clean_value';
  
  // ----------------------------------------------------------
  // 🧠 Hilfsfunktion: Wert eines Feldes auslesen
  // ----------------------------------------------------------
  $get_value = function ($member, $fid) {
    // PRIORITÄT 1: Flache Keys zuerst (deckt neue Keys wie member.addresses[].city ab)
    if (array_key_exists($fid, $member)) {
      return $member[$fid];
    }

    // 1) member.* Felder
    if (str_starts_with($fid, 'member.')) {
        $key = substr($fid, 7);

        // EasyVerein: viele Member-Felder liegen in "contact"
        if (isset($member['contact'][$key])) {
            return $member['contact'][$key];
        }
        if (isset($member['member'][$key])) {
            return $member['member'][$key];
        }
        return '';
    }

    // 2) cf.* (CustomFields – extracted)
    if (str_starts_with($fid, 'cf.')) {
        $cfid = substr($fid, 3);
        if (isset($member['member_cf_extracted'][$cfid])) {
            $cf = $member['member_cf_extracted'][$cfid];
            return $cf['display_value'] 
                ?? $cf['value'] 
                ?? '';
        }
        return '';
    }

    // 3) cfraw.*
    if (str_starts_with($fid, 'cfraw.')) {
        $cfid = substr($fid, 6);
        if (!empty($member['member_cf'])) {
            foreach ($member['member_cf'] as $cf) {
                if (!isset($cf['customField'])) continue;
                
                // Exakte Übereinstimmung: Extrahiere ID aus URL
                $url = $cf['customField'];
                $path = parse_url($url, PHP_URL_PATH);
                $field_id_from_url = $path ? basename($path) : null;
                
                // Exakte Übereinstimmung der IDs
                if ($field_id_from_url && (string)$field_id_from_url === (string)$cfid) {
                    return $cf['value'] ?? '';
                }
            }
        }
        return '';
    }

    // 4) contact.*
    if (str_starts_with($fid, 'contact.')) {
        $key = substr($fid, 8);
        // V3: Prüfe contact-Objekt (sowohl mit als auch ohne Präfix)
        if (isset($member['contact']) && is_array($member['contact'])) {
            // Zuerst ohne Präfix (firstName, familyName, city, etc.)
            if (isset($member['contact'][$key])) {
                return $member['contact'][$key];
            }
            // Dann mit Präfix (contact.firstName, contact.name, etc.) - für V3 gemischte Struktur
            if (isset($member['contact'][$fid])) {
                return $member['contact'][$fid];
            }
        }
        return '';
    }

    // 5) contactcf.*
    if (str_starts_with($fid, 'contactcf.')) {
        $cfid = substr($fid, 10);
        if (isset($member['contact_cf_extracted'][$cfid])) {
            $cf = $member['contact_cf_extracted'][$cfid];
            return $cf['display_value'] 
                ?? $cf['value'] 
                ?? '';
        }
        return '';
    }

    // 6) contactcfraw.*
    if (str_starts_with($fid, 'contactcfraw.')) {
        $cfid = substr($fid, 13);
        if (!empty($member['contact_cf'])) {
            foreach ($member['contact_cf'] as $cf) {
                if (!isset($cf['customField'])) continue;
                
                // Exakte Übereinstimmung: Extrahiere ID aus URL
                $url = $cf['customField'];
                $path = parse_url($url, PHP_URL_PATH);
                $field_id_from_url = $path ? basename($path) : null;
                
                // Exakte Übereinstimmung der IDs
                if ($field_id_from_url && (string)$field_id_from_url === (string)$cfid) {
                    return $cf['value'] ?? '';
                }
            }
        }
        return '';
    }

    // 7) consent.*
    if (str_starts_with($fid, 'consent.')) {
        $cid = substr($fid, 8);
        if (isset($member['consents'])) {
            foreach ($member['consents'] as $c) {
                if ((string)$c['id'] === $cid) {
                    return $c['value'] ?? '';
                }
            }
        }
        return '';
    }

    return '';
  };

  // ----------------------------------------------------------
  // 👤 Hilfsfunktion: Vollständigen Namen (Vor- und Nachname) extrahieren
  // ----------------------------------------------------------
  $get_member_name = function ($member) use ($get_value) {
    // Extrahiere Contact-Daten (konsistent mit map-render.php)
    $contact = [];
    if (isset($member['contact']) && is_array($member['contact'])) {
      // V2 oder V3 mit contact-Objekt
      $contact = $member['contact'];
    } else {
      // V3: Contact-Felder direkt im Root (contact.firstName, etc.)
      foreach ($member as $key => $value) {
        if (str_starts_with($key, 'contact.')) {
          $contact_key = substr($key, 8);
          $contact[$contact_key] = $value;
        }
      }
    }
    
    // Extrahiere Vor- und Nachname (konsistent mit map-render.php)
    $firstName = '';
    $familyName = '';
    
    // PRIORITÄT 1: Nested contact-Objekt
    if (!empty($contact['firstName']) || !empty($contact['familyName'])) {
      $firstName = trim($contact['firstName'] ?? '');
      $familyName = trim($contact['familyName'] ?? '');
    } else {
      // PRIORITÄT 2: Flache Keys im Member-Array
      $firstName = trim($member['contact.firstName'] ?? '');
      $familyName = trim($member['contact.familyName'] ?? '');
      
      // PRIORITÄT 3: Versuche get_value als Fallback
      if (empty($firstName)) {
        $firstName = bes_clean_value($get_value($member, 'contact.firstName'));
      }
      if (empty($familyName)) {
        $familyName = bes_clean_value($get_value($member, 'contact.familyName'));
      }
    }
    
    // Bereinige Werte
    $firstName = bes_clean_value($firstName);
    $familyName = bes_clean_value($familyName);
    
    // Kombiniere Vor- und Nachname
    $fullName = trim($firstName . ' ' . $familyName);
    
    // Fallback: Wenn kein Name gefunden wurde
    if (empty($fullName)) {
      return 'Profilbild';
    }
    
    return esc_attr($fullName);
  };

  // ----------------------------------------------------------
  // 🎨 Hilfsfunktion: Feldwert formatiert ausgeben
  // ----------------------------------------------------------
  $format_value = function ($value, $format) {
    // PRIORITÄT: Arrays vor wp_kses_post abfangen (verhindert "Array"-String)
    if (is_array($value) && !empty($value)) {
      // Native PHP-Array: join mit Komma
      $value = implode(', ', array_map('bes_clean_value', $value));
    } elseif (is_string($value) && str_starts_with(trim($value), '[')) {
      // JSON-Array-String: dekodieren und join
      $decoded = json_decode($value, true);
      if (is_array($decoded)) {
        $value = implode(', ', array_map('bes_clean_value', $decoded));
      }
    }
    
    // Jetzt wp_kses_post anwenden (nur auf String)
    $value = wp_kses_post($value);

    // Klickbare Links
    if (preg_match('/^(https?:\/\/|www\.)/i', $value)) {
      if (!str_starts_with($value, 'http')) {
        $value = 'https://' . $value;
      }
      $value = '<a href="' . esc_url($value) . '" target="_blank" rel="noopener">' . esc_html($value) . '</a>';
    }

    // Formatierung
    switch ($format) {
      case 'bold':
        $value = '<strong>' . $value . '</strong>';
        break;
      case 'heading':
        $value = '<h3 class="bes-heading">' . $value . '</h3>';
        break;
      default:
        break;
    }

    return $value;
  };

  // ----------------------------------------------------------
  // 🧩 Hilfsfunktion: Felder einer Area rendern (mit stabiler Sortierung)
  // ----------------------------------------------------------
  $render_area = function ($member, $config, $area, $get_value, $format_value) {
    $html = '';

    // 1️⃣ Filtere alle sichtbaren Felder dieser Area und sortiere nach 'order'
    $fields_in_area = array_filter($config, fn($f) => !empty($f['show']) && $f['area'] === $area);
    usort($fields_in_area, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

    $current_group = null;
    $group_html = '';

    // 2️⃣ Iteriere in Reihenfolge und erkenne Gruppenwechsel
    foreach ($fields_in_area as $field) {
      if ($field['id'] === '_profilePicture') continue;

      $fid    = $field['id'];
      $label  = isset($field['label']) && trim($field['label']) !== '' ? esc_html($field['label']) : '';
      $format = $field['format'] ?? 'normal';
      $group  = $field['inline_group'] ?? null;

      // Gruppenwechsel: vorherige Gruppe abschließen
      if ($current_group !== null && $group !== $current_group) {
        if ($current_group) {
          $html .= "<div class='bes-inline-group bes-inline-{$current_group}'>{$group_html}</div>";
        } else {
          $html .= $group_html;
        }
        $group_html = '';
      }

      // Feldwert abrufen
      $raw_value = $get_value($member, $fid);

      // Werte für Filter-Attribute und Display vorbereiten
      $raw_values_for_attr = [];

      // PRIORITÄT: Native PHP-Arrays erkennen (nicht nur JSON-Strings)
      if (is_array($raw_value) && !empty($raw_value)) {
        // Native PHP-Array: direkt verwenden
        $raw_values_for_attr = array_map('bes_clean_value', $raw_value);
        // Für Anzeige: implode
        $raw_value_display = implode(', ', array_map('bes_clean_value', $raw_value));
      } elseif (is_string($raw_value) && str_starts_with(trim($raw_value), '[')) {
        // JSON-Array-String: dekodieren
        $decoded = json_decode($raw_value, true);
        if (is_array($decoded)) {
          $raw_values_for_attr = array_map('bes_clean_value', $decoded);
          $raw_value_display = implode(', ', array_map('bes_clean_value', $decoded));
        } else {
          // Dekodierung fehlgeschlagen: als String behandeln
          $raw_values_for_attr = [bes_clean_value($raw_value)];
          $raw_value_display = $raw_value;
        }
      } elseif (is_string($raw_value) && str_contains($raw_value, ',')) {
        // Kommaliste: aufteilen
        $raw_values_for_attr = array_map('bes_clean_value', explode(',', $raw_value));
        $raw_value_display = $raw_value;
      } else {
        // Einzelwert (String, Number, null, false, etc.)
        $raw_values_for_attr = [bes_clean_value($raw_value)];
        $raw_value_display = $raw_value;
      }
      
      // Leere Werte entfernen (aber "0" und false behalten)
      $raw_values_for_attr = array_values(array_filter($raw_values_for_attr, function($v) {
        return $v !== '' && $v !== 'null' && $v !== 'undefined' && $v !== '[]';
      }));

      $is_country_field = function_exists('bes_field_is_country_filter') && bes_field_is_country_filter($field);
      if ($is_country_field && !empty($raw_values_for_attr)) {
          $norm_attr_parts = [];
          foreach ($raw_values_for_attr as $v) {
              $iso = bes_normalize_country_token($v);
              $norm_attr_parts[] = $iso ?: $v;
          }
          $raw_values_for_attr = array_values(array_unique($norm_attr_parts));
      }

      // Prüfe ob Wert vorhanden (Arrays: nicht leer)
      if (empty($raw_values_for_attr) || 
          (is_array($raw_value) && empty($raw_value)) ||
          $raw_value === '' || $raw_value === '[]' || $raw_value === 'null') {
        continue;
      }
      
      // Verwende Display-Wert für Formatierung
      if (isset($raw_value_display)) {
        $raw_value = $raw_value_display;
      }

      $formatted_value = $format_value($raw_value, $format);

      // 💾 Daten-Attribut: alle Einzelwerte als mit | getrennte Liste speichern
      // Werte sind bereits bereinigt (HTML-Tags entfernt), jetzt escapen für HTML-Attribut
      $raw_value_attr = esc_attr(implode('|', $raw_values_for_attr));


      if ($label && ($field['show_label'] ?? true)) {
        $group_html .= "<div class='bes-field' data-id='{$fid}' data-value='{$raw_value_attr}'><strong>{$label}:</strong> {$formatted_value}</div>";
      } else {
        $group_html .= "<div class='bes-field' data-id='{$fid}' data-value='{$raw_value_attr}'>{$formatted_value}</div>";
      }

      $current_group = $group;
    }

    // 3️⃣ Letzte Gruppe anhängen
    if ($group_html !== '') {
      if ($current_group) {
        $html .= "<div class='bes-inline-group bes-inline-{$current_group}'>{$group_html}</div>";
      } else {
        $html .= $group_html;
      }
    }

    return $html;
  };

  // ----------------------------------------------------------
  // 🔍 Filterleisten-Daten aufbauen (gemeinsam für Kacheln & Karte)
  // ----------------------------------------------------------
  $card_filter_order = bes_get_default_filter_order($config);
  $filter_fields     = bes_prepare_filter_fields($config, $card_filter_order);
  $filter_values     = bes_collect_filter_values($members, $filter_fields, $get_value);

  bes_debug_log(
    sprintf(
      'Filter-Felder: %d sortiert (config: %d)',
      count($filter_fields),
      count($config)
    ),
    'DEBUG',
    'bes_render_members'
  );

  // ----------------------------------------------------------
  // 🧱 Filterbar + Mitgliederkarten rendern
  // ----------------------------------------------------------
  // WICHTIG: ALLES im ob_start() Block rendern, um Escaping-Probleme zu vermeiden!
  ob_start(); ?>
  
  <!-- DEBUG: START bes_render_members output -->
  <!-- 🎨 Filterbar HTML -->
  <?php echo bes_render_filterbar($filter_fields, $filter_values); ?>
  
  <!-- 🧱 Mitgliederkarten Grid -->
  <div class="bes-members-grid">
    <?php 
    $card_index = 0; // Index für Lazy-Loading-Optimierung
    foreach ($members as $member): 
      // ID extrahieren: V3 hat member.id im Root, V2 hat id oder member['id']
      $id = null;
      if (isset($member['member.id'])) {
        // V3-Struktur (flach)
        $id = $member['member.id'];
      } elseif (isset($member['member']['id'])) {
        // V2-Struktur (verschachtelt)
        $id = $member['member']['id'];
      } elseif (isset($member['id'])) {
        // Fallback: direkt im Root
        $id = $member['id'];
      }
      
      if (!$id) {
        // Überspringe Mitglied ohne ID
        continue;
      }
      
      $img_path = BES_IMG . $id . '.png';
      $img_url  = BES_UPLOADS_URL . 'img/' . $id . '.png';
      
      // Lazy-Loading: Erste Karte eager (LCP-Bild), alle weiteren lazy
      $loading_attr = ($card_index === 0) ? 'loading="eager"' : 'loading="lazy"';
      
      // ALT-Text: Vollständiger Name des Mitglieds
      $alt_text = $get_member_name($member);
      
      $card_index++;
      ?>
      <article class="bes-card bes-member-card" data-member-id="<?php echo esc_attr($id); ?>">
        <h2 class="bes-sr-only"><?php echo $alt_text; ?></h2>
        <div class="bes-member-content">
          <div class="bes-fields-top">
            <?php if (file_exists($img_path)): ?>
              <div class="bes-member-image">
                <img decoding="async" <?php echo $loading_attr; ?> src="<?php echo esc_url($img_url); ?>" alt="<?php echo $alt_text; ?>">
              </div>
            <?php endif; ?>
            <?php echo $render_area($member, $config, 'above', $get_value, $format_value); ?>
            <?php $below_html = $render_area($member, $config, 'below', $get_value, $format_value); ?>
            <?php if ($below_html): ?>
              <button class="bes-toggle-btn" aria-expanded="false">Mehr anzeigen</button>
            <?php endif; ?>
          </div>

          <?php if ($below_html): ?>
            <div class="bes-fields-middle">
              <?php echo $below_html; ?>
              <button class="bes-toggle-btn" aria-expanded="false">Weniger anzeigen</button>
            </div>
          <?php endif; ?>
        </div>
        <?php echo bes_generate_member_schema_tag($member, $alt_text, $id, (file_exists($img_path) ? $img_url : '')); ?>
      </article>
    <?php endforeach; ?>
  </div>

  <button id="bes-load-more" class="bes-load-more" data-loaded="25">Mehr anzeigen</button>
<?php
  $complete_html = ob_get_clean();

  // ----------------------------------------------------------
  // 🔚 Rückgabe: Komplettes HTML (Filterbar + Grid)
  // ----------------------------------------------------------
  // Render-Transient bewusst nicht gespeichert (kein set_transient / bes_set_cached), siehe Funktionskopf.
  bes_debug_log(
    sprintf('HTML generiert (Länge: %d Zeichen)', strlen($complete_html)),
    'DEBUG',
    'bes_render_members'
  );

  // Rückgabe als ist (nicht escaped)
  return $complete_html;
}