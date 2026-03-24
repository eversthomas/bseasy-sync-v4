<?php
/**
 * BSEasy Sync - Hosting-Kompatibilität
 * 
 * Behebt typische Probleme auf deutschen Hostern:
 * - Strato, All-inkl, Mittwald, Ionos
 * 
 * @package BSEasySync
 * @author Tom Evers <https://bezugssysteme.de>
 * @since 3.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Erkennt den Hosting-Provider basierend auf Server-Umgebung
 * 
 * @return string Provider-Name oder 'unknown'
 */
function bes_detect_hosting_provider(): string
{
    // Prüfe Server-Variablen
    $server_name = isset($_SERVER['SERVER_NAME']) ? strtolower($_SERVER['SERVER_NAME']) : '';
    $http_host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
    $server_software = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower($_SERVER['SERVER_SOFTWARE']) : '';
    
    // Strato
    if (strpos($server_name, 'strato') !== false || 
        strpos($http_host, 'strato') !== false ||
        strpos($server_software, 'strato') !== false) {
        return 'strato';
    }
    
    // All-inkl
    if (strpos($server_name, 'all-inkl') !== false || 
        strpos($http_host, 'all-inkl') !== false ||
        strpos($server_name, 'kasserver') !== false) {
        return 'all-inkl';
    }
    
    // Mittwald
    if (strpos($server_name, 'mittwald') !== false || 
        strpos($http_host, 'mittwald') !== false) {
        return 'mittwald';
    }
    
    // Ionos (ehemals 1&1)
    if (strpos($server_name, 'ionos') !== false || 
        strpos($http_host, 'ionos') !== false ||
        strpos($server_name, '1und1') !== false) {
        return 'ionos';
    }
    
    return 'unknown';
}

/**
 * Gibt hosting-spezifische Limits zurück
 * 
 * @return array Array mit Limits
 */
function bes_get_hosting_limits(): array
{
    $provider = bes_detect_hosting_provider();
    
    $defaults = [
        'max_execution_time' => 300,      // 5 Minuten Standard
        'memory_limit' => '128M',
        'max_upload_size' => '10M',
        'wp_cron_timeout' => 900,         // 15 Minuten
        'api_timeout' => 30,
        'batch_size_recommended' => 150,
    ];
    
    $limits = [
        'strato' => [
            'max_execution_time' => 900,   // 15 Minuten (oft ignoriert)
            'memory_limit' => '128M',
            'max_upload_size' => '50M',
            'wp_cron_timeout' => 900,      // 15 Minuten
            'api_timeout' => 45,
            'batch_size_recommended' => 150,
            'set_time_limit_works' => false, // Oft ignoriert
        ],
        'all-inkl' => [
            'max_execution_time' => 600,   // 10 Minuten
            'memory_limit' => '128M',
            'max_upload_size' => '20M',
            'wp_cron_timeout' => 600,
            'api_timeout' => 30,
            'batch_size_recommended' => 100,
            'set_time_limit_works' => true,
        ],
        'mittwald' => [
            'max_execution_time' => 1800,  // 30 Minuten (Managed Hosting)
            'memory_limit' => '256M',
            'max_upload_size' => '100M',
            'wp_cron_timeout' => 1800,
            'api_timeout' => 60,
            'batch_size_recommended' => 200,
            'set_time_limit_works' => true,
        ],
        'ionos' => [
            'max_execution_time' => 600,   // 10 Minuten
            'memory_limit' => '128M',
            'max_upload_size' => '50M',
            'wp_cron_timeout' => 600,
            'api_timeout' => 30,
            'batch_size_recommended' => 120,
            'set_time_limit_works' => true,
        ],
    ];
    
    return isset($limits[$provider]) 
        ? array_merge($defaults, $limits[$provider]) 
        : $defaults;
}

/**
 * Passt Timeout-Einstellungen an den Hosting-Provider an
 * 
 * @param int $default_timeout Standard-Timeout
 * @return int Angepasster Timeout
 */
function bes_get_safe_timeout(int $default_timeout = 45): int
{
    $limits = bes_get_hosting_limits();
    $provider_timeout = $limits['api_timeout'] ?? $default_timeout;
    
    // Verwende den kleineren Wert für Sicherheit
    return min($default_timeout, $provider_timeout);
}

/**
 * Passt Batch-Größe an den Hosting-Provider an
 * 
 * @param int $default_batch_size Standard-Batch-Größe
 * @return int Angepasste Batch-Größe
 */
function bes_get_safe_batch_size(int $default_batch_size = 200): int
{
    $limits = bes_get_hosting_limits();
    $recommended = $limits['batch_size_recommended'] ?? $default_batch_size;
    
    // Verwende die kleinere Größe für Sicherheit
    return min($default_batch_size, $recommended);
}

/**
 * Versucht set_time_limit sicher zu setzen
 * 
 * @param int $seconds Sekunden (0 = unbegrenzt)
 * @return bool Erfolg
 */
function bes_safe_set_time_limit(int $seconds = 0): bool
{
    $limits = bes_get_hosting_limits();
    
    // Wenn set_time_limit nicht funktioniert, überspringen
    if (isset($limits['set_time_limit_works']) && !$limits['set_time_limit_works']) {
        return false;
    }
    
    // Prüfe ob Funktion verfügbar ist
    if (!function_exists('set_time_limit')) {
        return false;
    }
    
    // Prüfe ob safe_mode aktiv ist (veraltet, aber manchmal noch vorhanden)
    if (ini_get('safe_mode')) {
        return false;
    }
    
    // Versuche Timeout zu setzen
    @set_time_limit($seconds);
    
    return true;
}

/**
 * Prüft und erhöht Memory-Limit falls nötig
 * 
 * @param string $required_limit Benötigtes Limit (z.B. '256M')
 * @return bool Erfolg
 */
function bes_safe_increase_memory(string $required_limit = '256M'): bool
{
    $current_limit = ini_get('memory_limit');
    $limits = bes_get_hosting_limits();
    $provider_limit = $limits['memory_limit'] ?? '128M';
    
    // Konvertiere zu Bytes
    $current_bytes = bes_convert_to_bytes($current_limit);
    $required_bytes = bes_convert_to_bytes($required_limit);
    $provider_bytes = bes_convert_to_bytes($provider_limit);
    
    // Wenn aktuelles Limit bereits ausreicht
    if ($current_bytes >= $required_bytes) {
        return true;
    }
    
    // Verwende das kleinere Limit (Provider-Limit oder Required)
    $target_limit = min($required_bytes, $provider_bytes);
    $target_string = bes_bytes_to_string($target_limit);
    
    // Versuche Limit zu erhöhen
    @ini_set('memory_limit', $target_string);
    
    // Prüfe ob es funktioniert hat
    $new_limit = ini_get('memory_limit');
    $new_bytes = bes_convert_to_bytes($new_limit);
    
    return $new_bytes >= $target_limit;
}

/**
 * Konvertiert Memory-Limit-String zu Bytes
 * 
 * @param string $limit Limit-String (z.B. '128M', '256M')
 * @return int Bytes
 */
function bes_convert_to_bytes(string $limit): int
{
    $limit = trim($limit);
    $last = strtolower($limit[strlen($limit) - 1]);
    $value = (int) $limit;
    
    switch ($last) {
        case 'g':
            $value *= 1024;
        case 'm':
            $value *= 1024;
        case 'k':
            $value *= 1024;
    }
    
    return $value;
}

/**
 * Konvertiert Bytes zu Memory-Limit-String
 * 
 * @param int $bytes Bytes
 * @return string Limit-String
 */
function bes_bytes_to_string(int $bytes): string
{
    if ($bytes >= 1024 * 1024 * 1024) {
        return round($bytes / (1024 * 1024 * 1024)) . 'G';
    }
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024)) . 'M';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . 'K';
    }
    return $bytes . 'B';
}

/**
 * Prüft ob Verzeichnis beschreibbar ist und erstellt es falls nötig
 * 
 * @param string $path Verzeichnis-Pfad
 * @param int $permissions Berechtigungen (Standard: 0755)
 * @return bool Erfolg
 */
function bes_ensure_writable_directory(string $path, int $permissions = 0755): bool
{
    // Prüfe ob Verzeichnis existiert
    if (!is_dir($path)) {
        // Versuche Verzeichnis zu erstellen
        if (!wp_mkdir_p($path)) {
            bes_debug_log("Konnte Verzeichnis nicht erstellen: $path", 'ERROR', 'hosting');
            return false;
        }
    }
    
    // Prüfe ob beschreibbar
    if (!is_writable($path)) {
        // Versuche Berechtigungen zu setzen
        @chmod($path, $permissions);
        
        // Prüfe erneut
        if (!is_writable($path)) {
            bes_debug_log("Verzeichnis nicht beschreibbar: $path", 'ERROR', 'hosting');
            return false;
        }
    }
    
    return true;
}

/**
 * Prüft ob Datei beschreibbar ist
 * 
 * @param string $file Datei-Pfad
 * @param int $permissions Berechtigungen (Standard: 0644)
 * @return bool Erfolg
 */
function bes_ensure_writable_file(string $file, int $permissions = 0644): bool
{
    $dir = dirname($file);
    
    // Stelle sicher, dass Verzeichnis existiert
    if (!bes_ensure_writable_directory($dir)) {
        return false;
    }
    
    // Wenn Datei existiert, prüfe Berechtigungen
    if (file_exists($file) && !is_writable($file)) {
        @chmod($file, $permissions);
        
        if (!is_writable($file)) {
            bes_debug_log("Datei nicht beschreibbar: $file", 'ERROR', 'hosting');
            return false;
        }
    }
    
    return true;
}

/**
 * Sicherer file_put_contents mit Retry-Logik
 * 
 * @param string $file Datei-Pfad
 * @param string $data Daten
 * @param int $flags Flags für file_put_contents
 * @param int $retries Anzahl Wiederholungen
 * @return int|false Geschriebene Bytes oder false
 */
function bes_safe_file_put_contents(string $file, string $data, int $flags = 0, int $retries = 3)
{
    // Stelle sicher, dass Datei beschreibbar ist
    if (!bes_ensure_writable_file($file)) {
        return false;
    }
    
    // Versuche zu schreiben mit Retry-Logik
    $attempt = 0;
    while ($attempt < $retries) {
        $result = @file_put_contents($file, $data, $flags);
        
        if ($result !== false) {
            return $result;
        }
        
        $attempt++;
        
        // Kurze Pause vor Wiederholung
        if ($attempt < $retries) {
            usleep(100000); // 0.1 Sekunden
        }
    }
    
    bes_debug_log("Konnte Datei nicht schreiben nach $retries Versuchen: $file", 'ERROR', 'hosting');
    return false;
}

/**
 * Prüft ob WP-Cron funktioniert
 * 
 * @return bool True wenn WP-Cron funktioniert
 */
function bes_check_wp_cron_works(): bool
{
    // Prüfe ob DISABLE_WP_CRON gesetzt ist
    if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
        return false;
    }
    
    // Prüfe ob echte Cron-Jobs konfiguriert sind
    // (Wenn DISABLE_WP_CRON nicht gesetzt ist, wird WP-Cron verwendet)
    return true;
}

/**
 * Gibt Empfehlungen für den Hosting-Provider zurück
 * 
 * @return array Array mit Empfehlungen
 */
function bes_get_hosting_recommendations(): array
{
    $provider = bes_detect_hosting_provider();
    $limits = bes_get_hosting_limits();
    
    $recommendations = [];
    
    // Allgemeine Empfehlungen
    if ($limits['set_time_limit_works'] === false) {
        $recommendations[] = [
            'type' => 'warning',
            'message' => 'set_time_limit() wird möglicherweise ignoriert. Verwenden Sie kleinere Batch-Größen.',
            'action' => 'Batch-Größe auf ' . $limits['batch_size_recommended'] . ' reduzieren',
        ];
    }
    
    // Provider-spezifische Empfehlungen
    switch ($provider) {
        case 'strato':
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Strato: WP-Cron hat ein 15-Minuten-Limit. Automatische Fortsetzung ist aktiviert.',
                'action' => 'Keine Aktion erforderlich',
            ];
            break;
            
        case 'all-inkl':
            $recommendations[] = [
                'type' => 'info',
                'message' => 'All-inkl: Empfohlene Batch-Größe: ' . $limits['batch_size_recommended'],
                'action' => 'Batch-Größe in Einstellungen anpassen',
            ];
            break;
            
        case 'mittwald':
            $recommendations[] = [
                'type' => 'success',
                'message' => 'Mittwald: Managed Hosting mit höheren Limits. Optimale Performance möglich.',
                'action' => 'Keine Anpassungen erforderlich',
            ];
            break;
            
        case 'ionos':
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Ionos: Standard-Limits. Batch-Größe von ' . $limits['batch_size_recommended'] . ' empfohlen.',
                'action' => 'Batch-Größe in Einstellungen anpassen',
            ];
            break;
    }
    
    return $recommendations;
}

