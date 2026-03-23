<?php
if (!defined('ABSPATH')) exit;

/**
 * Fields Handler for besync (2025)
 *
 * - Scannt members_consent.json
 * - Führt Auto-Felder + Config (fields-config.json) zusammen
 * - Liefert Felder für das Backend-UI (Ajax bes_get_fields)
 * - Speichert Config inkl.:
 *   - label
 *   - area (above/below/unused)
 *   - order
 *   - show_label
 *   - filterable
 *   - inline_group
 *   - favorite
 *   - ignored
 */

/* ============================================================
 * BASISPFAD / KONSTANTE
 * ============================================================ */

// Wenn das Plugin an anderer Stelle BES_DATA schon definiert, weiterverwenden:
if (!defined('BES_DATA')) {
    define(
        'BES_DATA',
        trailingslashit(WP_CONTENT_DIR . '/uploads/bseasy-sync/')
    );
}

/* ============================================================
 * JSON HELFER
 * ============================================================ */

/**
 * Lädt eine JSON-Datei
 * 
 * @param string $file Dateiname relativ zu BES_DATA
 * @return array|null Array-Daten oder null bei Fehler
 */
function bes_load_json(string $file): ?array {
    $path = BES_DATA . $file;
    return bes_load_json_from_path($path);
}

/**
 * Lädt eine JSON-Datei von einem absoluten Pfad
 * 
 * @param string $path Vollständiger Pfad zur JSON-Datei
 * @return array|null Array-Daten oder null bei Fehler
 */
function bes_load_json_from_path(string $path): ?array {
    if (!file_exists($path)) {
        return null;
    }
    
    // Prüfe Dateigröße
    if (filesize($path) == 0) {
        return null;
    }
    
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '' || trim($raw) === '') {
        return null;
    }
    
    $data = json_decode($raw, true);
    
    // Prüfe auf JSON-Fehler
    if (json_last_error() !== JSON_ERROR_NONE) {
        if (function_exists('bes_debug_log')) {
            bes_debug_log('JSON-Fehler in ' . basename($path) . ': ' . json_last_error_msg(), 'ERROR', 'fields');
        }
        return null;
    }
    
    return is_array($data) ? $data : null;
}

/**
 * Speichert Daten als JSON-Datei (atomisch: tmp + rename)
 * 
 * @param string $file Dateiname relativ zu BES_DATA
 * @param array $data Zu speichernde Daten
 * @return bool Erfolg
 */
function bes_save_json(string $file, array $data): bool {
    $path = BES_DATA . $file;

    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p($dir);
        } else {
            mkdir($dir, 0775, true);
        }
    }

    // JSON-Encoding
    $json_content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json_content === false) {
        if (function_exists('bes_debug_log')) {
            bes_debug_log('JSON-Encoding-Fehler in bes_save_json: ' . json_last_error_msg(), 'ERROR', 'fields');
        }
        return false;
    }

    // Atomisches Schreiben: tmp + rename
    $tmp_file = $path . '.tmp';
    
    // Schreibe in temporäre Datei
    $result = @file_put_contents($tmp_file, $json_content, LOCK_EX);
    
    if ($result === false) {
        // Cleanup: Lösche tmp-Datei falls vorhanden
        if (file_exists($tmp_file)) {
            @unlink($tmp_file);
        }
        return false;
    }
    
    // Atomisches Umbenennen
    $rename_result = @rename($tmp_file, $path);
    
    if ($rename_result === false) {
        // Cleanup: Lösche tmp-Datei falls rename fehlschlug
        if (file_exists($tmp_file)) {
            @unlink($tmp_file);
        }
        return false;
    }
    
    return true;
}

/* ============================================================
 * CONFIG LADEN (mit Migration Array -> Objekt)
 * ============================================================ */

/**
 * Lädt die Felder-Konfiguration
 * 
 * @return array Konfigurations-Array
 */
function bes_load_fields_config(): array {
    // Zuerst neue Config-Datei prüfen
    $cfg = bes_load_json('fields-config.json');
    
    // Falls nicht vorhanden, prüfe alte Config-Datei (Migration)
    if (!$cfg) {
        $old_data_dir = trailingslashit(WP_CONTENT_DIR . '/uploads/easy2transfer-sync/');
        $old_config_file = $old_data_dir . 'fields-config.json';
        
        if (file_exists($old_config_file)) {
            // Alte Config gefunden - migrieren
            $old_cfg = bes_load_json_from_path($old_config_file);
            if ($old_cfg && !empty($old_cfg)) {
                // In neues Verzeichnis kopieren
                bes_save_json('fields-config.json', $old_cfg);
                $cfg = $old_cfg;
            }
        }
    }
    
    if (!$cfg) {
        // KEINE automatische Template-Initialisierung mehr
        // Template muss manuell über den Import-Button importiert werden
        return [];
    }

    // Alte Struktur? (Array von Objekten mit id)
    if (isset($cfg[0]) && is_array($cfg[0]) && isset($cfg[0]['id'])) {
        $migrated = [];
        foreach ($cfg as $item) {
            if (!isset($item['id'])) continue;
            $id = (string)$item['id'];
            $migrated[$id] = $item;
        }
        bes_save_json('fields-config.json', $migrated);
        return $migrated;
    }

    // Neue Struktur: Objekt (assoziatives Array)
    return $cfg;
}

/* ============================================================
 * ID NORMALISIERUNG
 * ============================================================ */

/**
 * Normalisiert eine ID zu einem String
 * 
 * @param mixed $id ID (kann int, string, etc. sein)
 * @return string Normalisierte ID als String
 */
function bes_norm_id($id): string {
    return (string)$id;
}

/* ============================================================
 * FELDER EXTRAHIEREN AUS members_consent.json
 * ============================================================ */

function bes_extract_all_fields() {
    // Lade V3-Konstanten falls nötig
    if (!defined('BES_DATA_V3')) {
        if (file_exists(BES_DIR . 'includes/constants-v3.php')) {
            require_once BES_DIR . 'includes/constants-v3.php';
        }
    }
    
    // V3 ist die einzige Quelle
    $json_source = get_option('bes_json_source', 'v3');
    // Stelle sicher, dass V3 gesetzt ist
    if ($json_source !== 'v3') {
        update_option('bes_json_source', 'v3');
        $json_source = 'v3';
    }
    
    // V3-Datei laden
    if (defined('BES_DATA_V3') && defined('BES_V3_MEMBERS_FILE')) {
        $v3_file = BES_DATA_V3 . BES_V3_MEMBERS_FILE;
        
        if (file_exists($v3_file) && filesize($v3_file) > 0) {
            // Lade V3-Helpers falls nötig
            if (!function_exists('bseasy_v3_read_json')) {
                if (file_exists(BES_DIR . 'sync/v3-helpers.php')) {
                    require_once BES_DIR . 'sync/v3-helpers.php';
                }
            }
            
            $json = bseasy_v3_read_json($v3_file);
            
            if ($json && is_array($json)) {
                // V3-Struktur: Direktes Array von Member-Objekten oder in 'data' verschachtelt
                if (isset($json['data']) && is_array($json['data'])) {
                    $records = $json['data'];
                } elseif (isset($json[0]) && is_array($json[0])) {
                    $records = $json;
                } else {
                    return ['fields' => []];
                }
                
                // V3-Felder extrahieren (bereits flach strukturiert)
                return bes_extract_fields_from_v3($records);
            }
        }
        
        // V3-Datei fehlt - keine Fallback-Logik mehr, da V3 die einzige Quelle ist
        return ['fields' => []];
    }
    
    // Sollte nicht erreicht werden, da V3 die einzige Quelle ist
    return ['fields' => []];

    if (!isset($json['data']) || !is_array($json['data'])) {
        if (is_array($json) && isset($json[0]) && is_array($json[0])) {
            $records = $json;
        } else {
            return ['fields' => []];
        }
    } else {
        $records = $json['data'];
    }

    $fields = [];

    foreach ($records as $r) {

        /** ----------------------------
         * 1) MEMBER-FELDER
         * ---------------------------- */
        if (isset($r['member']) && is_array($r['member'])) {
            foreach ($r['member'] as $key => $val) {
                $id = 'member.' . $key;
                if (!isset($fields[$id])) {
                    $fields[$id] = [
                        'id'      => $id,
                        'type'    => 'member',
                        'example' => $val,
                    ];
                }
            }
        }

        /** ----------------------------
         * 2) MEMBER_CF_EXTRACTED (bevorzugt - zuerst extrahieren)
         * ---------------------------- */
        if (isset($r['member_cf_extracted']) && is_array($r['member_cf_extracted'])) {
            foreach ($r['member_cf_extracted'] as $fid => $cf) {
                $id = 'cf.' . bes_norm_id($fid);

                $example = null;
                if (isset($cf['display_value']) && $cf['display_value'] !== '') {
                    $example = $cf['display_value'];
                } elseif (isset($cf['value'])) {
                    $example = $cf['value'];
                }

                if (!isset($fields[$id])) {
                    $fields[$id] = [
                        'id'      => $id,
                        'type'    => 'cf',
                        'example' => $example,
                    ];
                }
            }
        }

        /** ----------------------------
         * 3) MEMBER_CF (roh) - nur wenn cf.* nicht existiert
         * ---------------------------- */
        if (isset($r['member_cf']) && is_array($r['member_cf'])) {
            foreach ($r['member_cf'] as $cf) {
                if (!isset($cf['customField'])) continue;

                $url  = $cf['customField'];
                $path = parse_url($url, PHP_URL_PATH);
                $fid  = $path ? basename($path) : null;
                if (!$fid) continue;

                // Prüfe ob bereits cf.* (extracted) existiert
                $extracted_id = 'cf.' . bes_norm_id($fid);
                if (isset($fields[$extracted_id])) {
                    // Überspringe cfraw, da cf.* bereits existiert
                    continue;
                }

                $id = 'cfraw.' . bes_norm_id($fid);

                if (!isset($fields[$id])) {
                    $fields[$id] = [
                        'id'      => $id,
                        'type'    => 'cfraw',
                        'example' => isset($cf['value']) ? $cf['value'] : null,
                    ];
                }
            }
        }

        /** ----------------------------
         * 4) CONTACT-FELDER
         * ---------------------------- */
        if (isset($r['contact']) && is_array($r['contact'])) {
            foreach ($r['contact'] as $key => $val) {
                $id = 'contact.' . $key;
                if (!isset($fields[$id])) {
                    $fields[$id] = [
                        'id'      => $id,
                        'type'    => 'contact',
                        'example' => $val,
                    ];
                }
            }
        }

        /** ----------------------------
         * 5) CONTACT_CF_EXTRACTED (bevorzugt - zuerst extrahieren)
         * ---------------------------- */
        if (isset($r['contact_cf_extracted']) && is_array($r['contact_cf_extracted'])) {
            foreach ($r['contact_cf_extracted'] as $fid => $cf) {
                $id = 'contactcf.' . bes_norm_id($fid);

                $example = null;
                if (isset($cf['display_value']) && $cf['display_value'] !== '') {
                    $example = $cf['display_value'];
                } elseif (isset($cf['value'])) {
                    $example = $cf['value'];
                }

                if (!isset($fields[$id])) {
                    $fields[$id] = [
                        'id'      => $id,
                        'type'    => 'contactcf',
                        'example' => $example,
                    ];
                }
            }
        }

        /** ----------------------------
         * 6) CONTACT_CF (roh) - nur wenn contactcf.* nicht existiert
         * ---------------------------- */
        if (isset($r['contact_cf']) && is_array($r['contact_cf'])) {
            foreach ($r['contact_cf'] as $cf) {
                if (!isset($cf['customField'])) continue;

                $url  = $cf['customField'];
                $path = parse_url($url, PHP_URL_PATH);
                $fid  = $path ? basename($path) : null;
                if (!$fid) continue;

                // Prüfe ob bereits contactcf.* (extracted) existiert
                $extracted_id = 'contactcf.' . bes_norm_id($fid);
                if (isset($fields[$extracted_id])) {
                    // Überspringe contactcfraw, da contactcf.* bereits existiert
                    continue;
                }

                $id = 'contactcfraw.' . bes_norm_id($fid);

                if (!isset($fields[$id])) {
                    $fields[$id] = [
                        'id'      => $id,
                        'type'    => 'contactcfraw',
                        'example' => isset($cf['value']) ? $cf['value'] : null,
                    ];
                }
            }
        }

        /** ----------------------------
         * 7) CONSENTS (Altstruktur)
         * ---------------------------- */
        if (isset($r['consents']) && is_array($r['consents'])) {
            foreach ($r['consents'] as $c) {
                if (!isset($c['id'])) continue;

                $cid = bes_norm_id($c['id']);
                $id  = 'consent.' . $cid;
                $val = isset($c['value']) ? $c['value'] : null;

                if (!isset($fields[$id])) {
                    $fields[$id] = [
                        'id'      => $id,
                        'type'    => 'consent',
                        'example' => $val,
                    ];
                }
            }
        }
    }

    return ['fields' => $fields];
}

/**
 * Extrahiert Felder aus V3-Datei (flache Struktur)
 * 
 * @param array $records Array von Member-Datensätzen
 * @return array Array mit 'fields' Key
 */
function bes_extract_fields_from_v3($records) {
    $fields = [];
    
    foreach ($records as $r) {
        if (!is_array($r)) continue;
        
        // V3 hat flache Struktur: member.*, cf.*, contact.*, contact_cf.* direkt im Root
        foreach ($r as $key => $val) {
            // Überspringe Meta-Felder und contact-Objekt (wird separat behandelt)
            if ($key === 'syncedAt' || $key === '_meta' || $key === 'contact') {
                continue;
            }
            
            // Felder die bereits im richtigen Format sind
            if (preg_match('/^(member\.|cf\.|contact\.|contact_cf\.)/', $key)) {
                $id = $key;
                
                // Bestimme Typ
                if (strpos($id, 'member.') === 0 && strpos($id, 'member.cf.') === false) {
                    $type = 'member';
                } elseif (strpos($id, 'cf.') === 0) {
                    $type = 'cf';
                } elseif (strpos($id, 'contact.') === 0 && strpos($id, 'contact.cf.') === false) {
                    $type = 'contact';
                } elseif (strpos($id, 'contact_cf.') === 0 || strpos($id, 'contact.cf.') === 0) {
                    $type = 'contactcf';
                } else {
                    $type = 'other';
                }
                
                if (!isset($fields[$id])) {
                    $fields[$id] = [
                        'id'      => $id,
                        'type'    => $type,
                        'example' => $val,
                    ];
                }
            }
        }
        
        // Contact-Objekt separat behandeln (falls vorhanden)
        if (isset($r['contact']) && is_array($r['contact'])) {
            foreach ($r['contact'] as $key => $val) {
                // Contact-Felder können bereits als contact.* im Root sein oder hier im contact-Objekt
                $id = 'contact.' . $key;
                
                if (!isset($fields[$id])) {
                    $fields[$id] = [
                        'id'      => $id,
                        'type'    => 'contact',
                        'example' => $val,
                    ];
                }
            }
        }
    }
    
    return ['fields' => $fields];
}

/* ============================================================
 * FELDER + CONFIG MERGEN
 * ============================================================ */

function bes_merge_fields($auto_fields, $config) {
    // Wenn keine Config vorhanden, versuche Template zu mergen
    if (empty($config) && function_exists('bes_merge_template_with_fields')) {
        $config = bes_merge_template_with_fields($auto_fields, $config);
    }
    
    $merged = [];

    foreach ($auto_fields as $id => $auto) {

        $defaults = [
            'id'               => $id,
            'label'            => $id,         // Backend überschreibt
            'show'             => false,
            'order'            => 999,
            'area'             => 'unused',
            'show_label'       => true,
            'filterable'       => false,
            'show_in_filterbar' => false,
            'filter_priority'  => null,
            'inline_group'     => '',
            'example'          => isset($auto['example']) ? $auto['example'] : null,
            'type'             => isset($auto['type']) ? $auto['type'] : 'unknown',
            'favorite'         => false,
            'ignored'          => false,
        ];

        if (isset($config[$id]) && is_array($config[$id])) {
            // Config überschreibt Anzeige-Infos, favorite/ignored etc.
            $merged[$id] = array_merge($defaults, $config[$id]);

            // Beispiel & Typ kommen immer aus Auto-SCAN
            $merged[$id]['example'] = $defaults['example'];
            $merged[$id]['type']    = $defaults['type'];
        } else {
            $merged[$id] = $defaults;
        }
    }

    // Sortierung: erst nach area, dann nach order, dann nach label
    uasort($merged, function ($a, $b) {
        if ($a['area'] !== $b['area']) {
            return strcmp($a['area'], $b['area']);
        }
        if ($a['order'] !== $b['order']) {
            return $a['order'] <=> $b['order'];
        }
        return strcmp($a['label'], $b['label']);
    });

    return $merged;
}

/* ============================================================
 * AJAX: FELDER LADEN (BACKEND)
 * ============================================================ */

add_action('wp_ajax_bes_get_fields', function () {

    check_ajax_referer('bes_felder_nonce', 'nonce');
    
    // Rate-Limiting (60 Requests pro Minute)
    if (!function_exists('bes_check_rate_limit') || !bes_check_rate_limit('bes_get_fields', 60, 60)) {
        wp_send_json_error(['message' => __('Zu viele Anfragen. Bitte warten Sie einen Moment.', 'besync')]);
        return;
    }

    $config = bes_load_fields_config();
    $scan   = bes_extract_all_fields();

    // Debug-Informationen
    $debug_info = [
        'config_exists' => !empty($config),
        'config_count' => is_array($config) ? count($config) : 0,
        'scan_fields_count' => isset($scan['fields']) && is_array($scan['fields']) ? count($scan['fields']) : 0,
        'bes_data_dir' => BES_DATA,
        'bes_data_v2_dir' => function_exists('bes_get_data_dir') ? bes_get_data_dir('v2') : BES_DATA . 'v2/',
        'members_file_v2_exists' => file_exists((function_exists('bes_get_data_dir') ? bes_get_data_dir('v2') : BES_DATA . 'v2/') . 'members_consent_v2.json'),
        'config_file_exists' => file_exists(BES_DATA . 'fields-config.json'),
        'json_source' => 'v2',
    ];

    if (empty($scan['fields'])) {
        // Prüfe ob V2-Datei existiert
        $members_file = BES_DATA . 'members_consent_v2.json';
        $old_members_file = trailingslashit(WP_CONTENT_DIR . '/uploads/easy2transfer-sync/') . 'members_consent.json';
        
        $error_msg = __('Keine Felder gefunden.', 'besync');
        if (!file_exists($members_file) && !file_exists($old_members_file)) {
            $file_name = 'members_consent_v2.json';
            $error_msg .= ' ' . sprintf(__('Datei %s nicht gefunden. Bitte einen Sync durchführen.', 'besync'), $file_name);
        } elseif (file_exists($members_file) && filesize($members_file) == 0) {
            $file_name = 'members_consent_v2.json';
            $error_msg .= ' ' . sprintf(__('Datei %s ist leer. Bitte einen Sync durchführen.', 'besync'), $file_name);
        } else {
            $file_name = 'members_consent_v2.json';
            $error_msg .= ' ' . sprintf(__('Prüfe %s auf gültige Daten.', 'besync'), $file_name);
        }
        
        wp_send_json_error([
            'message' => $error_msg,
            'debug' => $debug_info
        ]);
    }

    $merged = bes_merge_fields($scan['fields'], $config);
    
    // Auto-generiere Labels für Felder ohne Label
    $merged = bes_auto_generate_labels($merged);

    wp_send_json_success([
        'fields' => array_values($merged),
    ]);
});

/* ============================================================
 * AJAX: FELDER SPEICHERN (BACKEND)
 * ============================================================ */

add_action('wp_ajax_bes_save_fields', function () {

    check_ajax_referer('bes_felder_nonce', 'nonce');
    
    // Rate-Limiting (30 Requests pro Minute - Speicher-Operationen sind teurer)
    if (!function_exists('bes_check_rate_limit') || !bes_check_rate_limit('bes_save_fields', 30, 60)) {
        wp_send_json_error(['message' => __('Zu viele Anfragen. Bitte warten Sie einen Moment.', 'besync')]);
        return;
    }

    if (!isset($_POST['fields'])) {
        wp_send_json_error(['message' => __('Keine Felder übermittelt.', 'besync')]);
    }

    // ✅ Input-Sanitization: wp_unslash() direkt nach $_POST-Zugriff
    $raw = isset($_POST['fields']) ? wp_unslash($_POST['fields']) : null;

    // Unterstützung für JSON-String oder Array
    if (is_string($raw)) {
        $decoded    = json_decode(stripslashes($raw), true);
        $fields_raw = is_array($decoded) ? $decoded : [];
    } else {
        $fields_raw = is_array($raw) ? $raw : [];
    }

    if (empty($fields_raw)) {
        wp_send_json_error(['message' => __('Leere Feldliste übermittelt.', 'besync')]);
    }

    $new_config = [];

    foreach ($fields_raw as $item) {
        if (!is_array($item) || !isset($item['id'])) {
            continue;
        }

        $id = bes_norm_id($item['id']);

        $new_config[$id] = [
            'id'               => $id,
            'label'            => isset($item['label']) && $item['label'] !== ''
                                    ? sanitize_text_field($item['label'])
                                    : $id,
            'show'             => !empty($item['show']),
            'order'            => isset($item['order']) ? intval($item['order']) : 999,
            'area'             => isset($item['area']) ? sanitize_text_field($item['area']) : 'unused',
            'show_label'       => isset($item['show_label']) ? (bool)$item['show_label'] : true,
            'filterable'       => !empty($item['filterable']),
            'show_in_filterbar' => !empty($item['show_in_filterbar']),
            'filter_priority'  => isset($item['filter_priority']) && $item['filter_priority'] !== null ? intval($item['filter_priority']) : null,
            'inline_group'     => isset($item['inline_group']) ? sanitize_text_field($item['inline_group']) : '',
            'favorite'         => !empty($item['favorite']),
            'ignored'          => !empty($item['ignored']),
        ];
        
        // filter_priority nur speichern, wenn show_in_filterbar aktiv ist
        if (empty($item['show_in_filterbar'])) {
            unset($new_config[$id]['filter_priority']);
        }
    }

    bes_save_json('fields-config.json', $new_config);

    wp_send_json_success(['message' => __('Felder gespeichert.', 'besync')]);
});

/* ============================================================
 * AJAX: CONFIG ALS TEMPLATE EXPORTIEREN
 * ============================================================ */

add_action('wp_ajax_bes_export_config_template', function () {
    
    check_ajax_referer('bes_felder_nonce', 'nonce');
    
    // Rate-Limiting (10 Requests pro Minute - Export ist teuer)
    if (!function_exists('bes_check_rate_limit') || !bes_check_rate_limit('bes_export_config_template', 10, 60)) {
        wp_send_json_error(['message' => __('Zu viele Anfragen. Bitte warten Sie einen Moment.', 'besync')]);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Keine Berechtigung.', 'besync')]);
    }
    
    if (!function_exists('bes_export_config_as_template')) {
        wp_send_json_error(['message' => __('Template-Funktion nicht verfügbar.', 'besync')]);
    }
    
    // Prüfe ob force-Parameter gesetzt ist (Bestätigung wurde gegeben)
    // ✅ Input-Sanitization: wp_unslash() direkt nach $_POST-Zugriff
    $force = isset($_POST['force']) ? wp_unslash($_POST['force']) === '1' : false;
    
    $result = bes_export_config_as_template($force);
    
    // Wenn Warnung zurückgegeben wurde
    if (is_array($result) && isset($result['warning']) && $result['warning'] === true) {
        wp_send_json_error([
            'message' => $result['message'],
            'warning' => true,
            'template_exists' => true
        ]);
        return;
    }
    
    // Erfolg oder Fehler
    if ($result === true) {
        wp_send_json_success([
            'message' => __('Konfiguration wurde als Template gespeichert.', 'besync'),
            'path' => BES_DIR . 'admin/templates/fields-config-default.json'
        ]);
    } else {
        wp_send_json_error(['message' => __('Fehler beim Exportieren der Template-Konfiguration.', 'besync')]);
    }
});

/**
 * AJAX-Handler: Field Intelligence Dashboard
 * ============================================================ */

add_action('wp_ajax_bes_get_field_intelligence', function () {
    
    check_ajax_referer('bes_felder_nonce', 'nonce');
    
    // Rate-Limiting (20 Requests pro Minute - Analyse ist teuer)
    if (!function_exists('bes_check_rate_limit') || !bes_check_rate_limit('bes_get_field_intelligence', 20, 60)) {
        wp_send_json_error(['message' => __('Zu viele Anfragen. Bitte warten Sie einen Moment.', 'besync')]);
        return;
    }
    
    if (!function_exists('bes_analyze_field_intelligence')) {
        wp_send_json_error(['message' => __('Field Intelligence Funktion nicht verfügbar.', 'besync')]);
        return;
    }
    
    $intelligence = bes_analyze_field_intelligence();
    
    if (isset($intelligence['error'])) {
        wp_send_json_error(['message' => $intelligence['error']]);
        return;
    }
    
    wp_send_json_success($intelligence);
});

/**
 * AJAX-Handler: Template-Konfiguration importieren
 * ============================================================ */

add_action('wp_ajax_bes_import_template', function () {
    
    check_ajax_referer('bes_felder_nonce', 'nonce');
    
    // Rate-Limiting (10 Requests pro Minute - Import ist teuer)
    if (!function_exists('bes_check_rate_limit') || !bes_check_rate_limit('bes_import_template', 10, 60)) {
        wp_send_json_error(['message' => __('Zu viele Anfragen. Bitte warten Sie einen Moment.', 'besync')]);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Keine Berechtigung.', 'besync')]);
    }
    
    // Prüfe ob bereits eine Config existiert
    $existing_config = bes_load_json('fields-config.json');
    if ($existing_config && !empty($existing_config)) {
        wp_send_json_error([
            'message' => __('Eine Config existiert bereits. Bitte zuerst löschen oder überschreiben.', 'besync')
        ]);
    }
    
    // Importiere Template
    if (!function_exists('bes_init_fields_config_from_template')) {
        wp_send_json_error([
            'message' => __('Template-Funktion nicht verfügbar.', 'besync')
        ]);
    }
    
    $result = bes_init_fields_config_from_template();
    
    if ($result) {
        wp_send_json_success([
            'message' => __('Template erfolgreich importiert.', 'besync')
        ]);
    } else {
        wp_send_json_error([
            'message' => __('Template konnte nicht importiert werden.', 'besync')
        ]);
    }
});

/* ============================================================
 * AJAX: DESIGN-EINSTELLUNGEN SPEICHERN
 * ============================================================ */

add_action('wp_ajax_bes_save_design', function () {
    check_ajax_referer('bes_felder_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Keine Berechtigung', 'besync')]);
        return;
    }
    
    // Rate-Limiting
    if (!function_exists('bes_check_rate_limit') || !bes_check_rate_limit('bes_save_design', 30, 60)) {
        wp_send_json_error(['message' => __('Zu viele Anfragen. Bitte warten Sie einen Moment.', 'besync')]);
        return;
    }
    
    if (!function_exists('bes_save_design_settings')) {
        wp_send_json_error(['message' => __('Design-Funktionen nicht verfügbar.', 'besync')]);
        return;
    }
    
    $settings = [
        'card_bg' => isset($_POST['card_bg']) ? sanitize_text_field($_POST['card_bg']) : '',
        'card_border' => isset($_POST['card_border']) ? sanitize_text_field($_POST['card_border']) : '',
        'card_text' => isset($_POST['card_text']) ? sanitize_text_field($_POST['card_text']) : '',
        'card_link' => isset($_POST['card_link']) ? sanitize_text_field($_POST['card_link']) : '',
        'card_stripe' => isset($_POST['card_stripe']) ? sanitize_text_field($_POST['card_stripe']) : '',
        'image_shadow' => isset($_POST['image_shadow']) ? (bool)$_POST['image_shadow'] : false,
        'button_bg' => isset($_POST['button_bg']) ? sanitize_text_field($_POST['button_bg']) : '',
        'button_bg_hover' => isset($_POST['button_bg_hover']) ? sanitize_text_field($_POST['button_bg_hover']) : '',
        'button_text' => isset($_POST['button_text']) ? sanitize_text_field($_POST['button_text']) : '',
    ];
    
    $result = bes_save_design_settings($settings);
    
    if ($result) {
        wp_send_json_success([
            'message' => __('Design-Einstellungen erfolgreich gespeichert.', 'besync')
        ]);
    } else {
        wp_send_json_error([
            'message' => __('Design-Einstellungen konnten nicht gespeichert werden.', 'besync')
        ]);
    }
});

/* ============================================================
 * AJAX: DESIGN AUF STANDARD ZURÜCKSETZEN
 * ============================================================ */

add_action('wp_ajax_bes_reset_design', function () {
    check_ajax_referer('bes_felder_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Keine Berechtigung', 'besync')]);
        return;
    }
    
    // Rate-Limiting
    if (!function_exists('bes_check_rate_limit') || !bes_check_rate_limit('bes_reset_design', 10, 60)) {
        wp_send_json_error(['message' => __('Zu viele Anfragen. Bitte warten Sie einen Moment.', 'besync')]);
        return;
    }
    
    if (!function_exists('bes_get_default_design_settings') || !function_exists('bes_save_design_settings')) {
        wp_send_json_error(['message' => __('Design-Funktionen nicht verfügbar.', 'besync')]);
        return;
    }
    
    $defaults = bes_get_default_design_settings();
    $result = bes_save_design_settings($defaults);
    
    if ($result) {
        wp_send_json_success([
            'message' => __('Design auf Standard zurückgesetzt.', 'besync'),
            'settings' => $defaults
        ]);
    } else {
        wp_send_json_error([
            'message' => __('Design konnte nicht zurückgesetzt werden.', 'besync')
        ]);
    }
});
