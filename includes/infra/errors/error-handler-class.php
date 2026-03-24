<?php
/**
 * BES_Error_Handler – zentrale Fehlerbehandlung (Klasse).
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
            : (string) $error;

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
            'unknown_error' => 'Ein unerwarteter Fehler ist aufgetreten. Bitte kontaktieren Sie den Administrator.',
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
