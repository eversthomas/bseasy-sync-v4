<?php
/**
 * Debug-Dateilog und PHP-Error-/Shutdown-Handler (Bootstrap).
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Debug-Log-Funktion für JavaScript-Fehler
 * Schreibt direkt in Datei (ohne AJAX)
 *
 * @param string $message Die Log-Nachricht
 * @param string $level Log-Level: INFO, ERROR, WARN, DEBUG
 * @param string $context Kontext (z.B. 'ui.js', 'admin')
 * @return bool Erfolg
 */
function bes_write_debug_log($message, $level = 'INFO', $context = 'admin') {
    if (!defined('BES_DIR')) {
        return false;
    }

    // Defense-in-Depth: Logging-Code darf nie crashen, auch nicht bei
    // Load-Order-Fehlern. Fallback nutzt PHPs error_log() statt File-IO.
    // EARLY-Marker signalisiert, dass die Funktion vor hosting-compatibility
    // aufgerufen wurde — Hinweis auf Bootstrap-Problem.
    if (!function_exists('bes_ensure_writable_directory')) {
        error_log('[BES ' . $level . ' EARLY] ' . $message);
        return false;
    }

    // Validiere BES_DIR Pfad
    $plugin_dir = realpath(BES_DIR);
    if ($plugin_dir === false) {
        error_log("BES Debug: Ungültiger BES_DIR Pfad");
        return false;
    }

    // Prüfe ob Pfad innerhalb WP_PLUGIN_DIR liegt
    $wp_plugin_dir = realpath(WP_PLUGIN_DIR);
    if ($wp_plugin_dir === false || strpos($plugin_dir, $wp_plugin_dir) !== 0) {
        error_log("BES Debug: BES_DIR liegt außerhalb von WP_PLUGIN_DIR");
        return false;
    }

    $debug_dir = trailingslashit($plugin_dir) . 'debug/';

    if (!bes_ensure_writable_directory($debug_dir)) {
        error_log("BES Debug: Verzeichnis nicht beschreibbar: " . $debug_dir);
        return false;
    }

    // Validiere Dateiname (kein Path-Traversal)
    $log_file = $debug_dir . 'debug.log';
    if (basename($log_file) !== 'debug.log') {
        error_log("BES Debug: Ungültiger Log-Dateiname");
        return false;
    }

    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$level}] [{$context}] {$message}\n";

    $result = bes_safe_file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

    if ($result === false) {
        error_log("BES Debug: Fehler beim Schreiben in: " . $log_file);
        return false;
    }

    return true;
}

/**
 * WordPress Error Handler - fängt PHP-Fehler ab und schreibt sie in die Debug-Log-Datei
 *
 * @param int $errno Fehler-Level
 * @param string $errstr Fehler-Nachricht
 * @param string $errfile Datei, in der der Fehler auftrat
 * @param int $errline Zeile, in der der Fehler auftrat
 * @return bool true wenn Fehler behandelt wurde
 */
function bes_wp_error_handler($errno, $errstr, $errfile, $errline) {
    // Nur Fehler loggen, die nicht unterdrückt werden sollen
    if (!(error_reporting() & $errno)) {
        return false;
    }

    // Bestimme Fehler-Level
    $level = 'INFO';
    switch ($errno) {
        case E_ERROR:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_PARSE:
        case E_USER_ERROR:
            $level = 'ERROR';
            break;
        case E_WARNING:
        case E_CORE_WARNING:
        case E_COMPILE_WARNING:
        case E_USER_WARNING:
            $level = 'WARN';
            break;
        case E_NOTICE:
        case E_USER_NOTICE:
            $level = 'INFO';
            break;
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
            $level = 'INFO';
            break;
    }

    // Extrahiere Dateinamen aus vollständigem Pfad
    $file_path = defined('ABSPATH') ? str_replace(ABSPATH, '', $errfile) : $errfile;

    // Formatiere Fehlermeldung
    $error_message = sprintf(
        "PHP %s: %s in %s:%d",
        $level,
        $errstr,
        $file_path,
        $errline
    );

    // Schreibe in Debug-Log
    bes_write_debug_log($error_message, $level, 'PHP_ERROR');

    // Lass WordPress den Fehler auch normal behandeln
    return false;
}

/**
 * WordPress Shutdown Handler - fängt Fatal Errors ab
 */
function bes_wp_shutdown_handler() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
        $file_path = defined('ABSPATH') ? str_replace(ABSPATH, '', $error['file']) : $error['file'];
        bes_write_debug_log(
            sprintf(
                "FATAL ERROR: %s in %s:%d",
                $error['message'],
                $file_path,
                $error['line']
            ),
            'ERROR',
            'PHP_FATAL'
        );
    }
}

/**
 * Registriert Error-/Shutdown-Handler im Admin (wie zuvor in bseasy-sync.php).
 */
function bes_bootstrap_register_admin_error_handlers() {
    if (!defined('BES_ERROR_HANDLER_REGISTERED')) {
        // Prüfe ob wir im Admin-Bereich sind UND es kein AJAX-Request ist
        if (is_admin() && !wp_doing_ajax() && function_exists('bes_write_debug_log')) {
            // Setze Error Handler für PHP-Fehler
            set_error_handler('bes_wp_error_handler', E_ALL);

            // Setze Shutdown Handler für Fatal Errors
            register_shutdown_function('bes_wp_shutdown_handler');

            // Markiere als registriert, damit es nicht nochmal passiert
            define('BES_ERROR_HANDLER_REGISTERED', true);

            // Logge dass Error Handler registriert wurde
            bes_write_debug_log("WordPress Error Handler registriert", "INFO", "bseasy-sync.php");
        }
    }
}
