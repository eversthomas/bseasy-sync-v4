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
