<?php
/**
 * Zentrales PHP-Debug-Logging (error_log-Kanal).
 *
 * Aus includes/error-handler.php ausgelagert (Phase 5).
 *
 * @package BSEasySync
 * @author Tom Evers <https://bezugssysteme.de>
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

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
