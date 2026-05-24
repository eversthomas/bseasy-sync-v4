<?php
/**
 * V3 Sync – Custom-Field-Optionen / Label-Auflösung (aus v3-helpers).
 *
 * @package BSEasySync
 */

if (!defined("ABSPATH")) {
    exit;
}

if (!defined("BES_DATA_V3")) {
    require_once BES_DIR . "includes/constants-v3.php";
}

// ============================================================
// V3 CUSTOM FIELD OPTION LABEL RESOLUTION (CF-spezifisch)
// ============================================================

/**
 * Stellt sicher, dass das V3-Cache-Verzeichnis existiert und index.php enthält.
 *
 * @return bool Erfolg
 */
function bseasy_v3_ensure_cache_dir(): bool {
    if (!defined('BES_DATA_V3') || empty(BES_DATA_V3)) {
        return false;
    }

    $cache_dir = BES_DATA_V3 . 'cache/';
    if (!bes_ensure_writable_directory($cache_dir)) {
        bes_debug_log('V3 Cache-Verzeichnis nicht beschreibbar: ' . $cache_dir, 'ERROR', 'v3-field-options');
        return false;
    }

    $index_file = $cache_dir . 'index.php';
    if (!file_exists($index_file)) {
        bes_safe_file_put_contents($index_file, "<?php\n// Silence is golden.\n");
    }

    return true;
}

/**
 * Extrahiert Option-ID aus URL oder numerischem Wert
 * 
 * @param mixed $url_or_id URL oder ID
 * @return int|null Option-ID oder null
 */
function bseasy_v3_option_id_from_url($url_or_id): ?int {
    if (is_numeric($url_or_id)) {
        return (int)$url_or_id;
    }
    
    if (is_string($url_or_id)) {
        // URL: extrahiere basename
        if (filter_var($url_or_id, FILTER_VALIDATE_URL)) {
            $path = parse_url($url_or_id, PHP_URL_PATH);
            $id = $path ? basename($path) : null;
            return $id !== null && is_numeric($id) ? (int)$id : null;
        }
        // Direkt numerischer String
        if (is_numeric($url_or_id)) {
            return (int)$url_or_id;
        }
    }
    
    if (is_array($url_or_id) && isset($url_or_id['id'])) {
        return is_numeric($url_or_id['id']) ? (int)$url_or_id['id'] : null;
    }
    
    return null;
}

/**
 * Holt Label aus Cache
 * 
 * @param int $option_id Option-ID
 * @return string|null Label oder null
 */
function bseasy_v3_get_cached_option_label(int $option_id): ?string {
    if (!defined('BES_DATA_V3') || empty(BES_DATA_V3)) {
        return null;
    }
    
    $cache_dir = BES_DATA_V3 . 'cache/';
    $cache_file = $cache_dir . "option_{$option_id}.json";
    
    if (!file_exists($cache_file)) {
        return null;
    }
    
    $data = bseasy_v3_read_json($cache_file);
    if ($data === null || !isset($data['label'])) {
        return null;
    }
    
    return $data['label'];
}

/**
 * Speichert Label im Cache
 * 
 * @param int $option_id Option-ID
 * @param string $label Label
 * @return bool Erfolg
 */
function bseasy_v3_set_cached_option_label(int $option_id, string $label): bool {
    if (!defined('BES_DATA_V3') || empty(BES_DATA_V3)) {
        return false;
    }
    
    if (!bseasy_v3_ensure_cache_dir()) {
        return false;
    }
    
    $cache_dir = BES_DATA_V3 . 'cache/';
    $cache_file = $cache_dir . "option_{$option_id}.json";
    
    $data = [
        'id' => $option_id,
        'label' => $label,
        'cached_at' => date('c')
    ];
    
    $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return bseasy_v3_safe_write_json($cache_file, $content);
}

/**
 * Holt Label einer Custom Field Option von der API
 * 
 * @param int $option_id Option-ID
 * @param string &$token API Token
 * @param string|null &$baseUsed Verwendete Base-URL
 * @return string|null Label oder null (bei 404/400/Exception wird null zurückgegeben, kein Abbruch)
 */
function bseasy_v3_fetch_custom_field_option_label(int $option_id, string &$token, ?string &$baseUsed = null): ?string {
    // Lade API-Request-Helper falls nötig
    if (!function_exists('bes_consent_api_safe_get')) {
        require_once BES_DIR . 'sync/api-core-consent-requests.php';
    }
    
    try {
        // API-Request OHNE query (EasyVerein erlaubt keine Queries bei custom-field-option)
        [$status, $data, $url] = bes_consent_api_safe_get(
            "custom-field-option/{$option_id}",
            [],
            $token,
            $baseUsed
        );
        
        // Bei 404/400: kein ERROR, sondern WARN und null zurückgeben (kein Abbruch)
        if ($status === 404 || $status === 400) {
            // Rate-limited Logging: nur alle 10 Optionen loggen, um Log-Explosion zu vermeiden
            static $log_counter = 0;
            $log_counter++;
            if ($log_counter % 10 === 1) {
                bseasy_v3_log("Option {$option_id} nicht gefunden (Status {$status}) - verwende ID als Fallback", 'INFO');
            }
            return null;
        }
        
        // Andere Fehler: WARN (kein ERROR, damit Explorer/Sync nicht abbricht)
        if ($status !== 200 || !is_array($data)) {
            bseasy_v3_log("Fehler beim Laden von Option {$option_id}: Status {$status}", 'WARN');
            return null;
        }
        
        // Priorität: displayName, label, name, value, title
        $label = null;
        if (!empty($data['displayName'])) {
            $label = (string)$data['displayName'];
        } elseif (!empty($data['label'])) {
            $label = (string)$data['label'];
        } elseif (!empty($data['name'])) {
            $label = (string)$data['name'];
        } elseif (!empty($data['value'])) {
            $label = (string)$data['value'];
        } elseif (!empty($data['title'])) {
            $label = (string)$data['title'];
        }
        
        return $label;
    } catch (Exception $e) {
        // Exception abfangen (z.B. "Keine Basis-URL erreichbar") - kein Abbruch
        // Rate-limited Logging
        static $exception_log_counter = 0;
        $exception_log_counter++;
        if ($exception_log_counter % 10 === 1) {
            bseasy_v3_log("Exception beim Laden von Option {$option_id}: " . $e->getMessage() . " - verwende ID als Fallback", 'WARN');
        }
        return null;
    }
}

/**
 * Holt CF-spezifische Select-Options-Map aus Cache oder API
 * 
 * @param int $cf_id Custom Field ID
 * @param string &$token API Token
 * @param string|null &$baseUsed Verwendete Base-URL
 * @return array Map von optionId => label
 */
function bseasy_v3_get_cf_select_options_map(int $cf_id, string &$token, ?string &$baseUsed = null): array {
    if (!defined('BES_DATA_V3') || empty(BES_DATA_V3)) {
        return [];
    }
    
    if (!bseasy_v3_ensure_cache_dir()) {
        return [];
    }
    
    $cache_dir = BES_DATA_V3 . 'cache/';
    $cache_file = $cache_dir . "cf_select_options_{$cf_id}.json";
    
    // 1) Cache lesen
    if (file_exists($cache_file)) {
        $cached = bseasy_v3_read_json($cache_file);
        if (is_array($cached) && isset($cached['options']) && is_array($cached['options'])) {
            return $cached['options'];
        }
    }
    
    // 2) Cache nicht vorhanden: von API laden
    try {
        // Lade API-Request-Helper falls nötig
        if (!function_exists('bes_consent_api_safe_get_try_query')) {
            require_once BES_DIR . 'sync/api-core-consent-requests.php';
        }
        
        // GET custom-field/<cf_id>?query={*} → lies selectOptions (URLs)
        [$status, $cf_data, $url] = bes_consent_api_safe_get_try_query(
            "custom-field/{$cf_id}",
            ['query' => '{*}'],
            $token,
            $baseUsed
        );
        
        if ($status !== 200 || !is_array($cf_data) || !isset($cf_data['selectOptions']) || !is_array($cf_data['selectOptions'])) {
            // CF-Definition nicht gefunden oder keine selectOptions
            bseasy_v3_log("CF {$cf_id}: Keine selectOptions gefunden (Status: {$status})", 'WARN');
            return [];
        }
        
        $options_map = [];
        
        // Für jede selectOption-URL: parse optionId und hole Label
        foreach ($cf_data['selectOptions'] as $option_url) {
            $option_id = bseasy_v3_option_id_from_url($option_url);
            
            if ($option_id === null) {
                continue;
            }
            
            // GET custom-field/<cf_id>/select-options/<optionId>?query={*}
            [$opt_status, $opt_data, $opt_url] = bes_consent_api_safe_get_try_query(
                "custom-field/{$cf_id}/select-options/{$option_id}",
                ['query' => '{*}'],
                $token,
                $baseUsed
            );
            
            if ($opt_status === 200 && is_array($opt_data)) {
                // Priorität: value, displayName, label, name, title
                $label = null;
                if (!empty($opt_data['value'])) {
                    $label = (string)$opt_data['value'];
                } elseif (!empty($opt_data['displayName'])) {
                    $label = (string)$opt_data['displayName'];
                } elseif (!empty($opt_data['label'])) {
                    $label = (string)$opt_data['label'];
                } elseif (!empty($opt_data['name'])) {
                    $label = (string)$opt_data['name'];
                } elseif (!empty($opt_data['title'])) {
                    $label = (string)$opt_data['title'];
                }
                
                if ($label !== null) {
                    $options_map[(string)$option_id] = $label;
                } else {
                    // Fallback: ID als String
                    $options_map[(string)$option_id] = (string)$option_id;
                }
            } else {
                // Option nicht gefunden: Fallback auf ID
                $options_map[(string)$option_id] = (string)$option_id;
            }
        }
        
        // 3) Cache schreiben
        $cache_data = [
            'cf_id' => $cf_id,
            'cached_at' => date('c'),
            'options' => $options_map
        ];
        
        $content = json_encode($cache_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        bseasy_v3_safe_write_json($cache_file, $content);
        
        return $options_map;
        
    } catch (Exception $e) {
        // Fehler beim Laden: kein Abbruch, leere Map zurückgeben
        bseasy_v3_log("Exception beim Laden von CF {$cf_id} Select-Options: " . $e->getMessage(), 'WARN');
        return [];
    }
}

/**
 * Löst selectedOptions (URLs/IDs) zu Labels auf (CF-spezifisch)
 * 
 * @param int $cf_id Custom Field ID
 * @param array $selectedOptions Array von URLs oder IDs
 * @param string &$token API Token
 * @param string|null &$baseUsed Verwendete Base-URL (wird nicht überschrieben durch option URLs)
 * @return array Array von Labels (Fallback: ID als String wenn Label nicht auflösbar)
 */
function bseasy_v3_resolve_selected_options_labels(int $cf_id, array $selectedOptions, string &$token, ?string &$baseUsed = null): array {
    // Hole CF-spezifische Options-Map
    // WICHTIG: baseUsed wird nicht überschrieben, da nur relative Pfade verwendet werden
    $baseUsed_backup = $baseUsed;
    $options_map = bseasy_v3_get_cf_select_options_map($cf_id, $token, $baseUsed);
    // Stelle sicher, dass baseUsed nicht durch option URLs überschrieben wurde
    if ($baseUsed_backup !== null && $baseUsed !== $baseUsed_backup) {
        $baseUsed = $baseUsed_backup;
    }
    
    $labels = [];
    
    foreach ($selectedOptions as $option) {
        $option_id = bseasy_v3_option_id_from_url($option);
        
        if ($option_id === null) {
            // Kann nicht zu ID konvertiert werden → als String zurückgeben
            $labels[] = is_string($option) ? $option : (string)$option;
            continue;
        }
        
        // Suche Label in Map (sowohl string als auch int keys unterstützen)
        $option_id_str = (string)$option_id;
        if (isset($options_map[$option_id_str])) {
            $labels[] = $options_map[$option_id_str];
        } elseif (isset($options_map[$option_id])) {
            // Fallback: int key
            $labels[] = $options_map[$option_id];
        } else {
            // Fallback: ID als String
            $labels[] = $option_id_str;
        }
    }
    
    return $labels;
}

/**
 * Löst selectedOptions (URLs/IDs) zu Labels auf (Veraltet: nutze bseasy_v3_resolve_selected_options_labels)
 * 
 * @deprecated Verwende bseasy_v3_resolve_selected_options_labels() mit CF-ID
 * @param array $selectedOptions Array von URLs oder IDs
 * @param string &$token API Token
 * @param string|null &$baseUsed Verwendete Base-URL
 * @return array Array von Labels (Fallback: ID als String wenn Label nicht auflösbar)
 */
function bseasy_v3_resolve_selected_options(array $selectedOptions, string &$token, ?string &$baseUsed = null): array {
    $labels = [];
    
    foreach ($selectedOptions as $option) {
        $option_id = bseasy_v3_option_id_from_url($option);
        
        if ($option_id === null) {
            // Kann nicht zu ID konvertiert werden → als String zurückgeben
            $labels[] = is_string($option) ? $option : (string)$option;
            continue;
        }
        
        // 1) Cache prüfen
        $label = bseasy_v3_get_cached_option_label($option_id);
        
        // 2) Falls nicht im Cache: API-Request (kann null zurückgeben bei 404/400/Exception)
        if ($label === null) {
            $label = bseasy_v3_fetch_custom_field_option_label($option_id, $token, $baseUsed);
            
            // 3) Cache speichern
            if ($label !== null) {
                // Label gefunden: speichern
                bseasy_v3_set_cached_option_label($option_id, $label);
            } else {
                // Label nicht gefunden (404/400/Exception): Fallback auf ID als String
                $label = (string)$option_id;
                // Cache ID als Fallback (verhindert wiederholte API-Requests)
                bseasy_v3_set_cached_option_label($option_id, $label);
            }
        }
        
        $labels[] = $label;
    }
    
    return $labels;
}

// ============================================================
// V3 CUSTOM FIELD VALUE EXTRACTION
// ============================================================

/**
 * Extrahiert den effektiven Wert aus einem Custom Field
 * Berücksichtigt selectedOptions (für Select-Felder) und value (für Standard-Felder)
 * 
 * @param array $cf Custom Field Array aus API
 * @param int|null $cf_id Custom Field ID (optional, für Label-Auflösung)
 * @param string|null &$token API Token (optional, nur für Label-Auflösung)
 * @param string|null &$baseUsed Verwendete Base-URL (optional)
 * @return mixed|null Array von Labels (bei selectedOptions) oder Wert (bei value) oder null
 */
function bseasy_v3_get_cf_effective_value(array $cf, ?int $cf_id = null, ?string &$token = null, ?string &$baseUsed = null) {
    // 1) selectedOptions: wenn nicht leer
    if (isset($cf['selectedOptions']) && is_array($cf['selectedOptions']) && !empty($cf['selectedOptions'])) {
        // IMMER versuchen Labels aufzulösen (falls CF-ID und Token vorhanden)
        if ($cf_id !== null && $token !== null) {
            try {
                // Mini-Debug für cf.312976233
                if ($cf_id === 312976233) {
                    bseasy_v3_log("CF 312976233: selectedOptions count=" . count($cf['selectedOptions']), 'INFO');
                }
                
                $labels = bseasy_v3_resolve_selected_options_labels($cf_id, $cf['selectedOptions'], $token, $baseUsed);
                
                // Mini-Debug für cf.312976233
                if ($cf_id === 312976233 && !empty($labels)) {
                    bseasy_v3_log("CF 312976233: first resolved label=" . (is_array($labels) ? $labels[0] : 'N/A'), 'INFO');
                }
                
                // Wenn Labels gefunden wurden (nicht nur IDs), gib Labels zurück
                if (!empty($labels)) {
                    // Prüfe ob Labels tatsächlich Labels sind (nicht nur numerische IDs)
                    $has_non_numeric = false;
                    foreach ($labels as $label) {
                        if (!is_numeric($label)) {
                            $has_non_numeric = true;
                            break;
                        }
                    }
                    if ($has_non_numeric) {
                        return $labels;
                    }
                    // Wenn nur numerische IDs: Fallback auf IDs (siehe unten)
                }
            } catch (Exception $e) {
                // Fehler beim Auflösen: Fallback auf IDs
                bseasy_v3_log("Fehler beim Auflösen von Labels für CF {$cf_id}: " . $e->getMessage(), 'WARN');
            }
        }
        
        // Fallback: Mappe URLs/IDs auf einfache Strings (wie bisher)
        $mapped = [];
        foreach ($cf['selectedOptions'] as $option) {
            $option_id = bseasy_v3_option_id_from_url($option);
            if ($option_id !== null) {
                $mapped[] = (string)$option_id;
            } elseif (is_string($option)) {
                if (filter_var($option, FILTER_VALIDATE_URL)) {
                    $path = parse_url($option, PHP_URL_PATH);
                    $id = $path ? basename($path) : $option;
                    $mapped[] = $id;
                } else {
                    $mapped[] = $option;
                }
            } else {
                $mapped[] = (string)$option;
            }
        }
        
        return !empty($mapped) ? $mapped : null;
    }
    
    // 2) sonst: return value
    return $cf['value'] ?? null;
}
