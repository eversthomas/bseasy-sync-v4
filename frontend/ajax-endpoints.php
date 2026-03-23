<?php
if (!defined('ABSPATH')) exit;

/**
 * Frontend AJAX-Endpunkte für öffentliche Nutzung
 * Beispiel: Suche, Filter, Pagination
 * 
 * @package BSEasySync
 */

/**
 * Frontend AJAX: Mitglieder filtern (öffentlich zugänglich)
 * 
 * Endpunkt: wp_ajax_nopriv_bes_filter_members
 * 
 * Parameter:
 * - search: Suchbegriff (optional)
 * - filters: JSON-String mit Filter-Objekten (optional)
 * - page: Seitenzahl für Pagination (optional, Standard: 1)
 * - per_page: Ergebnisse pro Seite (optional, Standard: 25, Max: 100)
 * 
 * @return JSON-Response mit gefilterten Mitgliedern
 */
add_action('wp_ajax_bes_filter_members', 'bes_ajax_filter_members');
add_action('wp_ajax_nopriv_bes_filter_members', 'bes_ajax_filter_members');

function bes_ajax_filter_members() {
    // Rate-Limiting: 120 Requests pro Minute für öffentliche Endpunkte
    if (!function_exists('bes_check_rate_limit') || !bes_check_rate_limit('bes_filter_members', 120, 60)) {
        wp_send_json_error([
            'error' => esc_html__('Zu viele Anfragen. Bitte warten Sie einen Moment.', 'besync')
        ]);
        return;
    }
    
    // ✅ Nonce-Prüfung (optional für Gäste, Pflicht für eingeloggte Nutzer)
    $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field($_REQUEST['nonce']) : '';
    
    // Wenn eingeloggt: Nonce prüfen
    if (is_user_logged_in()) {
        if (empty($nonce) || !wp_verify_nonce($nonce, 'bes_filter_members_nonce')) {
            wp_send_json_error([
                'error' => esc_html__('Sicherheitsprüfung fehlgeschlagen.', 'besync')
            ]);
            return;
        }
    }
    // Für Gäste: Nonce optional (Rate-Limiting schützt bereits)
    
    // Input-Validierung
    $search = isset($_REQUEST['search']) ? sanitize_text_field($_REQUEST['search']) : '';
    $page = isset($_REQUEST['page']) ? max(1, intval($_REQUEST['page'])) : 1;
    $per_page = isset($_REQUEST['per_page']) ? min(100, max(1, intval($_REQUEST['per_page']))) : 25;
    
    // Filter-Parameter validieren und normalisieren
    $filters = [];
    if (isset($_REQUEST['filters']) && is_string($_REQUEST['filters'])) {
        $filters_raw = json_decode(stripslashes($_REQUEST['filters']), true);
        if (is_array($filters_raw)) {
            // Prüfe ob Array-Format [{"field":"id","value":"x"}] oder Object-Format {"id":"x"}
            $is_array_format = !empty($filters_raw) && isset($filters_raw[0]) && is_array($filters_raw[0]) && isset($filters_raw[0]['field']);
            
            if ($is_array_format) {
                // Format B: Array von Objekten [{"field":"fieldId","value":"x"}, ...]
                foreach ($filters_raw as $item) {
                    if (is_array($item) && isset($item['field']) && isset($item['value'])) {
                        $field_id = $item['field'];
                        $value = $item['value'];
                        // Validiere Field-ID (alphanumerisch, Punkt, Unterstrich, Bindestrich)
                        if (preg_match('/^[a-z0-9._-]+$/i', $field_id)) {
                            $filters[sanitize_text_field($field_id)] = sanitize_text_field($value);
                        }
                    }
                }
            } else {
                // Format A: Object {"fieldId":"value", ...}
                foreach ($filters_raw as $field_id => $value) {
                    // Validiere Field-ID (alphanumerisch, Punkt, Unterstrich, Bindestrich)
                    if (preg_match('/^[a-z0-9._-]+$/i', $field_id)) {
                        $filters[sanitize_text_field($field_id)] = sanitize_text_field($value);
                    }
                }
            }
        }
    }
    
    // Whitelist: Erlaubte Field-IDs aus fields-config.json (show_in_filterbar=true)
    $allowed_field_ids = [];
    $config_file = BES_DATA . 'fields-config.json';
    if (file_exists($config_file)) {
        // ✅ Sichere File-Operation mit Pfad-Validierung
        if (function_exists('bes_safe_file_get_contents')) {
            $config_raw = bes_safe_file_get_contents($config_file, BES_DATA);
        } else {
            // Fallback für alte Versionen
            $config_raw = @file_get_contents($config_file);
        }
        if ($config_raw !== false) {
            $config_data = json_decode($config_raw, true);
            if (is_array($config_data)) {
                foreach ($config_data as $field) {
                    $field_id = $field['id'] ?? '';
                    if ($field_id && !empty($field['show_in_filterbar'])) {
                        $allowed_field_ids[] = $field_id;
                    }
                }
            }
        }
    }
    
    // Filter auf Whitelist beschränken (ignoriere nicht erlaubte Felder)
    $filtered_filters = [];
    $config_warning_logged = false; // Logge nur einmal pro Request
    foreach ($filters as $field_id => $value) {
        if (empty($allowed_field_ids)) {
            // Fallback: Wenn Config fehlt, logge Warnung aber erlaube alle Filter (nur einmal)
            if (!$config_warning_logged && function_exists('bes_debug_log')) {
                // Prüfe ob Debug aktiv ist (falls verfügbar)
                $should_log = true;
                if (defined('BES_DEBUG_MODE') && !BES_DEBUG_MODE) {
                    $should_log = false;
                }
                if ($should_log) {
                    bes_debug_log('fields-config.json fehlt oder ist ungültig - Filter-Whitelist kann nicht erstellt werden', 'WARN', 'ajax-filter');
                    $config_warning_logged = true;
                }
            }
            $filtered_filters[$field_id] = $value;
        } elseif (in_array($field_id, $allowed_field_ids, true)) {
            $filtered_filters[$field_id] = $value;
        }
        // Nicht erlaubte Felder werden ignoriert (silent)
    }
    $filters = $filtered_filters;
    
    // Lade V3-Konstanten falls nötig
    if (!defined('BES_DATA_V3')) {
        $constants_file = plugin_dir_path(__DIR__) . 'includes/constants-v3.php';
        if (file_exists($constants_file)) {
            require_once $constants_file;
        }
    }
    
    // V3 ist die einzige Quelle
    $json_source = get_option('bes_json_source', 'v3');
    // Stelle sicher, dass V3 gesetzt ist
    if ($json_source !== 'v3') {
        update_option('bes_json_source', 'v3');
        $json_source = 'v3';
    }
    
    $members_data = null;
    $members = null;
    
    // V3-Datei laden
    if (defined('BES_DATA_V3') && defined('BES_V3_MEMBERS_FILE')) {
        $v3_file = BES_DATA_V3 . BES_V3_MEMBERS_FILE;
        if (file_exists($v3_file) && filesize($v3_file) > 0) {
            // Lade V3-Helpers falls nötig
            if (!function_exists('bseasy_v3_read_json')) {
                $helpers_file = plugin_dir_path(__DIR__) . 'sync/v3-helpers.php';
                if (file_exists($helpers_file)) {
                    require_once $helpers_file;
                }
            }
            
            if (function_exists('bseasy_v3_read_json')) {
                $members_data = bseasy_v3_read_json($v3_file);
                // V3-Format: { "_meta": {...}, "data": [...] }
                if (isset($members_data['data']) && is_array($members_data['data'])) {
                    $members = $members_data['data'];
                }
            }
        }
    }
    
    // Kein Fallback zu V2 mehr - V3 ist die einzige Quelle
    if (!$members) {
        wp_send_json_error([
            'error' => esc_html__('V3-Datei fehlt oder ist leer. Bitte führe einen V3 Sync durch.', 'besync')
        ]);
        return;
    }
    
    // Helper: Feldwert abrufen (V2 vs V3)
    $get_field_value = function($member, $field_key) {
        // V3: Flache Struktur (member.*, cf.*, contact.* direkt im Root)
        if (isset($member[$field_key])) {
            return $member[$field_key];
        }
        
        // V3: Contact-Felder im contact-Objekt (ohne Präfix)
        if (str_starts_with($field_key, 'contact.')) {
            $key = substr($field_key, 8);
            if (isset($member['contact']) && is_array($member['contact'])) {
                // Zuerst ohne Präfix (firstName, familyName, city, etc.)
                if (isset($member['contact'][$key])) {
                    return $member['contact'][$key];
                }
                // Dann mit Präfix (contact.firstName, contact.name, etc.) - für V3 gemischte Struktur
                if (isset($member['contact'][$field_key])) {
                    return $member['contact'][$field_key];
                }
            }
        }
        
        // V2: Verschachtelte Struktur (member.contact.firstName)
        $parts = explode('.', $field_key);
        $value = $member;
        foreach ($parts as $part) {
            if (isset($value[$part])) {
                $value = $value[$part];
            } else {
                return null;
            }
        }
        return $value;
    };
    
    // Filter anwenden (vereinfachte serverseitige Filterung)
    $filtered = [];
    foreach ($members as $member) {
        $matches = true;
        
        // Helper: WordPress-kompatibles Lowercasing
        $to_lower = function_exists('mb_strtolower') ? 'mb_strtolower' : (function_exists('wp_strtolower') ? 'wp_strtolower' : 'strtolower');
        
        // Suchfilter
        if (!empty($search)) {
            $search_lower = call_user_func($to_lower, $search);
            $found = false;
            
            // Suche in Name (V2: contact.firstName, V3: contact.firstName oder member.firstName)
            $firstName = $get_field_value($member, 'contact.firstName') ?? $get_field_value($member, 'member.firstName') ?? '';
            $familyName = $get_field_value($member, 'contact.familyName') ?? $get_field_value($member, 'member.familyName') ?? '';
            $name = call_user_func($to_lower, trim($firstName . ' ' . $familyName));
            if (strpos($name, $search_lower) !== false) {
                $found = true;
            }
            
            // Suche in Stadt
            if (!$found) {
                $city = $get_field_value($member, 'contact.city') ?? '';
                if ($city && strpos(call_user_func($to_lower, $city), $search_lower) !== false) {
                    $found = true;
                }
            }
            
            if (!$found) {
                $matches = false;
            }
        }
        
        // Feld-Filter (konsistent mit JavaScript-Filterlogik)
        if ($matches && !empty($filters)) {
            // Helper: WordPress-kompatibles Lowercasing (einmal pro Member-Loop)
            $to_lower = function_exists('mb_strtolower') ? 'mb_strtolower' : (function_exists('wp_strtolower') ? 'wp_strtolower' : 'strtolower');
            
            foreach ($filters as $field_id => $filter_value) {
                $field_value = $get_field_value($member, $field_id);
                
                // Wenn Feld nicht vorhanden, Mitglied ausschließen
                if ($field_value === null || $field_value === '') {
                    $matches = false;
                    break;
                }
                
                // Normalisiere Werte zu lowercase strings
                $filter_value_lower = call_user_func($to_lower, trim((string)$filter_value));
                
                // Bestimme Filter-Typ basierend auf Field-ID
                $field_id_lower = call_user_func($to_lower, $field_id);
                $is_zip_field = str_contains($field_id_lower, 'zip') || str_contains($field_id_lower, 'plz');
                $is_city_field = str_contains($field_id_lower, 'city') || str_contains($field_id_lower, 'stadt') || str_contains($field_id_lower, 'ort');
                
                // Konvertiere Feldwert zu Array (für Mehrfachwerte)
                $field_values = [];
                if (is_string($field_value) && str_starts_with(trim($field_value), '[')) {
                    // JSON-Array
                    $decoded = json_decode($field_value, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $v) {
                            $v = is_string($v) ? trim($v) : (string)$v;
                            if ($v !== '' && $v !== 'null' && $v !== 'undefined') {
                                $field_values[] = call_user_func($to_lower, $v);
                            }
                        }
                    }
                } elseif (is_string($field_value) && str_contains($field_value, ',')) {
                    // Komma-getrennte Werte
                    $parts = explode(',', $field_value);
                    foreach ($parts as $v) {
                        $v = trim($v);
                        if ($v !== '' && $v !== 'null' && $v !== 'undefined') {
                            $field_values[] = call_user_func($to_lower, $v);
                        }
                    }
                } elseif (is_string($field_value) && str_contains($field_value, '|')) {
                    // Pipe-separierte Werte (wie in data-value Attribut)
                    $parts = explode('|', $field_value);
                    foreach ($parts as $v) {
                        $v = trim(strip_tags($v));
                        if ($v !== '' && $v !== 'null' && $v !== 'undefined') {
                            $field_values[] = call_user_func($to_lower, $v);
                        }
                    }
                } else {
                    // Einzelwert
                    $v = is_string($field_value) ? trim($field_value) : (string)$field_value;
                    if ($v !== '' && $v !== 'null' && $v !== 'undefined') {
                        $field_values[] = call_user_func($to_lower, $v);
                    }
                }
                
                // Wenn keine Werte vorhanden, Mitglied ausschließen
                if (empty($field_values)) {
                    $matches = false;
                    break;
                }
                
                // Matching-Regeln (konsistent mit JavaScript)
                $value_matches = false;
                
                if ($is_zip_field) {
                    // PLZ: startsWith (wie JS Zeile 439)
                    foreach ($field_values as $v) {
                        if (str_starts_with($v, $filter_value_lower)) {
                            $value_matches = true;
                            break;
                        }
                    }
                } elseif ($is_city_field) {
                    // Stadt: contains (wie JS Zeile 450)
                    foreach ($field_values as $v) {
                        if (str_contains($v, $filter_value_lower)) {
                            $value_matches = true;
                            break;
                        }
                    }
                } else {
                    // Select/Default: exact OR contains (wie JS Zeile 464-470)
                    foreach ($field_values as $v) {
                        // Exakte Übereinstimmung
                        if ($v === $filter_value_lower) {
                            $value_matches = true;
                            break;
                        }
                        // Teilübereinstimmung (für Komma-getrennte Werte)
                        if (str_contains($v, $filter_value_lower) || str_contains($filter_value_lower, $v)) {
                            $value_matches = true;
                            break;
                        }
                    }
                }
                
                if (!$value_matches) {
                    $matches = false;
                    break;
                }
            }
        }
        
        if ($matches) {
            $filtered[] = $member;
        }
    }
    
    // Pagination
    $total = count($filtered);
    $total_pages = ceil($total / $per_page);
    $offset = ($page - 1) * $per_page;
    $paginated = array_slice($filtered, $offset, $per_page);
    
    // Reduziere Daten für Response (nur relevante Felder)
    $response_members = [];
    foreach ($paginated as $member) {
        $firstName = $get_field_value($member, 'contact.firstName') ?? $get_field_value($member, 'member.firstName') ?? '';
        $familyName = $get_field_value($member, 'contact.familyName') ?? $get_field_value($member, 'member.familyName') ?? '';
        $city = $get_field_value($member, 'contact.city') ?? '';
        $zip = $get_field_value($member, 'contact.zip') ?? '';
        
        $response_members[] = [
            'id' => intval($get_field_value($member, 'member.id') ?? $member['id'] ?? 0),
            'name' => esc_html(trim($firstName . ' ' . $familyName)),
            'city' => esc_html($city),
            'zip' => esc_html($zip),
        ];
    }
    
    wp_send_json_success([
        'members' => $response_members,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => $total_pages,
        ],
        'filters_applied' => [
            'search' => $search,
            'filters' => $filters,
        ],
    ]);
}