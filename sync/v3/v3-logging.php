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
