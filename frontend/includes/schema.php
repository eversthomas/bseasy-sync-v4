<?php
/**
 * Schema.org – strukturierte Daten für Mitgliederprofile.
 *
 * Generiert JSON-LD-Markup (Person-Schema) pro Mitgliederkarte.
 * Das Markup hilft Suchmaschinen, Mitgliederprofile als strukturierte
 * Daten zu verstehen und ermöglicht Rich Results in der Suche.
 *
 * @package BSEasySync
 * @since 4.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalisiert eine Mitglieds-Website-URL für Schema.org.
 *
 * @param string $raw Rohe Eingabe (z. B. www.example.de)
 * @return string     Validierte URL oder ''
 */
function bes_schema_normalize_url(string $raw): string
{
    $url = trim($raw);
    if ($url === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    $validated = filter_var($url, FILTER_VALIDATE_URL);
    return $validated ? (string) $validated : '';
}

/**
 * Gibt das Schema.org-JSON-LD-Script-Tag für ein Mitglied zurück.
 *
 * @param array  $member   Mitglied-Datensatz aus members.json
 * @param string $name     Vollständiger Name (bereits ermittelt)
 * @param mixed  $id       Mitglieds-ID
 * @param string $img_url  Bild-URL (leer wenn kein Bild vorhanden)
 * @return string          <script type="application/ld+json">…</script> oder ''
 */
function bes_generate_member_schema_tag(array $member, string $name, $id, string $img_url = ''): string
{
    if (empty($name) || $name === 'Profilbild') {
        return '';
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type'    => 'Person',
        'name'     => $name,
    ];

    $given_name = bes_schema_get_field($member, 'given_name');
    if ($given_name !== '') {
        $schema['givenName'] = $given_name;
    }

    $family_name = bes_schema_get_field($member, 'family_name');
    if ($family_name !== '') {
        $schema['familyName'] = $family_name;
    }

    if ($img_url !== '') {
        $schema['image'] = $img_url;
    }

    $description = bes_schema_get_field($member, 'description');
    if ($description !== '') {
        $schema['description'] = $description;
    }

    $url_1 = bes_schema_normalize_url(bes_schema_get_field($member, 'website_1'));
    $url_2 = bes_schema_normalize_url(bes_schema_get_field($member, 'website_2'));
    $primary_url = $url_1 !== '' ? $url_1 : $url_2;
    if ($primary_url !== '') {
        $schema['url'] = $primary_url;
    }

    $same_as = array_values(array_unique(array_filter([$url_1, $url_2])));
    if (count($same_as) === 1) {
        $schema['sameAs'] = $same_as[0];
    } elseif (count($same_as) === 2) {
        $schema['sameAs'] = $same_as;
    }

    $company_name = bes_schema_get_field($member, 'company_name');
    if ($company_name !== '') {
        $schema['worksFor'] = [
            '@type' => 'Organization',
            'name'  => $company_name,
        ];
    }

    $city    = bes_schema_get_field($member, 'city');
    $zip     = bes_schema_get_field($member, 'zip');
    $street  = bes_schema_get_field($member, 'street');
    $state   = bes_schema_get_field($member, 'state');
    $country = bes_schema_get_field($member, 'country');

    if ($city !== '' || $zip !== '') {
        $address = ['@type' => 'PostalAddress'];
        if ($street !== '') {
            $address['streetAddress'] = $street;
        }
        if ($zip !== '') {
            $address['postalCode'] = $zip;
        }
        if ($city !== '') {
            $address['addressLocality'] = $city;
        }
        if ($state !== '') {
            $address['addressRegion'] = $state;
        }
        $address['addressCountry'] = $country !== '' ? $country : 'DE';
        $schema['address'] = $address;
    }

    $geo_lat = bes_schema_get_field($member, 'geo_lat');
    $geo_lng = bes_schema_get_field($member, 'geo_lng');
    if ($geo_lat !== '' && $geo_lng !== '') {
        $schema['geo'] = [
            '@type'     => 'GeoCoordinates',
            'latitude'  => (float) $geo_lat,
            'longitude' => (float) $geo_lng,
        ];
    }

    $methods   = bes_schema_get_field($member, 'methods');
    $offerings = bes_schema_get_field($member, 'offerings');
    $knows_about = array_values(array_unique(array_merge(
        is_array($methods) ? $methods : [],
        is_array($offerings) ? $offerings : []
    )));
    if (!empty($knows_about)) {
        $schema['knowsAbout'] = $knows_about;
    }

    $json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!$json) {
        return '';
    }

    return '<script type="application/ld+json">' . $json . '</script>';
}

/**
 * Liest einen Feldwert aus dem V3-Mitglieds-Array.
 *
 * @param array  $member Mitglied-Datensatz
 * @param string $field  Logischer Feldname (city, zip, methods, …)
 * @return string|array Gefundener Wert, '' oder []
 */
function bes_schema_get_field(array $member, string $field)
{
    switch ($field) {
        case 'city':
            return (string) ($member['contact']['contact.companyCity'] ?? '');

        case 'zip':
            return (string) ($member['contact']['contact.companyZip'] ?? '');

        case 'street':
            return (string) ($member['contact']['contact.companyStreet'] ?? '');

        case 'state':
            return (string) ($member['contact']['contact.companyState'] ?? '');

        case 'country':
            return (string) ($member['contact']['contact.company_country_code_invoice'] ?? '');

        case 'company_name':
            return (string) ($member['contact']['contact.companyName'] ?? '');

        case 'website_1':
            return (string) ($member['cf.50697325'] ?? '');

        case 'website_2':
            return (string) ($member['cf.50697329'] ?? '');

        case 'description':
            return (string) ($member['cf.313634622'] ?? '');

        case 'methods':
            $raw = $member['cf.312976233'] ?? '';
            if (is_array($raw)) {
                $values = array_map(static function ($v) {
                    return trim((string) $v);
                }, $raw);
                return array_values(array_unique(array_filter($values, static function ($v) {
                    return $v !== '';
                })));
            }
            if (is_string($raw) && trim($raw) !== '') {
                return [trim($raw)];
            }
            return [];

        case 'offerings':
            $raw = $member['cf.50697357'] ?? '';
            if (is_array($raw)) {
                $values = array_map(static function ($v) {
                    return trim((string) $v);
                }, $raw);
                return array_values(array_unique(array_filter($values, static function ($v) {
                    return $v !== '';
                })));
            }
            if (is_string($raw) && trim($raw) !== '') {
                return [trim($raw)];
            }
            return [];

        case 'geo_lat':
            if (!isset($member['contact.geoPositionCoords.lat']) || $member['contact.geoPositionCoords.lat'] === '') {
                return '';
            }
            $lat = (float) $member['contact.geoPositionCoords.lat'];
            return $lat === 0.0 ? '' : (string) $lat;

        case 'geo_lng':
            if (!isset($member['contact.geoPositionCoords.lng']) || $member['contact.geoPositionCoords.lng'] === '') {
                return '';
            }
            $lng = (float) $member['contact.geoPositionCoords.lng'];
            return $lng === 0.0 ? '' : (string) $lng;

        case 'given_name':
            return (string) ($member['contact']['contact.firstName'] ?? '');

        case 'family_name':
            return (string) ($member['contact']['contact.familyName'] ?? '');

        default:
            return '';
    }
}
