<?php
/**
 * Field Intelligence Dashboard
 * 
 * Analysiert Felder und liefert intelligente Vorschläge, Statistiken und Empfehlungen
 * 
 * @package BSEasySync
 * @since 3.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Analysiert alle Felder und liefert Intelligence-Daten
 * 
 * @return array Intelligence-Daten mit Statistiken, Vorschlägen, etc.
 */
function bes_analyze_field_intelligence(): array {
    // Lade Felder
    $config = bes_load_fields_config();
    $scan = bes_extract_all_fields();
    
    if (empty($scan['fields'])) {
        return [
            'error' => __('Keine Felder gefunden. Bitte einen Sync durchführen.', 'besync')
        ];
    }
    
    $merged = bes_merge_fields($scan['fields'], $config);
    $fields = array_values($merged);
    
    // Statistiken
    $stats = bes_calculate_field_statistics($fields);
    
    // Vorschläge
    $suggestions = bes_generate_field_suggestions($fields, $scan['fields']);
    
    // Empfehlungen
    $recommendations = bes_generate_recommendations($fields, $scan['fields']);
    
    // Duplikat-Analyse
    $duplicates = bes_find_potential_duplicates($fields);
    
    // Gruppierungs-Vorschläge
    $groupings = bes_suggest_field_groupings($fields);
    
    return [
        'stats' => $stats,
        'suggestions' => $suggestions,
        'recommendations' => $recommendations,
        'duplicates' => $duplicates,
        'groupings' => $groupings,
        'total_fields' => count($fields),
        'timestamp' => current_time('mysql')
    ];
}

/**
 * Berechnet Statistiken über die Felder
 * 
 * @param array $fields Alle Felder
 * @return array Statistiken
 */
function bes_calculate_field_statistics(array $fields): array {
    $stats = [
        'by_type' => [],
        'by_area' => ['above' => 0, 'below' => 0, 'unused' => 0],
        'by_status' => [
            'configured' => 0,
            'unconfigured' => 0,
            'ignored' => 0,
            'in_use' => 0
        ],
        'without_label' => 0,
        'without_example' => 0,
        'with_example' => 0,
        'custom_fields' => 0,
        'standard_fields' => 0,
    ];
    
    foreach ($fields as $field) {
        $type = $field['type'] ?? 'unknown';
        $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
        
        $area = $field['area'] ?? 'unused';
        if (isset($stats['by_area'][$area])) {
            $stats['by_area'][$area]++;
        }
        
        // Status
        $has_label = !empty($field['label']) && $field['label'] !== $field['id'];
        $is_configured = $has_label || $area !== 'unused' || !empty($field['show']);
        $is_in_use = in_array($area, ['above', 'below']);
        
        if ($is_configured) {
            $stats['by_status']['configured']++;
        } else {
            $stats['by_status']['unconfigured']++;
        }
        
        if (!empty($field['ignored'])) {
            $stats['by_status']['ignored']++;
        }
        
        if ($is_in_use) {
            $stats['by_status']['in_use']++;
        }
        
        // Labels & Examples
        if (!$has_label) {
            $stats['without_label']++;
        }
        
        if (empty($field['example'])) {
            $stats['without_example']++;
        } else {
            $stats['with_example']++;
        }
        
        // Custom Fields vs Standard
        if (in_array($type, ['cf', 'cfraw', 'contactcf', 'contactcfraw'])) {
            $stats['custom_fields']++;
        } else {
            $stats['standard_fields']++;
        }
    }
    
    return $stats;
}

/**
 * Generiert intelligente Vorschläge für Felder
 * 
 * @param array $merged_fields Gemergte Felder (mit Config)
 * @param array $auto_fields Auto-gescannte Felder (ohne Config)
 * @return array Vorschläge
 */
function bes_generate_field_suggestions(array $merged_fields, array $auto_fields): array {
    $suggestions = [
        'labels' => [],
        'categories' => [],
        'activation' => []
    ];
    
    foreach ($merged_fields as $field) {
        $id = $field['id'] ?? '';
        $label = $field['label'] ?? '';
        $example = $field['example'] ?? null;
        $area = $field['area'] ?? 'unused';
        $has_label = !empty($label) && $label !== $id;
        
        // Label-Vorschläge für Felder ohne Label
        if (!$has_label && $example) {
            $label_suggestion = bes_suggest_label_from_content($field);
            if ($label_suggestion) {
                $suggestions['labels'][] = [
                    'field_id' => $id,
                    'current_label' => $label,
                    'suggested_label' => $label_suggestion,
                    'confidence' => bes_calculate_label_confidence($field, $label_suggestion),
                    'reason' => bes_explain_label_suggestion($field, $label_suggestion)
                ];
            }
        }
        
        // Kategorisierungs-Vorschläge
        $category = bes_suggest_category($field);
        if ($category) {
            $suggestions['categories'][] = [
                'field_id' => $id,
                'suggested_category' => $category,
                'reason' => bes_explain_category_suggestion($field, $category)
            ];
        }
        
        // Aktivierungs-Vorschläge (Felder mit vielen Beispielwerten)
        if ($area === 'unused' && !empty($example) && !empty($field['ignored'])) {
            $suggestions['activation'][] = [
                'field_id' => $id,
                'reason' => __('Feld hat Beispielwerte und könnte nützlich sein', 'besync'),
                'example' => $example
            ];
        }
    }
    
    // Sortiere nach Confidence
    usort($suggestions['labels'], function($a, $b) {
        return ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
    });
    
    return $suggestions;
}

/**
 * Vorschlägt ein Label basierend auf Feld-Inhalt
 * 
 * @param array $field Feld-Daten
 * @return string|null Vorgeschlagenes Label
 */
function bes_suggest_label_from_content(array $field): ?string {
    $id = $field['id'] ?? '';
    $example = $field['example'] ?? null;
    $type = $field['type'] ?? '';
    
    if (!$example) {
        return null;
    }
    
    $example_str = is_array($example) ? implode(', ', $example) : (string)$example;
    
    // 1. Feld-ID hat höchste Priorität
    if (strpos($id, 'street') !== false || strpos($id, 'Straße') !== false) {
        return 'Straße';
    }
    if (strpos($id, 'familyName') !== false || strpos($id, 'Nachname') !== false) {
        return 'Nachname';
    }
    if (strpos($id, 'city') !== false || strpos($id, 'Stadt') !== false) {
        return 'Stadt';
    }
    if (strpos($id, 'zip') !== false || strpos($id, 'plz') !== false) {
        return 'PLZ';
    }
    if (strpos($id, 'email') !== false) {
        return strpos($id, 'private') !== false ? 'E-Mail (privat)' : 'E-Mail';
    }
    if (strpos($id, 'phone') !== false || strpos($id, 'tel') !== false) {
        return strpos($id, 'mobile') !== false ? 'Mobil' : 'Telefon';
    }
    
    // 2. Pattern-Erkennung für eindeutige Fälle
    if (preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $example_str)) {
        return 'E-Mail';
    }
    if (preg_match('/^https?:\/\//', $example_str) || preg_match('/^www\./', $example_str)) {
        return 'Website';
    }
    if (preg_match('/^[\d\s\+\-\(\)]+$/', $example_str) && strlen($example_str) > 5) {
        return 'Telefon';
    }
    if (preg_match('/^\d{5}$/', $example_str)) {
        return 'PLZ';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $example_str)) {
        return 'Datum';
    }
    
    return null;
}

/**
 * Berechnet Confidence-Wert für Label-Vorschlag
 * 
 * @param array $field Feld-Daten
 * @param string $suggested_label Vorgeschlagenes Label
 * @return int Confidence 0-100
 */
function bes_calculate_label_confidence(array $field, string $suggested_label): int {
    $id = $field['id'] ?? '';
    $example = $field['example'] ?? null;
    
    $confidence = 0;
    
    // Feld-ID Match = sehr hoch
    if (stripos($id, $suggested_label) !== false) {
        $confidence += 70;
    }
    
    // Pattern-Match = hoch
    if ($example) {
        $example_str = is_array($example) ? implode(', ', $example) : (string)$example;
        
        if (preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $example_str) && $suggested_label === 'E-Mail') {
            $confidence += 90;
        }
        if (preg_match('/^https?:\/\//', $example_str) && $suggested_label === 'Website') {
            $confidence += 90;
        }
        if (preg_match('/^\d{5}$/', $example_str) && $suggested_label === 'PLZ') {
            $confidence += 85;
        }
    }
    
    return min(100, $confidence);
}

/**
 * Erklärt warum ein Label vorgeschlagen wurde
 * 
 * @param array $field Feld-Daten
 * @param string $suggested_label Vorgeschlagenes Label
 * @return string Erklärung
 */
function bes_explain_label_suggestion(array $field, string $suggested_label): string {
    $id = $field['id'] ?? '';
    $example = $field['example'] ?? null;
    
    if (stripos($id, $suggested_label) !== false) {
        return sprintf(__('Feld-ID enthält "%s"', 'besync'), $suggested_label);
    }
    
    if ($example) {
        $example_str = is_array($example) ? implode(', ', $example) : (string)$example;
        
        if (preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $example_str)) {
            return __('Beispielwert ist eine E-Mail-Adresse', 'besync');
        }
        if (preg_match('/^https?:\/\//', $example_str)) {
            return __('Beispielwert ist eine URL', 'besync');
        }
        if (preg_match('/^\d{5}$/', $example_str)) {
            return __('Beispielwert ist eine 5-stellige Zahl (PLZ)', 'besync');
        }
    }
    
    return __('Basierend auf Feld-Analyse', 'besync');
}

/**
 * Vorschlägt eine Kategorie für ein Feld
 * 
 * @param array $field Feld-Daten
 * @return string|null Vorgeschlagene Kategorie
 */
function bes_suggest_category(array $field): ?string {
    $id = $field['id'] ?? '';
    $example = $field['example'] ?? null;
    
    // Kontaktinformationen
    if (stripos($id, 'email') !== false || stripos($id, 'phone') !== false || stripos($id, 'tel') !== false) {
        return 'Kontaktinformationen';
    }
    
    // Adressdaten
    if (stripos($id, 'street') !== false || stripos($id, 'city') !== false || stripos($id, 'zip') !== false || stripos($id, 'plz') !== false) {
        return 'Adressdaten';
    }
    
    // Web-Präsenz
    if (stripos($id, 'url') !== false || stripos($id, 'website') !== false || stripos($id, 'internet') !== false) {
        return 'Web-Präsenz';
    }
    
    if ($example) {
        $example_str = is_array($example) ? implode(', ', $example) : (string)$example;
        
        if (preg_match('/^https?:\/\//', $example_str) || preg_match('/^www\./', $example_str)) {
            return 'Web-Präsenz';
        }
        if (preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $example_str)) {
            return 'Kontaktinformationen';
        }
    }
    
    return null;
}

/**
 * Erklärt warum eine Kategorie vorgeschlagen wurde
 * 
 * @param array $field Feld-Daten
 * @param string $category Vorgeschlagene Kategorie
 * @return string Erklärung
 */
function bes_explain_category_suggestion(array $field, string $category): string {
    $id = $field['id'] ?? '';
    
    if (stripos($id, 'email') !== false || stripos($id, 'phone') !== false) {
        return __('Feld-ID deutet auf Kontaktinformationen hin', 'besync');
    }
    
    if (stripos($id, 'street') !== false || stripos($id, 'city') !== false) {
        return __('Feld-ID deutet auf Adressdaten hin', 'besync');
    }
    
    return __('Basierend auf Beispielwert-Analyse', 'besync');
}

/**
 * Generiert Empfehlungen für Felder
 * 
 * @param array $merged_fields Gemergte Felder
 * @param array $auto_fields Auto-gescannte Felder
 * @return array Empfehlungen
 */
function bes_generate_recommendations(array $merged_fields, array $auto_fields): array {
    $recommendations = [];
    
    // Felder mit vielen Beispielwerten, aber noch unused
    foreach ($merged_fields as $field) {
        if (($field['area'] ?? 'unused') === 'unused' && !empty($field['example']) && empty($field['ignored'])) {
            $recommendations[] = [
                'type' => 'activate',
                'field_id' => $field['id'] ?? '',
                'field_label' => $field['label'] ?? $field['id'],
                'message' => __('Dieses Feld hat Beispielwerte und könnte nützlich sein', 'besync'),
                'example' => $field['example']
            ];
        }
    }
    
    // Felder ohne Label, aber in Verwendung
    foreach ($merged_fields as $field) {
        $area = $field['area'] ?? 'unused';
        $label = $field['label'] ?? '';
        $has_label = !empty($label) && $label !== ($field['id'] ?? '');
        
        if (in_array($area, ['above', 'below']) && !$has_label) {
            $recommendations[] = [
                'type' => 'label',
                'field_id' => $field['id'] ?? '',
                'field_label' => $field['id'],
                'message' => __('Dieses Feld ist aktiv, hat aber kein benutzerfreundliches Label', 'besync')
            ];
        }
    }
    
    return $recommendations;
}

/**
 * Findet potenzielle Duplikate
 * 
 * @param array $fields Alle Felder
 * @return array Duplikat-Vorschläge
 */
function bes_find_potential_duplicates(array $fields): array {
    $duplicates = [];
    
    // Gruppiere nach ähnlichen Beispielwerten
    $example_groups = [];
    foreach ($fields as $field) {
        if (!empty($field['example'])) {
            $example_str = is_array($field['example']) ? implode(', ', $field['example']) : (string)$field['example'];
            $normalized = strtolower(trim($example_str));
            
            if (!isset($example_groups[$normalized])) {
                $example_groups[$normalized] = [];
            }
            $example_groups[$normalized][] = $field;
        }
    }
    
    // Finde Gruppen mit mehreren Feldern
    foreach ($example_groups as $normalized => $group) {
        if (count($group) > 1) {
            $duplicates[] = [
                'fields' => array_map(function($f) {
                    return [
                        'id' => $f['id'] ?? '',
                        'label' => $f['label'] ?? $f['id'],
                        'type' => $f['type'] ?? ''
                    ];
                }, $group),
                'example' => $normalized,
                'message' => __('Diese Felder haben ähnliche Beispielwerte', 'besync')
            ];
        }
    }
    
    return $duplicates;
}

/**
 * Vorschlägt Feld-Gruppierungen
 * 
 * @param array $fields Alle Felder
 * @return array Gruppierungs-Vorschläge
 */
function bes_suggest_field_groupings(array $fields): array {
    $groupings = [];
    
    // Finde Felder mit ähnlichen IDs (z.B. "Internet 1", "Internet 2")
    $id_patterns = [];
    foreach ($fields as $field) {
        $id = $field['id'] ?? '';
        
        // Extrahiere Basis-Pattern (z.B. "cf.506973" aus "cf.50697325" und "cf.50697329")
        if (preg_match('/^(cf\.|cfraw\.|contactcf\.|contactcfraw\.)(\d+)/', $id, $matches)) {
            $prefix = $matches[1];
            $number = $matches[2];
            // Nimm erste 6 Ziffern als Pattern
            $pattern = $prefix . substr($number, 0, 6);
            
            if (!isset($id_patterns[$pattern])) {
                $id_patterns[$pattern] = [];
            }
            $id_patterns[$pattern][] = $field;
        }
    }
    
    // Finde Patterns mit mehreren Feldern
    foreach ($id_patterns as $pattern => $group) {
        if (count($group) > 1) {
            // Prüfe ob Labels ähnlich sind
            $labels = array_filter(array_map(function($f) {
                return $f['label'] ?? '';
            }, $group));
            
            $similar_labels = false;
            if (count($labels) > 1) {
                $first_label = reset($labels);
                foreach ($labels as $label) {
                    if (stripos($label, $first_label) !== false || stripos($first_label, $label) !== false) {
                        $similar_labels = true;
                        break;
                    }
                }
            }
            
            if ($similar_labels || count($group) >= 2) {
                $groupings[] = [
                    'fields' => array_map(function($f) {
                        return [
                            'id' => $f['id'] ?? '',
                            'label' => $f['label'] ?? $f['id'],
                            'type' => $f['type'] ?? ''
                        ];
                    }, $group),
                    'suggested_group_name' => __('Ähnliche Felder', 'besync'),
                    'message' => __('Diese Felder könnten zusammengehören', 'besync')
                ];
            }
        }
    }
    
    return $groupings;
}




