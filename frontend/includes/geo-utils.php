<?php
/**
 * Geo-Hilfsfunktionen: Haversine-Distanz + Nominatim-Geocoding.
 *
 * Wird für die Radius-Suche in der Filterleiste verwendet.
 * Nominatim-Ergebnisse werden 7 Tage per Transient gecacht,
 * um API-Limits zu schonen.
 *
 * @package BSEasySync
 * @since 4.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Berechnet die Luftliniendistanz zwischen zwei Koordinatenpaaren (Haversine).
 *
 * @param float $lat1  Breitengrad Punkt 1
 * @param float $lon1  Längengrad Punkt 1
 * @param float $lat2  Breitengrad Punkt 2
 * @param float $lon2  Längengrad Punkt 2
 * @return float       Distanz in Kilometern
 */
function bes_haversine_distance(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $R     = 6371.0; // Erdradius in km
    $dLat  = deg2rad($lat2 - $lat1);
    $dLon  = deg2rad($lon2 - $lon1);
    $a     = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    $c     = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

/**
 * Geocodiert einen Ort (PLZ oder Stadtname) über die Nominatim-API.
 *
 * Ergebnis wird 7 Tage gecacht. Bei Fehler (HTTP, JSON, kein Treffer)
 * wird null zurückgegeben.
 *
 * @param string $query  PLZ oder Stadtname, z. B. "75173" oder "Pforzheim"
 * @return array|null    ['lat' => float, 'lng' => float] oder null
 */
function bes_geocode_location_query(string $query): ?array
{
    $query = trim($query);
    if (empty($query)) {
        return null;
    }

    $cache_key = 'bes_geo_' . md5(strtolower($query));
    $cached    = get_transient($cache_key);
    if ($cached !== false) {
        return $cached ?: null; // false → kein Treffer (gecacht), '' → ungültig
    }

    $url = add_query_arg([
        'q'            => $query,
        'format'       => 'json',
        'limit'        => 1,
        'countrycodes' => 'de,at,ch',
        'addressdetails' => 0,
    ], 'https://nominatim.openstreetmap.org/search');

    $response = wp_remote_get($url, [
        'timeout' => 5,
        'headers' => [
            'User-Agent' => 'BSEasySync WordPress Plugin/4.1 (contact@bseasy.de)',
        ],
    ]);

    if (is_wp_error($response)) {
        if (function_exists('bes_debug_log')) {
            bes_debug_log('Nominatim-Fehler: ' . $response->get_error_message(), 'WARN', 'geo-utils');
        }
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data[0]['lat']) || empty($data[0]['lon'])) {
        // Kein Treffer: als leeres Array cachen, damit wir nicht wiederholt anfragen
        set_transient($cache_key, [], DAY_IN_SECONDS);
        return null;
    }

    $result = [
        'lat' => (float) $data[0]['lat'],
        'lng' => (float) $data[0]['lon'],
    ];

    set_transient($cache_key, $result, 7 * DAY_IN_SECONDS);
    return $result;
}

/**
 * AJAX: Mitglieder-IDs zurückgeben, die innerhalb eines Radius liegen.
 *
 * Eingabe (POST/GET):
 *  - location  (string)  PLZ oder Stadtname
 *  - radius_km (int)     Suchradius in km (1–200, Standard: 25)
 *  - nonce     (string)  bes_filter_members_nonce
 *
 * Antwort: { member_ids: ["123", "456", ...] }
 */
add_action('wp_ajax_nopriv_bes_radius_search', 'bes_handle_radius_search');
add_action('wp_ajax_bes_radius_search', 'bes_handle_radius_search');

function bes_handle_radius_search(): void
{
    // Nonce prüfen (selbe Nonce wie normaler Filter, kein separates Nonce nötig)
    if (!check_ajax_referer('bes_filter_members_nonce', 'nonce', false)) {
        wp_send_json_error(['error' => __('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN)]);
        return;
    }

    // Rate-Limiting
    if (function_exists('bes_check_rate_limit') && !bes_check_rate_limit('bes_radius_search', 30, 60)) {
        wp_send_json_error(['error' => __('Zu viele Anfragen. Bitte warten.', BES_TEXT_DOMAIN)]);
        return;
    }

    $location  = sanitize_text_field(wp_unslash($_REQUEST['location']  ?? ''));
    $radius_km = max(1, min(200, intval($_REQUEST['radius_km'] ?? 25)));

    // Sichtbare Member-IDs für Centroid-Fallback (optional)
    $visible_ids_raw = array_filter(
        array_map('strval', (array) ($_REQUEST['center_member_ids'] ?? [])),
        fn($v) => ctype_digit($v) && $v !== ''
    );

    if (empty($location) && empty($visible_ids_raw)) {
        wp_send_json_error(['error' => __('Kein Ort angegeben.', BES_TEXT_DOMAIN)]);
        return;
    }

    // Mitglieder vorab laden (wird für Geocoding-Fallback und Distanzberechnung benötigt)
    if (!function_exists('bes_members_get_all')) {
        wp_send_json_error(['error' => __('Repository nicht verfügbar.', BES_TEXT_DOMAIN)]);
        return;
    }
    $members = bes_members_get_all();

    // Suchzentrum bestimmen: kurze PLZ-Präfixe (< 5 Ziffern) liefern über Nominatim
    // unzuverlässige Ergebnisse → direkt Centroid der sichtbaren Mitglieder nutzen.
    // Vollständige PLZ (5 DE / 4 AT/CH) und Stadtnamen → Nominatim, Centroid als Fallback.
    $is_short_numeric = !empty($location) && preg_match('/^\d{1,4}$/', $location);

    $center = null;
    if ($is_short_numeric && !empty($visible_ids_raw)) {
        // Partielle PLZ → Schwerpunkt der bereits sichtbaren Mitglieder
        $center = bes_compute_members_centroid($visible_ids_raw, $members);
    }
    if (!$center && !empty($location)) {
        // Vollständige PLZ / Stadtname → Nominatim (inkl. AT + CH)
        $center = bes_geocode_location_query($location);
    }
    if (!$center && !empty($visible_ids_raw)) {
        // Letzter Fallback: Centroid (falls Nominatim scheitert)
        $center = bes_compute_members_centroid($visible_ids_raw, $members);
    }
    if (!$center) {
        wp_send_json_error(['error' => __('Ort konnte nicht gefunden werden.', BES_TEXT_DOMAIN)]);
        return;
    }

    // Distanzfilterung
    $matching_ids = [];

    foreach ($members as $member) {
        // Koordinaten extrahieren (nested + flat)
        $lat = $member['contact']['geoPositionCoords']['lat']
            ?? $member['contact.geoPositionCoords.lat']
            ?? null;
        $lng = $member['contact']['geoPositionCoords']['lng']
            ?? $member['contact.geoPositionCoords.lng']
            ?? null;

        if ($lat === null || $lng === null) {
            continue;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        if ($lat === 0.0 && $lng === 0.0) {
            continue;
        }

        $distance = bes_haversine_distance($center['lat'], $center['lng'], $lat, $lng);

        if ($distance <= $radius_km) {
            $id = $member['member.id'] ?? $member['member']['id'] ?? $member['id'] ?? null;
            if ($id !== null) {
                $matching_ids[] = (string) $id;
            }
        }
    }

    wp_send_json_success(['member_ids' => $matching_ids]);
}

/**
 * Berechnet den geografischen Schwerpunkt (Centroid) einer Mitglieder-Liste.
 *
 * @param string[] $ids      Member-IDs als Strings
 * @param array    $members  Alle Mitglieder (aus Repository)
 * @return array|null        ['lat' => float, 'lng' => float] oder null
 */
function bes_compute_members_centroid(array $ids, array $members): ?array
{
    $lats = [];
    $lngs = [];

    foreach ($members as $member) {
        $id = strval($member['member.id'] ?? $member['member']['id'] ?? $member['id'] ?? '');
        if (!in_array($id, $ids, true)) {
            continue;
        }
        $lat = (float) ($member['contact.geoPositionCoords.lat']
            ?? $member['contact']['geoPositionCoords']['lat']
            ?? 0);
        $lng = (float) ($member['contact.geoPositionCoords.lng']
            ?? $member['contact']['geoPositionCoords']['lng']
            ?? 0);
        if ($lat !== 0.0 || $lng !== 0.0) {
            $lats[] = $lat;
            $lngs[] = $lng;
        }
    }

    if (empty($lats)) {
        return null;
    }

    return [
        'lat' => array_sum($lats) / count($lats),
        'lng' => array_sum($lngs) / count($lngs),
    ];
}
