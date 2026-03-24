<?php
/**
 * Consent-Sync: Geocoding (Nominatim).
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

// ------------------------------------------------------------
// 🌍 GEOCODIERUNGS-FUNKTIONEN
// ============================================

/**
 * Fallback-Geocodierung für Kontaktdaten
 * Wenn geoPositionCoords fehlt:
 * 1. Versuche geschäftliche Adresse (companyZip + companyCity)
 * 2. Falls nicht → private Adresse (zip + city)
 * 3. Falls auch nicht → skip Geocodierung
 * 
 * Nutzt Nominatim (OpenStreetMap) mit Rate-Limiting
 */
function bes_geocode_contact_fallback($contact, $baseUsed = ''): array
{
    // Prüfe ob schon gültige Koordinaten vorhanden sind
    if (!empty($contact['geoPositionCoords']) && 
        is_array($contact['geoPositionCoords']) && 
        !empty($contact['geoPositionCoords']['lat']) && 
        !empty($contact['geoPositionCoords']['lng'])) {
        return $contact; // Bereits vorhanden, keine Geocodierung nötig
    }

    // Fallback-Logik: Versuche Adresse zu geocodieren
    $zip = '';
    $city = '';
    $street = '';
    
    // 1. Versuch: Geschäftliche Adresse
    if (!empty($contact['companyZip']) && !empty($contact['companyCity'])) {
        $zip = trim($contact['companyZip']);
        $city = trim($contact['companyCity']);
        $street = !empty($contact['companyStreet']) ? trim($contact['companyStreet']) : '';
        bes_consent_log("⏳ Geocodierung: Nutze geschäftliche Adresse ($zip $city)", 'DEBUG');
    } 
    // 2. Fallback: Private Adresse
    elseif (!empty($contact['zip']) && !empty($contact['city'])) {
        $zip = trim($contact['zip']);
        $city = trim($contact['city']);
        $street = !empty($contact['street']) ? trim($contact['street']) : '';
        bes_consent_log("⏳ Geocodierung: Nutze private Adresse ($zip $city)", 'DEBUG');
    }
    
    // Falls keine Adresse vorhanden → skip
    if (empty($zip) || empty($city)) {
        bes_consent_log("⚠️ Keine Adresse für Geocodierung gefunden", 'DEBUG');
        return $contact;
    }

    // Geocodiere mit Nominatim
    $coords = bes_geocode_nominatim($street, $city, $zip);
    
    if ($coords) {
        $contact['geoPositionCoords'] = $coords;
        bes_consent_log("✓ Geocodierung erfolgreich: lat={$coords['lat']}, lng={$coords['lng']}", 'DEBUG');
    } else {
        bes_consent_log("⚠️ Geocodierung fehlgeschlagen für $zip $city", 'WARN');
    }
    
    return $contact;
}

/**
 * Geocodiert eine Adresse via Nominatim (OpenStreetMap)
 * Rate-Limit: 1 Request pro Sekunde
 */
function bes_geocode_nominatim($street = '', $city = '', $zip = ''): ?array
{
    // Baue Query: PLZ + Stadt + Straße
    $query_parts = array_filter([$street, $zip, $city], 'strlen');
    $query = implode(', ', $query_parts);
    
    if (strlen($query) < 3) {
        return null;
    }

    // Rate-Limiting: Min. 1 Sekunde zwischen Requests
    static $last_nominatim_time = 0;
    $time_since_last = microtime(true) - $last_nominatim_time;
    if ($time_since_last < 1.0) {
        usleep((1.0 - $time_since_last) * 1000000);
    }
    $last_nominatim_time = microtime(true);

    // Nominatim-Request
    $url = 'https://nominatim.openstreetmap.org/search?q=' . urlencode($query) . '&format=json&limit=1&country=de';
    
    $response = wp_remote_get($url, [
        'timeout' => 10,
        'headers' => [
            'User-Agent' => 'BSEasy-Sync/3.0 (WordPress Plugin)'
        ]
    ]);
    
    if (is_wp_error($response)) {
        bes_consent_log("Nominatim-Fehler: " . $response->get_error_message(), 'WARN');
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (empty($data) || !is_array($data)) {
        return null;
    }

    $result = $data[0] ?? null;
    if ($result && isset($result['lat']) && isset($result['lon'])) {
        return [
            'lat' => floatval($result['lat']),
            'lng' => floatval($result['lon'])
        ];
    }

    return null;
}
