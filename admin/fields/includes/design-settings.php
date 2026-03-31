<?php
/**
 * Compatibility-Stub: Design Settings
 *
 * Die eigentliche Implementierung liegt jetzt in includes/design/design-settings.php,
 * da die Funktionen sowohl vom Admin als auch vom Frontend benötigt werden.
 *
 * @package BSEasySync
 * @since 4.0.0
 * @deprecated Verwende includes/design/design-settings.php direkt.
 */

if (!defined('ABSPATH')) exit;

// Einmalig laden, falls noch nicht geschehen (Ladereihenfolge-Fallback)
if (!function_exists('bes_get_design_settings')) {
    require_once BES_DIR . 'includes/design/design-settings.php';
}
