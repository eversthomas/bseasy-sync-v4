<?php
if (!defined('ABSPATH')) exit;

/**
 * Rendering der Mitglieder-Karte mit Leaflet.js
 */

// Sicherstelle, dass die Konstanten geladen sind
if (!defined('BES_DATA')) {
    $main_file = plugin_dir_path(dirname(__DIR__)) . 'bseasy-sync.php';
    if (file_exists($main_file)) {
        require_once $main_file;
    } else {
        // Fallback, falls Hauptplugin nicht geladen ist
        $upload_dir = wp_upload_dir();
        define('BES_UPLOADS_DIR', trailingslashit($upload_dir['basedir']) . 'bseasy-sync/');
        define('BES_UPLOADS_URL', trailingslashit($upload_dir['baseurl']) . 'bseasy-sync/');
        define('BES_DATA', BES_UPLOADS_DIR);
        define('BES_IMG', BES_UPLOADS_DIR . 'img/');
    }
}

require_once dirname(__DIR__) . '/includes/filter-helpers.php';

/**
 * Rendert die Mitglieder-Karte
 * 
 * @return string HTML-Output der Karte
 */
function bes_render_members_map(): string
{
    // Mitgliederdaten über Repository prüfen (keine direkte Abhängigkeit zu sync/)
    if (!function_exists('bes_members_file_exists') || !bes_members_file_exists()) {
        return '';
    }
    
    $config_file = BES_DATA . 'fields-config.json';

    // ============================================================
    // CACHE CHECK
    // ============================================================
    // Cache-Key inkludiert auch die PHP-Datei-Version für Code-Änderungen und JSON-Quelle
    $map_render_file = __FILE__;
    $filter_helpers_file = dirname(__DIR__) . '/includes/filter-helpers.php';
    $cache_key = 'bes_members_map_' . md5(
        bes_members_get_file_path() .
        bes_members_get_file_mtime() .
        $config_file .
        (file_exists($config_file) ? filemtime($config_file) : 0) .
        $map_render_file .
        (file_exists($map_render_file) ? filemtime($map_render_file) : 0) .
        $filter_helpers_file .
        (file_exists($filter_helpers_file) ? filemtime($filter_helpers_file) : 0) .
        BES_VERSION // Plugin-Version
    );
    
    // Verwende hosting-kompatible Cache-Funktion
    if (function_exists('bes_get_cached')) {
        $cached = bes_get_cached($cache_key);
    } else {
        $cached = get_transient($cache_key);
    }
    
    if ($cached !== false) {
        bes_debug_log('Map-Cache Hit', 'DEBUG', 'bes_render_members_map');
        return $cached;
    }
    
    bes_debug_log('Map-Cache Miss - generiere neue Karte', 'DEBUG', 'bes_render_members_map');

    // ============================================================
    // VALIDIERUNG
    // ============================================================
    if (!file_exists($config_file)) {
        $error_msg = '<div style="padding: 20px; background: #fee; border: 2px solid red; color: #333;">';
        $error_msg .= '<p style="font-weight: bold; color: red;">❌ Map-Fehler: Konfigurationsdatei nicht gefunden!</p>';
        $error_msg .= '<p style="font-size: 12px; color: #666;">';
        $error_msg .= '<strong>config_file:</strong> ' . esc_html($config_file) . ' → ❌ MISSING<br>';
        $error_msg .= '</p></div>';

        BES_Error_Handler::handle('Konfigurationsdatei nicht gefunden: ' . $config_file, 'bes_render_members_map');
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

    if ($config_raw === false) {
        BES_Error_Handler::handle('Konfigurationsdatei konnte nicht gelesen werden', 'bes_render_members_map');
        return '<p>Fehler beim Laden der Konfiguration.</p>';
    }

    // ✅ JSON-Decode mit sofortiger Fehlerprüfung
    try {
        if (function_exists('bes_safe_json_decode')) {
            $config_data = bes_safe_json_decode($config_raw, true);
        } else {
            $config_data = json_decode($config_raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON-Decode-Fehler in fields-config.json: ' . json_last_error_msg());
            }
        }
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
        if (class_exists('BES_Error_Handler')) {
            BES_Error_Handler::handle($error_msg, 'bes_render_members_map');
        } elseif (function_exists('bes_debug_log')) {
            bes_debug_log($error_msg, 'ERROR', 'bes_render_members_map');
        }
        return '<p>Fehler beim Laden der Konfiguration.</p>';
    }
    $config = is_array($config_data) ? $config_data : [];

    // Sortiere nach order
    usort($config, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

    // ----------------------------------------------------------
    // 🧹 Globale Hilfsfunktion: Werte bereinigen (falls nicht bereits definiert)
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

    // Hilfsfunktion: Feldwert abrufen (identisch mit Kacheln-Renderer)
    $get_value = function ($member, $fid) {
        // PRIORITÄT 1: Flache Keys zuerst (deckt neue Keys wie member.addresses[].city ab)
        if (array_key_exists($fid, $member)) {
            return $member[$fid];
        }
        
        if (str_starts_with($fid, 'member.')) {
            $key = substr($fid, 7);
            if (isset($member['contact'][$key])) {
                return $member['contact'][$key];
            }
            if (isset($member['member'][$key])) {
                return $member['member'][$key];
            }
            return '';
        }

        if (str_starts_with($fid, 'cf.')) {
            $cfid = substr($fid, 3);
            if (isset($member['member_cf_extracted'][$cfid])) {
                $cf = $member['member_cf_extracted'][$cfid];
                return $cf['display_value'] ?? $cf['value'] ?? '';
            }
            return '';
        }

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

        if (str_starts_with($fid, 'contactcf.')) {
            $cfid = substr($fid, 10);
            if (isset($member['contact_cf_extracted'][$cfid])) {
                $cf = $member['contact_cf_extracted'][$cfid];
                return $cf['display_value'] ?? $cf['value'] ?? '';
            }
            return '';
        }

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
    // 🎨 Hilfsfunktion: Feldwert formatiert ausgeben (wie in renderer.php)
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

        // Klickbare Links (kurzer Anzeigetext, z. B. solutionsteps.ch)
        if (preg_match('/^(https?:\/\/|www\.)/i', $value)) {
            $value = function_exists('bes_format_field_link_html')
                ? bes_format_field_link_html($value)
                : esc_html($value);
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
    // 🧩 Hilfsfunktion: Felder einer Area rendern (wie in renderer.php)
    // ----------------------------------------------------------
    $render_area = function ($member, $config, $area, $get_value, $format_value) {
        $html = '';

        // 1️⃣ Filtere alle sichtbaren Felder dieser Area und sortiere nach 'order'
        $fields_in_area = array_filter($config, fn($f) => !empty($f['show']) && $f['area'] === $area);
        usort($fields_in_area, 'bes_compare_area_fields');

        $current_group = null;
        $group_html = '';

        // 2️⃣ Iteriere in Reihenfolge und erkenne Gruppenwechsel
        foreach ($fields_in_area as $field) {
            if ($field['id'] === '_profilePicture') continue;

            $fid    = $field['id'];
            $label  = isset($field['label']) && trim($field['label']) !== '' ? esc_html($field['label']) : '';
            $format = function_exists('bes_field_display_format') ? bes_field_display_format($field) : ($field['format'] ?? 'normal');
            $group  = $field['inline_group'] ?? null;

            // Gruppenwechsel: vorherige Gruppe abschließen
            if ($current_group !== null && $group !== $current_group) {
                bes_flush_inline_group_html($html, $current_group, $group_html);
                $group_html = '';
            }

            // Feldwert abrufen
            $raw_value = $get_value($member, $fid);

            // PRIORITÄT: Native PHP-Arrays erkennen (nicht nur JSON-Strings)
            if (is_array($raw_value) && !empty($raw_value)) {
                // Native PHP-Array: implode für Anzeige
                $raw_value = implode(', ', array_map('bes_clean_value', $raw_value));
            } elseif (is_string($raw_value) && str_starts_with(trim($raw_value), '[')) {
                // JSON-Array-String: dekodieren
                $decoded = json_decode($raw_value, true);
                if (is_array($decoded)) {
                    $raw_value = implode(', ', array_map('bes_clean_value', $decoded));
                }
            } elseif (is_string($raw_value) && str_contains($raw_value, ',')) {
                // Bereits Kommaliste: belassen
            }

            // Prüfe ob Wert vorhanden (Arrays: nicht leer)
            if ((is_array($raw_value) && empty($raw_value)) ||
                $raw_value === '' || $raw_value === '[]' || $raw_value === 'null') {
                continue;
            }

            $formatted_value = $format_value($raw_value, $format);

            if ($label && ($field['show_label'] ?? true)) {
                $group_html .= "<div class='bes-field'><strong>{$label}:</strong> {$formatted_value}</div>";
            } else {
                $group_html .= "<div class='bes-field'>{$formatted_value}</div>";
            }

            $current_group = $group;
        }

        // 3️⃣ Letzte Gruppe anhängen
        bes_flush_inline_group_html($html, $current_group, $group_html);

        return $html;
    };

    // 🗺️ MAP-FILTER-FELDER: gleiche Reihenfolge wie Kacheln
    $map_filter_order = bes_get_default_filter_order($config);
    $filter_fields    = bes_prepare_filter_fields($config, $map_filter_order);
    $filter_values    = bes_collect_filter_values($members, $filter_fields, $get_value);

    // Map-Einstellungen laden
    $map_enabled = (bool) get_option('bes_map_enabled', 1);
    $map_style = get_option('bes_map_style', 'light');
    $map_zoom = (int) get_option('bes_map_zoom', 6);
    $map_center_lat = floatval(get_option('bes_map_center_lat', 51.1657));
    $map_center_lng = floatval(get_option('bes_map_center_lng', 10.4515));

    if (!$map_enabled) {
        return '<p>❌ Die Mitglieder-Karte ist nicht aktiviert.</p>';
    }

    // Marker-Daten für JavaScript vorbereiten
    $markers = [];
    foreach ($members as $member) {
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
            continue; // Skip members without ID
        }
        
        // Contact-Daten extrahieren (V3: contact.* im Root oder contact-Objekt, V2: contact-Objekt)
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
        
        // Koordinaten extrahieren: zuerst nested, dann flache Keys
        $lat = null;
        $lng = null;
        
        // PRIORITÄT 1: Nested contact['geoPositionCoords']
        if (!empty($contact['geoPositionCoords']) && 
            isset($contact['geoPositionCoords']['lat']) && 
            isset($contact['geoPositionCoords']['lng'])) {
            $lat = $contact['geoPositionCoords']['lat'];
            $lng = $contact['geoPositionCoords']['lng'];
        } else {
            // PRIORITÄT 2: Flache Keys im Member-Array
            $lat = $member['contact.geoPositionCoords.lat'] ?? null;
            $lng = $member['contact.geoPositionCoords.lng'] ?? null;
        }
        
        // String-Zahlen zu float casten
        if ($lat !== null && is_string($lat)) {
            $lat = is_numeric($lat) ? (float)$lat : null;
        }
        if ($lng !== null && is_string($lng)) {
            $lng = is_numeric($lng) ? (float)$lng : null;
        }
        
        // Prüfe ob Koordinaten vorhanden
        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            continue; // Skip members without coordinates
        }
        
        // Name extrahieren: zuerst nested contact, dann flache Keys
        $name = '';
        if (!empty($contact['firstName']) || !empty($contact['familyName'])) {
            $name = trim(($contact['firstName'] ?? '') . ' ' . ($contact['familyName'] ?? ''));
        } else {
            // Fallback: Flache Keys
            $firstName = $member['contact.firstName'] ?? '';
            $familyName = $member['contact.familyName'] ?? '';
            $name = trim($firstName . ' ' . $familyName);
        }
        
        // City extrahieren: zuerst nested contact, dann flache Keys
        $city = 'N/A';
        if (!empty($contact['city'])) {
            $city = $contact['city'];
        } else {
            // Fallback: Flache Keys
            $city = $member['contact.city'] ?? 'N/A';
        }
        $img_path = BES_IMG . $id . '.png';
        // Nur URL setzen, wenn Bild tatsächlich existiert
        $img_url = file_exists($img_path) ? BES_UPLOADS_URL . 'img/' . $id . '.png' : null;

        // Filter-Werte für diesen Marker (nicht die globale $filter_values der Leiste überschreiben)
        $marker_filters = [];
        foreach ($filter_fields as $field) {
            $fid = $field['id'];
            $raw_value = $get_value($member, $fid);
            
            // Normalisiere Werte (analog zu renderer.php)
            $normalized_values = [];
            
            if (is_array($raw_value)) {
                // Bereits ein Array (z.B. von JSON decode in get_value)
                foreach ($raw_value as $v) {
                    $cleaned = bes_clean_filter_value($v);
                    if ($cleaned !== '' && $cleaned !== 'null' && $cleaned !== 'undefined' && $cleaned !== '[]') {
                        $normalized_values[] = $cleaned;
                    }
                }
            } elseif (is_string($raw_value) && str_starts_with(trim($raw_value), '[')) {
                // JSON-Array dekodieren
                $decoded = json_decode($raw_value, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $v) {
                        $cleaned = bes_clean_filter_value($v);
                        if ($cleaned !== '' && $cleaned !== 'null' && $cleaned !== 'undefined' && $cleaned !== '[]') {
                            $normalized_values[] = $cleaned;
                        }
                    }
                }
            } elseif (is_string($raw_value) && str_contains($raw_value, ',')) {
                // Komma-getrennte Werte
                $parts = explode(',', $raw_value);
                foreach ($parts as $v) {
                    $cleaned = bes_clean_filter_value($v);
                    if ($cleaned !== '' && $cleaned !== 'null' && $cleaned !== 'undefined' && $cleaned !== '[]') {
                        $normalized_values[] = $cleaned;
                    }
                }
            } else {
                // Einzelwert (string, number, null, false, etc.)
                $cleaned = bes_clean_filter_value($raw_value);
                if ($cleaned !== '' && $cleaned !== 'null' && $cleaned !== 'undefined' && $cleaned !== '[]') {
                    $normalized_values[] = $cleaned;
                }
            }

            if (function_exists('bes_field_is_country_filter') && bes_field_is_country_filter($field)) {
                $norm_country = [];
                foreach ($normalized_values as $v) {
                    $iso = bes_normalize_country_token($v);
                    $norm_country[] = $iso ?: $v;
                }
                $normalized_values = array_values(array_unique($norm_country));
            }

            // Immer Array setzen (auch wenn leer)
            $marker_filters[$fid] = $normalized_values;
        }

        // Popup-Inhalte rendern (wie Cards-Ansicht)
        $popup_above = $render_area($member, $config, 'above', $get_value, $format_value);
        $popup_below = $render_area($member, $config, 'below', $get_value, $format_value);

        $marker_data = [
            'id' => $id,
            'lat' => floatval($lat),
            'lng' => floatval($lng),
            'name' => esc_html($name),
            'city' => esc_html($city),
            'filters' => $marker_filters,
            'popupAbove' => $popup_above,
            'popupBelow' => $popup_below,
        ];
        
        // Nur image hinzufügen, wenn URL vorhanden ist
        if ($img_url !== null) {
            $marker_data['image'] = esc_url($img_url);
            $marker_data['imageAlt'] = esc_attr($name ?: 'Profilbild'); // ALT-Text für Bild
        }
        
        $markers[] = $marker_data;
    }
    
    // Filter: Erlaube Entwicklern, Marker-Daten zu modifizieren
    if (function_exists('bes_filter_map_markers')) {
        $markers = bes_filter_map_markers($markers);
    }

    ob_start();
    ?>
    <div class="bes-map-container">
        <!-- Gemeinsame Filterleiste -->
        <?php
        echo bes_render_filterbar(
            $filter_fields,
            $filter_values,
            [
                'wrapper_classes' => 'bes-filterbar bes-map-filterbar',
                'reset_id'        => 'bes-reset-map',
                'search_id'       => 'bes-search-map',
                'zip_as_text'     => true,
            ]
        );
        ?>

        <!-- Karte mit Data-Attributen als Fallback -->
        <?php
        $bes_map_filter_fields_meta = array_values(array_map(static function ($f) {
            return [
                'id' => $f['id'],
                'label' => $f['label'] ?? '',
                'countryFilter' => function_exists('bes_field_is_country_filter') && bes_field_is_country_filter($f),
            ];
        }, $filter_fields));
        $bes_map_country_labels = function_exists('bes_country_filter_labels_js') ? bes_country_filter_labels_js() : [];

        $map_data_json = wp_json_encode([
            'markers' => $markers,
            'filterFields' => $bes_map_filter_fields_meta,
            'countryFilterLabels' => $bes_map_country_labels,
            'mapSettings' => [
                'center' => [$map_center_lat, $map_center_lng],
                'zoom' => $map_zoom,
                'style' => $map_style
            ],
            'uploadsUrl' => BES_UPLOADS_URL
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>
        <div id="bes-members-map" 
             class="bes-map" 
             style="height: 600px; border-radius: 8px; margin-top: 20px;"
             data-map-markers="<?php echo esc_attr(wp_json_encode($markers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>"
             data-map-filters="<?php echo esc_attr(wp_json_encode($bes_map_filter_fields_meta, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>"
             data-map-settings="<?php echo esc_attr(wp_json_encode([
                 'center' => [$map_center_lat, $map_center_lng],
                 'zoom' => $map_zoom,
                 'style' => $map_style
             ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>"
             data-uploads-url="<?php echo esc_attr(BES_UPLOADS_URL); ?>">
        </div>
    </div>

    <!-- JSON-Daten als Script-Tag (Primär-Methode) -->
    <script type="application/json" id="bes-map-data">
        <?php echo $map_data_json; ?>
    </script>

    <?php
    $output = ob_get_clean();
    
    // ============================================================
    // CACHE SPEICHERN
    // ============================================================
    // Verwende hosting-kompatible Cache-Funktion
    if (function_exists('bes_set_cached')) {
        bes_set_cached($cache_key, $output);
    } else {
        // Verwende hosting-kompatible Cache-Funktion
        if (function_exists('bes_set_cached')) {
            bes_set_cached($cache_key, $output);
        } else {
            set_transient($cache_key, $output, BES_CACHE_DURATION);
        }
    }
    bes_debug_log(
        sprintf('Karte generiert und gecacht (Länge: %d Zeichen, %d Marker)', 
            strlen($output), 
            count($markers)
        ), 
        'DEBUG', 
        'bes_render_members_map'
    );
    
    return $output;
}

