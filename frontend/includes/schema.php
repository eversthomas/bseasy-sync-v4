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

    // Adresse
    $city    = bes_schema_get_field($member, ['contact.city', 'contact' => 'city']);
    $zip     = bes_schema_get_field($member, ['contact.zip', 'contact' => 'zip']);
    $country = bes_schema_get_field($member, ['contact.country', 'contact' => 'country']) ?: 'DE';

    if ($city || $zip) {
        $address = ['@type' => 'PostalAddress'];
        if ($zip)     $address['postalCode']       = $zip;
        if ($city)    $address['addressLocality']  = $city;
        if ($country) $address['addressCountry']   = $country;
        $schema['address'] = $address;
    }

    // Profilbild
    if ($img_url) {
        $schema['image'] = $img_url;
    }

    // Website / URL (erstes vorhandenes Web-Feld nutzen)
    $website = bes_schema_get_field($member, ['contact.website', 'contact.homepage', 'contact' => 'website']);
    if ($website && filter_var($website, FILTER_VALIDATE_URL)) {
        $schema['url'] = $website;
    }

    // E-Mail
    $email = bes_schema_get_field($member, ['contact.email', 'contact' => 'email']);
    if ($email && is_email($email)) {
        $schema['email'] = $email;
    }

    $json = wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!$json) {
        return '';
    }

    return '<script type="application/ld+json">' . $json . '</script>';
}

/**
 * Liest einen Feldwert aus dem Mitglieds-Array über eine Prioritätsliste.
 *
 * Unterstützt:
 *  - Flache Keys: 'contact.city' → $member['contact.city']
 *  - Nested Keys: 'contact' => 'city' → $member['contact']['city']
 *
 * @param array $member     Mitglied-Datensatz
 * @param array $candidates Prioritätsliste von Keys (flach oder ['parent' => 'child'])
 * @return string           Gefundener Wert oder ''
 */
function bes_schema_get_field(array $member, array $candidates): string
{
    foreach ($candidates as $key => $value) {
        if (is_int($key)) {
            // Flacher Key: z. B. 'contact.city'
            if (!empty($member[$value])) {
                return (string) $member[$value];
            }
        } else {
            // Nested: $key = 'contact', $value = 'city'
            if (!empty($member[$key][$value])) {
                return (string) $member[$key][$value];
            }
        }
    }
    return '';
}
