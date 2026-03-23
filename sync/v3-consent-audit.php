<?php
/**
 * BSEasy Sync V3 - Consent Audit
 * 
 * Analysiert Diskrepanzen zwischen erwarteten und gefundenen Consent-Mitgliedern
 * 
 * @package BSEasySync
 * @subpackage V3
 * @since 3.0.0
 */

if (!defined('ABSPATH')) exit;

require_once BES_DIR . 'sync/v3-helpers.php';
require_once BES_DIR . 'sync/api-core-consent-requests.php';
require_once BES_DIR . 'sync/api-core-consent-member-fetch.php';

// Stelle sicher, dass bes_get_consent_field_id() verfügbar ist
if (!function_exists('bes_get_consent_field_id')) {
    require_once BES_DIR . 'sync/api-core-consent.php';
}

/**
 * Führt vollständige Consent-Audit-Analyse durch
 * 
 * @param string &$token API Token
 * @param string|null &$baseUsed Base URL
 * @return array Audit-Report
 */
function bseasy_v3_audit_consent(string &$token, ?string &$baseUsed = null): array {
    $audit = [
        'started_at' => date('c'),
        'consent_field_meta' => null,
        'server_filter_counts' => [],
        'local_check_results' => [],
        'difference_list' => [],
        'value_distribution' => [],
        'errors' => [],
    ];
    
    try {
        // ============================================================
        // SCHRITT 1: CONSENT-FIELD META IDENTIFIZIEREN
        // ============================================================
        bseasy_v3_log("AUDIT: Schritt 1 - Identifiziere Consent-Field Meta...", 'INFO');
        
        $consent_field_id = (int)bes_get_consent_field_id();
        if ($consent_field_id <= 0) {
            $audit['errors'][] = "Consent-Feld-ID nicht konfiguriert";
            return $audit;
        }
        
        [$status, $cf_data, $url] = bes_consent_api_safe_get_try_query(
            "custom-field/{$consent_field_id}",
            ['query' => '{*}'],
            $token,
            $baseUsed
        );
        
        if ($status !== 200 || !is_array($cf_data)) {
            $audit['errors'][] = "Konnte Consent-Feld Meta nicht laden (Status: $status)";
            return $audit;
        }
        
        $audit['consent_field_meta'] = [
            'id' => $consent_field_id,
            'name' => $cf_data['name'] ?? ($cf_data['fieldName'] ?? null),
            'label' => $cf_data['label'] ?? ($cf_data['displayName'] ?? null),
            'type' => $cf_data['type'] ?? ($cf_data['fieldType'] ?? ($cf_data['dataType'] ?? null)),
            'raw' => $cf_data,
        ];
        
        $consent_field_name = $audit['consent_field_meta']['name'];
        if (!$consent_field_name) {
            $audit['errors'][] = "Consent-Feld-Name konnte nicht ermittelt werden";
            return $audit;
        }
        
        bseasy_v3_log("AUDIT: Consent-Feld gefunden - ID: $consent_field_id, Name: $consent_field_name, Type: " . ($audit['consent_field_meta']['type'] ?? 'unknown'), 'INFO');
        
        // ============================================================
        // SCHRITT 2: SERVERFILTER-VERGLEICH (A/B/C)
        // ============================================================
        bseasy_v3_log("AUDIT: Schritt 2 - Serverfilter-Vergleich...", 'INFO');
        bseasy_v3_update_status(20, 100, "AUDIT: Schritt 2 - Serverfilter-Vergleich...", 'running');
        
        // A) CURRENT (wie jetzt)
        bseasy_v3_update_status(25, 100, "AUDIT: Lade IDs mit Filter A (CURRENT)...", 'running');
        $ids_a = bseasy_v3_audit_fetch_ids($consent_field_name, $token, $baseUsed, [
            'custom_field_value__in' => 'true,True',
            '_isApplication' => 'false',
            'resignationDate__isnull' => 'true',
        ], 'A_CURRENT');
        
        // B) EXPANDED VALUES
        bseasy_v3_update_status(40, 100, "AUDIT: Lade IDs mit Filter B (EXPANDED)...", 'running');
        $ids_b = bseasy_v3_audit_fetch_ids($consent_field_name, $token, $baseUsed, [
            'custom_field_value__in' => 'true,True,TRUE,1,"1"',
            '_isApplication' => 'false',
            'resignationDate__isnull' => 'true',
        ], 'B_EXPANDED_VALUES');
        
        // C) NO ACTIVE ONLY FILTER
        bseasy_v3_update_status(55, 100, "AUDIT: Lade IDs mit Filter C (NO ACTIVE)...", 'running');
        $ids_c = bseasy_v3_audit_fetch_ids($consent_field_name, $token, $baseUsed, [
            'custom_field_value__in' => 'true,True,TRUE,1,"1"',
            '_isApplication' => 'false',
            // resignationDate__isnull weggelassen
        ], 'C_NO_ACTIVE_FILTER');
        
        $audit['server_filter_counts'] = [
            'A_CURRENT' => [
                'count' => count($ids_a),
                'ids' => array_slice($ids_a, 0, 10),
                'ids_last' => array_slice($ids_a, -10),
            ],
            'B_EXPANDED_VALUES' => [
                'count' => count($ids_b),
                'ids' => array_slice($ids_b, 0, 10),
                'ids_last' => array_slice($ids_b, -10),
            ],
            'C_NO_ACTIVE_FILTER' => [
                'count' => count($ids_c),
                'ids' => array_slice($ids_c, 0, 10),
                'ids_last' => array_slice($ids_c, -10),
            ],
        ];
        
        bseasy_v3_log("AUDIT: Serverfilter - A: " . count($ids_a) . ", B: " . count($ids_b) . ", C: " . count($ids_c), 'INFO');
        
        // ============================================================
        // SCHRITT 3: LOKALER CONSENT-CHECK
        // ============================================================
        bseasy_v3_log("AUDIT: Schritt 3 - Lokaler Consent-Check für " . count($ids_a) . " IDs...", 'INFO');
        bseasy_v3_update_status(60, 100, "AUDIT: Schritt 3 - Lokaler Consent-Check für " . count($ids_a) . " IDs...", 'running');
        
        $check_results = bseasy_v3_audit_local_consent_check(
            $ids_a,
            $consent_field_id,
            $token,
            $baseUsed
        );
        
        $audit['local_check_results'] = $check_results;
        
        // ============================================================
        // SCHRITT 4: DIFFERENZLISTE (DIE 21)
        // ============================================================
        bseasy_v3_log("AUDIT: Schritt 4 - Erstelle Differenzliste...", 'INFO');
        bseasy_v3_update_status(90, 100, "AUDIT: Schritt 4 - Erstelle Differenzliste...", 'running');
        
        $difference_ids = [];
        foreach ($check_results['details'] as $detail) {
            if (!$detail['check_old']) {
                $difference_ids[] = $detail;
            }
        }
        
        $audit['difference_list'] = [
            'count' => count($difference_ids),
            'ids' => array_slice($difference_ids, 0, 25), // Erste 25 oder alle
        ];
        
        bseasy_v3_log("AUDIT: Differenzliste - " . count($difference_ids) . " IDs mit CHECK_OLD=false", 'INFO');
        
        // ============================================================
        // SCHRITT 5: VALUE-DISTRIBUTION
        // ============================================================
        $value_dist = [];
        foreach ($check_results['details'] as $detail) {
            $key = $detail['value_type'] . ':' . json_encode($detail['value']);
            if (!isset($value_dist[$key])) {
                $value_dist[$key] = [
                    'value' => $detail['value'],
                    'value_type' => $detail['value_type'],
                    'count' => 0,
                    'check_old' => 0,
                    'check_new' => 0,
                ];
            }
            $value_dist[$key]['count']++;
            if ($detail['check_old']) $value_dist[$key]['check_old']++;
            if ($detail['check_new']) $value_dist[$key]['check_new']++;
        }
        
        // Sortiere nach Häufigkeit
        uasort($value_dist, fn($a, $b) => $b['count'] <=> $a['count']);
        $audit['value_distribution'] = array_slice($value_dist, 0, 10, true);
        
        $audit['finished_at'] = date('c');
        $audit['success'] = true;
        
        bseasy_v3_update_status(100, 100, "AUDIT: Consent-Audit abgeschlossen", 'done');
        
        return $audit;
        
    } catch (Exception $e) {
        $audit['errors'][] = "Exception: " . $e->getMessage();
        $audit['finished_at'] = date('c');
        bseasy_v3_update_status(0, 100, "AUDIT: Fehler - " . $e->getMessage(), 'error');
        return $audit;
    }
}

/**
 * Holt Member-IDs mit spezifischen Filter-Parametern
 * 
 * @param string $consent_field_name Feldname
 * @param string &$token API Token
 * @param string|null &$baseUsed Base URL
 * @param array $extra_params Zusätzliche Query-Parameter
 * @param string $label Label für Logging
 * @return array Member-IDs
 */
function bseasy_v3_audit_fetch_ids(string $consent_field_name, string &$token, ?string &$baseUsed = null, array $extra_params = [], string $label = ''): array {
    $params = array_merge([
        'limit' => 100,
        'ordering' => 'id',
        'custom_field_name' => $consent_field_name,
    ], $extra_params);
    
    $rows = bes_consent_api_fetch_all_list('member', $params, $token, $baseUsed);
    
    $ids = [];
    foreach ($rows as $row) {
        if (!empty($row['id'])) {
            $ids[] = (int)$row['id'];
        }
    }
    
    bseasy_v3_log("AUDIT: $label - " . count($ids) . " IDs gefunden", 'INFO');
    
    return $ids;
}

/**
 * Führt lokalen Consent-Check für eine Liste von IDs durch
 * 
 * @param array $member_ids Member-IDs
 * @param int $consent_field_id Consent-Feld-ID
 * @param string &$token API Token
 * @param string|null &$baseUsed Base URL
 * @return array Check-Ergebnisse
 */
function bseasy_v3_audit_local_consent_check(array $member_ids, int $consent_field_id, string &$token, ?string &$baseUsed = null): array {
    $results = [
        'total_ids' => count($member_ids),
        'check_old_count' => 0,
        'check_new_count' => 0,
        'cf_not_found_count' => 0,
        'member_cf_api_error_count' => 0,
        'details' => [],
    ];
    
    $processed = 0;
    $total = count($member_ids);
    foreach ($member_ids as $member_id) {
        $processed++;
        if ($processed % 10 === 0) {
            $progress = 60 + (int)(($processed / $total) * 30); // 60-90% für Schritt 3
            bseasy_v3_log("AUDIT: Lokaler Check - $processed/$total verarbeitet", 'INFO');
            bseasy_v3_update_status($progress, 100, "AUDIT: Lokaler Check - $processed/$total verarbeitet", 'running');
        }
        
        $detail = [
            'id' => $member_id,
            'cf_found' => false,
            'member_cf_meta_ok' => true,
            'member_cf_meta_status' => null,
            'member_cf_meta_failed_page' => null,
            'value' => null,
            'value_type' => null,
            'selectedOptions_count' => 0,
            'check_old' => false,
            'check_new' => false,
        ];
        
        try {
            // Hole Member-Detail
            [$s1, $d1, $u1] = bes_consent_api_safe_get_try_query(
                "member/$member_id",
                ['query' => '{*}'],
                $token,
                $baseUsed
            );
            
            if ($s1 !== 200 || !is_array($d1)) {
                $detail['member_detail_error'] = "Status: $s1";
                $results['details'][] = $detail;
                continue;
            }
            
            // Hole member_cf
            $member_cf_meta = bes_consent_api_fetch_all_list_with_meta(
                "member/$member_id/custom-fields",
                ['limit' => 100, 'query' => '{*}'],
                $token,
                $baseUsed
            );
            
            $detail['member_cf_meta_ok'] = $member_cf_meta['meta']['ok'] ?? false;
            $detail['member_cf_meta_status'] = $member_cf_meta['meta']['last_status'] ?? null;
            $detail['member_cf_meta_failed_page'] = $member_cf_meta['meta']['failed_page'] ?? null;
            
            if (!$detail['member_cf_meta_ok']) {
                $results['member_cf_api_error_count']++;
            }
            
            $member_cf = $member_cf_meta['items'] ?? [];
            
            // Suche Consent-Feld
            foreach ($member_cf as $cf) {
                if (!isset($cf['customField'])) continue;
                
                if (!str_contains((string)$cf['customField'], (string)$consent_field_id)) {
                    continue;
                }
                
                // Consent-Feld gefunden!
                $detail['cf_found'] = true;
                $detail['value'] = $cf['value'] ?? null;
                $detail['value_type'] = isset($cf['value']) ? gettype($cf['value']) : 'null';
                $detail['selectedOptions_count'] = isset($cf['selectedOptions']) && is_array($cf['selectedOptions']) ? count($cf['selectedOptions']) : 0;
                $detail['raw_cf'] = $cf; // Vollständiger CF-Eintrag für Debugging
                
                // CHECK_OLD: aktueller Code
                $check_old = false;
                if (($cf['value'] ?? null) === true || 
                    (isset($cf['value']) && is_string($cf['value']) && strtolower(trim($cf['value'])) === 'true')) {
                    $check_old = true;
                }
                $detail['check_old'] = $check_old;
                
                // CHECK_NEW: erweiterte Prüfung
                $check_new = false;
                if (($cf['value'] ?? null) === true) {
                    $check_new = true;
                } elseif (isset($cf['value']) && is_string($cf['value'])) {
                    $val_lower = strtolower(trim($cf['value']));
                    if (in_array($val_lower, ['true', '1', 'yes', 'on'])) {
                        $check_new = true;
                    }
                } elseif (isset($cf['value']) && is_int($cf['value']) && $cf['value'] === 1) {
                    $check_new = true;
                } elseif (isset($cf['selectedOptions']) && is_array($cf['selectedOptions']) && !empty($cf['selectedOptions'])) {
                    $check_new = true;
                }
                $detail['check_new'] = $check_new;
                
                break; // Nur erstes Consent-Feld prüfen
            }
            
            if (!$detail['cf_found']) {
                $results['cf_not_found_count']++;
            }
            
            if ($detail['check_old']) {
                $results['check_old_count']++;
            }
            if ($detail['check_new']) {
                $results['check_new_count']++;
            }
            
        } catch (Exception $e) {
            $detail['exception'] = $e->getMessage();
        }
        
        $results['details'][] = $detail;
        
        usleep(100000); // Rate-Limiting
    }
    
    return $results;
}
