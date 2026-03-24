<?php
/**
 * Consent-Sync: Health-Check und JSON-Analyse.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

// ------------------------------------------------------------
// 🛠️ ZUSÄTZLICHE DEBUG-HILFSFUNKTIONEN
// ------------------------------------------------------------

/**
 * Erstellt einen Gesundheitscheck-Report
 */
function bes_consent_health_check(): array
{
    $report = [
        'timestamp' => date('c'),
        'directories' => [
            'BES_DATA' => [
                'path' => BES_DATA,
                'exists' => is_dir(BES_DATA),
                'writable' => is_writable(BES_DATA)
            ],
            'BES_IMG' => [
                'path' => BES_IMG,
                'exists' => is_dir(BES_IMG),
                'writable' => is_writable(BES_IMG)
            ]
        ],
        'files' => [],
        'config' => [
            'debug_mode' => BES_DEBUG_MODE,
            'debug_verbose' => BES_DEBUG_VERBOSE,
            'target_fields' => BES_TARGET_CUSTOM_FIELDS,
            'consent_field' => bes_get_consent_field_id(),
            'api_bases' => BES_API_BASES
        ],
        'token' => [
            'configured' => !empty(get_option('bes_api_token', '')) && function_exists('bes_decrypt_token') && !empty(bes_decrypt_token(get_option('bes_api_token', '')))
        ]
    ];
    
    // Prüfe vorhandene JSON-Dateien (nur V2)
    foreach (glob(bes_get_data_dir() . 'members_consent_v2*.json') as $file) {
        $report['files'][basename($file)] = [
            'size' => filesize($file),
            'modified' => date('c', filemtime($file))
        ];
    }
    
    return $report;
}

/**
 * Schreibt Health-Check Report
 */
function bes_consent_write_health_check(): string
{
    $report = bes_consent_health_check();
    $file = BES_DATA . 'health_check.json';
    file_put_contents($file, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    bes_consent_log("Health-Check Report erstellt: $file");
    return $file;
}

/**
 * Analysiert Custom Fields in einem bestehenden JSON
 */
function bes_consent_analyze_existing_json(string $jsonFile): array
{
    if (!file_exists($jsonFile)) {
        return ['error' => 'Datei nicht gefunden'];
    }
    
    $data = json_decode(file_get_contents($jsonFile), true);
    if (!$data || !isset($data['data'])) {
        return ['error' => 'Ungültige JSON-Struktur'];
    }
    
    $analysis = [
        'total_members' => count($data['data']),
        'fields_found' => [],
        'field_types' => [],
        'sample_values' => []
    ];
    
    foreach ($data['data'] as $member) {
        // Analysiere Member Custom Fields
        if (isset($member['member_cf_extracted'])) {
            foreach ($member['member_cf_extracted'] as $fieldId => $fieldData) {
                if (!isset($analysis['fields_found'][$fieldId])) {
                    $analysis['fields_found'][$fieldId] = 0;
                    $analysis['field_types'][$fieldId] = [];
                    $analysis['sample_values'][$fieldId] = [];
                }
                $analysis['fields_found'][$fieldId]++;
                $analysis['field_types'][$fieldId][$fieldData['type']] = 
                    ($analysis['field_types'][$fieldId][$fieldData['type']] ?? 0) + 1;
                
                if (count($analysis['sample_values'][$fieldId]) < 5) {
                    $analysis['sample_values'][$fieldId][] = $fieldData['display_value'];
                }
            }
        }
        
        // Analysiere Contact Custom Fields
        if (isset($member['contact_cf_extracted'])) {
            foreach ($member['contact_cf_extracted'] as $fieldId => $fieldData) {
                $key = "contact_$fieldId";
                if (!isset($analysis['fields_found'][$key])) {
                    $analysis['fields_found'][$key] = 0;
                    $analysis['field_types'][$key] = [];
                    $analysis['sample_values'][$key] = [];
                }
                $analysis['fields_found'][$key]++;
                $analysis['field_types'][$key][$fieldData['type']] = 
                    ($analysis['field_types'][$key][$fieldData['type']] ?? 0) + 1;
                
                if (count($analysis['sample_values'][$key]) < 5) {
                    $analysis['sample_values'][$key][] = $fieldData['display_value'];
                }
            }
        }
    }
    
    return $analysis;
}
