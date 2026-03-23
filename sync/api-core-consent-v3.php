<?php
/**
 * BSEasy Sync V3 - Hauptfunktion
 * 
 * Schlanke Sync-Pipeline die nur ausgewählte Felder synchronisiert
 * 
 * @package BSEasySync
 * @subpackage V3
 * @author Tom Evers <https://bezugssysteme.de>
 * @since 3.0.0
 */

if (!defined('ABSPATH')) exit;

// Lade Basis-Konstanten
if (!defined('BES_DATA')) {
    $main_file = plugin_dir_path(__DIR__) . 'bseasy-sync.php';
    if (file_exists($main_file)) {
        require_once $main_file;
    }
}

// Lade V3-Helpers
require_once BES_DIR . 'sync/v3-helpers.php';

// Lade generische API-Funktionen ZUERST (für API-Calls)
require_once BES_DIR . 'sync/api-core-consent-requests.php';
require_once BES_DIR . 'sync/api-core-consent-token.php';

// Lade Member-ID-Sammlung (für bes_consent_api_fetch_member_ids)
require_once BES_DIR . 'sync/api-core-consent-member-fetch.php';

// Lade gemeinsame API-Funktionen (nur lesend)
// api-core-consent.php wird bereits von api-core-consent-requests.php geladen

// ============================================================
// V3 SYNC - HAUPTFUNKTION
// ============================================================

/**
 * Führt V3 Sync-Durchlauf aus
 * 
 * @param int $offset Start-Offset
 * @param int $limit Batch-Größe
 * @return array Ergebnis
 */
function bseasy_v3_run_sync(int $offset = 0, int $limit = 200): array {
    try {
        // Stelle sicher, dass Verzeichnisse existieren
        bseasy_v3_setup_directories();
        
        // Token prüfen
        $encrypted_token = get_option('bes_api_token', '');
        if (empty($encrypted_token)) {
            return ['success' => false, 'error' => 'Kein API-Token konfiguriert'];
        }
        
        $token = function_exists('bes_decrypt_token') ? bes_decrypt_token($encrypted_token) : $encrypted_token;
        if (empty($token)) {
            return ['success' => false, 'error' => 'Token konnte nicht entschlüsselt werden'];
        }
        
        // Prüfe Consent-Feld-ID (für V3 Sync erforderlich, außer wenn "Alle Mitglieder" aktiviert)
        $sync_all_members = get_option('bes_sync_all_members', false);
        $consent_field_id = (int)bes_get_consent_field_id();
        
        if (!$sync_all_members && $consent_field_id <= 0) {
            return [
                'success' => false, 
                'error' => 'Consent-Feld-ID nicht konfiguriert. Bitte im Sync-Tab unter "Consent-Feld-ID" eintragen. Tipp: Die ID kann aus dem API Explorer-Ergebnis (field_catalog_v3.json) entnommen werden. Alternativ können Sie "Alle Mitglieder synchronisieren" aktivieren (mit entsprechender Warnung).'
            ];
        }
        
        if ($sync_all_members) {
            bseasy_v3_log("⚠️ WARNUNG: Sync-Modus 'Alle Mitglieder' aktiviert - Consent-Filter wird übersprungen!", 'WARN');
        }
        
        // Lade Selection
        $selection = bseasy_v3_load_selection();
        if (empty($selection) || empty($selection['fields'])) {
            return ['success' => false, 'error' => 'Keine Feldauswahl konfiguriert. Bitte zuerst API Explorer ausführen.'];
        }
        
        // Validiere Pflichtfelder
        $required_fields = BES_V3_REQUIRED_FIELDS;
        $missing_required = [];
        foreach ($required_fields as $req_field) {
            if (!in_array($req_field, $selection['fields'])) {
                $missing_required[] = $req_field;
            }
        }
        
        if (!empty($missing_required)) {
            // Füge fehlende Pflichtfelder automatisch hinzu
            $selection['fields'] = array_unique(array_merge($required_fields, $selection['fields']));
            bseasy_v3_save_selection($selection);
            bseasy_v3_log("Pflichtfelder automatisch hinzugefügt: " . implode(', ', $missing_required), 'INFO');
        }
        
        // Batch-Größe validieren
        $batch_size = (int)get_option(BES_V3_OPTION_PREFIX . 'batch_size', $limit);
        if ($batch_size < BES_V3_BATCH_SIZE_MIN) $batch_size = BES_V3_BATCH_SIZE_MIN;
        if ($batch_size > BES_V3_BATCH_SIZE_MAX) $batch_size = BES_V3_BATCH_SIZE_MAX;
        $limit = $batch_size;
        
        $meta = [
            'started' => date('c'),
            'offset' => $offset,
            'limit' => $limit,
            'method' => 'v3',
            'selection_hash' => md5(json_encode($selection['fields'])),
        ];
        
        $stats = [
            'members_checked' => 0,
            'members_with_consent' => 0,
            'members_without_consent' => 0,
            'throttled_members' => 0,
            'http_429_detail' => 0,
            'http_429_cf' => 0,
            'errors' => [],
        ];
        
        // Timeout-Setting
        if (function_exists('bes_safe_set_time_limit')) {
            bes_safe_set_time_limit(0);
        } else {
            @set_time_limit(0);
        }
        
        if (function_exists('bes_safe_increase_memory')) {
            bes_safe_increase_memory('256M');
        }
        
        bseasy_v3_log("V3 Sync gestartet – Offset $offset, Limit $limit", 'INFO');
        
        // ============================================================
        // PHASE 1: Member-ID-Sammlung (wie V2)
        // ============================================================
        
        $sync_all_members = get_option('bes_sync_all_members', false);
        $allMembers = bseasy_v3_fetch_member_ids($token, $baseUsed, $stats, $sync_all_members);
        $total = count($allMembers);
        $start = $offset;
        $end = min($offset + $limit, $total);
        
        $meta['range'] = [$start, $end];
        $meta['total_members'] = $total;
        
        update_option(BES_V3_OPTION_PREFIX . 'total_members', $total);
        
        $estimated_parts = $limit > 0 ? (int)ceil($total / $limit) : 1;
        $current_part = $limit > 0 ? (int)floor($offset / $limit) + 1 : 1;
        
        bseasy_v3_log("✓ IDs gesammelt: $total gesamt, verarbeite $start–$end (Durchlauf $current_part von ~$estimated_parts)", 'INFO');
        
        // Speichere current_part in Option für Status-Endpoint
        update_option(BES_V3_OPTION_PREFIX . 'current_part', $current_part);
        
        bseasy_v3_update_status($start, max($total, 1), "IDs gesammelt (V3) – Durchlauf $current_part/$estimated_parts ($start–$end von $total)", 'running', [
            'current_part' => $current_part,
            'total_parts' => $estimated_parts
        ]);
        
        // ============================================================
        // PHASE 2: Member-Detail-Verarbeitung (nur ausgewählte Felder)
        // ============================================================
        
        $filtered = bseasy_v3_process_member_batch(
            $allMembers,
            $start,
            $end,
            $total,
            $token,
            $baseUsed,
            $stats,
            $meta,
            $selection
        );
        
        // Prüfe ob Verarbeitung vorzeitig beendet wurde
        $status_file = BES_DATA_V3 . BES_V3_STATUS_FILE;
        $was_cancelled = false;
        $was_timeout = false;
        
        if (file_exists($status_file)) {
            $status_data = bseasy_v3_read_json($status_file);
            if (isset($status_data['state']) && $status_data['state'] === 'cancelled') {
                $was_cancelled = true;
            } elseif (isset($status_data['state']) && $status_data['state'] === 'paused') {
                $was_timeout = true;
            }
        }
        
        // Prüfe Zeitlimit
        if (!empty($meta['started'])) {
            $elapsed = time() - strtotime($meta['started']);
            if ($elapsed > 780) {
                $was_timeout = true;
            }
        }
        
        // Speichere unvollständige Daten falls cancelled oder timeout
        if (($was_cancelled || $was_timeout) && !empty($filtered)) {
            $partNum = (int)floor($offset / max($limit, 1)) + 1;
            $file = BES_DATA_V3 . "members_consent_v3_part{$partNum}.json";
            $payload = [
                '_meta' => array_merge($meta, [
                    'finished' => date('c'),
                    'members_total' => $total,
                    'members_checked' => $stats['members_checked'],
                    'members_with_consent' => count($filtered),
                    'throttled_members' => $stats['throttled_members'] ?? 0,
                    'http_429_detail' => $stats['http_429_detail'] ?? 0,
                    'http_429_cf' => $stats['http_429_cf'] ?? 0,
                    'incomplete' => true,
                    'stopped_at' => $end - 1,
                    'reason' => $was_cancelled ? 'cancelled' : 'timeout'
                ]),
                'data' => $filtered,
            ];
            
            bseasy_v3_safe_write_json($file, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            if ($was_cancelled) {
                bseasy_v3_log("⏸️ Sync wurde gestoppt – Teil $partNum gespeichert", 'INFO');
                return [
                    'success' => false,
                    'ok' => false,
                    'error' => 'Sync wurde gestoppt',
                    'incomplete' => true,
                    'meta' => $meta,
                    'stats' => $stats,
                    'members_total' => $total,
                    'needs_more' => false
                ];
            } else {
                bseasy_v3_log("⚠️ Zeitlimit erreicht – Teil $partNum unvollständig gespeichert", 'WARN');
                return [
                    'success' => true,
                    'ok' => true,
                    'incomplete' => true,
                    'message' => "Zeitlimit erreicht bei Mitglied " . $end,
                    'meta' => $meta,
                    'stats' => $stats,
                    'members_total' => $total,
                    'needs_more' => true
                ];
            }
        }
        
        // ============================================================
        // Part speichern
        // ============================================================
        
        $partNum = (int)floor($offset / max($limit, 1)) + 1;
        $file = BES_DATA_V3 . "members_consent_v3_part{$partNum}.json";
        
        $payload = [
            '_meta' => array_merge($meta, [
                'finished' => date('c'),
                'members_total' => $total,
                'members_checked' => $stats['members_checked'],
                'members_with_consent' => count($filtered),
                'throttled_members' => $stats['throttled_members'] ?? 0,
                'http_429_detail' => $stats['http_429_detail'] ?? 0,
                'http_429_cf' => $stats['http_429_cf'] ?? 0,
                'selection_hash' => $meta['selection_hash'],
            ]),
            'data' => $filtered,
        ];
        
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        bseasy_v3_safe_write_json($file, $json);
        
        bseasy_v3_log("✓ Part $partNum gespeichert: " . count($filtered) . " Mitglieder mit Consent", 'INFO');
        
        // Speichere current_part in Option für Status-Endpoint
        update_option(BES_V3_OPTION_PREFIX . 'current_part', $current_part);
        
        bseasy_v3_update_status($end, max($total, 1), "Durchlauf $current_part/$estimated_parts abgeschlossen (V3)", 'running', [
            'current_part' => $current_part,
            'total_parts' => $estimated_parts
        ]);
        
        // ============================================================
        // Merge am Ende
        // ============================================================
        
        if ($end >= $total) {
            bseasy_v3_log("PHASE 3: Führe alle Teile zusammen (V3)...", 'INFO');
            $merge_result = bseasy_v3_merge_parts();
            
            if (!empty($merge_result['success'])) {
                update_option(BES_V3_OPTION_PREFIX . 'last_sync_time', current_time('mysql'));
                update_option(BES_V3_OPTION_PREFIX . 'last_sync_members_with_consent', (int)($merge_result['members_count'] ?? 0));
                
                bseasy_v3_log("✓ Alle Teile zusammengeführt → members_consent_v3.json", 'INFO');
                
                if (function_exists('bes_clear_render_cache')) {
                    bes_clear_render_cache();
                }
            } else {
                bseasy_v3_log("⚠️ Zusammenführung fehlgeschlagen: " . ($merge_result['error'] ?? 'Unbekannter Fehler'), 'ERROR');
            }
        }
        
        // Berechne ob weitere Durchläufe nötig sind
        $needs_more = ($offset + $limit) < $total;
        
        return [
            'success' => true,
            'ok' => true,
            'message' => "Durchlauf $current_part/$estimated_parts abgeschlossen",
            'meta' => $meta,
            'stats' => $stats,
            'part' => $partNum,
            'total_parts' => $estimated_parts,
            'members_total' => $total,
            'needs_more' => $needs_more
        ];
        
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
        bseasy_v3_log("FATAL ERROR in V3 Sync: " . $error_msg, 'ERROR');
        
        return [
            'success' => false,
            'ok' => false,
            'error' => $error_msg,
            'meta' => $meta ?? [],
            'stats' => $stats ?? [],
        ];
    }
}

// ============================================================
// SELECTION MANAGEMENT
// ============================================================

/**
 * Lädt Feldauswahl
 * 
 * @return array Selection-Array
 */
function bseasy_v3_load_selection(): array {
    $selection_file = BES_DATA_V3 . BES_V3_SELECTION;
    
    if (file_exists($selection_file)) {
        $selection = bseasy_v3_read_json($selection_file);
        if ($selection && isset($selection['fields']) && is_array($selection['fields'])) {
            return $selection;
        }
    }
    
    // Fallback: Lade aus fields-config falls vorhanden
    $fields_config = get_option('bes_fields_config', null);
    if ($fields_config && isset($fields_config['v3_selection']) && is_array($fields_config['v3_selection'])) {
        return [
            'fields' => $fields_config['v3_selection'],
            'source' => 'fields_config',
            'updated_at' => date('c'),
        ];
    }
    
    // Leere Selection
    return [
        'fields' => BES_V3_REQUIRED_FIELDS,
        'source' => 'default',
        'updated_at' => date('c'),
    ];
}

/**
 * Speichert Feldauswahl
 * 
 * @param array $selection Selection-Array
 * @return bool Erfolg
 */
function bseasy_v3_save_selection(array $selection): bool {
    bseasy_v3_setup_directories();
    
    $selection_file = BES_DATA_V3 . BES_V3_SELECTION;
    
    if (!isset($selection['updated_at'])) {
        $selection['updated_at'] = date('c');
    }
    
    $json = json_encode($selection, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return bseasy_v3_safe_write_json($selection_file, $json);
}

// ============================================================
// V3 SYNC HELPER FUNCTIONS
// ============================================================

/**
 * Sammelt Member-IDs (wie V2)
 */
function bseasy_v3_fetch_member_ids(string &$token, ?string &$baseUsed = null, array &$stats = [], bool $sync_all_members = false): array {
    // Nutze generische API-Funktion
    return bes_consent_api_fetch_member_ids($token, $baseUsed, $stats, $sync_all_members);
}

/**
 * Verarbeitet Member-Batch (nur ausgewählte Felder)
 */
function bseasy_v3_process_member_batch(
    array $member_ids,
    int $start_index,
    int $end_index,
    int $total,
    string &$token,
    ?string &$baseUsed = null,
    array &$stats = [],
    array $meta = [],
    array $selection = []
): array {
    $filtered = [];
    $selected_fields = $selection['fields'] ?? [];
    
    bseasy_v3_log("PHASE 2: Lade Mitglieder-Details (nur ausgewählte Felder)...", 'INFO');
    
    for ($i = $start_index; $i < $end_index; $i++) {
        // Status-Update alle 10 Mitglieder (für dynamische Anzeige)
        if ($i % 10 === 0 || $i === $start_index) {
            $progress = $i + 1;
            $progress_pct = $total > 0 ? (int)(($progress / $total) * 100) : 0;
            $current_part = isset($meta['current_part']) ? $meta['current_part'] : 1;
            $total_parts = isset($meta['total_parts']) ? $meta['total_parts'] : 0;
            
            $status_msg = "Verarbeite Mitglied " . ($i + 1) . " von $total";
            if ($total_parts > 0) {
                $status_msg .= " (Durchlauf $current_part von $total_parts)";
            }
            
            bseasy_v3_update_status($progress, max($total, 1), $status_msg, 'running', [
                'current_part' => $current_part,
                'total_parts' => $total_parts
            ]);
        }
        
        // Prüfe alle 50 Mitglieder ob Sync gestoppt wurde
        if ($i % 50 === 0) {
            $status_file = BES_DATA_V3 . BES_V3_STATUS_FILE;
            if (file_exists($status_file)) {
                $status_data = bseasy_v3_read_json($status_file);
                if (isset($status_data['state']) && $status_data['state'] === 'cancelled') {
                    bseasy_v3_log("⏸️ Sync wurde gestoppt bei Mitglied $i", 'INFO');
                    bseasy_v3_update_status($i, max($total, 1), "Sync wurde gestoppt bei Mitglied " . ($i + 1), 'cancelled');
                    return $filtered;
                }
            }
        }
        
        // Prüfe Zeitlimit
        if (!empty($meta['started'])) {
            $elapsed = time() - strtotime($meta['started']);
            if ($elapsed > 780) {
                bseasy_v3_log("⚠️ Zeitlimit erreicht (13 Minuten) – stoppe bei Mitglied $i", 'WARN');
                bseasy_v3_update_status($i, max($total, 1), "Zeitlimit erreicht bei Mitglied " . ($i + 1), 'paused');
                return $filtered;
            }
        }
        
        $memberId = $member_ids[$i] ?? null;
        if (!$memberId) continue;
        
        $stats['members_checked']++;
        
        try {
            // Member laden
            [$s1, $d1, $u1] = bes_consent_api_safe_get_try_query(
                "member/$memberId",
                ['query' => '{*}'],
                $token,
                $baseUsed
            );
            
            // 429 Guard: Nicht als "without_consent" zählen, sondern throttled markieren
            if ($s1 === 429) {
                $stats['http_429_detail']++;
                $stats['throttled_members']++;
                bseasy_v3_log("429 Rate-Limit bei Member $memberId (Detail-Request) – markiere als throttled", 'WARN');
                $stats['errors'][] = "Member $memberId: Status 429 (throttled)";
                // Member nicht als "without_consent" zählen, sondern später retry
                continue;
            }
            
            if ($s1 !== 200 || !is_array($d1)) {
                bseasy_v3_log("Fehler beim Laden von Member $memberId: Status $s1", 'WARN');
                $stats['errors'][] = "Member $memberId: Status $s1";
                continue;
            }
            
            // Consent-Prüfung (nur wenn nicht "Alle Mitglieder"-Modus)
            $sync_all_members = get_option('bes_sync_all_members', false);
            $has_consent = true; // Standard: alle erlauben wenn sync_all_members aktiv
            
            if (!$sync_all_members) {
                $consent_field_id = (int)bes_get_consent_field_id();
                $member_cf_meta = bes_consent_api_fetch_all_list_with_meta(
                    "member/$memberId/custom-fields",
                    ['limit' => 100, 'query' => '{*}'],
                    $token,
                    $baseUsed
                );
                
                // 429 Guard: Wenn CF-Fetch fehlgeschlagen wegen 429, nicht als "without_consent" zählen
                $member_cf_meta_ok = $member_cf_meta['meta']['ok'] ?? false;
                $member_cf_meta_status = $member_cf_meta['meta']['last_status'] ?? null;
                
                if (!$member_cf_meta_ok && $member_cf_meta_status === 429) {
                    $stats['http_429_cf']++;
                    $stats['throttled_members']++;
                    bseasy_v3_log("429 Rate-Limit bei Member $memberId (CF-Request) – markiere als throttled", 'WARN');
                    $stats['errors'][] = "Member $memberId: CF-Request Status 429 (throttled)";
                    // Member nicht als "without_consent" zählen, sondern später retry
                    continue;
                }
                
                $member_cf = $member_cf_meta['items'];
                
                $has_consent = false;
                foreach ($member_cf as $cf) {
                    if (!isset($cf['customField'])) continue;
                    // PHP 7.4 kompatibel: strpos statt str_contains
                    if (strpos((string)$cf['customField'], (string)$consent_field_id) === false) continue;
                    
                    if (($cf['value'] ?? null) === true || 
                        (isset($cf['value']) && is_string($cf['value']) && strtolower(trim($cf['value'])) === 'true')) {
                        $has_consent = true;
                        break;
                    }
                }
            }
            
            if (!$has_consent) {
                $stats['members_without_consent']++;
                continue;
            }
            
            $stats['members_with_consent']++;
            
            // Extrahiere nur ausgewählte Felder
            // Custom Fields müssen geladen werden, auch wenn sync_all_members aktiv ist (für Feldauswahl)
            if (!isset($member_cf)) {
                $member_cf_meta = bes_consent_api_fetch_all_list_with_meta(
                    "member/$memberId/custom-fields",
                    ['limit' => 100, 'query' => '{*}'],
                    $token,
                    $baseUsed
                );
                $member_cf = $member_cf_meta['items'] ?? [];
            }
            
            $member_data = bseasy_v3_extract_selected_fields($d1, $member_cf, $selected_fields, 'member', $token, $baseUsed);
            
            // Contact Details IMMER laden (für Geocoding, Bilder und Geo-Koordinaten, unabhängig von Feldauswahl)
            $contactId = null;
            $contact_details_loaded = false;
            $d3 = null;
            
            if (isset($d1['contactDetails'])) {
                if (is_array($d1['contactDetails']) && !empty($d1['contactDetails']['id'])) {
                    $contactId = (int)$d1['contactDetails']['id'];
                } elseif (is_string($d1['contactDetails']) && $d1['contactDetails'] !== '') {
                    $contactId = (int)basename($d1['contactDetails']);
                }
            }
            
            // Contact-Daten laden (auch wenn nicht in Feldauswahl, für Geo-Koordinaten und Bilder)
            if ($contactId) {
                try {
                    [$s3, $d3, $u3] = bes_consent_api_safe_get_try_query(
                        "contact-details/$contactId",
                        ['query' => '{*}'],
                        $token,
                        $baseUsed
                    );
                    
                    if ($s3 === 200 && is_array($d3)) {
                        $contact_details_loaded = true;
                        
                        // Geo-Koordinaten IMMER extrahieren (unabhängig von Feldauswahl, analog zu Profilbild)
                        if (isset($d3['geoPositionCoords']) && is_array($d3['geoPositionCoords'])) {
                            $geo_coords = $d3['geoPositionCoords'];
                            // lat extrahieren (kein empty(), damit 0 gültig bleibt)
                            if (isset($geo_coords['lat']) && $geo_coords['lat'] !== null && $geo_coords['lat'] !== '') {
                                $member_data['contact.geoPositionCoords.lat'] = (float)$geo_coords['lat'];
                            }
                            // lng extrahieren (kein empty(), damit 0 gültig bleibt)
                            if (isset($geo_coords['lng']) && $geo_coords['lng'] !== null && $geo_coords['lng'] !== '') {
                                $member_data['contact.geoPositionCoords.lng'] = (float)$geo_coords['lng'];
                            }
                        }
                        
                        // Contact Custom Fields nur laden, wenn Contact-Felder in der Auswahl sind
                        $has_contact_fields = false;
                        foreach ($selected_fields as $field_key) {
                            if (strpos($field_key, 'contact.') === 0 || strpos($field_key, 'contactcf.') === 0) {
                                $has_contact_fields = true;
                                break;
                            }
                        }
                        
                        if ($has_contact_fields) {
                            $contact_cf_meta = bes_consent_api_fetch_all_list_with_meta(
                                "contact-details/$contactId/custom-fields",
                                ['limit' => 100, 'query' => '{*}'],
                                $token,
                                $baseUsed
                            );
                            $contact_cf = $contact_cf_meta['items'];
                            
                            // Extrahiere ausgewählte Contact-Felder
                            $contact_data = bseasy_v3_extract_selected_fields($d3, $contact_cf, $selected_fields, 'contact', $token, $baseUsed);
                            
                            // WICHTIG: Geocoding nur durchführen, wenn geoPositionCoords in der Auswahl ist
                            $contact = $d3 ?? [];
                            $needs_geocoding = in_array('contact.geoPositionCoords', $selected_fields) || in_array('geoPositionCoords', $selected_fields);
                            if ($needs_geocoding && function_exists('bes_geocode_contact_fallback')) {
                                $contact = bes_geocode_contact_fallback($contact, $baseUsed);
                                // Füge geoPositionCoords nur hinzu, wenn es in der Auswahl ist
                                if (!empty($contact['geoPositionCoords']) && is_array($contact['geoPositionCoords'])) {
                                    if (in_array('contact.geoPositionCoords', $selected_fields)) {
                                        $contact_data['geoPositionCoords'] = $contact['geoPositionCoords'];
                                    } elseif (in_array('geoPositionCoords', $selected_fields)) {
                                        $contact_data['geoPositionCoords'] = $contact['geoPositionCoords'];
                                    }
                                }
                            }
                            
                            // Füge Contact-Felder nur hinzu, wenn sie in der Auswahl sind
                            // (firstName, familyName, city werden bereits durch extract_selected_fields extrahiert, wenn sie in der Auswahl sind)
                            
                            if (!empty($contact_data)) {
                                $member_data['contact'] = $contact_data;
                            }
                        }
                    } else {
                        // Contact-details konnte nicht geladen werden (kein Abbruch)
                        bseasy_v3_log("Contact-details für Member $memberId konnte nicht geladen werden (Status: $s3)", 'WARN');
                    }
                } catch (Exception $e) {
                    // Fehler beim Laden von contact-details (kein Abbruch)
                    bseasy_v3_log("Fehler beim Laden von contact-details für Member $memberId: " . $e->getMessage(), 'WARN');
                }
            }
            
            // WICHTIG: Profilbild immer herunterladen (unabhängig von Feldauswahl)
            $img_url = null;
            if (!empty($d1['_profilePicture'])) {
                $img_url = $d1['_profilePicture'];
            } elseif ($contact_details_loaded && !empty($d3['_profilePicture'])) {
                $img_url = $d3['_profilePicture'];
            }
            
            if (!empty($img_url)) {
                $img_id = $memberId;
                
                // Stelle sicher, dass BES_IMG definiert ist
                if (!defined('BES_IMG')) {
                    if (defined('BES_UPLOADS_DIR')) {
                        define('BES_IMG', trailingslashit(BES_UPLOADS_DIR) . 'img/');
                    } else {
                        $upload_dir = wp_upload_dir();
                        define('BES_IMG', trailingslashit($upload_dir['basedir']) . 'bseasy-sync/img/');
                    }
                }
                
                if (!is_dir(BES_IMG)) {
                    if (function_exists('wp_mkdir_p')) {
                        wp_mkdir_p(BES_IMG);
                    } else {
                        @mkdir(BES_IMG, 0755, true);
                    }
                }
                
                $response = wp_remote_get($img_url, [
                    'timeout' => 30,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'image/*'
                    ]
                ]);
                
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $body = wp_remote_retrieve_body($response);
                    $headers = wp_remote_retrieve_headers($response);
                    $content_type = $headers['content-type'] ?? 'image/jpeg';
                    // PHP 7.4 kompatibel: if/elseif statt match
                    if (strpos($content_type, 'png') !== false) {
                        $ext = 'png';
                    } elseif (strpos($content_type, 'webp') !== false) {
                        $ext = 'webp';
                    } elseif (strpos($content_type, 'gif') !== false) {
                        $ext = 'gif';
                    } elseif (strpos($content_type, 'jpeg') !== false) {
                        $ext = 'jpg';
                    } elseif (strpos($content_type, 'jpg') !== false) {
                        $ext = 'jpg';
                    } else {
                        $ext = 'jpg'; // Fallback
                    }
                    
                    // Lösche alte Dateien
                    $old_files = glob(BES_IMG . $img_id . '.*');
                    if ($old_files !== false) {
                        foreach ($old_files as $old_file) @unlink($old_file);
                    }
                    
                    $img_path = BES_IMG . $img_id . '.' . $ext;
                    $write_result = function_exists('bes_safe_file_put_contents')
                        ? bes_safe_file_put_contents($img_path, $body)
                        : @file_put_contents($img_path, $body);
                    
                    if ($write_result !== false) {
                        bseasy_v3_log("✓ Profilbild für Member $memberId heruntergeladen", 'INFO');
                    }
                }
            }
            
            // Füge syncedAt hinzu
            $member_data['syncedAt'] = date('c');
            
            $filtered[] = $member_data;
            
            usleep(150000);
            
        } catch (Exception $e) {
            bseasy_v3_log("EXCEPTION bei Member $memberId: " . $e->getMessage(), 'ERROR');
            $stats['errors'][] = "Member $memberId: " . $e->getMessage();
            continue;
        }
    }
    
    return $filtered;
}

/**
 * Holt Wert aus verschachtelter Datenstruktur via Pfad
 * 
 * Unterstützt:
 * - foo.bar (Objekt/Array verschachtelt)
 * - foo[] (Array von Scalars oder Objekten)
 * - foo[].bar (map: bar aus jedem Element)
 * - mehrere [] Segmente (z.B. a[].b[].c)
 * 
 * @param array $data Daten-Array
 * @param string $path Pfad (z.B. "addresses[].city" oder "organization.name")
 * @return mixed|null Scalar/Array/Object oder null wenn Pfad nicht existiert
 *                    Bei Pfaden mit []: immer Array (auch wenn 1 Element), leeres Array, oder null
 */
function bseasy_v3_get_path_value(array $data, string $path) {
    if (empty($path)) {
        return null;
    }
    
    // Direkter Key (kein Punkt, kein [])
    if (strpos($path, '.') === false && strpos($path, '[]') === false) {
        return $data[$path] ?? null;
    }
    
    // Pfad aufteilen
    $parts = explode('.', $path);
    $current = $data;
    
    foreach ($parts as $idx => $part) {
        // Array-Marker [] behandeln
        if (strpos($part, '[]') !== false) {
            $key = str_replace('[]', '', $part);
            
            // Key existiert nicht oder ist kein Array
            if (!isset($current[$key]) || !is_array($current[$key])) {
                return null;
            }
            
            $array_data = $current[$key];
            
            // Leeres Array
            if (empty($array_data)) {
                return [];
            }
            
            // Nächster Teil im Pfad (nach [])
            $next_part = $parts[$idx + 1] ?? null;
            
            if ($next_part) {
                // Verschachtelt: sammle Werte aus allen Elementen
                $values = [];
                foreach ($array_data as $item) {
                    if (!is_array($item)) {
                        // Scalar: überspringe (sollte nicht passieren bei [].bar)
                        continue;
                    }
                    
                    // Rekursiver Aufruf für nächsten Teil
                    $nested_value = bseasy_v3_get_path_value($item, $next_part);
                    if ($nested_value !== null) {
                        // Wenn Array zurückgegeben wird, merge es
                        if (is_array($nested_value) && !empty($nested_value)) {
                            $values = array_merge($values, is_array($nested_value[0] ?? null) ? $nested_value : [$nested_value]);
                        } else {
                            $values[] = $nested_value;
                        }
                    }
                }
                
                // Überspringe restliche Teile (bereits verarbeitet)
                $remaining_parts = array_slice($parts, $idx + 2);
                if (!empty($remaining_parts)) {
                    // Weitere Verschachtelung: rekursiv für jedes gesammelte Element
                    $final_values = [];
                    foreach ($values as $val) {
                        if (is_array($val)) {
                            $deep_value = bseasy_v3_get_path_value($val, implode('.', $remaining_parts));
                            if ($deep_value !== null) {
                                if (is_array($deep_value) && !empty($deep_value)) {
                                    $final_values = array_merge($final_values, is_array($deep_value[0] ?? null) ? $deep_value : [$deep_value]);
                                } else {
                                    $final_values[] = $deep_value;
                                }
                            }
                        }
                    }
                    $values = $final_values;
                }
                
                // Gib Array zurück (auch wenn 1 Element)
                return !empty($values) ? $values : [];
            } else {
                // Kein nächster Teil: gib gesamtes Array zurück
                return $array_data;
            }
        }
        
        // Normaler Key
        if (!isset($current[$part])) {
            return null;
        }
        
        $current = $current[$part];
        
        // Wenn nicht mehr verschachtelt und letzter Teil: return
        if (!is_array($current) && $idx === count($parts) - 1) {
            return $current;
        }
        
        // Wenn nicht mehr verschachtelt aber noch Teile übrig: null
        if (!is_array($current)) {
            return null;
        }
    }
    
    return $current;
}

/**
 * Extrahiert nur ausgewählte Felder
 */
function bseasy_v3_extract_selected_fields(
    array $member_data,
    array $custom_fields,
    array $selected_fields,
    string $type,
    string &$token,
    ?string &$baseUsed = null
): array {
    $result = [];
    
    foreach ($selected_fields as $field_key) {
        // Prüfe ob Feld zu diesem Typ gehört
        // Erlaube auch Felder ohne Präfix (z.B. syncedAt) wenn type='member'
        if ($type === 'member' && strpos($field_key, 'member.') !== 0 && strpos($field_key, 'cf.') !== 0 && strpos($field_key, 'contactcf.') !== 0 && strpos($field_key, 'contact.') !== 0) {
            // Feld ohne Präfix - prüfe ob es direkt im member_data existiert
            $value = bseasy_v3_get_path_value($member_data, $field_key);
            if ($value !== null && !(is_array($value) && count($value) === 0)) {
                $result[$field_key] = $value;
            }
            continue;
        }
        
        // Für contact-Felder: nur contact.*, cf.* oder contactcf.* erlauben
        if ($type === 'contact' && strpos($field_key, 'contact.') !== 0 && strpos($field_key, 'cf.') !== 0 && strpos($field_key, 'contactcf.') !== 0) {
            continue;
        }
        
        // Für member-Felder: nur member.*, cf.* oder contactcf.* erlauben (außer Felder ohne Präfix, die oben behandelt wurden)
        if ($type === 'member' && strpos($field_key, 'member.') !== 0 && strpos($field_key, 'cf.') !== 0 && strpos($field_key, 'contactcf.') !== 0) {
            continue;
        }
        
        // Custom Fields: cf.* oder contactcf.*
        if (strpos($field_key, 'cf.') === 0 || strpos($field_key, 'contactcf.') === 0) {
            // Extrahiere CF-ID
            $cf_id = (int)str_replace(['cf.', 'contactcf.'], '', $field_key);
            
            foreach ($custom_fields as $cf) {
                $cf_field_id = null;
                if (isset($cf['customField'])) {
                    if (is_numeric($cf['customField'])) {
                        $cf_field_id = (int)$cf['customField'];
                    } elseif (is_string($cf['customField'])) {
                        $cf_field_id = (int)basename($cf['customField']);
                    }
                }
                
                if ($cf_field_id === $cf_id) {
                    // Nutze Helper für effective value (berücksichtigt selectedOptions + Labels)
                    // Übergebe CF-ID für CF-spezifische Label-Auflösung
                    $result[$field_key] = bseasy_v3_get_cf_effective_value($cf, $cf_id, $token, $baseUsed);
                    break;
                }
            }
        } else {
            // Standard-Feld (kann verschachtelt sein)
            $field_name = str_replace($type . '.', '', $field_key);
            
            // Versuche verschachtelte Pfad-Auflösung
            $value = bseasy_v3_get_path_value($member_data, $field_name);
            if ($value !== null && !(is_array($value) && count($value) === 0)) {
                $result[$field_key] = $value;
            }
        }
    }
    
    return $result;
}

/**
 * Führt V3 Parts zusammen
 */
function bseasy_v3_merge_parts(): array {
    $merge_lock_key = 'v3_merge';
    $merge_lock_timeout = 300;
    
    if (bseasy_v3_is_locked($merge_lock_key)) {
        return [
            'success' => false,
            'error' => 'Zusammenführung läuft bereits. Bitte warten Sie einige Minuten.'
        ];
    }
    
    bseasy_v3_set_lock($merge_lock_key, $merge_lock_timeout);
    bseasy_v3_log("Merge V3 gestartet - Lock gesetzt", 'INFO');
    
    try {
        $merged = [];
        $mergedMeta = [
            'merged_at' => date('c'),
            'parts_count' => 0,
            'total_members' => 0,
            'method' => 'v3',
            'selection_hash' => null,
            'errors' => [],
        ];
        
        // Finde alle Part-Dateien dynamisch (unabhängig von Batch-Größe)
        // Statt fester Schleife bis 50 verwenden wir glob() um alle Parts zu finden
        $part_files = glob(BES_DATA_V3 . 'members_consent_v3_part*.json');
        
        if (empty($part_files) || !is_array($part_files)) {
            bseasy_v3_release_lock($merge_lock_key);
            return [
                'success' => false,
                'error' => 'Keine Parts zum Zusammenführen gefunden.'
            ];
        }
        
        // Sortiere Part-Dateien nach Part-Nummer (für korrekte Reihenfolge beim Merge)
        usort($part_files, function($a, $b) {
            // Extrahiere Part-Nummer aus Dateinamen: members_consent_v3_part{nummer}.json
            preg_match('/part(\d+)\.json$/', $a, $matchA);
            preg_match('/part(\d+)\.json$/', $b, $matchB);
            $numA = isset($matchA[1]) ? (int)$matchA[1] : 0;
            $numB = isset($matchB[1]) ? (int)$matchB[1] : 0;
            return $numA <=> $numB;
        });
        
        bseasy_v3_log("Merge: " . count($part_files) . " Part-Dateien gefunden", 'INFO');
        
        // Verarbeite alle gefundenen Part-Dateien
        foreach ($part_files as $path) {
            if (!file_exists($path)) continue;
            
            $data = bseasy_v3_read_json($path);
            if (!$data) continue;
            
            if (isset($data['data']) && is_array($data['data'])) {
                $merged = array_merge($merged, $data['data']);
                $mergedMeta['parts_count']++;
                
                if (isset($data['_meta']) && is_array($data['_meta'])) {
                    $part_meta = $data['_meta'];
                    
                    if (isset($part_meta['selection_hash']) && !$mergedMeta['selection_hash']) {
                        $mergedMeta['selection_hash'] = $part_meta['selection_hash'];
                    }
                    
                    if (isset($part_meta['members_total'])) {
                        if (!isset($mergedMeta['total_members']) || (int)$part_meta['members_total'] > (int)$mergedMeta['total_members']) {
                            $mergedMeta['total_members'] = (int)$part_meta['members_total'];
                        }
                    }
                }
            }
        }
        
        $mergedMeta['total_members'] = count($merged);
        $mergedMeta['generated_at'] = date('c');
        
        $mergedFile = BES_DATA_V3 . BES_V3_MEMBERS_FILE;
        $mergedPayload = [
            '_meta' => $mergedMeta,
            'data' => $merged
        ];
        
        $json_data = json_encode($mergedPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json_data === false) {
            bseasy_v3_release_lock($merge_lock_key);
            return [
                'success' => false,
                'error' => 'JSON-Encoding-Fehler: ' . json_last_error_msg()
            ];
        }
        
        if (!bseasy_v3_safe_write_json($mergedFile, $json_data)) {
            bseasy_v3_release_lock($merge_lock_key);
            return [
                'success' => false,
                'error' => 'Konnte Datei nicht schreiben: ' . $mergedFile
            ];
        }
        
        bseasy_v3_release_lock($merge_lock_key);
        
        update_option(BES_V3_OPTION_PREFIX . 'last_sync_time', date('Y-m-d H:i:s'));
        update_option(BES_V3_OPTION_PREFIX . 'last_sync_members_with_consent', count($merged));
        
        if (function_exists('bes_clear_render_cache')) {
            bes_clear_render_cache();
        }
        
        bseasy_v3_log("✓ Merge V3 erfolgreich: " . count($merged) . " Mitglieder", 'INFO');
        
        return [
            'success' => true,
            'message' => sprintf(
                'Zusammenführung erfolgreich: %d Mitglieder in %d Parts zusammengeführt.',
                count($merged),
                $mergedMeta['parts_count']
            ),
            'members_count' => count($merged),
            'parts_count' => $mergedMeta['parts_count']
        ];
        
    } catch (Exception $e) {
        bseasy_v3_release_lock($merge_lock_key);
        bseasy_v3_log("EXCEPTION in Merge V3: " . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'error' => 'Fehler beim Zusammenführen: ' . $e->getMessage()
        ];
    }
}
