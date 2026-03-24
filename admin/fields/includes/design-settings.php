<?php
/**
 * BSEasy Sync - Design Settings Handler
 * 
 * Verwaltet Design-Einstellungen für Frontend-Cards
 * 
 * @package BSEasySync
 * @author Tom Evers <https://bezugssysteme.de>
 * @since 3.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Standard-Design-Einstellungen
 * 
 * @return array Standard-Design-Werte
 */
function bes_get_default_design_settings(): array {
    return [
        'card_bg' => '#ffffff',
        'card_border' => 'rgba(0,0,0,0.10)',
        'card_text' => '#1c1c1c',
        'card_link' => '#D99400', // Linkfarbe
        'card_stripe' => '#F7A600', // Linker Rahmen (::before)
        'image_shadow' => true, // Bild-Schatten aktiviert
        'button_bg' => '#F7A600',
        'button_bg_hover' => '#D99400',
        'button_text' => '#000000',
    ];
}

/**
 * Lädt gespeicherte Design-Einstellungen
 * 
 * @return array Design-Einstellungen
 */
function bes_get_design_settings(): array {
    $saved = get_option('bes_card_design_settings', []);
    $defaults = bes_get_default_design_settings();
    
    // Merge mit Defaults (sichert ab, dass alle Werte vorhanden sind)
    return wp_parse_args($saved, $defaults);
}

/**
 * Speichert Design-Einstellungen
 * 
 * @param array $settings Design-Einstellungen
 * @return bool Erfolg
 */
function bes_save_design_settings(array $settings): bool {
    $defaults = bes_get_default_design_settings();
    $sanitized = [];
    
    // Validiere und sanitize jede Einstellung
    foreach ($defaults as $key => $default_value) {
        if (isset($settings[$key])) {
            // Boolean-Werte (z.B. image_shadow)
            if (is_bool($default_value)) {
                $sanitized[$key] = (bool)$settings[$key];
            } else {
                // Farbwerte
                $color = sanitize_text_field($settings[$key]);
                // Validiere Hex-Farbcode oder rgba()
                if (bes_validate_color($color)) {
                    $sanitized[$key] = $color;
                } else {
                    // Bei ungültiger Farbe: Default verwenden
                    $sanitized[$key] = $default_value;
                }
            }
        } else {
            $sanitized[$key] = $default_value;
        }
    }
    
    // Prüfe ob sich etwas geändert hat
    $current = get_option('bes_card_design_settings', []);
    $has_changes = false;
    
    foreach ($sanitized as $key => $value) {
        if (!isset($current[$key]) || $current[$key] !== $value) {
            $has_changes = true;
            break;
        }
    }
    
    // Wenn keine Änderungen: trotzdem true zurückgeben (für Reset-Funktion)
    if (!$has_changes && !empty($current)) {
        // Cache trotzdem invalidiert durch Cache-Key-Erweiterung
        if (function_exists('bes_clear_render_cache')) {
            bes_clear_render_cache();
        }
        return true;
    }
    
    $result = update_option('bes_card_design_settings', $sanitized, false);
    
    // Cache invalidiert automatisch durch Cache-Key-Erweiterung
    // Aber zur Sicherheit: Render-Cache löschen
    if (function_exists('bes_clear_render_cache')) {
        bes_clear_render_cache();
    }
    
    return $result !== false;
}

/**
 * Validiert eine Farbangabe (Hex oder rgba)
 * 
 * @param string $color Farbwert
 * @return bool Gültig
 */
function bes_validate_color(string $color): bool {
    // Leer = ungültig
    if (empty($color)) {
        return false;
    }
    
    // Hex-Farbcode (#RRGGBB oder #RRGGBBAA)
    if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3}|[A-Fa-f0-9]{8})$/', $color)) {
        return true;
    }
    
    // rgba() Format
    if (preg_match('/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(,\s*[\d.]+\s*)?\)$/', $color)) {
        return true;
    }
    
    // rgb() Format
    if (preg_match('/^rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)$/', $color)) {
        return true;
    }
    
    // CSS-Farbnamen (basic)
    $css_colors = ['transparent', 'inherit', 'initial', 'unset'];
    if (in_array(strtolower($color), $css_colors, true)) {
        return true;
    }
    
    return false;
}

/**
 * Generiert Inline-CSS für Design-Überschreibung
 * 
 * @param bool $with_style_tags Ob <style>-Tags eingeschlossen werden sollen
 * @return string CSS-Code
 */
function bes_generate_design_css(bool $with_style_tags = false): string {
    $settings = bes_get_design_settings();
    
    $css = $with_style_tags ? '<style id="bes-card-design-override">' : '';
    $css .= ':root {';
    $css .= '  --bes-card-bg-custom: ' . esc_attr($settings['card_bg']) . ';';
    $css .= '  --bes-card-border-custom: ' . esc_attr($settings['card_border']) . ';';
    $css .= '  --bes-card-text-custom: ' . esc_attr($settings['card_text']) . ';';
    $css .= '  --bes-card-link-custom: ' . esc_attr($settings['card_link']) . ';';
    $css .= '  --bes-card-stripe-custom: ' . esc_attr($settings['card_stripe']) . ';';
    $css .= '  --bes-button-bg-custom: ' . esc_attr($settings['button_bg']) . ';';
    $css .= '  --bes-button-bg-hover-custom: ' . esc_attr($settings['button_bg_hover']) . ';';
    $css .= '  --bes-button-text-custom: ' . esc_attr($settings['button_text']) . ';';
    $css .= '}';
    
    // Überschreibe Card-Styles
    $css .= '.bes-member-card {';
    $css .= '  background: var(--bes-card-bg-custom) !important;';
    $css .= '  border-color: var(--bes-card-border-custom) !important;';
    $css .= '  color: var(--bes-card-text-custom) !important;';
    $css .= '}';
    
    // Linker Rahmen (::before)
    $css .= '.bes-member-card::before {';
    $css .= '  background: var(--bes-card-stripe-custom) !important;';
    $css .= '}';
    
    // Card-Text-Farben
    $css .= '.bes-member-card .bes-field,';
    $css .= '.bes-member-card .bes-heading {';
    $css .= '  color: var(--bes-card-text-custom) !important;';
    $css .= '}';
    
    $css .= '.bes-member-card .bes-field strong {';
    $css .= '  color: var(--bes-card-text-custom) !important;';
    $css .= '}';
    
    // Link-Farben
    $css .= '.bes-member-card .bes-field a {';
    $css .= '  color: var(--bes-card-link-custom) !important;';
    $css .= '}';
    
    // Bild-Rahmen entfernen (immer)
    $css .= '.bes-member-card .bes-member-image img {';
    $css .= '  border: none !important;';
    $css .= '}';
    
    // Bild-Schatten auf Container (wenn aktiviert)
    if (!empty($settings['image_shadow'])) {
        $css .= '.bes-member-card .bes-member-image {';
        $css .= '  overflow: visible !important;'; // WICHTIG: overflow: hidden schneidet Schatten ab!
        $css .= '  box-shadow: 0 2px 8px 0 rgba(0, 0, 0, 0.15) !important;';
        $css .= '}';
    } else {
        $css .= '.bes-member-card .bes-member-image {';
        $css .= '  box-shadow: none !important;';
        $css .= '}';
    }
    
    // Button-Styles
    $css .= '.bes-member-card .bes-toggle-btn,';
    $css .= '.bes-load-more {';
    $css .= '  background: var(--bes-button-bg-custom) !important;';
    $css .= '  color: var(--bes-button-text-custom) !important;';
    $css .= '}';
    
    $css .= '.bes-member-card .bes-toggle-btn:hover,';
    $css .= '.bes-load-more:hover {';
    $css .= '  background: var(--bes-button-bg-hover-custom) !important;';
    $css .= '}';
    
    $css .= $with_style_tags ? '</style>' : '';
    
    return $css;
}
