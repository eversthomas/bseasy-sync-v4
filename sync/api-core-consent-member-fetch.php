<?php
/**
 * BSEasy Sync - Member-ID-Sammlung
 * 
 * Enthält die Logik für die Sammlung aller Member-IDs
 * mit serverseitiger Filterung oder Fallback auf alle IDs.
 * Diese Funktion wird sowohl von V2 als auch V3 verwendet.
 * 
 * @package BSEasySync
 * @subpackage Consent
 */

if (!defined('ABSPATH')) exit;

// Lade Basis-Konstanten
if (!defined('BES_DATA')) {
    $main_file = plugin_dir_path(__DIR__) . 'bseasy-sync.php';
    if (file_exists($main_file)) {
        require_once $main_file;
    } else {
        $upload_dir = wp_upload_dir();
        define('BES_UPLOADS_DIR', trailingslashit($upload_dir['basedir']) . 'bseasy-sync/');
        define('BES_UPLOADS_URL', trailingslashit($upload_dir['baseurl']) . 'bseasy-sync/');
        define('BES_DATA', BES_UPLOADS_DIR);
        define('BES_IMG', BES_UPLOADS_DIR . 'img/');
    }
}

// Lade API-Request-Funktionen ZUERST
require_once BES_DIR . 'sync/api-core-consent-requests.php';
// api-core-consent.php wird bereits von api-core-consent-requests.php geladen

// ============================================================
// 📋 MEMBER-ID-SAMMLUNG
// ============================================================

/**
 * Sammelt alle Member-IDs für den Consent-Dump
 * 
 * Führt Phase 1 durch: Consent-Feld-ID/Name-Auflösung,
 * serverseitige Filterung (wenn möglich) oder Fallback auf alle IDs.
 * 
 * @param string $token API Token (by reference)
 * @param string|null $baseUsed Verwendete Base-URL (by reference)
 * @param array $stats Stats-Array (by reference) - wird mit consent_field_id, consent_field_name, server_filtered aktualisiert
 * @param bool $sync_all_members Wenn true, werden alle Mitglieder geladen (ohne Consent-Filter)
 * @return array Array von Member-IDs (integers)
 */
if (!function_exists('bes_consent_api_fetch_member_ids')) {
function bes_consent_api_fetch_member_ids(string &$token, ?string &$baseUsed = null, array &$stats = [], bool $sync_all_members = false): array
{
    $allMembers = [];

    // Wenn "Alle Mitglieder"-Modus aktiviert, Consent-Filter überspringen
    if ($sync_all_members) {
        $stats['server_filtered'] = false;
        $stats['consent_field_id'] = 0;
        $stats['consent_field_name'] = null;
        bes_consent_log("⚠️ WARNUNG: Sync-Modus 'Alle Mitglieder' aktiviert – lade ALLE Member-IDs ohne Consent-Filter!", 'WARN');
        
        // Lade alle Mitglieder ohne Filter (konsistent mit Fallback-Verhalten)
        $page = 1;
        $hasNext = true;

        while ($hasNext && count($allMembers) < 50000) {
            [$code, $data, $url] = bes_consent_api_safe_get_try_query('member', [
                'limit' => 100,
                'page' => $page,
                'showCount' => 'true',
                'query' => bes_consent_api_get_member_list_query()
            ], $token, $baseUsed);

            if ($code !== 200 || !is_array($data)) {
                bes_consent_log("Fehler beim Laden von Member-Liste (Seite $page): Status $code", 'ERROR');
                break;
            }

            // Normalisiere Daten (wie im Fallback-Modus) für konsistente Verarbeitung
            $list = bes_consent_norm_list($data);
            foreach ($list as $row) {
                if (!empty($row['id'])) {
                    $allMembers[] = (int)$row['id'];
                }
            }

            // Unterstütze beide Paginierungsmethoden (hasNext und next) für Robustheit
            $hasNext = false;
            if (isset($data['hasNext']) && $data['hasNext'] === true) {
                $hasNext = true;
            } elseif (isset($data['next']) && !empty($data['next'])) {
                $hasNext = true;
            }
            
            bes_consent_log("Seite $page: " . count($list) . " Mitglieder, Total: " . count($allMembers), 'INFO');
            $page++;
            usleep(200000); // Kleine Pause zwischen Requests (wie im Fallback-Modus)
        }

        bes_consent_log("✓ Alle Member-IDs gesammelt (ohne Consent-Filter): " . count($allMembers), 'INFO');
        return $allMembers;
    }

    // Consent-Feld-ID/Name-Auflösung
    $consent_field_id = (int)bes_get_consent_field_id(); // Plugin-Konfig: ID
    $stats['consent_field_id'] = $consent_field_id;

    $consent_field_name = null;
    if ($consent_field_id > 0) {
        $consent_field_name = bes_consent_api_get_custom_field_name_by_id($consent_field_id, $token, $baseUsed);
        $stats['consent_field_name'] = $consent_field_name;
    }

    if ($consent_field_name) {
        // Serverseitige Filterung
        $stats['server_filtered'] = true;
        bes_consent_log("PHASE 1: Lade NUR Consent=true Mitglieder (Field: '$consent_field_name', ID: $consent_field_id) ...");

        $rows = bes_consent_api_fetch_all_list('member', [
            'limit' => 100,
            'ordering' => 'id',
            'custom_field_name' => $consent_field_name,
            // robust: true kann als true/True/1 kommen
            'custom_field_value__in' => 'true,True',
            // optional: Bewerbungen raus
            '_isApplication' => 'false',
            // optional: nur aktive Mitglieder
            'resignationDate__isnull' => 'true',
        ], $token, $baseUsed);

        foreach ($rows as $row) {
            if (!empty($row['id'])) $allMembers[] = (int)$row['id'];
        }

        bes_consent_log("✓ Serverseitig gefilterte Mitglieder-IDs: " . count($allMembers));
    } else {
        // Fallback (wenn Name nicht auflösbar): altes Verhalten (alle IDs sammeln)
        $stats['server_filtered'] = false;
        bes_consent_log("⚠️ Consent-Feldname konnte nicht aufgelöst werden – Fallback: sammle ALLE Member-IDs und filtere lokal", 'WARN');

        $page = 1;
        $hasNext = true;

        while ($hasNext && count($allMembers) < 50000) {
            [$code, $data, $url] = bes_consent_api_safe_get_try_query('member', [
                'limit' => 100,
                'page' => $page,
                'showCount' => 'true',
                // query optional; try_query wrapper entfernt es ggf.
                'query' => bes_consent_api_get_member_list_query()
            ], $token, $baseUsed);

            if ($code !== 200 || !is_array($data)) {
                $error = "Fehler bei Seite $page: Status $code";
                bes_consent_log($error, 'ERROR');
                throw new Exception($error);
            }

            $list = bes_consent_norm_list($data);
            foreach ($list as $row) {
                if (!empty($row['id'])) $allMembers[] = (int)$row['id'];
            }

            $hasNext = isset($data['next']) && !empty($data['next']);
            bes_consent_log("Seite $page: " . count($list) . " Mitglieder, Total: " . count($allMembers));
            $page++;
            usleep(200000);
        }
    }

    return $allMembers;
}
}
