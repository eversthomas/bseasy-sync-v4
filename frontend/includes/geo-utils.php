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
 * Sammelt alle PLZ-artigen Werte eines Mitglieds (flach + contact-Objekt).
 *
 * @param array $member
 * @return string[]
 */
function bes_member_collect_zip_values(array $member): array
{
    $out = [];
    // Root: teils flach aus älteren Exporten (z. B. contact.companyZip auf oberster Ebene)
    $flat_keys = ['contact.companyZip', 'contact.zip'];
    foreach ($flat_keys as $k) {
        if (isset($member[$k]) && $member[$k] !== '' && (is_string($member[$k]) || is_numeric($member[$k]))) {
            $out[] = (string) $member[$k];
        }
    }
    // V3-Consent / EasyVerein: verschachteltes contact-Objekt nutzt dieselben Feld-IDs als Keys
    // (z. B. "contact.companyZip" — nicht nur "companyZip").
    if (!empty($member['contact']) && is_array($member['contact'])) {
        $nested_keys = ['contact.companyZip', 'contact.zip', 'companyZip', 'zip', 'postalCode'];
        foreach ($nested_keys as $sub) {
            if (!array_key_exists($sub, $member['contact'])) {
                continue;
            }
            $v = $member['contact'][$sub];
            if ($v === '' || (!is_string($v) && !is_numeric($v))) {
                continue;
            }
            $out[] = (string) $v;
        }
    }

    return array_values(array_filter(array_map('trim', $out)));
}

/**
 * Prüft, ob eine Mitglieds-PLZ mit dem Suchpräfix beginnt (wie Frontend: startsWith, kleingeschrieben).
 */
function bes_member_zip_matches_prefix(array $member, string $prefix): bool
{
    $prefix = strtolower(trim($prefix));
    if ($prefix === '') {
        return true;
    }
    foreach (bes_member_collect_zip_values($member) as $z) {
        $z = strtolower((string) $z);
        if ($z !== '' && strncmp($z, $prefix, strlen($prefix)) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * Alle Member-IDs, deren PLZ zum Präfix passt (für Schwerpunkt bei partieller PLZ).
 *
 * @param array  $members
 * @param string $prefix
 * @return string[]
 */
function bes_member_ids_with_zip_prefix(array $members, string $prefix): array
{
    $ids = [];
    foreach ($members as $member) {
        if (!bes_member_zip_matches_prefix($member, $prefix)) {
            continue;
        }
        $id = $member['member.id'] ?? $member['member']['id'] ?? $member['id'] ?? null;
        if ($id !== null) {
            $ids[] = (string) $id;
        }
    }

    return $ids;
}

/**
 * AJAX: Mitglieder-IDs zurückgeben, die innerhalb eines Radius liegen.
 *
 * Eingabe (POST/GET):
 *  - location  (string)  PLZ oder Stadtname
 *  - radius_km (int) Suchradius in km (1–200, Standard: 10)
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
    $radius_km = max(1, min(200, intval($_REQUEST['radius_km'] ?? 10)));

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

    // Suchzentrum:
    // - Kurze PLZ (1–4 Ziffern): Schwerpunkt aller Mitglieder mit passendem PLZ-Präfix (stabil beim
    //   Radiuswechsel), sonst sichtbare Karten, sonst Nominatim.
    // - Vollständige PLZ / Freitext: zuerst Nominatim, dann Centroid der sichtbaren Karten.
    $is_short_numeric = !empty($location) && preg_match('/^\d{1,4}$/', $location);

    $center        = null;
    $center_source = null;
    if ($is_short_numeric) {
        $prefix_ids = bes_member_ids_with_zip_prefix($members, $location);
        $center     = bes_compute_members_centroid($prefix_ids, $members);
        if ($center) {
            $center_source = 'prefix_zip_centroid';
        }
        if (!$center && !empty($visible_ids_raw)) {
            $center = bes_compute_members_centroid($visible_ids_raw, $members);
            if ($center) {
                $center_source = 'client_center_member_ids_centroid';
            }
        }
        if (!$center && !empty($location)) {
            $center = bes_geocode_location_query($location);
            if ($center) {
                $center_source = 'nominatim';
            }
        }
    } else {
        if (!empty($location)) {
            $center = bes_geocode_location_query($location);
            if ($center) {
                $center_source = 'nominatim';
            }
        }
        if (!$center && !empty($visible_ids_raw)) {
            $center = bes_compute_members_centroid($visible_ids_raw, $members);
            if ($center) {
                $center_source = 'client_center_member_ids_centroid';
            }
        }
    }
    if (!$center) {
        if (defined('BES_RADIUS_SEARCH_DEBUG') && BES_RADIUS_SEARCH_DEBUG) {
            bes_radius_search_emit_debug_log(
                $location,
                $radius_km,
                $visible_ids_raw,
                $members,
                null,
                null,
                $is_short_numeric,
                0
            );
        }
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

    if (defined('BES_RADIUS_SEARCH_DEBUG') && BES_RADIUS_SEARCH_DEBUG) {
        bes_radius_search_emit_debug_log(
            $location,
            $radius_km,
            $visible_ids_raw,
            $members,
            $center,
            $center_source,
            $is_short_numeric,
            count($matching_ids)
        );
    }

    wp_send_json_success(['member_ids' => $matching_ids]);
}

/**
 * Temporäres Diagnose-Log für Radius-Anfragen (PHP error_log / WP_DEBUG.log).
 *
 * Aktivierung in wp-config.php:
 * define('BES_RADIUS_SEARCH_DEBUG', true);
 *
 * @param string               $location
 * @param int                  $radius_km
 * @param string[]             $center_member_ids  Gefilterte Client-IDs (nur Ziffern)
 * @param array                $members
 * @param array|null           $center_used        ['lat'=>…,'lng'=>…] oder null
 * @param string|null          $center_source      z. B. nominatim, client_center_member_ids_centroid
 * @param bool                 $is_short_numeric
 * @param int                  $matches_count
 */
function bes_radius_search_emit_debug_log(
    string $location,
    int $radius_km,
    array $center_member_ids,
    array $members,
    ?array $center_used,
    ?string $center_source,
    bool $is_short_numeric,
    int $matches_count
): void {
    $client_centroid = !empty($center_member_ids)
        ? bes_compute_members_centroid($center_member_ids, $members)
        : null;

    $nominatim_coords = !empty($location) ? bes_geocode_location_query($location) : null;

    $valid_geo = 0;
    foreach ($members as $m) {
        $lat = $m['contact']['geoPositionCoords']['lat']
            ?? $m['contact.geoPositionCoords.lat']
            ?? null;
        $lng = $m['contact']['geoPositionCoords']['lng']
            ?? $m['contact.geoPositionCoords.lng']
            ?? null;
        if ($lat === null || $lng === null) {
            continue;
        }
        $lat = (float) $lat;
        $lng = (float) $lng;
        if ($lat === 0.0 && $lng === 0.0) {
            continue;
        }
        ++$valid_geo;
    }

    $payload = [
        'location'               => $location,
        'radius_km'              => $radius_km,
        'is_short_numeric_plz'   => $is_short_numeric,
        'center_member_ids'      => [
            'count'    => count($center_member_ids),
            'centroid' => $client_centroid,
        ],
        'nominatim_coords'       => $nominatim_coords,
        'members_total'           => count($members),
        'members_valid_geo_count' => $valid_geo,
        'center_used'             => $center_used
            ? [
                'lat'    => $center_used['lat'],
                'lng'    => $center_used['lng'],
                'source' => $center_source,
            ]
            : null,
        'matches_count'          => $matches_count,
    ];

    error_log('[BES radius-search debug] ' . wp_json_encode($payload, JSON_UNESCAPED_UNICODE));
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
