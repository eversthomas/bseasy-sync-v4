<?php
/**
 * Consent-Sync: Logging und Debug-Hilfen.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

// ------------------------------------------------------------
// 🪵 LOGGING & PROGRESS
// ------------------------------------------------------------
/**
 * Consent-Sync spezifisches Logging (schreibt in Datei)
 * 
 * @param string $msg Log-Nachricht
 * @param string $level Log-Level
 * @return void
 */
function bes_consent_log(string $msg, string $level = 'INFO'): void
{
    // Nutze das zentrale Debug-System für WordPress-Log
    bes_debug_log($msg, $level, 'CONSENT');
    
    // Zusätzlich in Sync-Log-Datei schreiben (für Sync-spezifische Logs)
    // Pfad-Validierung für BES_DATA
    $log_dir = realpath(BES_DATA);
    if ($log_dir === false) {
        // Versuche BES_DATA zu erstellen falls es nicht existiert
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p(BES_DATA);
            $log_dir = realpath(BES_DATA);
        }
    }
    
    // Validiere dass Pfad innerhalb WP_CONTENT_DIR liegt
    if ($log_dir === false) {
        error_log("BES Consent Log: Ungültiger BES_DATA Pfad");
        return;
    }
    
    $wp_content_dir = realpath(WP_CONTENT_DIR);
    if ($wp_content_dir === false || strpos($log_dir, $wp_content_dir) !== 0) {
        error_log("BES Consent Log: BES_DATA liegt außerhalb von WP_CONTENT_DIR");
        return;
    }
    
    // Stelle sicher dass Verzeichnis existiert mit sicheren Berechtigungen
    if (!is_dir($log_dir)) {
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p($log_dir);
        } else {
            @mkdir($log_dir, 0755, true);
        }
        @chmod($log_dir, 0755);
    }
    
    // Validiere Dateiname (kein Path-Traversal)
    $logfile = $log_dir . DIRECTORY_SEPARATOR . 'sync.log';
    if (basename($logfile) !== 'sync.log') {
        error_log("BES Consent Log: Ungültiger Log-Dateiname");
        return;
    }
    
    static $initialized = false;
    if (!$initialized) {
        $init_line = "=== New Consent Sync Run: " . date('c') . " ===\n";
        if (bes_safe_file_put_contents($logfile, $init_line, FILE_APPEND | LOCK_EX) === false) {
            bes_debug_log('Consent-Log Init fehlgeschlagen: ' . $logfile, 'ERROR', 'consent-log');
        }
        $initialized = true;
    }
    $timestamp = date('Y-m-d H:i:s');
    $formatted = "[$timestamp] [$level] [CONSENT] $msg\n";
    error_log($formatted, 3, $logfile);
    
    // In Debug-Modus auch in separate Debug-Log
    if (BES_DEBUG_MODE && $level === 'DEBUG') {
        $debugfile = $log_dir . DIRECTORY_SEPARATOR . 'debug.log';
        if (basename($debugfile) === 'debug.log') {
            error_log($formatted, 3, $debugfile);
        }
    }
}

// ------------------------------------------------------------
// 🐛 DEBUG-FUNKTIONEN
// ------------------------------------------------------------

/**
 * Loggt detaillierte Informationen über ein Custom Field
 */
function bes_debug_custom_field(array $cf, string $context = ''): void
{
    if (!BES_DEBUG_VERBOSE) return;
    
    $debugMsg = "Custom Field Debug" . ($context ? " ($context)" : "") . ":\n";
    $debugMsg .= "  - ID: " . ($cf['id'] ?? 'N/A') . "\n";
    $debugMsg .= "  - CustomField: " . json_encode($cf['customField'] ?? 'N/A') . "\n";
    $debugMsg .= "  - Value: " . json_encode($cf['value'] ?? 'N/A') . "\n";
    $debugMsg .= "  - SelectedOptions: " . json_encode($cf['selectedOptions'] ?? 'N/A') . "\n";
    $debugMsg .= "  - LastChanged: " . ($cf['_lastChanged'] ?? 'N/A');
    
    bes_consent_log($debugMsg, 'DEBUG');
}

/**
 * Erstellt einen Snapshot aller Custom Fields für ein Mitglied
 */
function bes_debug_snapshot_custom_fields(int $memberId, array $memberCF, array $contactCF): void
{
    if (!BES_DEBUG_MODE) return;
    
    $snapshotFile = BES_DATA . 'cf_snapshot_' . $memberId . '.json';
    $snapshot = [
        'member_id' => $memberId,
        'timestamp' => date('c'),
        'member_custom_fields' => $memberCF,
        'contact_custom_fields' => $contactCF
    ];
    
    file_put_contents($snapshotFile, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    bes_consent_log("Debug-Snapshot erstellt: $snapshotFile", 'DEBUG');
}

/**
 * Loggt API-Request Details
 */
function bes_debug_api_request(string $url, int $code, ?array $data = null): void
{
    if (!BES_DEBUG_VERBOSE) return;
    
    $debugMsg = "API Request:\n";
    $debugMsg .= "  URL: $url\n";
    $debugMsg .= "  Status: $code\n";
    if ($data) {
        $debugMsg .= "  Response Keys: " . implode(', ', array_keys($data));
    }
    
    bes_consent_log($debugMsg, 'DEBUG');
}
