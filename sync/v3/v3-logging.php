<?php
/**
 * V3 Sync – Logging (aus v3-helpers ausgelagert).
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
// V3 LOGGING
// ============================================================

/**
 * Schreibt eine Zeile in eine V3-Log-Datei (sync-v3.log / debug-v3.log).
 *
 * @param string $log_file Absoluter Pfad zur Log-Datei
 * @param string $log_entry Zeile inkl. Newline
 * @return bool Erfolg
 */
function bseasy_v3_append_log_line(string $log_file, string $log_entry): bool {
    $written = bes_safe_file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    if ($written === false) {
        bes_debug_log('V3 Log-Schreiben fehlgeschlagen: ' . $log_file, 'ERROR', 'v3-log');
        return false;
    }
    return true;
}

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

    if (!bes_ensure_writable_directory(BES_DATA_V3)) {
        bes_debug_log('V3 Log-Verzeichnis nicht beschreibbar: ' . BES_DATA_V3, 'ERROR', 'v3-log');
        return false;
    }
    
    $log_file = BES_DATA_V3 . BES_V3_LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$level}] [{$context}] {$message}\n";
    
    return bseasy_v3_append_log_line($log_file, $log_entry);
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

    if (!bes_ensure_writable_directory(BES_DATA_V3)) {
        bes_debug_log('V3 Debug-Log-Verzeichnis nicht beschreibbar: ' . BES_DATA_V3, 'ERROR', 'v3-log');
        return false;
    }
    
    $log_file = BES_DATA_V3 . BES_V3_DEBUG_LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$level}] [{$context}] {$message}\n";
    
    return bseasy_v3_append_log_line($log_file, $log_entry);
}
