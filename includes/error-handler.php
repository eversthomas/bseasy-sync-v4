<?php
/**
 * BSEasy Sync - Error Handler & Debug Utilities
 * 
 * @package BSEasySync
 * @author Tom Evers <https://bezugssysteme.de>
 * @since 3.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Debug-Logging mit Level-System
 * 
 * @param string $message Die Log-Nachricht
 * @param string $level Log-Level: DEBUG, INFO, WARN, ERROR
 * @param string $context Optionaler Kontext (z.B. Funktionsname)
 * @return void
 */
function bes_debug_log(string $message, string $level = 'INFO', string $context = ''): void
{
    // In Produktion nur ERROR und WARN loggen
    if (!BES_DEBUG_MODE && !in_array($level, ['ERROR', 'WARN'])) {
        return;
    }
    
    // DEBUG-Level nur wenn explizit aktiviert
    if ($level === 'DEBUG' && !BES_DEBUG_VERBOSE) {
        return;
    }
    
    $context_str = $context ? "[{$context}] " : '';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] [BES {$level}] {$context_str}{$message}";
    
    error_log($log_message);
}

/**
 * Error Handler Klasse
 */
class BES_Error_Handler
{
    /**
     * Behandelt einen Fehler und gibt benutzerfreundliche Meldung zurück
     * 
     * @param string|Exception $error Fehler-Objekt oder Nachricht
     * @param string $context Kontext (z.B. 'renderer', 'sync')
     * @param bool $user_friendly Soll eine benutzerfreundliche Meldung zurückgegeben werden?
     * @return string|null Benutzerfreundliche Fehlermeldung oder null
     */
    public static function handle($error, string $context = '', bool $user_friendly = false): ?string
    {
        $error_message = is_object($error) && $error instanceof Exception 
            ? $error->getMessage() 
            : (string)$error;
        
        $error_code = self::extract_error_code($error_message);
        
        // Logging
        bes_debug_log($error_message, 'ERROR', $context);
        
        if (is_object($error) && $error instanceof Exception && BES_DEBUG_VERBOSE) {
            bes_debug_log('Stack Trace: ' . $error->getTraceAsString(), 'DEBUG', $context);
        }
        
        // Benutzerfreundliche Meldung zurückgeben
        if ($user_friendly) {
            return self::get_user_friendly_message($error_code);
        }
        
        return null;
    }
    
    /**
     * Extrahiert einen Error-Code aus der Fehlermeldung
     * 
     * @param string $message Fehlermeldung
     * @return string Error-Code
     */
    private static function extract_error_code(string $message): string
    {
        if (strpos($message, 'nicht gefunden') !== false || strpos($message, 'not found') !== false) {
            return 'file_not_found';
        }
        if (strpos($message, 'Token') !== false || strpos($message, 'token') !== false) {
            return 'token_error';
        }
        if (strpos($message, 'Berechtigung') !== false || strpos($message, 'permission') !== false) {
            return 'permission_denied';
        }
        if (strpos($message, 'JSON') !== false || strpos($message, 'json') !== false) {
            return 'json_error';
        }
        if (strpos($message, 'Timeout') !== false || strpos($message, 'timeout') !== false) {
            return 'timeout';
        }
        
        return 'unknown_error';
    }
    
    /**
     * Gibt eine benutzerfreundliche Fehlermeldung zurück
     * 
     * @param string $error_code Error-Code
     * @return string Benutzerfreundliche Meldung
     */
    public static function get_user_friendly_message(string $error_code): string
    {
        $messages = [
            'file_not_found' => 'Die Daten werden gerade aktualisiert. Bitte versuchen Sie es in ein paar Minuten erneut.',
            'token_error' => 'Die API-Verbindung konnte nicht hergestellt werden. Bitte überprüfen Sie die Einstellungen im Admin-Bereich.',
            'permission_denied' => 'Sie haben keine Berechtigung für diese Aktion.',
            'json_error' => 'Die Daten konnten nicht verarbeitet werden. Bitte führen Sie einen neuen Sync durch.',
            'timeout' => 'Die Anfrage hat zu lange gedauert. Bitte versuchen Sie es erneut.',
            'unknown_error' => 'Ein unerwarteter Fehler ist aufgetreten. Bitte kontaktieren Sie den Administrator.'
        ];
        
        return $messages[$error_code] ?? $messages['unknown_error'];
    }
    
    /**
     * Validiert und bereinigt Input
     * 
     * @param mixed $value Der zu validierende Wert
     * @param string $type Typ: 'int', 'string', 'email', 'url', 'field_id'
     * @param mixed $default Default-Wert bei Fehler
     * @return mixed Bereinigter Wert oder Default
     */
    public static function validate_input($value, string $type, $default = null)
    {
        switch ($type) {
            case 'int':
                $value = intval($value);
                return $value > 0 ? $value : $default;
                
            case 'string':
                return sanitize_text_field($value);
                
            case 'email':
                return is_email($value) ? $value : $default;
                
            case 'url':
                return esc_url_raw($value);
                
            case 'field_id':
                // Erlaubt: alphanumerisch, Punkt, Unterstrich, Bindestrich
                return preg_match('/^[a-z0-9._-]+$/i', $value) ? $value : $default;
                
            default:
                return $default;
        }
    }
}

/**
 * Verschlüsselt einen API-Token für sichere Speicherung
 * Verwendet AES-256-CBC mit HMAC für Integritätsprüfung
 * 
 * @param string $token Der zu verschlüsselnde Token
 * @return string Verschlüsselter Token (Base64-kodiert: IV|Ciphertext|HMAC)
 */
function bes_encrypt_token(string $token): string
{
    if (empty($token)) {
        return '';
    }
    
    // Prüfe ob OpenSSL verfügbar ist
    if (!function_exists('openssl_encrypt') || !function_exists('openssl_random_pseudo_bytes') || !function_exists('openssl_cipher_iv_length')) {
        // Fallback: Einfache Base64-Kodierung wenn OpenSSL nicht verfügbar
        return base64_encode($token);
    }
    
    // Verwende WordPress Salt für Verschlüsselung
    if (!function_exists('wp_salt')) {
        // Fallback: Einfache Base64-Kodierung wenn wp_salt nicht verfügbar
        return base64_encode($token);
    }
    
    $key = wp_salt('auth');
    $iv_length = openssl_cipher_iv_length('AES-256-CBC');
    
    if ($iv_length === false) {
        // Fallback: Einfache Base64-Kodierung wenn IV-Länge nicht ermittelt werden kann
        return base64_encode($token);
    }
    
    $iv = openssl_random_pseudo_bytes($iv_length);
    
    if ($iv === false) {
        // Fallback: Einfache Base64-Kodierung wenn IV nicht generiert werden kann
        return base64_encode($token);
    }
    
    $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);
    
    if ($encrypted === false) {
        // Fallback: Einfache Base64-Kodierung bei Fehler
        return base64_encode($token);
    }
    
    // HMAC für Integritätsprüfung (verhindert Manipulation)
    $hmac_key = wp_salt('secure_auth'); // Anderes Salt für HMAC
    $hmac_data = $iv . $encrypted;
    
    if (function_exists('hash_hmac')) {
        $hmac = hash_hmac('sha256', $hmac_data, $hmac_key, true);
    } else {
        // Fallback ohne HMAC (weniger sicher)
        $hmac = '';
    }
    
    // Kombiniere IV, verschlüsselten Text und HMAC
    return base64_encode($iv . $encrypted . $hmac);
}

/**
 * Entschlüsselt einen API-Token
 * Prüft HMAC für Integritätsprüfung
 * 
 * @param string $encrypted_token Der verschlüsselte Token
 * @return string Entschlüsselter Token oder leerer String bei Fehler
 */
function bes_decrypt_token(string $encrypted_token): string
{
    if (empty($encrypted_token)) {
        return '';
    }
    
    // Prüfe ob OpenSSL verfügbar ist
    if (!function_exists('openssl_decrypt') || !function_exists('openssl_cipher_iv_length')) {
        // OpenSSL nicht verfügbar, versuche Base64-Decode
        $decoded = base64_decode($encrypted_token, true);
        return $decoded !== false ? $decoded : $encrypted_token;
    }
    
    // Prüfe ob Token bereits unverschlüsselt ist (Migration)
    $test_decode = base64_decode($encrypted_token, true);
    if ($test_decode === false) {
        // Nicht Base64-kodiert, vermutlich altes Format
        return $encrypted_token;
    }
    
    $decoded = $test_decode;
    
    // Prüfe ob IV vorhanden ist (neues Format)
    $iv_length = openssl_cipher_iv_length('AES-256-CBC');
    if ($iv_length === false || strlen($decoded) < $iv_length) {
        // Kein IV vorhanden, vermutlich altes Format oder Base64-kodiert
        return $decoded;
    }
    
    // Prüfe ob HMAC vorhanden ist (neues Format mit HMAC)
    $hmac_length = 32; // SHA-256 HMAC ist 32 Bytes
    $has_hmac = strlen($decoded) >= ($iv_length + $hmac_length);
    
    if ($has_hmac && function_exists('hash_hmac')) {
        // Neues Format mit HMAC: IV|Ciphertext|HMAC
        $iv = substr($decoded, 0, $iv_length);
        $encrypted = substr($decoded, $iv_length, -$hmac_length);
        $hmac_received = substr($decoded, -$hmac_length);
        
        // Prüfe HMAC
        if (!function_exists('wp_salt')) {
            // Fallback: Versuche als unverschlüsselt zu behandeln
            return $encrypted_token;
        }
        
        $hmac_key = wp_salt('secure_auth');
        $hmac_data = $iv . $encrypted;
        $hmac_calculated = hash_hmac('sha256', $hmac_data, $hmac_key, true);
        
        // Vergleiche HMAC (timing-safe)
        if (!hash_equals($hmac_received, $hmac_calculated)) {
            // HMAC stimmt nicht überein - Token wurde manipuliert!
            if (function_exists('bes_debug_log')) {
                bes_debug_log('Token-HMAC-Prüfung fehlgeschlagen - mögliche Manipulation erkannt', 'ERROR', 'security');
            }
            return ''; // Leerer String bei Manipulation
        }
    } else {
        // Altes Format ohne HMAC: IV|Ciphertext
        $iv = substr($decoded, 0, $iv_length);
        $encrypted = substr($decoded, $iv_length);
    }
    
    if (!function_exists('wp_salt')) {
        // Fallback: Versuche als unverschlüsselt zu behandeln
        return $encrypted_token;
    }
    
    $key = wp_salt('auth');
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    
    if ($decrypted === false) {
        // Fallback: Versuche als unverschlüsselt zu behandeln
        return $encrypted_token;
    }
    
    return $decrypted;
}

/**
 * Sichere JSON-Decode-Operation mit sofortiger Fehlerprüfung
 * 
 * @param string $json_string JSON-String zum Dekodieren
 * @param bool $assoc Assoziatives Array zurückgeben (Standard: true)
 * @return array|object|null Dekodierte Daten oder null bei Fehler
 * @throws Exception Bei JSON-Decode-Fehler
 */
function bes_safe_json_decode(string $json_string, bool $assoc = true) {
    $data = json_decode($json_string, $assoc);
    
    // ✅ Sofortige Prüfung nach json_decode()
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_msg = 'JSON-Decode-Fehler: ' . json_last_error_msg();
        if (function_exists('bes_debug_log')) {
            bes_debug_log($error_msg, 'ERROR', 'json_decode');
        }
        throw new Exception($error_msg);
    }
    
    return $assoc ? (array)$data : $data;
}

/**
 * Sichere File-Operation: Liest Datei mit Pfad-Validierung
 * Verhindert Path-Traversal-Angriffe
 * 
 * @param string $file_path Dateipfad (relativ oder absolut)
 * @param string $allowed_dir Erlaubtes Basis-Verzeichnis
 * @return string|null Dateiinhalt oder null bei Fehler
 */
function bes_safe_file_get_contents(string $file_path, string $allowed_dir): ?string {
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

