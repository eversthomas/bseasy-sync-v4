<?php
/**
 * Member Repository – zentrale Datenzugriffsschicht für Mitgliederdaten.
 *
 * Kapselt das Lesen von members_consent_v3.json und stellt eine saubere
 * öffentliche API bereit. Weder Sync- noch Frontend-Code greift direkt
 * auf die JSON-Datei zu – alle Zugriffe laufen über dieses Repository.
 *
 * Abhängigkeiten: ausschließlich includes/ (safe-io.php, constants-v3.php).
 * Keine Abhängigkeit zu sync/ oder admin/.
 *
 * @package BSEasySync
 * @since 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gibt alle synchronisierten Mitglieder zurück.
 *
 * Liest members_consent_v3.json und extrahiert das `data`-Array.
 * Bei Fehler (Datei fehlt, JSON ungültig) wird ein leeres Array zurückgegeben.
 *
 * @return array Mitglieder-Array (flach, jedes Element ein Mitglied-Record)
 */
function bes_members_get_all(): array
{
    $file = bes_members_get_file_path();

    if (!bes_members_file_exists()) {
        return [];
    }

    if (function_exists('bes_safe_file_get_contents')) {
        $raw = bes_safe_file_get_contents($file, BES_DATA_V3);
    } else {
        $raw = file_get_contents($file);
        if ($raw === false) {
            if (function_exists('bes_debug_log')) {
                bes_debug_log('Lesefehler Mitglieder-Datei: ' . $file, 'ERROR', 'filesystem');
            }
            return [];
        }
    }

    if (empty($raw)) {
        return [];
    }

    try {
        $decoded = function_exists('bes_safe_json_decode')
            ? bes_safe_json_decode($raw, true)
            : (array) json_decode($raw, true);
    } catch (Exception $e) {
        if (function_exists('bes_debug_log')) {
            bes_debug_log('Member Repository: JSON-Fehler beim Lesen – ' . $e->getMessage(), 'ERROR', 'member-repository');
        }
        return [];
    }

    // V3-Format: { "_meta": {...}, "data": [...] }
    if (isset($decoded['data']) && is_array($decoded['data'])) {
        return $decoded['data'];
    }

    // Fallback: direktes Array (ältere Formate)
    if (is_array($decoded) && !empty($decoded)) {
        return $decoded;
    }

    return [];
}

/**
 * Gibt die Anzahl synchronisierter Mitglieder zurück.
 *
 * @return int Anzahl Mitglieder, 0 wenn Datei fehlt
 */
function bes_members_get_count(): int
{
    return count(bes_members_get_all());
}

/**
 * Gibt den Unix-Timestamp der letzten Änderung der Members-Datei zurück.
 * Wird für Cache-Key-Generierung verwendet.
 *
 * @return int Timestamp oder 0 wenn Datei nicht vorhanden
 */
function bes_members_get_file_mtime(): int
{
    $file = bes_members_get_file_path();
    if (!file_exists($file)) {
        return 0;
    }
    return (int) filemtime($file);
}

/**
 * Prüft ob die Members-Datei vorhanden und nicht leer ist.
 *
 * @return bool
 */
function bes_members_file_exists(): bool
{
    $file = bes_members_get_file_path();
    return file_exists($file) && filesize($file) > 0;
}

/**
 * Gibt den absoluten Pfad zur Members-Datei zurück.
 *
 * @return string
 */
function bes_members_get_file_path(): string
{
    if (defined('BES_DATA_V3') && defined('BES_V3_MEMBERS_FILE')) {
        return BES_DATA_V3 . BES_V3_MEMBERS_FILE;
    }
    // Notfall-Fallback (sollte durch Ladereihenfolge nie aufgerufen werden)
    return WP_CONTENT_DIR . '/uploads/bseasy-sync/v3/members_consent_v3.json';
}
