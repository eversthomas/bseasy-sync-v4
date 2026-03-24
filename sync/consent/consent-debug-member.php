<?php
/**
 * Consent-Sync: Debug einzelnes Mitglied.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

// 📊 DEBUG-COMMAND FÜR TESTING
// ============================================

// V1-Funktion bes_consent_merge_parts() wurde entfernt - nur noch V2 wird verwendet

/**
 * Test-Funktion: Analysiert ein einzelnes Mitglied im Detail
 * Verwendung: bes_consent_debug_single_member(123456)
 */
function bes_consent_debug_single_member(int $memberId): array
{
    $encrypted_token = get_option('bes_api_token', '');
    if (empty($encrypted_token)) {
        return ['error' => 'Kein Token'];
    }
    
    // Entschlüssele Token
    $token = function_exists('bes_decrypt_token') ? bes_decrypt_token($encrypted_token) : $encrypted_token;
    if (empty($token)) {
        return ['error' => 'Token konnte nicht entschlüsselt werden'];
    }
    
    $baseUsed = null;
    $result = [
        'member_id' => $memberId,
        'timestamp' => date('c'),
        'steps' => []
    ];
    
    try {
        // Schritt 1: Member laden
        bes_consent_log("DEBUG: Lade Mitglied $memberId");
        [$s1, $d1] = bes_consent_api_safe_get("member/$memberId", ['query' => '{*}'], $token, $baseUsed);
        $result['steps']['member_load'] = ['status' => $s1, 'has_data' => !empty($d1)];
        
        // Schritt 2: Member Custom Fields laden
        bes_consent_log("DEBUG: Lade Member Custom Fields");
        [$s2, $d2] = bes_consent_api_safe_get("member/$memberId/custom-fields", ['limit' => 400, 'query' => '{*}'], $token, $baseUsed);
        $member_cf = bes_consent_norm_list($d2 ?? []);
        $result['steps']['member_cf_load'] = [
            'status' => $s2, 
            'count' => count($member_cf),
            'raw_data' => $member_cf
        ];
        
        // Schritt 3: Extrahiere Custom Fields
        bes_consent_log("DEBUG: Extrahiere Custom Fields");
        $extracted = bes_consent_extract_custom_fields_with_options(
            $member_cf, 
            BES_TARGET_CUSTOM_FIELDS, 
            $token, 
            $baseUsed,
            'debug'
        );
        $result['steps']['extraction'] = $extracted;
        
        // Schritt 4: Consent prüfen
        $has_consent = false;
        $consent_field_id = bes_get_consent_field_id();
        foreach ($member_cf as $cf) {
            if (isset($cf['customField']) && str_contains($cf['customField'], (string)$consent_field_id)) {
                if ((isset($cf['value']) && strtolower(trim($cf['value'])) === 'true') ||
                    (isset($cf['selectedOptions']) && !empty($cf['selectedOptions']))) {
                    $has_consent = true;
                    break;
                }
            }
        }
        $result['steps']['consent_check'] = ['has_consent' => $has_consent];
        
        // Speichere Debug-Report
        $reportFile = BES_DATA . "debug_member_{$memberId}.json";
        file_put_contents($reportFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        bes_consent_log("DEBUG: Report gespeichert: $reportFile");
        
        return $result;
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
        bes_consent_log("DEBUG ERROR: " . $e->getMessage(), 'ERROR');
        return $result;
    }
}
