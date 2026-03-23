<?php
/**
 * BSEasy Sync - Intelligente Label-Generierung
 * 
 * Generiert benutzerfreundliche Labels basierend auf:
 * - Feld-ID und Typ
 * - Beispielwerten
 * - Bekannten Feldnamen-Mustern
 * 
 * @package BSEasySync
 * @since 3.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Generiert ein benutzerfreundliches Label für ein Feld
 * 
 * @param array $field Feld-Daten mit 'id', 'type', 'example'
 * @return string Generiertes Label
 */
function bes_generate_field_label(array $field): string
{
    $id = $field['id'] ?? '';
    $type = $field['type'] ?? 'unknown';
    $example = $field['example'] ?? null;
    
    // 1. Bekannte Feldnamen-Mappings
    $known_fields = bes_get_known_field_mappings();
    if (isset($known_fields[$id])) {
        return $known_fields[$id];
    }
    
    // 2. Versuche Label aus Beispielwert abzuleiten
    if ($example !== null && !empty($example)) {
        $label_from_example = bes_extract_label_from_example($example, $id);
        if ($label_from_example) {
            return $label_from_example;
        }
    }
    
    // 3. Generiere Label aus ID-Struktur
    $label_from_id = bes_generate_label_from_id($id, $type);
    
    return $label_from_id;
}

/**
 * Bekannte Feldnamen-Mappings
 * 
 * @return array Mapping von Feld-ID zu Label
 */
function bes_get_known_field_mappings(): array
{
    return [
        // Member-Felder
        'member.id' => 'Mitglieds-ID',
        'member.membershipNumber' => 'Mitgliedsnummer',
        'member.membershipStatus' => 'Mitgliedsstatus',
        'member.membershipType' => 'Mitgliedstyp',
        'member.joinDate' => 'Beitrittsdatum',
        'member.exitDate' => 'Austrittsdatum',
        
        // Contact-Felder
        'contact.firstName' => 'Vorname',
        'contact.familyName' => 'Nachname',
        'contact.name' => 'Name',
        'contact.email' => 'E-Mail',
        'contact.companyEmail' => 'E-Mail (Firma)',
        'contact.privateEmail' => 'E-Mail (privat)',
        'contact.phone' => 'Telefon',
        'contact.mobilePhone' => 'Mobil',
        'contact.street' => 'Straße',
        'contact.zip' => 'PLZ',
        'contact.city' => 'Ort',
        'contact.country' => 'Land',
        'contact.bio' => 'Biografie',
        'contact.birthday' => 'Geburtstag',
        'contact.gender' => 'Geschlecht',
        
        // Häufige Custom Field Patterns
        'cf.50359307' => 'Online Angebote',
        'cf.50697357' => 'Bereitschaftsdienst',
    ];
}

/**
 * Extrahiert Label-Hinweise aus Beispielwerten
 * 
 * @param mixed $example Beispielwert
 * @param string $field_id Feld-ID
 * @return string|null Generiertes Label oder null
 */
function bes_extract_label_from_example($example, string $field_id): ?string
{
    if (is_array($example)) {
        $example = implode(', ', $example);
    }
    
    $example_str = (string) $example;
    
    // Erkenne häufige Muster
    $patterns = [
        // E-Mail
        '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/' => 'E-Mail',
        
        // Telefon
        '/^[\d\s\+\-\(\)]+$/' => 'Telefon',
        
        // URL
        '/^https?:\/\//' => 'Website',
        
        // Datum
        '/^\d{4}-\d{2}-\d{2}/' => 'Datum',
        
        // Boolean
        '/^(ja|nein|yes|no|true|false|1|0)$/i' => 'Ja/Nein',
        
        // PLZ
        '/^\d{5}$/' => 'PLZ',
    ];
    
    foreach ($patterns as $pattern => $label) {
        if (preg_match($pattern, $example_str)) {
            // Versuche spezifischeres Label aus ID zu generieren
            if (strpos($field_id, 'email') !== false) {
                return 'E-Mail';
            }
            if (strpos($field_id, 'phone') !== false || strpos($field_id, 'tel') !== false) {
                return 'Telefon';
            }
            if (strpos($field_id, 'zip') !== false || strpos($field_id, 'plz') !== false) {
                return 'PLZ';
            }
            return $label;
        }
    }
    
    // Wenn Beispiel sehr kurz ist, könnte es ein Label sein
    if (strlen($example_str) < 50 && !is_numeric($example_str)) {
        // Prüfe ob es wie ein Label aussieht (keine Sonderzeichen, erste Buchstaben groß)
        if (preg_match('/^[A-ZÄÖÜ][a-zäöüß\s]+$/', $example_str)) {
            return ucfirst(trim($example_str));
        }
    }
    
    return null;
}

/**
 * Generiert Label aus Feld-ID
 * 
 * @param string $id Feld-ID
 * @param string $type Feld-Typ
 * @return string Generiertes Label
 */
function bes_generate_label_from_id(string $id, string $type): string
{
    // Entferne Präfixe
    $clean_id = $id;
    $prefixes = ['member.', 'contact.', 'cf.', 'cfraw.', 'contactcf.', 'contactcfraw.', 'consent.'];
    foreach ($prefixes as $prefix) {
        if (strpos($clean_id, $prefix) === 0) {
            $clean_id = substr($clean_id, strlen($prefix));
            break;
        }
    }
    
    // CamelCase zu lesbarem Text
    $label = bes_camelcase_to_label($clean_id);
    
    // Typ-spezifische Präfixe
    $type_labels = [
        'member' => 'Mitglied: ',
        'contact' => 'Kontakt: ',
        'cf' => 'Custom Field: ',
        'cfraw' => 'Custom Field (roh): ',
        'contactcf' => 'Kontakt Custom: ',
        'contactcfraw' => 'Kontakt Custom (roh): ',
        'consent' => 'Einwilligung: ',
    ];
    
    $prefix = $type_labels[$type] ?? '';
    
    // Wenn nur eine Zahl, zeige Custom Field ID
    if (is_numeric($clean_id)) {
        return $prefix . 'Feld ' . $clean_id;
    }
    
    return $prefix . $label;
}

/**
 * Konvertiert CamelCase zu lesbarem Label
 * 
 * @param string $camelCase CamelCase-String
 * @return string Lesbares Label
 */
function bes_camelcase_to_label(string $camelCase): string
{
    // Füge Leerzeichen vor Großbuchstaben ein
    $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $camelCase);
    
    // Ersetze Unterstriche durch Leerzeichen
    $label = str_replace('_', ' ', $label);
    
    // Erste Buchstaben groß
    $label = ucwords(strtolower($label));
    
    // Spezielle Abkürzungen
    $abbreviations = [
        'Id' => 'ID',
        'Url' => 'URL',
        'Email' => 'E-Mail',
        'Zip' => 'PLZ',
        'Cf' => 'Custom Field',
    ];
    
    foreach ($abbreviations as $abbr => $replacement) {
        $label = str_replace($abbr, $replacement, $label);
    }
    
    return trim($label);
}

/**
 * Generiert Labels für alle Felder ohne Label
 * 
 * @param array $fields Array von Feldern
 * @return array Felder mit generierten Labels
 */
function bes_auto_generate_labels(array $fields): array
{
    foreach ($fields as &$field) {
        // Nur wenn kein Label oder Label = ID
        if (empty($field['label']) || $field['label'] === $field['id']) {
            $field['label'] = bes_generate_field_label($field);
        }
    }
    
    return $fields;
}

