<?php
if (!defined('ABSPATH')) exit;

/**
 * Hilfsfunktionen für die einheitliche Filterleiste (Kacheln & Karte).
 */

/**
 * Liefert die Standard-Reihenfolge der Filterfelder.
 * Falls show_in_filterbar und filter_priority in der Config vorhanden sind, wird diese verwendet.
 *
 * @param array $config Optional: Config-Array für filter_priority
 * @return array
 */
function bes_get_default_filter_order(array $config = []): array {
    // Wenn Config vorhanden und Felder mit show_in_filterbar existieren
    if (!empty($config)) {
        $filterbar_fields = [];
        foreach ($config as $field) {
            $field_id = $field['id'] ?? '';
            $show_in_filterbar = !empty($field['show_in_filterbar']);
            $filter_priority = isset($field['filter_priority']) && $field['filter_priority'] !== null 
                ? intval($field['filter_priority']) 
                : 999; // Standard-Priorität für Felder ohne explizite Priorität
            
            if ($show_in_filterbar) {
                $filterbar_fields[] = [
                    'id' => $field_id,
                    'priority' => $filter_priority,
                ];
            }
        }
        
        // Sortiere nach filter_priority (niedrigere Zahl = weiter links)
        if (!empty($filterbar_fields)) {
            usort($filterbar_fields, function($a, $b) {
                if ($a['priority'] !== $b['priority']) {
                    return $a['priority'] <=> $b['priority'];
                }
                // Bei gleicher Priorität: alphabetisch nach ID
                return strcmp($a['id'], $b['id']);
            });
            
            // Extrahiere nur die IDs
            return array_map(function($item) {
                return $item['id'];
            }, $filterbar_fields);
        }
    }
    
    // Fallback: Standard-Reihenfolge
    return [
        'contact.companyZip',  // PLZ
        'contact.companyCity', // Stadt
        'cf.50697357',         // Leistungsangebote (Angebote)
        'cf.50359307',         // Zielgruppen
        'cfraw.50799935',      // Methoden
    ];
}

/**
 * Wählt die filterbaren Felder aus und sortiert sie nach Vorgabe.
 *
 * @param array $config
 * @param array $preferred_order
 * @return array
 */
function bes_prepare_filter_fields(array $config, array $preferred_order): array {
    // Felder, die explizit ausgeschlossen werden sollen (auch wenn show_in_filterbar: true)
    $excluded_fields = [
        'contact.familyName', // Name-Feld soll nicht in Filterleiste erscheinen
    ];
    
    // Erstelle Index für schnelleren Zugriff
    $config_index = [];
    foreach ($config as $field) {
        $field_id = $field['id'] ?? '';
        if ($field_id) {
            $config_index[$field_id] = $field;
        }
    }
    
    $filter_fields = [];

    // Zuerst Felder in vorgegebener Reihenfolge durchgehen (aus show_in_filterbar + filter_priority)
    foreach ($preferred_order as $field_id) {
        // Überspringe ausgeschlossene Felder
        if (in_array($field_id, $excluded_fields, true)) {
            continue;
        }
        
        // Wenn Feld in Config existiert, verwende es
        if (isset($config_index[$field_id])) {
            $field = $config_index[$field_id];
            // Einbeziehen wenn show_in_filterbar aktiv ist
            if (!empty($field['show_in_filterbar'])) {
                $filter_fields[] = $field;
            }
        } else {
            // Feld existiert nicht in Config, aber ist in preferred_order
            // Erstelle minimales Feld-Array für die Filterleiste
            $filter_fields[] = [
                'id' => $field_id,
                'label' => $field_id, // Fallback, wird später überschrieben falls vorhanden
                'show_in_filterbar' => true,
            ];
        }
    }

    // Rest anhängen (falls weitere Felder mit show_in_filterbar existieren, die nicht in preferred_order sind)
    foreach ($config as $field) {
        $field_id = $field['id'] ?? '';
        
        // Überspringe ausgeschlossene Felder
        if (in_array($field_id, $excluded_fields, true)) {
            continue;
        }
        
        // Überspringe Felder, die bereits in preferred_order sind
        if (in_array($field_id, $preferred_order, true)) {
            continue;
        }
        
        // Nur Felder mit show_in_filterbar hinzufügen
        if (!empty($field['show_in_filterbar'])) {
            $filter_fields[] = $field;
        }
    }

    return $filter_fields;
}

/**
 * Globale Hilfsfunktion: Werte bereinigen (HTML entfernen, normalisieren)
 * 
 * @param mixed $value
 * @return string|array
 */
function bes_clean_filter_value($value) {
    if (empty($value)) return '';
    
    // Wenn es ein Array ist, rekursiv bereinigen
    if (is_array($value)) {
        return array_map('bes_clean_filter_value', $value);
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

/**
 * Sammle alle möglichen Werte für die Filterfelder.
 *
 * @param array    $members
 * @param array    $filter_fields
 * @param callable $get_value
 * @return array
 */
function bes_collect_filter_values(array $members, array $filter_fields, callable $get_value): array {
    $filter_values = [];

    foreach ($filter_fields as $field) {
        $fid    = $field['id'] ?? '';
        $values = [];

        foreach ($members as $member) {
            $raw_value = $get_value($member, $fid);

            // PRIORITÄT 1: Native PHP-Arrays (V3-Struktur: cf.* sind Arrays)
            if (is_array($raw_value) && !empty($raw_value)) {
                foreach ($raw_value as $v) {
                    $v = bes_clean_filter_value($v);
                    if ($v !== '' && $v !== 'null' && $v !== 'undefined' && !in_array($v, $values, true)) {
                        $values[] = $v;
                    }
                }
            } elseif (is_string($raw_value) && substr(ltrim($raw_value), 0, 1) === '[') {
                // PRIORITÄT 2: JSON-Array-String (PHP 7.4 kompatibel: substr statt str_starts_with)
                $decoded = json_decode($raw_value, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $v) {
                        $v = bes_clean_filter_value($v);
                        if ($v !== '' && $v !== 'null' && $v !== 'undefined' && !in_array($v, $values, true)) {
                            $values[] = $v;
                        }
                    }
                }
            } elseif (is_string($raw_value) && strpos($raw_value, ',') !== false) {
                // PRIORITÄT 3: Kommaliste (PHP 7.4 kompatibel: strpos statt str_contains)
                $parts = explode(',', $raw_value);
                foreach ($parts as $v) {
                    $v = bes_clean_filter_value($v);
                    if ($v !== '' && $v !== 'null' && $v !== 'undefined' && !in_array($v, $values, true)) {
                        $values[] = $v;
                    }
                }
            } else {
                // PRIORITÄT 4: Einzelwert (String, Number, null, false, etc.)
                $raw_value = bes_clean_filter_value($raw_value);
                if ($raw_value !== '' && $raw_value !== 'null' && $raw_value !== 'undefined' && $raw_value !== '[]' && !in_array($raw_value, $values, true)) {
                    $values[] = $raw_value;
                }
            }
        }

        sort($values);
        $filter_values[$fid] = $values;
    }

    return $filter_values;
}

/**
 * Rendert die Filterleiste als HTML.
 *
 * @param array $filter_fields
 * @param array $filter_values
 * @param array $args {
 *   @type bool   $search_enabled   Ob ein Suchfeld angezeigt wird. Default true.
 *   @type string $reset_id         ID des Reset-Buttons. Default bes-reset.
 *   @type string $search_id        ID des Suchfelds. Default bes-search.
 *   @type string $wrapper_classes  Wrapper-Klassen. Default 'bes-filterbar'.
 *   @type bool   $zip_as_text      PLZ-Felder als Textfeld rendern. Default true.
 *   @type string $search_placeholder Placeholder für das Suchfeld.
 * }
 * @return string
 */
function bes_render_filterbar(array $filter_fields, array $filter_values, array $args = []): string {
    $defaults = [
        'search_enabled'     => true,
        'reset_id'           => 'bes-reset',
        'search_id'          => 'bes-search',
        'wrapper_classes'    => 'bes-filterbar',
        'zip_as_text'        => true,
        'search_placeholder' => __('Suche...', 'bseasy-sync'),
    ];

    $args = array_merge($defaults, $args);

    ob_start();
    ?>
    <div class="<?php echo esc_attr($args['wrapper_classes']); ?>">
        <?php if ($args['search_enabled']) : ?>
            <div class="bes-filter bes-search">
                <input
                    type="text"
                    id="<?php echo esc_attr($args['search_id']); ?>"
                    placeholder="<?php echo esc_attr($args['search_placeholder']); ?>">
            </div>
        <?php endif; ?>

        <?php if (!empty($filter_fields)) : ?>
            <?php foreach ($filter_fields as $field) : ?>
                <?php
                $fid   = $field['id'] ?? '';
                $label = $field['label'] ?? 'Feld';
                $is_zip_field = str_contains($fid, 'Zip') || str_contains($fid, 'zip') ||
                    str_contains($label, 'PLZ') || str_contains($label, 'Postleitzahl') ||
                    str_contains($fid, 'plz');
                $is_city_field = str_contains($fid, 'City') || str_contains($fid, 'city') ||
                    str_contains($label, 'Stadt') || str_contains($label, 'Ort');
                ?>
                <div class="bes-filter">
                    <label><?php echo esc_html($label); ?></label>

                    <?php if ($args['zip_as_text'] && ($is_zip_field || $is_city_field)) : ?>
                        <input
                            type="text"
                            class="<?php echo $is_zip_field ? 'bes-filter-zip' : 'bes-filter-city'; ?>"
                            data-field="<?php echo esc_attr($fid); ?>"
                            placeholder="<?php echo $is_zip_field ? esc_attr__('PLZ eingeben...', 'bseasy-sync') : esc_attr__('Stadt eingeben...', 'bseasy-sync'); ?>">
                    <?php else : ?>
                        <select data-field="<?php echo esc_attr($fid); ?>">
                            <option value=""><?php esc_html_e('Alle', 'bseasy-sync'); ?></option>
                            <?php if (isset($filter_values[$fid])) : ?>
                                <?php foreach ($filter_values[$fid] as $value) : ?>
                                    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($value); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <button id="<?php echo esc_attr($args['reset_id']); ?>" class="bes-btn-reset"><?php esc_html_e('Zurücksetzen', 'bseasy-sync'); ?></button>
    </div>
    <?php

    return ob_get_clean();
}


