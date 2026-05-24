<?php
/**
 * Legacy-Hilfsfunktionen für V2-Datenpfade (Kompatibilität).
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gibt das V2-Datenverzeichnis zurück (nur für Kompatibilität/Fallback)
 *
 * @deprecated V2 wird nicht mehr verwendet, nur noch für Fallback
 * @param string $version Optional, ignoriert
 * @return string Pfad zum Verzeichnis
 */
function bes_get_data_dir($version = 'v2') {
    return trailingslashit(BES_UPLOADS_DIR) . 'v2/';
}

/**
 * Lädt eine JSON-Datei aus dem V2-Datenverzeichnis (nur für Kompatibilität/Fallback)
 *
 * @deprecated V2 wird nicht mehr verwendet, nur noch für Fallback
 * @param string $file Dateiname (z.B. 'members_consent_v2.json')
 * @param string $version Optional, ignoriert
 * @return array|null Array-Daten oder null bei Fehler
 */
function bes_load_json_versioned($file, $version = 'v2') {
    $dir = bes_get_data_dir();
    $path = $dir . $file;

    if (!file_exists($path)) {
        return null;
    }

    if (function_exists('bes_load_json_from_path')) {
        return bes_load_json_from_path($path);
    }

    $content = file_get_contents($path);
    if ($content === false) {
        if (function_exists('bes_debug_log')) {
            bes_debug_log('Legacy JSON nicht lesbar: ' . $path, 'WARN', 'legacy');
        }
        return null;
    }

    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $data;
}
