<?php
/**
 * Frontend-Fassade für Darstellungs-Einstellungen (gespeichert in der Admin-Domäne).
 *
 * Delegiert an die bestehenden Funktionen aus `admin/fields/includes/design-settings.php`,
 * sobald diese geladen sind – ohne doppelte Fachlogik.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return array Design-Einstellungen wie `bes_get_design_settings()` oder Fallback.
 */
function bes_frontend_get_design_settings(): array {
    if (function_exists('bes_get_design_settings')) {
        return bes_get_design_settings();
    }
    if (function_exists('bes_get_default_design_settings')) {
        return bes_get_default_design_settings();
    }
    return [];
}

/**
 * @param bool $with_style_tags Wie bei `bes_generate_design_css()`.
 * @return string Inline-CSS oder leer.
 */
function bes_frontend_generate_design_css(bool $with_style_tags = false): string {
    if (function_exists('bes_generate_design_css')) {
        return bes_generate_design_css($with_style_tags);
    }
    return '';
}
