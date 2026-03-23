<?php
/**
 * BSEasy Sync V3 - Shared Helper Functions
 * 
 * Wiederverwendbare Helper-Funktionen für V3, die keine V2-Abhängigkeiten haben
 * 
 * @package BSEasySync
 * @subpackage V3
 * @author Tom Evers <https://bezugssysteme.de>
 * @since 3.0.0
 */

if (!defined('ABSPATH')) exit;

// Lade V3-Konstanten
if (!defined('BES_DATA_V3')) {
    require_once BES_DIR . 'includes/constants-v3.php';
}

// ============================================================
// V3 LOGGING
// ============================================================

/**
 * V3-spezifisches Logging (schreibt in sync-v3.log)
 * 
 * @param string $message Log-Nachricht
 * @param string $level Log-Level (INFO, WARN, ERROR, DEBUG)
 * @param string $context Kontext (optional)
 * @return bool Erfolg
 */
function bseasy_v3_log(string $message, string $level = 'INFO', string $context = 'v3'): bool {
    if (!defined('BES_DATA_V3') || empty(BES_DATA_V3)) {
        return false;
    }
    
    // Stelle sicher, dass Verzeichnis existiert
    if (!file_exists(BES_DATA_V3)) {
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p(BES_DATA_V3);
            @chmod(BES_DATA_V3, 0755);
        } else {
            @mkdir(BES_DATA_V3, 0755, true);
        }
    }
    
    $log_file = BES_DATA_V3 . BES_V3_LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$level}] [{$context}] {$message}\n";
    
    return @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX) !== false;
}

/**
 * V3 Debug-Logging (schreibt in debug-v3.log)
 * 
 * @param string $message Log-Nachricht
 * @param string $level Log-Level
 * @param string $context Kontext
 * @return bool Erfolg
 */
function bseasy_v3_debug_log(string $message, string $level = 'DEBUG', string $context = 'v3'): bool {
    if (!defined('BES_DEBUG_MODE') || !BES_DEBUG_MODE) {
        return false;
    }
    
    if (!defined('BES_DATA_V3') || empty(BES_DATA_V3)) {
        return false;
    }
    
    // Stelle sicher, dass Verzeichnis existiert
    if (!file_exists(BES_DATA_V3)) {
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p(BES_DATA_V3);
            @chmod(BES_DATA_V3, 0755);
        } else {
            @mkdir(BES_DATA_V3, 0755, true);
        }
    }
    
    $log_file = BES_DATA_V3 . BES_V3_DEBUG_LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$level}] [{$context}] {$message}\n";
    
    return @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX) !== false;
}

// ============================================================
// V3 STATUS MANAGEMENT
// ============================================================

/**
 * Aktualisiert V3 Status-Datei
 * 
 * @param int $progress Fortschritt (0-100)
 * @param int $total Gesamtanzahl
 * @param string $message Status-Nachricht
 * @param string $state Status (running, done, error, cancelled, idle)
 * @param array $extra Zusätzliche Daten
 * @return bool Erfolg
 */
function bseasy_v3_update_status(int $progress, int $total, string $message = '', string $state = 'running', array $extra = []): bool {
    if (!defined('BES_DATA_V3') || empty(BES_DATA_V3)) {
        return false;
    }
    
    // Stelle sicher, dass Verzeichnis existiert
    if (!file_exists(BES_DATA_V3)) {
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p(BES_DATA_V3);
            @chmod(BES_DATA_V3, 0755);
        } else {
            @mkdir(BES_DATA_V3, 0755, true);
        }
    }
    
    $status_file = BES_DATA_V3 . BES_V3_STATUS_FILE;
    
    $data = array_merge([
        'state' => $state,
        'progress' => $progress,
        'total' => $total,
        'message' => $message,
        'timestamp' => date('c'),
        'offset' => get_option(BES_V3_OPTION_PREFIX . 'offset', 0),
        'started_at' => get_option(BES_V3_OPTION_PREFIX . 'started_at', null),
        'last_run_at' => date('c'),
        'cancelled' => ($state === 'cancelled'),
        'last_error' => get_option(BES_V3_OPTION_PREFIX . 'last_error', null),
    ], $extra);
    
    $result = @file_put_contents($status_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    
    // Speichere auch in Historie
    bseasy_v3_save_history($data);
    
    return $result !== false;
}

/**
 * Speichert V3 Sync-Historie
 * 
 * @param array $status_data Status-Daten
 * @return bool Erfolg
 */
function bseasy_v3_save_history(array $status_data): bool {
    if (!defined('BES_DATA_V3') || empty(BES_DATA_V3)) {
        return false;
    }
    
    $history_file = BES_DATA_V3 . BES_V3_HISTORY_FILE;
    $history = [];
    
    if (file_exists($history_file)) {
        $history = json_decode(@file_get_contents($history_file), true) ?: [];
    }
    
    // Füge neuen Eintrag hinzu (nur bei Status-Wechsel)
    $last_entry = end($history);
    if (!$last_entry || $last_entry['state'] !== $status_data['state']) {
        $history[] = $status_data;
        
        // Behalte nur letzte 10 Einträge
        if (count($history) > 10) {
            $history = array_slice($history, -10);
        }
        
        return @file_put_contents($history_file, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
    }
    
    return true;
}

// ============================================================
// V3 FILE OPERATIONS
// ============================================================

/**
 * Atomisches Schreiben von JSON-Dateien (tmp + rename)
 * 
 * @param string $file Ziel-Datei
 * @param string $content JSON-Inhalt
 * @return bool Erfolg
 */
function bseasy_v3_safe_write_json(string $file, string $content): bool {
    if (!defined('BES_DATA_V3') || empty(BES_DATA_V3)) {
        return false;
    }
    
    // Stelle sicher, dass Verzeichnis existiert
    $dir = dirname($file);
    if (!file_exists($dir)) {
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p($dir);
            @chmod($dir, 0755);
        } else {
            @mkdir($dir, 0755, true);
        }
    }
    
    // Temporäre Datei
    $tmp_file = $file . '.tmp';
    
    // Schreibe in temporäre Datei
    $result = @file_put_contents($tmp_file, $content, LOCK_EX);
    
    if ($result === false) {
        return false;
    }
    
    // Atomisches Umbenennen
    return @rename($tmp_file, $file);
}

/**
 * Liest JSON-Datei sicher
 * 
 * @param string $file Dateipfad
 * @return array|null Array oder null bei Fehler
 */
function bseasy_v3_read_json(string $file): ?array {
    if (!file_exists($file)) {
        return null;
    }
    
    $content = @file_get_contents($file);
    if ($content === false) {
        return null;
    }
    
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }
    
    return $data;
}

// ============================================================
// V3 PII MASKING
// ============================================================

/**
 * Maskiert PII in Strings
 * 
 * @param string $value Wert
 * @return string Maskierter Wert
 */
function bseasy_v3_mask_pii(string $value): string {
    if (empty($value)) {
        return $value;
    }
    
    // E-Mail maskieren
    $value = preg_replace(BES_V3_PII_PATTERNS['email'], '***@***.***', $value);
    
    // Telefon maskieren
    $value = preg_replace(BES_V3_PII_PATTERNS['phone'], '***-***-****', $value);
    
    // Adresse maskieren
    $value = preg_replace(BES_V3_PII_PATTERNS['address'], '*** ***', $value);
    
    return $value;
}

/**
 * Maskiert PII in Arrays rekursiv
 * 
 * @param array $data Daten-Array
 * @param array $pii_keys Keys die PII enthalten könnten
 * @return array Maskierte Daten
 */
function bseasy_v3_mask_pii_array(array $data, array $pii_keys = ['email', 'phone', 'mobile', 'street', 'address', 'privateEmail', 'companyEmail']): array {
    foreach ($data as $key => $value) {
        if (is_string($value)) {
            // Prüfe ob Key PII-indizierend ist
            $key_lower = strtolower($key);
            $is_pii_key = false;
            foreach ($pii_keys as $pii_key) {
                if (strpos($key_lower, strtolower($pii_key)) !== false) {
                    $is_pii_key = true;
                    break;
                }
            }
            
            if ($is_pii_key) {
                $data[$key] = bseasy_v3_mask_pii($value);
            }
        } elseif (is_array($value)) {
            $data[$key] = bseasy_v3_mask_pii_array($value, $pii_keys);
        }
    }
    
    return $data;
}

// ============================================================
// V3 LOCKING
// ============================================================

/**
 * Setzt V3 Lock
 * 
 * @param string $lock_key Lock-Key
 * @param int $timeout Timeout in Sekunden
 * @return bool Erfolg
 */
function bseasy_v3_set_lock(string $lock_key, int $timeout = 300): bool {
    $transient_key = BES_V3_OPTION_PREFIX . 'lock_' . $lock_key;
    $existing_lock = get_transient($transient_key);
    
    if ($existing_lock !== false) {
        // Prüfe ob Lock abgelaufen ist
        $lock_timestamp = is_numeric($existing_lock) ? (int)$existing_lock : 0;
        $lock_age = time() - $lock_timestamp;
        
        if ($lock_age > $timeout) {
            // Lock ist abgelaufen - lösche es
            delete_transient($transient_key);
        } else {
            // Lock ist noch aktiv
            return false;
        }
    }
    
    // Setze neuen Lock
    return set_transient($transient_key, time(), $timeout) !== false;
}

/**
 * Entfernt V3 Lock
 * 
 * @param string $lock_key Lock-Key
 * @return bool Erfolg
 */
function bseasy_v3_release_lock(string $lock_key): bool {
    $transient_key = BES_V3_OPTION_PREFIX . 'lock_' . $lock_key;
    return delete_transient($transient_key);
}

/**
 * Prüft ob V3 Lock aktiv ist
 * 
 * @param string $lock_key Lock-Key
 * @return bool Lock aktiv
 */
function bseasy_v3_is_locked(string $lock_key): bool {
    $transient_key = BES_V3_OPTION_PREFIX . 'lock_' . $lock_key;
    return get_transient($transient_key) !== false;
}

// ============================================================
// V3 DIRECTORY SETUP
// ============================================================

/**
 * Erstellt V3-Verzeichnisstruktur mit Schutz-Dateien
 * 
 * @return bool Erfolg
 */
function bseasy_v3_setup_directories(): bool {
    if (!defined('BES_DATA_V3') || empty(BES_DATA_V3)) {
        return false;
    }
    
    // Erstelle Verzeichnis
    if (!file_exists(BES_DATA_V3)) {
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p(BES_DATA_V3);
            @chmod(BES_DATA_V3, 0755);
        } else {
            @mkdir(BES_DATA_V3, 0755, true);
        }
    }
    
    // Erstelle index.php (Schutz vor Directory Listing)
    $index_file = BES_DATA_V3 . 'index.php';
    if (!file_exists($index_file)) {
        @file_put_contents($index_file, "<?php\n// Silence is golden.\n");
    }
    
    // Erstelle .htaccess (Schutz vor direktem Zugriff)
    $htaccess_file = BES_DATA_V3 . '.htaccess';
    if (!file_exists($htaccess_file)) {
        $htaccess_content = "# BSEasy Sync V3 - Schutz vor direktem Zugriff\n";
        $htaccess_content .= "<FilesMatch \"\\.(json|log)$\">\n";
        $htaccess_content .= "    Order allow,deny\n";
        $htaccess_content .= "    Deny from all\n";
        $htaccess_content .= "</FilesMatch>\n";
        @file_put_contents($htaccess_file, $htaccess_content);
    }
    
    return true;
}

// ============================================================
// V3 CUSTOM FIELD OPTION LABEL RESOLUTION (CF-spezifisch)
// ============================================================

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
    
    $cache_dir = BES_DATA_V3 . 'cache/';
    
    // Stelle sicher, dass Cache-Verzeichnis existiert
    if (!file_exists($cache_dir)) {
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p($cache_dir);
            @chmod($cache_dir, 0755);
        } else {
            @mkdir($cache_dir, 0755, true);
        }
        
        // Schutz-Datei
        $index_file = $cache_dir . 'index.php';
        if (!file_exists($index_file)) {
            @file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }
    }
    
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
    
    $cache_dir = BES_DATA_V3 . 'cache/';
    
    // Stelle sicher, dass Cache-Verzeichnis existiert
    if (!file_exists($cache_dir)) {
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p($cache_dir);
            @chmod($cache_dir, 0755);
        } else {
            @mkdir($cache_dir, 0755, true);
        }
        
        // Schutz-Datei
        $index_file = $cache_dir . 'index.php';
        if (!file_exists($index_file)) {
            @file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }
    }
    
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
