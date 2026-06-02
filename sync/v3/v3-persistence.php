<?php
/**
 * V3 Sync – Persistenz, Status, Locks, Verzeichnisse (aus v3-helpers).
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
            if (!chmod(BES_DATA_V3, 0755)) {
                bes_debug_log('chmod fehlgeschlagen: ' . BES_DATA_V3, 'WARN', 'filesystem');
            }
        } else {
            if (!mkdir(BES_DATA_V3, 0755, true)) {
                bes_debug_log('mkdir fehlgeschlagen: ' . BES_DATA_V3, 'ERROR', 'filesystem');
            }
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
        'started_at' => (($started_ts = (int) get_option(BES_V3_OPTION_PREFIX . 'sync_started_at', 0)) > 0)
            ? date('c', $started_ts)
            : get_option(BES_V3_OPTION_PREFIX . 'started_at', null),
        'last_run_at' => date('c'),
        'cancelled' => ($state === 'cancelled'),
        'last_error' => get_option(BES_V3_OPTION_PREFIX . 'last_error', null),
    ], $extra);
    
    $result = file_put_contents($status_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    if ($result === false) {
        bes_debug_log('Schreibfehler Status-Datei: ' . $status_file, 'ERROR', 'filesystem');
    }

    // Speichere auch in Historie
    bseasy_v3_save_history($data);
    
    return $result !== false;
}

/**
 * Startet die Gesamt-Sync-Zeitmessung (nur erster Durchlauf).
 */
function bseasy_v3_sync_start_timer($part = 1): void {
    if ((int) $part > 1) {
        return;
    }
    update_option(BES_V3_OPTION_PREFIX . 'sync_started_at', time());
}

/**
 * Beendet Zeitmessung nach erfolgreichem Komplett-Sync.
 *
 * @return array{duration_sec:int,duration_human:string}
 */
function bseasy_v3_sync_finalize_duration(): array {
    $duration_sec = 0;
    $started = (int) get_option(BES_V3_OPTION_PREFIX . 'sync_started_at', 0);

    if ($started > 0) {
        $duration_sec = max(0, time() - $started);
    }

    if ($duration_sec <= 0) {
        $duration_sec = bseasy_v3_sync_compute_duration_from_parts();
    }

    if ($duration_sec > 0) {
        update_option(BES_V3_OPTION_PREFIX . 'last_sync_duration_sec', $duration_sec);
    }

    delete_option(BES_V3_OPTION_PREFIX . 'sync_started_at');

    return [
        'duration_sec' => $duration_sec,
        'duration_human' => bseasy_v3_format_duration($duration_sec),
    ];
}

/**
 * Ermittelt Sync-Dauer aus Part-1 started + letztem finished (robust, unabhängig vom Timer).
 */
function bseasy_v3_sync_compute_duration_from_parts(): int {
    if (!defined('BES_DATA_V3') || !defined('BES_V3_MEMBERS_PART_PREFIX')) {
        return 0;
    }

    $part_files = glob(BES_DATA_V3 . BES_V3_MEMBERS_PART_PREFIX . '*.json');
    if (empty($part_files) || !is_array($part_files)) {
        return 0;
    }

    usort($part_files, function ($a, $b) {
        preg_match('/part(\d+)\.json$/', $a, $matchA);
        preg_match('/part(\d+)\.json$/', $b, $matchB);
        return ((int) ($matchA[1] ?? 0)) <=> ((int) ($matchB[1] ?? 0));
    });

    $first = bseasy_v3_read_json($part_files[0]);
    if (empty($first['_meta']['started'])) {
        return 0;
    }

    $start_ts = (int) strtotime((string) $first['_meta']['started']);
    if ($start_ts <= 0) {
        return 0;
    }

    $last_file = $part_files[count($part_files) - 1];
    $last = bseasy_v3_read_json($last_file);
    $end_ts = 0;
    if (!empty($last['_meta']['finished'])) {
        $end_ts = (int) strtotime((string) $last['_meta']['finished']);
    }
    if ($end_ts <= 0) {
        $end_ts = time();
    }

    return $end_ts >= $start_ts ? ($end_ts - $start_ts) : 0;
}

/**
 * Stellt sicher, dass die letzte Sync-Dauer gespeichert ist (Fallback nach abgeschlossenem Sync).
 *
 * @return array{duration_sec:int,duration_human:string}
 */
function bseasy_v3_sync_ensure_duration_stored(): array {
    $duration_sec = (int) get_option(BES_V3_OPTION_PREFIX . 'last_sync_duration_sec', 0);

    if ($duration_sec <= 0) {
        $duration_sec = bseasy_v3_sync_compute_duration_from_parts();
        if ($duration_sec > 0) {
            update_option(BES_V3_OPTION_PREFIX . 'last_sync_duration_sec', $duration_sec);
        }
    }

    return [
        'duration_sec' => $duration_sec,
        'duration_human' => bseasy_v3_format_duration($duration_sec),
    ];
}

/**
 * Letzte gespeicherte Sync-Dauer (nach erfolgreichem Abschluss).
 *
 * @return array{duration_sec:int,duration_human:string}
 */
function bseasy_v3_get_last_sync_duration(): array {
    $duration_sec = (int) get_option(BES_V3_OPTION_PREFIX . 'last_sync_duration_sec', 0);

    return [
        'duration_sec' => $duration_sec,
        'duration_human' => bseasy_v3_format_duration($duration_sec),
    ];
}

/**
 * Formatiert Sekunden für die Admin-Anzeige (deutsch).
 */
function bseasy_v3_format_duration(int $seconds): string {
    if ($seconds <= 0) {
        return '';
    }

    if ($seconds < 60) {
        return sprintf('%d Sek', $seconds);
    }

    $minutes = intdiv($seconds, 60);
    $remaining = $seconds % 60;

    if ($minutes < 60) {
        if ($remaining > 0) {
            return sprintf('%d Min %d Sek', $minutes, $remaining);
        }
        return sprintf('%d Min', $minutes);
    }

    $hours = intdiv($minutes, 60);
    $minutes = $minutes % 60;

    if ($minutes > 0) {
        return sprintf('%d Std %d Min', $hours, $minutes);
    }

    return sprintf('%d Std', $hours);
}

/**
 * Hängt die Dauer an eine Erfolgsmeldung an (falls vorhanden).
 */
function bseasy_v3_sync_message_with_duration(string $message, array $duration): string {
    if (empty($duration['duration_human'])) {
        return $message;
    }

    return $message . ' — Dauer: ' . $duration['duration_human'];
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
        $raw = file_get_contents($history_file);
        if ($raw === false) {
            bes_debug_log('Lesefehler Historien-Datei: ' . $history_file, 'WARN', 'filesystem');
        }
        $history = json_decode($raw ?: '', true) ?: [];
    }
    
    // Füge neuen Eintrag hinzu (nur bei Status-Wechsel)
    $last_entry = end($history);
    if (!$last_entry || $last_entry['state'] !== $status_data['state']) {
        $history[] = $status_data;
        
        // Behalte nur letzte 10 Einträge
        if (count($history) > 10) {
            $history = array_slice($history, -10);
        }
        
        $written = file_put_contents($history_file, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        if ($written === false) {
            bes_debug_log('Schreibfehler Historien-Datei: ' . $history_file, 'ERROR', 'filesystem');
        }
        return $written !== false;
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
            if (!chmod($dir, 0755)) {
                bes_debug_log('chmod fehlgeschlagen: ' . $dir, 'WARN', 'filesystem');
            }
        } else {
            if (!mkdir($dir, 0755, true)) {
                bes_debug_log('mkdir fehlgeschlagen: ' . $dir, 'ERROR', 'filesystem');
            }
        }
    }

    // Temporäre Datei
    $tmp_file = $file . '.tmp';

    // Schreibe in temporäre Datei
    $result = file_put_contents($tmp_file, $content, LOCK_EX);

    if ($result === false) {
        bes_debug_log('Schreibfehler tmp-Datei: ' . $tmp_file, 'ERROR', 'filesystem');
        return false;
    }

    // Atomisches Umbenennen
    if (!rename($tmp_file, $file)) {
        bes_debug_log('rename fehlgeschlagen: ' . $tmp_file . ' → ' . $file, 'ERROR', 'filesystem');
        return false;
    }
    return true;
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
    
    $content = file_get_contents($file);
    if ($content === false) {
        bes_debug_log('Lesefehler JSON-Datei: ' . $file, 'WARN', 'filesystem');
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
            if (!chmod(BES_DATA_V3, 0755)) {
                bes_debug_log('chmod fehlgeschlagen: ' . BES_DATA_V3, 'WARN', 'filesystem');
            }
        } else {
            if (!mkdir(BES_DATA_V3, 0755, true)) {
                bes_debug_log('mkdir fehlgeschlagen: ' . BES_DATA_V3, 'ERROR', 'filesystem');
            }
        }
    }

    // Erstelle index.php (Schutz vor Directory Listing)
    $index_file = BES_DATA_V3 . 'index.php';
    if (!file_exists($index_file)) {
        if (file_put_contents($index_file, "<?php\n// Silence is golden.\n") === false) {
            bes_debug_log('Schreibfehler index.php: ' . $index_file, 'WARN', 'filesystem');
        }
    }

    // Erstelle .htaccess (Schutz vor direktem Zugriff)
    $htaccess_file = BES_DATA_V3 . '.htaccess';
    if (!file_exists($htaccess_file)) {
        $htaccess_content = "# BSEasy Sync V3 - Schutz vor direktem Zugriff\n";
        $htaccess_content .= "<FilesMatch \"\\.(json|log)$\">\n";
        $htaccess_content .= "    Order allow,deny\n";
        $htaccess_content .= "    Deny from all\n";
        $htaccess_content .= "</FilesMatch>\n";
        if (file_put_contents($htaccess_file, $htaccess_content) === false) {
            bes_debug_log('Schreibfehler .htaccess: ' . $htaccess_file, 'WARN', 'filesystem');
        }
    }
    
    return true;
}
