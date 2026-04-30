<?php
/**
 * Länder-Normalisierung für die Filterleiste (ISO 3166-1 alpha-2 + gängige Schreibweisen aus EasyVerein).
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ISO-Code => deutscher Anzeigename (für Dropdown & JS).
 *
 * @return array<string, string>
 */
function bes_country_iso_german_labels(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $map = [
        'DE' => 'Deutschland',
        'AT' => 'Österreich',
        'CH' => 'Schweiz',
        'BE' => 'Belgien',
        'NL' => 'Niederlande',
        'LU' => 'Luxemburg',
        'FR' => 'Frankreich',
        'IT' => 'Italien',
        'ES' => 'Spanien',
        'PT' => 'Portugal',
        'IE' => 'Irland',
        'GB' => 'Vereinigtes Königreich',
        'DK' => 'Dänemark',
        'SE' => 'Schweden',
        'NO' => 'Norwegen',
        'FI' => 'Finnland',
        'IS' => 'Island',
        'PL' => 'Polen',
        'CZ' => 'Tschechien',
        'SK' => 'Slowakei',
        'HU' => 'Ungarn',
        'RO' => 'Rumänien',
        'BG' => 'Bulgarien',
        'GR' => 'Griechenland',
        'HR' => 'Kroatien',
        'SI' => 'Slowenien',
        'EE' => 'Estland',
        'LV' => 'Lettland',
        'LT' => 'Litauen',
        'MT' => 'Malta',
        'CY' => 'Zypern',
        'LI' => 'Liechtenstein',
        'MC' => 'Monaco',
        'AD' => 'Andorra',
        'SM' => 'San Marino',
        'VA' => 'Vatikanstadt',
        'RS' => 'Serbien',
        'BA' => 'Bosnien und Herzegowina',
        'ME' => 'Montenegro',
        'MK' => 'Nordmazedonien',
        'AL' => 'Albanien',
        'TR' => 'Türkei',
        'UA' => 'Ukraine',
        'MD' => 'Moldau',
        'BY' => 'Weißrussland',
        'RU' => 'Russland',
    ];

    return $map;
}

/**
 * Kleinschreibung-Alias => ISO (für bes_normalize_country_token und JS-Karten).
 *
 * @return array<string, string>
 */
function bes_country_alias_to_iso_map(): array
{
    static $aliases = null;
    if ($aliases !== null) {
        return $aliases;
    }

    $labels = bes_country_iso_german_labels();
    $aliases = [];

    foreach ($labels as $iso => $label) {
        $aliases[mb_strtolower($iso, 'UTF-8')] = strtoupper($iso);
        $aliases[mb_strtolower($label, 'UTF-8')] = strtoupper($iso);
    }

    $extras = [
        'germany'         => 'DE',
        'austria'         => 'AT',
        'oesterreich'     => 'AT',
        'österreich'     => 'AT',
        'switzerland'     => 'CH',
        'belgium'         => 'BE',
        'belgien'        => 'BE',
        'schweiz'        => 'CH',
        'deutschland'    => 'DE',
        'brd'            => 'DE',
        'bundesrepublik deutschland' => 'DE',
        'france'         => 'FR',
        'italy'          => 'IT',
        'italien'        => 'IT',
        'spain'          => 'ES',
        'spanien'        => 'ES',
        'netherlands'    => 'NL',
        'the netherlands'=> 'NL',
        'holland'        => 'NL',
        'luxembourg'     => 'LU',
        'luxemburg'      => 'LU',
        'great britain'  => 'GB',
        'united kingdom' => 'GB',
        'england'        => 'GB',
        'scotland'       => 'GB',
        'wales'          => 'GB',
        'northern ireland' => 'GB',
    ];

    foreach ($extras as $k => $iso) {
        $aliases[$k] = $iso;
    }

    // Häufige EasyVerein-/Tippfehler-Kürzel (vorsichtig, nur eindeutige Fälle)
    $aliases['d'] = 'DE';
    $aliases['ch'] = 'CH';
    $aliases['at'] = 'AT';
    $aliases['de'] = 'DE';
    $aliases['be'] = 'BE';
    $aliases['nl'] = 'NL';
    $aliases['fr'] = 'FR';
    $aliases['it'] = 'IT';
    $aliases['es'] = 'ES';
    $aliases['pl'] = 'PL';
    $aliases['cz'] = 'CZ';
    $aliases['uk'] = 'GB';

    return $aliases;
}

/**
 * @return array<string, string>
 */
function bes_country_filter_labels_js(): array
{
    return bes_country_iso_german_labels();
}

/**
 * Alias (kleingeschrieben) => ISO für Frontend (URL-Hash, alte Werte).
 *
 * @return array<string, string>
 */
function bes_country_filter_alias_normalize_js(): array
{
    return bes_country_alias_to_iso_map();
}

function bes_country_iso_is_known(string $iso): bool
{
    $iso = strtoupper($iso);
    $labels = bes_country_iso_german_labels();

    return isset($labels[$iso]);
}

/**
 * Einzelnen Rohwert (z. B. „Deutschland“, „DE“, „D“) auf ISO abbilden oder null.
 */
function bes_normalize_country_token(string $raw): ?string
{
    $t = trim($raw);
    if ($t === '') {
        return null;
    }

    if (preg_match('/^[a-z]{2}$/i', $t)) {
        $u = strtoupper($t);
        if ($u === 'UK') {
            $u = 'GB';
        }
        if (bes_country_iso_is_known($u)) {
            return $u;
        }
    }

    $key = mb_strtolower($t, 'UTF-8');
    $map = bes_country_alias_to_iso_map();

    $iso = $map[$key] ?? null;
    if ($iso !== null) {
        $iso = strtoupper($iso);
        if ($iso === 'UK') {
            $iso = 'GB';
        }
        if (bes_country_iso_is_known($iso)) {
            return $iso;
        }
    }

    return null;
}

function bes_field_is_country_filter(array $field): bool
{
    $fid = strtolower($field['id'] ?? '');
    $lab = strtolower(trim($field['label'] ?? ''));

    if ($fid !== '' && str_contains($fid, 'country')) {
        return true;
    }
    if ($fid !== '' && str_contains($fid, 'staat') && !str_contains($fid, 'verein')) {
        return true;
    }

    if (in_array($lab, ['land', 'country', 'staat', 'staatsangehörigkeit', 'nationalität'], true)) {
        return true;
    }

    if ($lab !== '' && (
        str_contains($lab, 'land') && !str_contains($lab, 'urlaub')
        && !str_contains($lab, 'leistungs')
        && !str_contains($lab, 'einzugs')
    )) {
        if (str_contains($lab, 'wohn') || str_contains($lab, 'mitglied')) {
            return true;
        }
    }

    $full = $fid . ' ' . $lab;
    if (str_contains($full, 'staatsangeh')) {
        return true;
    }

    return false;
}

function bes_country_filter_option_label(string $option_value): string
{
    $v = trim($option_value);
    if (preg_match('/^[A-Z]{2}$/', $v) && bes_country_iso_is_known($v)) {
        $labels = bes_country_iso_german_labels();

        return $labels[$v] ?? $v;
    }

    return $v;
}

/**
 * Von Rohwerten aus Mitgliedern zu eindeutigen Optionswerten (ISO oder unbekannt roh), sortiert nach Anzeigename.
 *
 * @param array<int, string> $raw_values
 * @return array<int, string>
 */
function bes_country_filter_merge_distinct_values(array $raw_values): array
{
    $canonical = [];

    foreach ($raw_values as $r) {
        $r = (string) $r;
        if ($r === '') {
            continue;
        }
        $iso    = bes_normalize_country_token($r);
        $key    = $iso ?: $r;
        $keyU   = $iso ? strtoupper($iso) : $key;
        $canonical[$keyU] = true;
    }

    $keys = array_keys($canonical);
    usort($keys, function ($a, $b) {
        $la = bes_country_filter_option_label($a);
        $lb = bes_country_filter_option_label($b);

        return strcmp($la, $lb);
    });

    return $keys;
}
