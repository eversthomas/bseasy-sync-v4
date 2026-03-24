<?php
/**
 * Consent-Sync: Extraktion, Kontakt flach, Listen-Normalisierung.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extrahiert Custom Field Werte inklusive Select Options
 * 
 * @param array $customFields Array von Custom Field Objekten
 * @param array $targetFieldIds IDs der zu extrahierenden Felder
 * @param string $token API Token für Select-Option Auflösung
 * @param string|null $baseUsed Verwendete Base-URL
 * @param string $context Kontext für Logging (z.B. "member" oder "contact")
 * @return array Assoziatives Array [field_id => extracted_data]
 */
function bes_consent_extract_custom_fields_with_options(
    array $customFields, 
    array $targetFieldIds, 
    string $token, 
    ?string &$baseUsed = null,
    string $context = 'unknown'
): array
{
    $extracted = [];
    $foundFields = [];
    
    bes_consent_log("Extrahiere Custom Fields ($context): " . count($customFields) . " Felder, Suche nach IDs: " . (empty($targetFieldIds) ? 'ALLE' : implode(', ', $targetFieldIds)));
    
    foreach ($customFields as $cfIndex => $cf) {
        // Debug: Zeige jedes Custom Field im Verbose-Modus
        if (BES_DEBUG_VERBOSE) {
            bes_debug_custom_field($cf, "$context #$cfIndex");
        }
        
        // ------------------------------------------------
        // Custom Field ID extrahieren (ROBUST wie im Analyse-Skript)
        // ------------------------------------------------
        $fieldId = null;
        
        if (isset($cf['customField']) && is_string($cf['customField'])) {
            // z.B. "https://easyverein.com/api/v2.0/custom-field/50359304"
            $fieldId = (int) basename($cf['customField']);
        }
        
        if (!$fieldId) {
            if (BES_DEBUG_VERBOSE) {
                bes_consent_log("Konnte Field-ID nicht extrahieren aus: " . json_encode($cf['customField'] ?? 'N/A'), 'DEBUG');
            }
            continue;
        }
        
        $foundFields[] = $fieldId;
        
        // ------------------------------------------------
        // FILTER: Wenn targetFieldIds LEER ist → ALLE Felder extrahieren.
        // Wenn NICHT leer → nur die angegebenen IDs.
        // ------------------------------------------------
        if (!empty($targetFieldIds) && !in_array($fieldId, $targetFieldIds, true)) {
            continue;
        }
        
        bes_consent_log("Ziel-Feld gefunden: $fieldId ($context)");
        
        // Basis-Struktur
        $result = [
            'field_id' => $fieldId,
            'record_id' => $cf['id'] ?? null,
            'type' => 'unknown',
            'raw_value' => null,
            'display_value' => null,
            'options' => [],
            'last_changed' => $cf['_lastChanged'] ?? null,
            'context' => $context
        ];
        
        // FALL 1: Auswahlfeld mit selectedOptions
        if (isset($cf['selectedOptions']) && is_array($cf['selectedOptions']) && !empty($cf['selectedOptions'])) {
            $result['type'] = 'select';
            $result['raw_value'] = $cf['selectedOptions'];
            
            bes_consent_log("Feld $fieldId ist Select-Field mit " . count($cf['selectedOptions']) . " Optionen");
            
            // OPTIMIERUNG (2025-01-27): Batch-Processing für Select-Options
            // Sammle alle Option-URLs und resolve sie in einem Batch
            $optionUrls = array_filter($cf['selectedOptions'], 'is_string');
            
            if (!empty($optionUrls)) {
                // Batch-Processing: Resolve alle Options auf einmal
                $resolvedOptionsMap = bes_consent_resolve_select_options_batch(
                    array_values($optionUrls),
                    $token,
                    $baseUsed
                );
                
                // Konvertiere Map zu Array
                $resolvedOptions = [];
                foreach ($optionUrls as $optionUrl) {
                    if (isset($resolvedOptionsMap[$optionUrl])) {
                        $resolvedOptions[] = $resolvedOptionsMap[$optionUrl];
                    } else {
                        bes_consent_log("Option konnte nicht aufgelöst werden: $optionUrl", 'WARN');
                    }
                }
            } else {
                $resolvedOptions = [];
            }
            
            $result['options'] = $resolvedOptions;
            
            // Display-Wert zusammensetzen
            $labels = array_filter(array_map(fn($opt) => $opt['label'] ?? $opt['value'] ?? null, $resolvedOptions));
            $result['display_value'] = !empty($labels) ? implode(', ', $labels) : '(leer)';
            
            bes_consent_log("✓ Field $fieldId ($context, Select): " . $result['display_value']);
        }
        // FALL 2: Einfaches value Feld (Freitext, Checkbox, etc.)
        elseif (isset($cf['value'])) {
            $value = $cf['value'];
            
            if (is_bool($value)) {
                $result['type'] = 'boolean';
                $result['raw_value'] = $value;
                $result['display_value'] = $value ? 'Ja' : 'Nein';
            } elseif (is_array($value)) {
                $result['type'] = 'array';
                $result['raw_value'] = $value;
                $result['display_value'] = json_encode($value, JSON_UNESCAPED_UNICODE);
            } else {
                $result['type'] = 'text';
                $result['raw_value'] = $value;
                $result['display_value'] = (string)$value;
            }
            
            bes_consent_log("✓ Field $fieldId ($context, {$result['type']}): " . $result['display_value']);
        }
        // FALL 3: selectedOptions existiert aber ist leer
        elseif (isset($cf['selectedOptions'])) {
            $result['type'] = 'select';
            $result['display_value'] = '(keine Auswahl)';
            bes_consent_log("Field $fieldId ($context, Select): leer");
        }
        // FALL 4: Feld existiert aber ist komplett leer
        else {
            $result['type'] = 'empty';
            $result['display_value'] = '';
            bes_consent_log("Field $fieldId ($context): komplett leer");
        }
        
        $extracted[$fieldId] = $result;
    }
    
    // Prüfe ob alle gesuchten Felder gefunden wurden
    $missingFields = [];
    if (!empty($targetFieldIds)) {
        $missingFields = array_diff($targetFieldIds, array_keys($extracted));
    }
    if (!empty($missingFields)) {
        bes_consent_log("⚠️ Fehlende Felder im $context: " . implode(', ', $missingFields), 'WARN');
        bes_consent_log("Gefundene Field-IDs im $context: " . implode(', ', array_unique($foundFields)), 'DEBUG');
    }
    
    return $extracted;
}

// ------------------------------------------------------------
// 🔒 SICHERER API-GET
// ------------------------------------------------------------
// Diese Funktion wurde nach api-core-consent-requests.php verschoben
// Die neue Version hat Token-Refresh und bessere Fehlerbehandlung

// ------------------------------------------------------------
// 📋 KONTAKTFELDER FLACH EXTRAHIEREN
// ------------------------------------------------------------
function bes_consent_extract_flat_contact(array $contact): array
{
    $fields = ['firstName', 'familyName', 'name', 'email', 'companyEmail', 'privateEmail'];
    $flat = [];
    foreach ($fields as $f) {
        $flat[$f] = $contact[$f]
            ?? ($contact['contact'][$f] ?? null)
            ?? ($contact['data'][$f] ?? null)
            ?? ($contact['data']['contact'][$f] ?? null)
            ?? null;
    }
    return array_filter($flat, fn($v) => !is_null($v) && $v !== '');
}

// V1-Funktion bes_run_consent_dump() wurde entfernt - nur noch V3 wird verwendet
// API-Basisfunktionen wurden nach api-core-consent-requests.php verschoben

// ------------------------------------------------------------
// 🧮 NORMALISIERE LISTEN (mit Tiefenlimit)
// ------------------------------------------------------------
function bes_consent_norm_list($payload, int $max_depth = 5): array
{
    // Tiefenlimit-Prüfung
    static $current_depth = 0;
    if ($current_depth >= $max_depth) {
        bes_consent_log("norm_list: Maximale Rekursionstiefe erreicht ($max_depth)", 'WARN');
        $current_depth = 0; // Reset für nächsten Aufruf
        return [];
    }
    
    if (!is_array($payload)) {
        if (BES_DEBUG_VERBOSE) {
            bes_consent_log("norm_list: Payload ist kein Array: " . gettype($payload), 'DEBUG');
        }
        $current_depth = 0; // Reset
        return [];
    }
    
    $keys = array_keys($payload);
    
    // Bereits numerisches Array
    if ($keys === range(0, count($payload) - 1)) {
        $current_depth = 0; // Reset
        return $payload;
    }
    
    // Suche nach bekannten List-Keys
    foreach (['results', 'data', 'items', 'list', 'rows'] as $k) {
        if (!empty($payload[$k]) && is_array($payload[$k])) {
            if (BES_DEBUG_VERBOSE) {
                bes_consent_log("norm_list: Liste gefunden unter Key '$k' (Tiefe: " . ($current_depth + 1) . ")", 'DEBUG');
            }
            $current_depth++;
            $result = bes_consent_norm_list($payload[$k], $max_depth);
            $current_depth = 0; // Reset nach Rückkehr
            return $result;
        }
    }
    
    if (BES_DEBUG_VERBOSE) {
        bes_consent_log("norm_list: Keine Liste gefunden, verfügbare Keys: " . implode(', ', $keys), 'DEBUG');
    }
    
    $current_depth = 0; // Reset
    return [];
}
