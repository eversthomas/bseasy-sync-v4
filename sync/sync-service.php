<?php
/**
 * Sync Service – öffentliche API-Fassade für das Sync-Modul.
 *
 * Diese Datei ist der einzige erlaubte Einstiegspunkt in das Sync-Modul
 * von außen (admin/, frontend/). Sie lädt alle internen Sync-Dateien
 * und stellt benannte Wrapper-Funktionen bereit.
 *
 * WICHTIG: Nur diese Datei darf von admin/ oder frontend/ per require_once
 * eingebunden werden. Direkte Includes auf sync-interne Dateien sind
 * außerhalb von sync/ verboten.
 *
 * @package BSEasySync
 * @since 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================================
// INTERNE ABHÄNGIGKEITEN LADEN
// Alle Sync-internen Dateien werden hier gebündelt geladen.
// ============================================================

// Helpers: Logging, Persistenz, Feldoptionen
require_once __DIR__ . '/v3-helpers.php';

// Consent-API-Funktionen (bes_consent_norm_list etc.)
if (!function_exists('bes_consent_norm_list')) {
    if (!function_exists('bes_consent_api_get')) {
        require_once __DIR__ . '/api-core-consent-requests.php';
    } else {
        require_once __DIR__ . '/api-core-consent.php';
    }
}

// API Explorer
require_once __DIR__ . '/api-explorer-v3.php';

// Haupt-Sync-Engine
require_once __DIR__ . '/api-core-consent-v3.php';

// Consent-Audit
if (file_exists(__DIR__ . '/consent/consent-audit.php')) {
    require_once __DIR__ . '/consent/consent-audit.php';
}

// ============================================================
// ÖFFENTLICHE API
// Wrapper-Funktionen mit stabilen Namen. Die AJAX-Handler und
// der bootstrap/load.php rufen nur diese Funktionen auf.
// ============================================================

/**
 * Sync-Status aktualisieren.
 *
 * @param int    $progress  Aktueller Fortschritt
 * @param int    $total     Gesamtanzahl
 * @param string $message   Status-Nachricht
 * @param string $state     Status-Typ ('running', 'done', 'error', 'cancelled')
 * @param array  $extra     Zusätzliche Daten (z. B. current_part, total_parts)
 * @return bool Erfolg
 */
function bes_sync_update_status(int $progress, int $total, string $message = '', string $state = 'running', array $extra = []): bool
{
    return bseasy_v3_update_status($progress, $total, $message, $state, $extra);
}

/**
 * Sync-Durchlauf starten.
 *
 * @param int $offset Start-Offset
 * @param int $limit  Batch-Größe
 * @return array Ergebnis ['success' => bool, ...]
 */
function bes_sync_run(int $offset = 0, int $limit = 200): array
{
    return bseasy_v3_run_sync($offset, $limit);
}

/**
 * Aktuellen Sync-Status aus der Statusdatei lesen.
 *
 * @return array Status-Daten oder ['state' => 'idle'] wenn keine Datei
 */
function bes_sync_get_status(): array
{
    $file = BES_DATA_V3 . BES_V3_STATUS_FILE;
    if (!file_exists($file)) {
        return ['state' => 'idle', 'progress' => 0];
    }
    return bseasy_v3_read_json($file) ?: ['state' => 'idle', 'progress' => 0];
}

/**
 * Sync abbrechen (Status auf 'cancelled' setzen, Cron-Hook entfernen).
 */
function bes_sync_cancel(): void
{
    bseasy_v3_update_status(0, 0, 'Sync wurde gestoppt', 'cancelled');
    delete_option(BES_V3_OPTION_PREFIX . 'current_part');

    $hook = BES_V3_CRON_HOOK;
    if (function_exists('wp_unschedule_hook')) {
        wp_unschedule_hook($hook);
    } else {
        wp_clear_scheduled_hook($hook);
    }
}

/**
 * Sync zurücksetzen (Statusdatei und Part-Optionen löschen).
 */
function bes_sync_reset(): void
{
    wp_clear_scheduled_hook(BES_V3_CRON_HOOK);

    $status_file = BES_DATA_V3 . BES_V3_STATUS_FILE;
    if (file_exists($status_file)) {
        @unlink($status_file); // Legitim: Sync-Reset-Cleanup nach file_exists(), Fehler irrelevant
    }

    delete_option(BES_V3_OPTION_PREFIX . 'current_part');
    delete_option(BES_V3_OPTION_PREFIX . 'total_members');
    delete_option(BES_V3_OPTION_PREFIX . 'last_error');
    delete_option(BES_V3_OPTION_PREFIX . 'sync_started_at');
}

/**
 * Aktuelle Feldauswahl laden.
 *
 * @return array Selection-Daten mit 'fields'-Array
 */
function bes_sync_get_selection(): array
{
    return bseasy_v3_load_selection();
}

/**
 * Feldauswahl speichern.
 *
 * @param array $selection Selection-Array (muss 'fields'-Key enthalten)
 * @return bool Erfolg
 */
function bes_sync_save_selection(array $selection): bool
{
    return bseasy_v3_save_selection($selection);
}

/**
 * Part-Dateien zu finaler members.json zusammenführen.
 *
 * @return array Ergebnis ['success' => bool, 'members_count' => int, ...]
 */
function bes_sync_merge_parts(): array
{
    return bseasy_v3_merge_parts();
}

/**
 * Feldkatalog aus field_catalog_v3.json laden.
 *
 * @return array|null Katalog-Daten oder null wenn Datei fehlt
 */
function bes_sync_get_catalog(): ?array
{
    $catalog_file = BES_DATA_V3 . BES_V3_FIELD_CATALOG;
    if (!file_exists($catalog_file)) {
        return null;
    }
    return bseasy_v3_read_json($catalog_file) ?: null;
}

/**
 * Explorer starten (asynchron via WP-Cron planen).
 * Gibt false zurück, wenn der Cron-Job nicht geplant werden konnte.
 *
 * @param int  $sample_size    Anzahl Mitglieder für Stichprobe
 * @param bool $fresh_from_api Immer frische API-Daten holen
 * @return bool true wenn erfolgreich geplant
 */
function bes_explorer_schedule(int $sample_size, bool $fresh_from_api = true): bool
{
    $hook = BES_V3_EXPLORER_CRON_HOOK;
    $args = [$sample_size, $fresh_from_api];

    wp_clear_scheduled_hook($hook, $args);
    return wp_schedule_single_event(time(), $hook, $args) !== false;
}

/**
 * Explorer abbrechen.
 */
function bes_explorer_cancel(): void
{
    bseasy_v3_update_status(0, 100, 'API Explorer wurde gestoppt', 'cancelled');
    delete_option(BES_V3_OPTION_PREFIX . 'explorer_running');

    $hook = BES_V3_EXPLORER_CRON_HOOK;
    if (function_exists('wp_unschedule_hook')) {
        wp_unschedule_hook($hook);
    } else {
        wp_clear_scheduled_hook($hook);
    }
}

/**
 * Consent-Audit asynchron starten (via WP-Cron).
 *
 * @return bool true wenn erfolgreich geplant
 */
function bes_audit_schedule(): bool
{
    $hook = 'bes_run_audit_consent_v3';
    wp_clear_scheduled_hook($hook);
    return wp_schedule_single_event(time(), $hook) !== false;
}

/**
 * Audit-Status und -Ergebnis laden.
 *
 * @return array Status-Daten mit optionalem 'audit'-Key für Ergebnisse
 */
function bes_audit_get_status(): array
{
    $result = [
        'running'    => (bool) get_option(BES_V3_OPTION_PREFIX . 'audit_running', false),
        'has_result' => false,
    ];

    $audit_file = BES_DATA_V3 . 'audit_consent_v3.json';
    if (file_exists($audit_file)) {
        $result['has_result'] = true;
        $audit_data = bseasy_v3_read_json($audit_file);
        if ($audit_data) {
            $result['audit'] = $audit_data;
        }
    }

    $status_file = BES_DATA_V3 . BES_V3_STATUS_FILE;
    if (file_exists($status_file)) {
        $status_data = bseasy_v3_read_json($status_file);
        if ($status_data && isset($status_data['message'])) {
            $result['status_message'] = $status_data['message'];
            $result['state'] = $status_data['state'] ?? 'unknown';
        }
    }

    return $result;
}
