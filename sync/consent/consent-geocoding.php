<?php
/**
 * Consent-Sync: Pins aus der Geschäftsadresse (Nominatim / OpenStreetMap).
 *
 * Datenschutz: Pins entstehen ausschließlich aus der Geschäftsadresse
 * (companyStreet, companyZip, companyCity, Land). Private Adressen und die in
 * EasyVerein hinterlegten Koordinaten werden nicht verwendet. Mitglieder ohne
 * Geschäftsadresse bekommen keinen Pin.
 *
 * Nominatim-Regeln: höchstens 1 Anfrage pro Sekunde, HTTP-Status wird geprüft,
 * User-Agent mit Seiten-URL, Ergebnisse werden pro Adresse dauerhaft gecacht.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Option für den Adress-Cache (autoload=false). */
const BES_GEOCODE_CACHE_OPTION = 'bes_geocode_cache';

/** Nicht gefundene Adressen werden erst nach dieser Zeit erneut angefragt. */
const BES_GEOCODE_MISS_TTL = 30 * DAY_IN_SECONDS;

/**
 * Normalisiert ein Länderfeld auf einen ISO-Code (de, at, ch, ...).
 *
 * @param string $country_name Freitext (z. B. "Deutschland", "Schweiz", "DE")
 * @param string $country_code Code aus der API (z. B. "DE"), hat Vorrang
 */
function bes_geocode_country_code(string $country_name = '', string $country_code = ''): string
{
    $code = strtolower(trim($country_code));
    if (preg_match('/^[a-z]{2}$/', $code)) {
        return $code;
    }

    $map = [
        'd' => 'de', 'de' => 'de', 'deutschland' => 'de', 'germany' => 'de',
        'at' => 'at', 'a' => 'at', 'österreich' => 'at', 'oesterreich' => 'at', 'austria' => 'at',
        'ch' => 'ch', 'schweiz' => 'ch', 'switzerland' => 'ch', 'suisse' => 'ch',
        'be' => 'be', 'belgien' => 'be', 'belgium' => 'be',
        'nl' => 'nl', 'niederlande' => 'nl', 'netherlands' => 'nl',
        'lu' => 'lu', 'luxemburg' => 'lu', 'luxembourg' => 'lu',
        'fr' => 'fr', 'frankreich' => 'fr', 'france' => 'fr',
        'it' => 'it', 'italien' => 'it', 'italy' => 'it',
        'dk' => 'dk', 'dänemark' => 'dk', 'denmark' => 'dk',
        'pl' => 'pl', 'polen' => 'pl', 'poland' => 'pl',
        'cz' => 'cz', 'tschechien' => 'cz',
        'es' => 'es', 'spanien' => 'es', 'spain' => 'es',
        'gb' => 'gb', 'uk' => 'gb', 'großbritannien' => 'gb', 'vereinigtes königreich' => 'gb',
    ];
    $key = mb_strtolower(trim($country_name));

    // Unbekannte Angaben (z. B. ein Ortsname im Länderfeld) → Standard Deutschland
    return $map[$key] ?? 'de';
}

/**
 * Wandelt "Musterweg 60-62" in "60 Musterweg" um (Nominatim erwartet Hausnummer zuerst).
 */
function bes_geocode_normalize_street(string $street): string
{
    $street = trim(preg_replace('/\s+/u', ' ', $street));
    if ($street === '') {
        return '';
    }
    if (preg_match('/^(.+?)\s+(\d+[a-zA-Z]?)(?:\s*[-–\/]\s*\d+[a-zA-Z]?)?$/u', $street, $m)) {
        return $m[2] . ' ' . trim($m[1], " ,");
    }
    return $street;
}

/**
 * Cache-Schlüssel pro Adresse (Straße, PLZ, Ort, Land).
 */
function bes_geocode_cache_key(string $street, string $zip, string $city, string $cc): string
{
    return sha1(mb_strtolower(implode('|', [$street, $zip, $city, $cc])));
}

/**
 * Lädt den Cache (einmal pro Request).
 */
function &bes_geocode_cache(): array
{
    static $cache = null;
    if ($cache === null) {
        $loaded = get_option(BES_GEOCODE_CACHE_OPTION, []);
        $cache = is_array($loaded) ? $loaded : [];
    }
    return $cache;
}

function bes_geocode_cache_save(): void
{
    $cache = &bes_geocode_cache();
    update_option(BES_GEOCODE_CACHE_OPTION, $cache, false);
}

/**
 * Eine Nominatim-Anfrage (strukturiert). Hält 1 Anfrage/Sekunde ein.
 *
 * @return array{status:string, lat?:float, lng?:float}
 *   status: "ok" | "none" (kein Treffer) | "error" (nicht erreichbar/gedrosselt, nicht cachen)
 */
function bes_geocode_nominatim_request(array $params): array
{
    static $last = 0.0;
    $wait = 1.05 - (microtime(true) - $last);
    if ($wait > 0) {
        usleep((int) ($wait * 1000000));
    }
    $last = microtime(true);

    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query(
        $params + ['format' => 'jsonv2', 'limit' => 1, 'addressdetails' => 0],
        '',
        '&',
        PHP_QUERY_RFC3986
    );

    $site = function_exists('home_url') ? home_url() : '';
    $version = defined('BES_VERSION') ? BES_VERSION : '4';
    $response = wp_remote_get($url, [
        'timeout' => 10,
        'headers' => ['User-Agent' => 'BSEasy-Sync/' . $version . ($site !== '' ? ' (+' . $site . ')' : '')],
    ]);

    if (is_wp_error($response)) {
        bes_consent_log('Nominatim nicht erreichbar: ' . $response->get_error_message(), 'WARN');
        return ['status' => 'error'];
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        bes_consent_log('Nominatim HTTP-Status ' . $code, 'WARN');
        return ['status' => 'error'];
    }

    $data = json_decode((string) wp_remote_retrieve_body($response), true);
    if (!is_array($data)) {
        bes_consent_log('Nominatim: ungültige Antwort', 'WARN');
        return ['status' => 'error'];
    }
    if ($data === [] || !isset($data[0]['lat'], $data[0]['lon'])) {
        return ['status' => 'none'];
    }

    return ['status' => 'ok', 'lat' => (float) $data[0]['lat'], 'lng' => (float) $data[0]['lon']];
}

/**
 * Liefert den Pin für ein Mitglied – ausschließlich aus der Geschäftsadresse.
 *
 * @param array $contact Contact-Details aus der API (nur companyStreet/Zip/City/Country werden gelesen)
 * @return array{lat:float, lng:float, precision:string}|null  precision: "address" | "locality"
 */
function bes_geocode_business_pin(array $contact, string $member_ref = ''): ?array
{
    static $started = 0.0;
    static $consecutive_errors = 0;
    static $deferred = 0;

    $street = bes_geocode_normalize_street((string) ($contact['companyStreet'] ?? ''));
    $zip    = trim((string) ($contact['companyZip'] ?? ''));
    $city   = trim((string) ($contact['companyCity'] ?? ''));

    // Ohne PLZ und Ort keine Geschäftsadresse → kein Pin (keine private Ersatzadresse)
    if ($zip === '' && $city === '') {
        return null;
    }

    $cc  = bes_geocode_country_code(
        (string) ($contact['companyCountry'] ?? ''),
        (string) ($contact['company_country_code'] ?? '')
    );
    $key = bes_geocode_cache_key($street, $zip, $city, $cc);

    $cache = &bes_geocode_cache();
    if (isset($cache[$key])) {
        $entry = $cache[$key];
        if (isset($entry['lat'], $entry['lng'])) {
            return ['lat' => (float) $entry['lat'], 'lng' => (float) $entry['lng'], 'precision' => (string) ($entry['p'] ?? 'address')];
        }
        if (isset($entry['miss']) && (time() - (int) $entry['miss']) < BES_GEOCODE_MISS_TTL) {
            return null;
        }
    }

    // Schutz vor Dauerfehlern/Drosselung und Zeitbudget pro Lauf
    $budget = function_exists('apply_filters') ? (int) apply_filters('bes_geocode_time_budget', 240) : 240;
    if ($started === 0.0) {
        $started = microtime(true);
    }
    if ($consecutive_errors >= 3 || (microtime(true) - $started) > $budget) {
        $deferred++;
        if ($deferred === 1) {
            bes_consent_log('Geocoding pausiert (Fehler oder Zeitbudget); Rest wird beim nächsten Sync nachgeholt', 'WARN');
        }
        return null;
    }

    $base = ['countrycodes' => $cc];
    if ($zip !== '') {
        $base['postalcode'] = $zip;
    }
    if ($city !== '') {
        $base['city'] = $city;
    }

    $result = null;
    $precision = 'locality';

    if ($street !== '') {
        $result = bes_geocode_nominatim_request($base + ['street' => $street]);
        $precision = 'address';
    }
    if ($result === null || $result['status'] === 'none') {
        $result = bes_geocode_nominatim_request($base);
        $precision = 'locality';
    }

    if ($result['status'] === 'error') {
        $consecutive_errors++;
        return null;
    }
    $consecutive_errors = 0;

    if ($result['status'] === 'none') {
        $cache[$key] = ['miss' => time()];
        bes_geocode_cache_save();
        return null;
    }

    $cache[$key] = ['lat' => $result['lat'], 'lng' => $result['lng'], 'p' => $precision];
    bes_geocode_cache_save();

    return ['lat' => $result['lat'], 'lng' => $result['lng'], 'precision' => $precision];
}

/**
 * Entfernung in Metern zwischen zwei Koordinaten (Haversine).
 */
function bes_geocode_distance_m(float $lat1, float $lng1, float $lat2, float $lng2): int
{
    $r = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return (int) round(2 * $r * asin(min(1, sqrt($a))));
}

/**
 * Hält pro Mitglied fest, was sich am Pin ändert – ohne Koordinaten zu speichern.
 * Die Datei (v3/geocode_report.json) wird beim Sync-Ende geschrieben und ist per .htaccess geschützt.
 *
 * @param array|null $old_coords alte EasyVerein-Koordinaten (nur zur Entfernungsberechnung, werden nicht gespeichert)
 */
function bes_geocode_report(string $member_ref, ?array $old_coords, ?array $pin): void
{
    static $report = [];
    static $registered = false;

    $has_old = $old_coords !== null && isset($old_coords['lat'], $old_coords['lng']);
    if ($pin === null) {
        $status = $has_old ? 'pin_entfaellt' : 'kein_pin';
        $moved = null;
        $precision = null;
    } else {
        $precision = $pin['precision'];
        if ($has_old) {
            $status = 'verschoben';
            $moved = bes_geocode_distance_m((float) $old_coords['lat'], (float) $old_coords['lng'], $pin['lat'], $pin['lng']);
        } else {
            $status = 'neu';
            $moved = null;
        }
    }
    $report[$member_ref] = ['status' => $status, 'precision' => $precision, 'moved_m' => $moved];

    if ($registered || !defined('BES_DATA_V3')) {
        return;
    }
    $registered = true;
    register_shutdown_function(static function () use (&$report) {
        $file = BES_DATA_V3 . 'geocode_report.json';
        $existing = [];
        if (is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded) && isset($decoded['members']) && is_array($decoded['members'])) {
                $existing = $decoded['members'];
            }
        }
        $merged = array_replace($existing, $report);
        file_put_contents(
            $file,
            wp_json_encode(['updated_at' => date('c'), 'members' => $merged], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    });
}
