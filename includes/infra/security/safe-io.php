<?php
/**
 * Sichere JSON-/Datei-Zugriffe und Rate-Limiting für AJAX.
 *
 * Aus includes/error-handler.php ausgelagert (Phase 5). Inhalt unverändert.
 *
 * @package BSEasySync
 * @author Tom Evers <https://bezugssysteme.de>
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sichere JSON-Decode-Operation mit sofortiger Fehlerprüfung
 *
 * @param string $json_string JSON-String zum Dekodieren
 * @param bool $assoc Assoziatives Array zurückgeben (Standard: true)
 * @return array|object|null Dekodierte Daten oder null bei Fehler
 * @throws Exception Bei JSON-Decode-Fehler
 */
function bes_safe_json_decode(string $json_string, bool $assoc = true)
{
    $data = json_decode($json_string, $assoc);

    // ✅ Sofortige Prüfung nach json_decode()
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_msg = 'JSON-Decode-Fehler: ' . json_last_error_msg();
        if (function_exists('bes_debug_log')) {
            bes_debug_log($error_msg, 'ERROR', 'json_decode');
        }
        throw new Exception($error_msg);
    }

    return $assoc ? (array) $data : $data;
}

/**
 * Sichere File-Operation: Liest Datei mit Pfad-Validierung
 * Verhindert Path-Traversal-Angriffe
 *
 * @param string $file_path Dateipfad (relativ oder absolut)
 * @param string $allowed_dir Erlaubtes Basis-Verzeichnis
 * @return string|null Dateiinhalt oder null bei Fehler
 */
function bes_safe_file_get_contents(string $file_path, string $allowed_dir): ?string
{
    // 1. Realpath prüfen (löst relative Pfade auf)
    $real_path = realpath($file_path);
    if ($real_path === false) {
        if (function_exists('bes_debug_log')) {
            bes_debug_log("Datei nicht gefunden oder ungültiger Pfad: $file_path", 'WARN', 'file_security');
        }
        return null; // Datei existiert nicht
    }

    // 2. Erlaubtes Verzeichnis prüfen
    $allowed_real = realpath($allowed_dir);
    if ($allowed_real === false) {
        if (function_exists('bes_debug_log')) {
            bes_debug_log("Erlaubtes Verzeichnis existiert nicht: $allowed_dir", 'ERROR', 'file_security');
        }
        return null; // Erlaubtes Verzeichnis existiert nicht
    }

    // 3. Prüfen ob Datei innerhalb erlaubtem Verzeichnis liegt
    if (strpos($real_path, $allowed_real) !== 0) {
        // Path-Traversal erkannt!
        if (function_exists('bes_debug_log')) {
            bes_debug_log("Path-Traversal-Versuch erkannt: $file_path (erlaubt: $allowed_dir)", 'WARN', 'file_security');
        }
        return null;
    }

    // 4. Prüfen ob es eine Datei ist (nicht Verzeichnis)
    if (!is_file($real_path)) {
        if (function_exists('bes_debug_log')) {
            bes_debug_log("Pfad ist keine Datei: $real_path", 'WARN', 'file_security');
        }
        return null;
    }

    // 5. Jetzt sicher lesen
    $content = @file_get_contents($real_path);
    if ($content === false) {
        if (function_exists('bes_debug_log')) {
            bes_debug_log("Fehler beim Lesen der Datei: $real_path", 'ERROR', 'file_security');
        }
        return null;
    }

    return $content;
}

/**
 * Rate-Limiting für AJAX-Endpunkte
 * Verhindert DoS-Angriffe durch zu viele Requests
 *
 * @param string $endpoint_name Name des Endpunkts (z.B. 'bes_status')
 * @param int $max_requests Maximale Anzahl Requests pro Zeitfenster
 * @param int $time_window Zeitfenster in Sekunden (Standard: 60)
 * @return bool True wenn Request erlaubt, false wenn Rate-Limit erreicht
 */
function bes_check_rate_limit(string $endpoint_name, int $max_requests = 60, int $time_window = 60): bool
{
    $user_id = get_current_user_id();
    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : 'unknown';

    // Rate-Limit-Key basierend auf Endpunkt, User-ID und IP
    $rate_limit_key = 'bes_rate_limit_' . md5($endpoint_name . '_' . $user_id . '_' . $ip_address);

    $current_count = get_transient($rate_limit_key);

    if ($current_count === false) {
        // Erster Request in diesem Zeitfenster
        set_transient($rate_limit_key, 1, $time_window);
        return true;
    }

    $current_count = intval($current_count);

    if ($current_count >= $max_requests) {
        // Rate-Limit erreicht
        if (function_exists('bes_debug_log')) {
            bes_debug_log(
                "Rate-Limit erreicht für Endpunkt: $endpoint_name (User: $user_id, IP: $ip_address, Requests: $current_count)",
                'WARN',
                'rate_limit'
            );
        }
        return false;
    }

    // Erhöhe Zähler
    set_transient($rate_limit_key, $current_count + 1, $time_window);
    return true;
}
