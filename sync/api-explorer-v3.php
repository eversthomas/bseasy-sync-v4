<?php
/**
 * BSEasy Sync V3 - API Explorer
 * 
 * Katalogisiert alle verfügbaren Felder aus der EasyVerein API
 * Erstellt field_catalog_v3.json mit Feldliste, Metadaten und Beispielwerten (PII-maskiert)
 * 
 * @package BSEasySync
 * @subpackage V3
 * @author Tom Evers <https://bezugssysteme.de>
 * @since 3.0.0
 */

if (!defined('ABSPATH')) exit;

// Lade Basis-Konstanten
if (!defined('BES_DATA')) {
    $main_file = plugin_dir_path(__DIR__) . 'bseasy-sync.php';
    if (file_exists($main_file)) {
        require_once $main_file;
    }
}

// Lade V3-Helpers
require_once BES_DIR . 'sync/v3-helpers.php';

// Lade generische API-Funktionen ZUERST
require_once BES_DIR . 'sync/api-core-consent-requests.php';
// api-core-consent.php wird bereits von api-core-consent-requests.php geladen

// ============================================================
// API EXPLORER - HAUPTFUNKTION
// ============================================================

/**
 * Prüft, ob der Explorer anhand der Status-Datei abgebrochen werden soll.
 *
 * @return bool
 */
function bseasy_v3_explorer_is_cancelled(): bool {
    if (!defined('BES_DATA_V3')) {
        return false;
    }
    
    $status_file = BES_DATA_V3 . BES_V3_STATUS_FILE;
    if (!file_exists($status_file)) {
        return false;
    }
    
    $status_data = bseasy_v3_read_json($status_file);
    
    return isset($status_data['state']) && $status_data['state'] === 'cancelled';
}

/**
 * Führt API Explorer aus und erstellt Feldkatalog
 * 
 * @param int $sample_size Anzahl der Mitglieder für Statistik (50-200)
 * @param bool $fresh_from_api Ob frische Daten von API geholt werden sollen
 * @return array Ergebnis mit success, message, catalog_path, stats
 */
function bseasy_v3_run_explorer(int $sample_size = 100, bool $fresh_from_api = true): array {
    try {
        // Validierung
        if ($sample_size < BES_V3_EXPLORER_SAMPLE_MIN || $sample_size > BES_V3_EXPLORER_SAMPLE_MAX) {
            $sample_size = BES_V3_EXPLORER_SAMPLE_DEFAULT;
        }
        
        // Stelle sicher, dass Verzeichnisse existieren
        bseasy_v3_setup_directories();
        
        // Token prüfen
        $encrypted_token = get_option('bes_api_token', '');
        if (empty($encrypted_token)) {
            return [
                'success' => false,
                'error' => 'Kein API-Token konfiguriert'
            ];
        }
        
        $token = function_exists('bes_decrypt_token') ? bes_decrypt_token($encrypted_token) : $encrypted_token;
        if (empty($token)) {
            return [
                'success' => false,
                'error' => 'Token konnte nicht entschlüsselt werden'
            ];
        }
        
        bseasy_v3_log("API Explorer gestartet - Sample: $sample_size, Fresh: " . ($fresh_from_api ? 'Ja' : 'Nein'), 'INFO');
        
        // Wurde Explorer bereits als abgebrochen markiert?
        if (bseasy_v3_explorer_is_cancelled()) {
            bseasy_v3_log("API Explorer wurde vor Start abgebrochen.", 'INFO');
            return [
                'success' => false,
                'error'   => 'API Explorer wurde gestoppt',
            ];
        }
        
        $baseUsed = null;
        $catalog = [
            'generated_at' => date('c'),
            'sample_size' => $sample_size,
            'fresh_from_api' => $fresh_from_api,
            'fields' => [],
            'stats' => [
                'total_fields' => 0,
                'member_fields' => 0,
                'member_custom_fields' => 0,
                'contact_fields' => 0,
                'contact_custom_fields' => 0,
            ]
        ];
        
        // ============================================================
        // PHASE 1: Member-Felder sammeln
        // ============================================================
        bseasy_v3_log("Phase 1: Sammle Member-Felder...", 'INFO');
        bseasy_v3_update_status(10, 100, "Phase 1: Lade Sample-Mitglieder...", 'running');
        
        // Hole Sample-Mitglieder
        $sample_members = [];
        if ($fresh_from_api) {
            // Hole frische Daten von API
            $sample_members = bseasy_v3_fetch_sample_members($token, $baseUsed, $sample_size);
        } else {
            // Nutze letztes Dump falls vorhanden
            $v2_file = bes_get_data_dir('v2') . 'members_consent_v2.json';
            if (file_exists($v2_file)) {
                $v2_data = bseasy_v3_read_json($v2_file);
                if ($v2_data && isset($v2_data['data']) && is_array($v2_data['data'])) {
                    $sample_members = array_slice($v2_data['data'], 0, $sample_size);
                }
            }
        }
        
        // Prüfe nach dem Laden des Samples auf Abbruch
        if (bseasy_v3_explorer_is_cancelled()) {
            bseasy_v3_log("API Explorer abgebrochen nach Laden des Member-Samples.", 'INFO');
            return [
                'success' => false,
                'error'   => 'API Explorer wurde gestoppt',
            ];
        }
        
        if (empty($sample_members)) {
            return [
                'success' => false,
                'error' => 'Konnte keine Mitglieder-Daten abrufen'
            ];
        }
        
        bseasy_v3_update_status(20, 100, "Phase 1: Analysiere Member-Felder...", 'running');
        
        // Analysiere Member-Felder
        $member_fields = bseasy_v3_analyze_member_fields($sample_members);
        $catalog['fields']['member'] = $member_fields;
        $catalog['stats']['member_fields'] = count($member_fields);
        $catalog['stats']['total_fields'] += count($member_fields);
        
        bseasy_v3_update_status(40, 100, "Phase 1: Analysiere Member Custom Fields...", 'running');
        
        // Prüfe auf Abbruch vor den Member Custom Fields
        if (bseasy_v3_explorer_is_cancelled()) {
            bseasy_v3_log("API Explorer abgebrochen vor Analyse der Member Custom Fields.", 'INFO');
            return [
                'success' => false,
                'error'   => 'API Explorer wurde gestoppt',
            ];
        }
        
        // Analysiere Member Custom Fields
        $member_cf = bseasy_v3_analyze_custom_fields($sample_members, 'member', $token, $baseUsed);
        $catalog['fields']['member_cf'] = $member_cf;
        $catalog['stats']['member_custom_fields'] = count($member_cf);
        $catalog['stats']['total_fields'] += count($member_cf);
        
        // ============================================================
        // PHASE 2: Contact-Felder sammeln (falls vorhanden)
        // ============================================================
        bseasy_v3_log("Phase 2: Sammle Contact-Felder...", 'INFO');
        bseasy_v3_update_status(60, 100, "Phase 2: Analysiere Contact-Felder...", 'running');
        
        // Prüfe auf Abbruch vor den Contact-Feldern
        if (bseasy_v3_explorer_is_cancelled()) {
            bseasy_v3_log("API Explorer abgebrochen vor Analyse der Contact-Felder.", 'INFO');
            return [
                'success' => false,
                'error'   => 'API Explorer wurde gestoppt',
            ];
        }
        
        $contact_fields = bseasy_v3_analyze_contact_fields($sample_members);
        if (!empty($contact_fields)) {
            $catalog['fields']['contact'] = $contact_fields;
            $catalog['stats']['contact_fields'] = count($contact_fields);
            $catalog['stats']['total_fields'] += count($contact_fields);
        }
        
        bseasy_v3_update_status(80, 100, "Phase 2: Analysiere Contact Custom Fields...", 'running');
        
        // Prüfe auf Abbruch vor den Contact Custom Fields
        if (bseasy_v3_explorer_is_cancelled()) {
            bseasy_v3_log("API Explorer abgebrochen vor Analyse der Contact Custom Fields.", 'INFO');
            return [
                'success' => false,
                'error'   => 'API Explorer wurde gestoppt',
            ];
        }
        
        // Analysiere Contact Custom Fields
        $contact_cf = bseasy_v3_analyze_custom_fields($sample_members, 'contact', $token, $baseUsed);
        if (!empty($contact_cf)) {
            $catalog['fields']['contact_cf'] = $contact_cf;
            $catalog['stats']['contact_custom_fields'] = count($contact_cf);
            $catalog['stats']['total_fields'] += count($contact_cf);
        }
        
        // ============================================================
        // PHASE 3: Speichere Katalog
        // ============================================================
        bseasy_v3_log("Phase 3: Speichere Feldkatalog...", 'INFO');
        bseasy_v3_update_status(90, 100, "Phase 3: Speichere Feldkatalog...", 'running');
        
        $catalog_file = BES_DATA_V3 . BES_V3_FIELD_CATALOG;
        $json = json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        if (!bseasy_v3_safe_write_json($catalog_file, $json)) {
            return [
                'success' => false,
                'error' => 'Konnte Feldkatalog nicht speichern'
            ];
        }
        
        // Speichere Explorer-Metadaten
        update_option(BES_V3_OPTION_PREFIX . 'explorer_last_run', time());
        update_option(BES_V3_OPTION_PREFIX . 'explorer_field_count', $catalog['stats']['total_fields']);
        
        bseasy_v3_log("API Explorer erfolgreich abgeschlossen - {$catalog['stats']['total_fields']} Felder gefunden", 'INFO');
        
        return [
            'success' => true,
            'message' => sprintf(
                'Feldkatalog erstellt: %d Felder gefunden (%d Member, %d Member CF, %d Contact, %d Contact CF)',
                $catalog['stats']['total_fields'],
                $catalog['stats']['member_fields'],
                $catalog['stats']['member_custom_fields'],
                $catalog['stats']['contact_fields'],
                $catalog['stats']['contact_custom_fields']
            ),
            'catalog_path' => $catalog_file,
            'stats' => $catalog['stats']
        ];
        
    } catch (Throwable $e) {
        $error_msg = $e->getMessage();
        bseasy_v3_log("API Explorer Fehler: $error_msg", 'ERROR');
        
        return [
            'success' => false,
            'error' => $error_msg
        ];
    }
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Flacht verschachtelte Datenstrukturen auf und erzeugt Keys mit Pfaden
 * 
 * @param mixed $data Daten (Array, Objekt oder Scalar)
 * @param string $prefix Präfix für Keys (z.B. "member" oder "contact")
 * @param int $maxDepth Maximale Verschachtelungstiefe (default: 2)
 * @param int $currentDepth Aktuelle Tiefe (intern)
 * @return array Array von ['key' => 'member.addresses[].city', 'value' => '...', 'has_value' => true]
 */
function bseasy_v3_flatten_keys_with_values($data, string $prefix = '', int $maxDepth = 2, int $currentDepth = 0): array {
    $result = [];
    
    // Tiefenlimit erreicht
    if ($currentDepth >= $maxDepth) {
        return $result;
    }
    
    // Nicht-Array/Objekt: direkt zurückgeben
    if (!is_array($data)) {
        $has_value = $data !== null && $data !== '';
        return [[
            'key' => $prefix,
            'value' => $data,
            'has_value' => $has_value
        ]];
    }
    
    // Leeres Array
    if (empty($data)) {
        return [[
            'key' => $prefix . '[]',
            'value' => [],
            'has_value' => false
        ]];
    }
    
    // Prüfe ob Array von Scalars oder Objekten
    $is_numeric_array = array_keys($data) === range(0, count($data) - 1);
    $is_scalar_array = $is_numeric_array && !empty($data) && !is_array($data[0]);
    
    if ($is_scalar_array) {
        // Array von Scalars: "hat mind. 1 nicht-leeres Element"
        $has_value = false;
        foreach ($data as $item) {
            if ($item !== null && $item !== '') {
                $has_value = true;
                break;
            }
        }
        return [[
            'key' => $prefix . '[]',
            'value' => $data,
            'has_value' => $has_value
        ]];
    }
    
    // Array von Objekten: flatten jedes Element mit [] Marker
    if ($is_numeric_array && !empty($data) && is_array($data[0])) {
        // Für jedes Objekt im Array: flatten mit [] Marker
        foreach ($data as $idx => $obj) {
            if (!is_array($obj)) {
                continue;
            }
            foreach ($obj as $obj_key => $obj_value) {
                // Überspringe interne Felder
                if (is_string($obj_key) && strpos($obj_key, '_') === 0) {
                    continue;
                }
                $new_prefix = $prefix === '' ? ($obj_key . '[]') : ($prefix . '.' . $obj_key . '[]');
                $nested = bseasy_v3_flatten_keys_with_values($obj_value, $new_prefix, $maxDepth, $currentDepth + 1);
                $result = array_merge($result, $nested);
            }
        }
        return $result;
    }
    
    // Assoziatives Array: rekursiv flatten
    foreach ($data as $key => $value) {
        // Überspringe interne Felder
        if (is_string($key) && strpos($key, '_') === 0) {
            continue;
        }
        
        // Erstelle neuen Prefix
        $new_prefix = $prefix === '' ? $key : ($prefix . '.' . $key);
        
        // Rekursiver Aufruf
        $nested = bseasy_v3_flatten_keys_with_values($value, $new_prefix, $maxDepth, $currentDepth + 1);
        $result = array_merge($result, $nested);
    }
    
    return $result;
}

/**
 * Holt Sample-Mitglieder von API
 * 
 * @param string $token API Token
 * @param string|null $baseUsed Base URL (by reference)
 * @param int $sample_size Anzahl
 * @return array Array von Member-Daten
 */
function bseasy_v3_fetch_sample_members(string &$token, ?string &$baseUsed = null, int $sample_size = 100): array {
    $members = [];
    
    // Nutze generische API-Funktionen
    if (!function_exists('bes_consent_api_safe_get_try_query')) {
        require_once BES_DIR . 'sync/api-core-consent-requests.php';
    }
    
    // Hole Member-Liste (mit Pagination)
    $page = 1;
    $limit = min(100, $sample_size);
    
    while (count($members) < $sample_size && $page <= 10) {
        [$code, $data, $url] = bes_consent_api_safe_get_try_query('member', [
            'limit' => $limit,
            'page' => $page,
            'ordering' => 'id',
        ], $token, $baseUsed);
        
        if ($code !== 200 || !is_array($data)) {
            break;
        }
        
        $list = bes_consent_norm_list($data);
        foreach ($list as $row) {
            if (count($members) >= $sample_size) {
                break;
            }
            
            $member_id = $row['id'] ?? null;
            if (!$member_id) {
                continue;
            }
            
            // Hole Member-Details
            [$s1, $d1, $u1] = bes_consent_api_safe_get_try_query(
                "member/$member_id",
                ['query' => '{*}'],
                $token,
                $baseUsed
            );
            
            if ($s1 === 200 && is_array($d1)) {
                // Hole Custom Fields
                $cf_meta = bes_consent_api_fetch_all_list_with_meta(
                    "member/$member_id/custom-fields",
                    ['limit' => 100, 'query' => '{*}'],
                    $token,
                    $baseUsed
                );
                
                $d1['_member_cf'] = $cf_meta['items'] ?? [];
                
                // Hole Contact Details falls vorhanden
                $contact_id = null;
                if (isset($d1['contactDetails'])) {
                    if (is_array($d1['contactDetails']) && !empty($d1['contactDetails']['id'])) {
                        $contact_id = (int)$d1['contactDetails']['id'];
                    } elseif (is_string($d1['contactDetails']) && $d1['contactDetails'] !== '') {
                        $contact_id = (int)basename($d1['contactDetails']);
                    }
                }
                
                if ($contact_id) {
                    [$s3, $d3, $u3] = bes_consent_api_safe_get_try_query(
                        "contact-details/$contact_id",
                        ['query' => '{*}'],
                        $token,
                        $baseUsed
                    );
                    
                    if ($s3 === 200 && is_array($d3)) {
                        // Hole Contact Custom Fields
                        $contact_cf_meta = bes_consent_api_fetch_all_list_with_meta(
                            "contact-details/$contact_id/custom-fields",
                            ['limit' => 100, 'query' => '{*}'],
                            $token,
                            $baseUsed
                        );
                        
                        $d3['_contact_cf'] = $contact_cf_meta['items'] ?? [];
                        $d1['_contact'] = $d3;
                    }
                }
                
                $members[] = [
                    'id' => $member_id,
                    'member' => $d1,
                ];
            }
            
            usleep(100000); // Rate-Limiting
        }
        
        $page++;
        usleep(200000);
    }
    
    return $members;
}

/**
 * Analysiert Member-Felder (inkl. verschachtelte Keys bis maxDepth=2)
 * 
 * @param array $sample_members Sample-Mitglieder
 * @param bool $sort_by_fill Ob nach Füllgrad sortiert werden soll (default: false)
 * @return array Feldkatalog
 */
function bseasy_v3_analyze_member_fields(array $sample_members, bool $sort_by_fill = false): array {
    $fields = [];
    $field_stats = [];
    
    foreach ($sample_members as $member_data) {
        $member = $member_data['member'] ?? [];
        
        // Überspringe interne Felder auf Root-Level
        $filtered_member = [];
        foreach ($member as $key => $value) {
            if (strpos($key, '_') !== 0) {
                $filtered_member[$key] = $value;
            }
        }
        
        // Flatten verschachtelte Strukturen
        $flattened = bseasy_v3_flatten_keys_with_values($filtered_member, '', 2, 0);
        
        foreach ($flattened as $item) {
            $key = $item['key'];
            $value = $item['value'];
            $has_value = $item['has_value'];
            
            if (!isset($field_stats[$key])) {
                $field_stats[$key] = [
                    'key' => $key,
                    'filled_count' => 0,
                    'example_values' => [],
                    'types' => [],
                ];
            }
            
            // Zähle nur wenn Wert vorhanden (nicht null, nicht '')
            if ($has_value) {
                $field_stats[$key]['filled_count']++;
                
                // Sammle Beispielwerte (max 3, PII-maskiert)
                if (count($field_stats[$key]['example_values']) < 3) {
                    $example = is_array($value) ? json_encode($value) : (string)$value;
                    $example = bseasy_v3_mask_pii($example);
                    if (strlen($example) > 100) {
                        $example = substr($example, 0, 100) . '...';
                    }
                    $field_stats[$key]['example_values'][] = $example;
                }
                
                // Sammle Typen
                $type = gettype($value);
                if (!in_array($type, $field_stats[$key]['types'])) {
                    $field_stats[$key]['types'][] = $type;
                }
            }
        }
    }
    
    $total = count($sample_members);
    
    // Konvertiere zu finalem Format
    foreach ($field_stats as $key => $stats) {
        $full_key = "member.$key";
        $fields[] = [
            'key' => $full_key,
            'field_key' => $key,
            'filled_count' => $stats['filled_count'],
            'filled_pct' => round(($stats['filled_count'] / $total) * 100, 1),
            'example_values' => $stats['example_values'],
            'types' => $stats['types'],
        ];
    }
    
    // Optionale Sortierung nach Füllgrad
    if ($sort_by_fill) {
        usort($fields, function($a, $b) {
            return $b['filled_pct'] <=> $a['filled_pct'];
        });
    }
    
    return $fields;
}

/**
 * Analysiert Contact-Felder (inkl. verschachtelte Keys bis maxDepth=2)
 * 
 * @param array $sample_members Sample-Mitglieder
 * @param bool $sort_by_fill Ob nach Füllgrad sortiert werden soll (default: false)
 * @return array Feldkatalog
 */
function bseasy_v3_analyze_contact_fields(array $sample_members, bool $sort_by_fill = false): array {
    $fields = [];
    $field_stats = [];
    
    foreach ($sample_members as $member_data) {
        $contact = $member_data['member']['_contact'] ?? null;
        if (!$contact || !is_array($contact)) {
            continue;
        }
        
        // Überspringe interne Felder auf Root-Level
        $filtered_contact = [];
        foreach ($contact as $key => $value) {
            if (strpos($key, '_') !== 0) {
                $filtered_contact[$key] = $value;
            }
        }
        
        // Flatten verschachtelte Strukturen
        $flattened = bseasy_v3_flatten_keys_with_values($filtered_contact, '', 2, 0);
        
        foreach ($flattened as $item) {
            $key = $item['key'];
            $value = $item['value'];
            $has_value = $item['has_value'];
            
            if (!isset($field_stats[$key])) {
                $field_stats[$key] = [
                    'key' => $key,
                    'filled_count' => 0,
                    'example_values' => [],
                    'types' => [],
                ];
            }
            
            // Zähle nur wenn Wert vorhanden (nicht null, nicht '')
            if ($has_value) {
                $field_stats[$key]['filled_count']++;
                
                // Sammle Beispielwerte (max 3, PII-maskiert)
                if (count($field_stats[$key]['example_values']) < 3) {
                    $example = is_array($value) ? json_encode($value) : (string)$value;
                    $example = bseasy_v3_mask_pii($example);
                    if (strlen($example) > 100) {
                        $example = substr($example, 0, 100) . '...';
                    }
                    $field_stats[$key]['example_values'][] = $example;
                }
                
                // Sammle Typen
                $type = gettype($value);
                if (!in_array($type, $field_stats[$key]['types'])) {
                    $field_stats[$key]['types'][] = $type;
                }
            }
        }
    }
    
    if (empty($field_stats)) {
        return [];
    }
    
    $total = count($sample_members);
    
    // Konvertiere zu finalem Format
    foreach ($field_stats as $key => $stats) {
        $full_key = "contact.$key";
        $fields[] = [
            'key' => $full_key,
            'field_key' => $key,
            'filled_count' => $stats['filled_count'],
            'filled_pct' => round(($stats['filled_count'] / $total) * 100, 1),
            'example_values' => $stats['example_values'],
            'types' => $stats['types'],
        ];
    }
    
    // Optionale Sortierung nach Füllgrad
    if ($sort_by_fill) {
        usort($fields, function($a, $b) {
            return $b['filled_pct'] <=> $a['filled_pct'];
        });
    }
    
    return $fields;
}

/**
 * Analysiert Custom Fields
 * 
 * @param array $sample_members Sample-Mitglieder
 * @param string $type 'member' oder 'contact'
 * @param string $token API Token
 * @param string|null $baseUsed Base URL
 * @param bool $sort_by_fill Ob nach Füllgrad sortiert werden soll (default: false)
 * @return array Feldkatalog
 */
function bseasy_v3_analyze_custom_fields(array $sample_members, string $type, string &$token, ?string &$baseUsed = null, bool $sort_by_fill = false): array {
    $fields = [];
    $cf_stats = [];
    $cf_meta_cache = [];
    
    // Nutze generische API-Funktionen für Custom Field Meta
    if (!function_exists('bes_consent_api_get_custom_field_meta_by_id')) {
        require_once BES_DIR . 'sync/api-core-consent-requests.php';
    }
    
    foreach ($sample_members as $member_data) {
        $cf_key = $type === 'member' ? '_member_cf' : '_contact_cf';
        $cfs = $member_data['member'][$cf_key] ?? [];
        
        if ($type === 'contact' && isset($member_data['member']['_contact'])) {
            $cfs = $member_data['member']['_contact'][$cf_key] ?? [];
        }
        
        foreach ($cfs as $cf) {
            $cf_id = null;
            if (isset($cf['customField'])) {
                if (is_numeric($cf['customField'])) {
                    $cf_id = (int)$cf['customField'];
                } elseif (is_string($cf['customField'])) {
                    $cf_id = (int)basename($cf['customField']);
                }
            }
            
            if (!$cf_id) {
                continue;
            }
            
            // ✅ Korrekte Key-Generierung: contactcf.* für Contact Custom Fields, cf.* für Member Custom Fields
            $cf_key_full = $type === 'contact' ? "contactcf.$cf_id" : "cf.$cf_id";
            
            if (!isset($cf_stats[$cf_key_full])) {
                $cf_stats[$cf_key_full] = [
                    'id' => $cf_id,
                    'key' => $cf_key_full,
                    'filled_count' => 0,
                    'example_values' => [],
                    'types' => [],
                    'meta' => null,
                ];
                
                // Hole Meta (mit Cache)
                if (!isset($cf_meta_cache[$cf_id])) {
                    $cf_meta_cache[$cf_id] = bes_consent_api_get_custom_field_meta_by_id($cf_id, $token, $baseUsed);
                }
                $cf_stats[$cf_key_full]['meta'] = $cf_meta_cache[$cf_id];
            }
            
            // Nutze Helper für effective value (berücksichtigt selectedOptions + Labels)
            // Übergebe CF-ID für CF-spezifische Label-Auflösung
            $value = bseasy_v3_get_cf_effective_value($cf, $cf_id, $token, $baseUsed);
            
            // Prüfe ob Wert vorhanden (nicht null, nicht '', nicht leeres Array)
            $has_value = $value !== null && $value !== '' && !(is_array($value) && empty($value));
            
            if ($has_value) {
                $cf_stats[$cf_key_full]['filled_count']++;
                
                // Sammle Beispielwerte (max 3, PII-maskiert)
                // Arrays werden als JSON-String gespeichert (für Kompatibilität)
                if (count($cf_stats[$cf_key_full]['example_values']) < 3) {
                    if (is_array($value)) {
                        // Array: als JSON-String speichern (Labels sind bereits enthalten)
                        $example = json_encode($value, JSON_UNESCAPED_UNICODE);
                    } else {
                        $example = (string)$value;
                    }
                    $example = bseasy_v3_mask_pii($example);
                    if (strlen($example) > 100) {
                        $example = substr($example, 0, 100) . '...';
                    }
                    $cf_stats[$cf_key_full]['example_values'][] = $example;
                }
                
                // Sammle Typen
                $type_val = gettype($value);
                if (!in_array($type_val, $cf_stats[$cf_key_full]['types'])) {
                    $cf_stats[$cf_key_full]['types'][] = $type_val;
                }
            }
        }
    }
    
    $total = count($sample_members);
    
    // Konvertiere zu finalem Format
    foreach ($cf_stats as $key => $stats) {
        $fields[] = [
            'key' => $stats['key'],
            'field_id' => $stats['id'],
            'filled_count' => $stats['filled_count'],
            'filled_pct' => round(($stats['filled_count'] / $total) * 100, 1),
            'example_values' => $stats['example_values'],
            'types' => $stats['types'],
            'meta' => $stats['meta'],
        ];
    }
    
    // Optionale Sortierung nach Füllgrad
    if ($sort_by_fill) {
        usort($fields, function($a, $b) {
            return $b['filled_pct'] <=> $a['filled_pct'];
        });
    }
    
    return $fields;
}
