<?php
/**
 * BSEasy Sync - Generische API-Request-Funktionen
 * 
 * Enthält alle generischen API-Request-Funktionen für die EasyVerein API.
 * Diese Funktionen werden sowohl von V2 als auch V3 verwendet.
 * 
 * @package BSEasySync
 * @subpackage Consent
 */

if (!defined('ABSPATH')) exit;

// Lade Basis-Konstanten
if (!defined('BES_DATA')) {
    $main_file = plugin_dir_path(__DIR__) . 'bseasy-sync.php';
    if (file_exists($main_file)) {
        require_once $main_file;
    } else {
        $upload_dir = wp_upload_dir();
        define('BES_UPLOADS_DIR', trailingslashit($upload_dir['basedir']) . 'bseasy-sync/');
        define('BES_UPLOADS_URL', trailingslashit($upload_dir['baseurl']) . 'bseasy-sync/');
        define('BES_DATA', BES_UPLOADS_DIR);
        define('BES_IMG', BES_UPLOADS_DIR . 'img/');
    }
}

// Lade Token-Refresh-Funktion
require_once BES_DIR . 'sync/api-core-consent-token.php';
// Lade gemeinsame Funktionen (für bes_consent_log, etc.)
// WICHTIG: Nur laden wenn bes_consent_api_get noch nicht existiert (verhindert zirkuläre Abhängigkeit)
if (!function_exists('bes_consent_api_get')) {
    require_once BES_DIR . 'sync/api-core-consent.php';
}

// ============================================================
// 🔗 GENERISCHE API-FUNKTIONEN
// ============================================================

/**
 * Optimierte API-Request-Funktion mit Token-Refresh
 *
 * @param string $path API-Pfad (relativ, ohne leading /)
 * @param array $query Query-Parameter
 * @param string $token API Token (wird bei Refresh aktualisiert)
 * @param string|null $baseUsed Verwendete Base-URL
 * @return array [code, data, body, headers, url]
 */
if (!function_exists('bes_consent_api_get')) {
function bes_consent_api_get(string $path, array $query, string &$token, ?string &$baseUsed = null): array
{
    $last_error = null;
    $errors_by_base = []; // Sammle Fehler pro Base-URL für besseres Logging

    // Prüfe ob $path eine absolute URL ist
    $is_absolute_url = preg_match('~^https?://~i', $path);
    
    if ($is_absolute_url) {
        // Absolute URL: direkt verwenden, Base-Schleife überspringen
        $url = $path;
        if ($query) {
            // Query-Parameter anhängen (prüfe ob bereits vorhanden)
            $separator = strpos($url, '?') !== false ? '&' : '?';
            $url .= $separator . http_build_query($query);
        }
        
        // Extrahiere Base-URL aus absoluter URL für $baseUsed
        $parsed = parse_url($url);
        if ($parsed && isset($parsed['scheme']) && isset($parsed['host'])) {
            $baseUsed = $parsed['scheme'] . '://' . $parsed['host'] . '/api';
        }
        
        // Führe Request direkt aus (ohne Base-Schleife)
        $timeout = defined('BES_API_TIMEOUT') ? BES_API_TIMEOUT : 45;
        if (function_exists('bes_get_safe_timeout')) {
            $timeout = bes_get_safe_timeout($timeout);
        }

        $args = [
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate',
                'User-Agent' => 'BSEasy-Sync/' . BES_VERSION . '; ' . home_url(),
            ],
            'sslverify' => true,
            'compress' => true,
        ];

        $args = apply_filters('bes_api_http_request_args', $args, $url);

        $res = wp_remote_get($url, $args);

        if (is_wp_error($res)) {
            $error_message = $res->get_error_message();
            $error_code = $res->get_error_code();
            $error_log = "WP Error bei absoluter URL: $url";
            if ($error_code) {
                $error_log .= " | Error-Code: $error_code";
            }
            $error_log .= " | Message: $error_message";
            bes_consent_log($error_log, 'ERROR');
            throw new Exception("Fehler bei absoluter URL $url: $error_message");
        }

        $code = wp_remote_retrieve_response_code($res);
        $headers = wp_remote_retrieve_headers($res);
        $body = wp_remote_retrieve_body($res);

        // ⚠️ TOKEN-REFRESH PRÜFUNG
        if (isset($headers['tokenrefreshneeded']) && strtolower((string)$headers['tokenrefreshneeded']) === 'true') {
            bes_consent_log("Token-Refresh erforderlich - aktualisiere Token...", 'INFO');
            $token = bes_consent_api_refresh_token($token);

            // Retry mit neuem Token
            $args['headers']['Authorization'] = 'Bearer ' . $token;
            $res = wp_remote_get($url, $args);
            if (!is_wp_error($res)) {
                $code = wp_remote_retrieve_response_code($res);
                $body = wp_remote_retrieve_body($res);
                $headers = wp_remote_retrieve_headers($res);
            } else {
                $error_message = $res->get_error_message();
                $error_code = $res->get_error_code();
                $error_log = "WP Error (nach Token-Refresh) bei absoluter URL: $url";
                if ($error_code) {
                    $error_log .= " | Error-Code: $error_code";
                }
                $error_log .= " | Message: $error_message";
                bes_consent_log($error_log, 'ERROR');
                throw new Exception("Fehler nach Token-Refresh bei absoluter URL $url: $error_message");
            }
        }

        do_action('bes_api_after_api_request', $url, $code, $body);

        $json = json_decode($body, true);

        if ($json === null && $body !== '') {
            bes_consent_log("JSON Parse Error bei $url (Status: $code)", 'WARN');
            if (defined('BES_DEBUG_VERBOSE') && BES_DEBUG_VERBOSE) {
                bes_consent_log("Response Body (erste 500 Zeichen): " . substr($body, 0, 500), 'DEBUG');
            }
            throw new Exception("JSON Parse Error bei absoluter URL $url (Status: $code)");
        }

        return [$code, $json, $body, $headers, $url];
    }

    // Relative Path: wie bisher mit Base-Schleife
    foreach (BES_API_BASES as $base) {
        $url = rtrim($base, '/') . '/' . BES_API_VERSION . '/' . ltrim($path, '/');
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $timeout = defined('BES_API_TIMEOUT') ? BES_API_TIMEOUT : 45;
        if (function_exists('bes_get_safe_timeout')) {
            $timeout = bes_get_safe_timeout($timeout);
        }

        $args = [
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Accept-Encoding' => 'gzip, deflate',
                'User-Agent' => 'BSEasy-Sync/' . BES_VERSION . '; ' . home_url(),
            ],
            'sslverify' => true,
            'compress' => true,
        ];

        $args = apply_filters('bes_api_http_request_args', $args, $url);

        $res = wp_remote_get($url, $args);

        if (is_wp_error($res)) {
            $error_message = $res->get_error_message();
            $error_code = $res->get_error_code();
            $last_error = $error_message;
            
            // Detailliertes Logging für WP_Error
            $error_log = "WP Error bei Base-URL: $base | URL: $url";
            if ($error_code) {
                $error_log .= " | Error-Code: $error_code";
            }
            $error_log .= " | Message: $error_message";
            bes_consent_log($error_log, 'ERROR');
            
            $errors_by_base[$base] = "WP_Error ($error_code): $error_message";
            continue;
        }

        $code = wp_remote_retrieve_response_code($res);
        $headers = wp_remote_retrieve_headers($res);
        $body = wp_remote_retrieve_body($res);
        $baseUsed = $base;

        // ⚠️ TOKEN-REFRESH PRÜFUNG
        if (isset($headers['tokenrefreshneeded']) && strtolower((string)$headers['tokenrefreshneeded']) === 'true') {
            bes_consent_log("Token-Refresh erforderlich - aktualisiere Token...", 'INFO');
            $token = bes_consent_api_refresh_token($token);

            // Retry mit neuem Token
            $args['headers']['Authorization'] = 'Bearer ' . $token;
            $res = wp_remote_get($url, $args);
            if (!is_wp_error($res)) {
                $code = wp_remote_retrieve_response_code($res);
                $body = wp_remote_retrieve_body($res);
                $headers = wp_remote_retrieve_headers($res);
            } else {
                // Auch Retry kann fehlschlagen
                $error_message = $res->get_error_message();
                $error_code = $res->get_error_code();
                $error_log = "WP Error (nach Token-Refresh) bei Base-URL: $base | URL: $url";
                if ($error_code) {
                    $error_log .= " | Error-Code: $error_code";
                }
                $error_log .= " | Message: $error_message";
                bes_consent_log($error_log, 'ERROR');
                $errors_by_base[$base] = "WP_Error nach Token-Refresh ($error_code): $error_message";
                continue;
            }
        }

        // ⚠️ 429 RATE-LIMIT PRÜFUNG (VOR allgemeiner 4xx/5xx Behandlung)
        if ($code === 429) {
            // Parse Retry-After Header oder detail-Text
            $retry_after_seconds = null;
            
            // 1) Retry-After Header (case-insensitive)
            if (isset($headers['retry-after'])) {
                $retry_after_seconds = (int)$headers['retry-after'];
            } elseif (isset($headers['Retry-After'])) {
                $retry_after_seconds = (int)$headers['Retry-After'];
            }
            
            if ($retry_after_seconds > 0) {
                bes_consent_log("429 Rate-Limit bei $url – Retry-After Header: {$retry_after_seconds}s", 'WARN');
            }
            
            // 2) Fallback: Parse aus detail-Text "Erwarte Verfügbarkeit in X Sekunden"
            if (($retry_after_seconds === null || $retry_after_seconds <= 0) && $body !== '') {
                $json_data = json_decode($body, true);
                if (is_array($json_data) && isset($json_data['detail']) && is_string($json_data['detail'])) {
                    if (preg_match('/Erwarte Verfügbarkeit in (\d+) Sekunden?/i', $json_data['detail'], $matches)) {
                        $retry_after_seconds = (int)$matches[1];
                        bes_consent_log("429 Rate-Limit bei $url – Parsed aus detail-Text: {$retry_after_seconds}s", 'WARN');
                    }
                }
            }
            
            // 3) Fallback: Exponential Backoff (2, 4, 8, 16, 32, max 60)
            if ($retry_after_seconds === null || $retry_after_seconds <= 0) {
                static $backoff_attempts = [];
                $backoff_key = $base . '|' . $path;
                if (!isset($backoff_attempts[$backoff_key])) {
                    $backoff_attempts[$backoff_key] = 0;
                }
                $backoff_attempts[$backoff_key]++;
                $backoff_power = min(5, $backoff_attempts[$backoff_key] - 1); // max 2^5 = 32
                $retry_after_seconds = min(60, pow(2, max(1, $backoff_power))); // 2, 4, 8, 16, 32, 60 (max)
                bes_consent_log("429 Rate-Limit bei $url – Exponential Backoff: {$retry_after_seconds}s (Versuch {$backoff_attempts[$backoff_key]})", 'WARN');
            }
            
            // Sleep + Retry (max 3 Versuche pro Base)
            static $retry_counts = [];
            $retry_key = $base . '|' . $path;
            if (!isset($retry_counts[$retry_key])) {
                $retry_counts[$retry_key] = 0;
            }
            $retry_counts[$retry_key]++;
            
            if ($retry_counts[$retry_key] <= 3) {
                bes_consent_log("429 Rate-Limit bei $base – Warte {$retry_after_seconds}s und retry (Versuch {$retry_counts[$retry_key]}/3)...", 'WARN');
                sleep($retry_after_seconds + 1); // +1 für Sicherheit
                // Retry: gehe zurück zum Request (continue in Base-Schleife)
                continue; // Springe zurück zum foreach (Bases-Schleife)
            } else {
                // Max Retries erreicht
                bes_consent_log("429 Rate-Limit bei $base – Max Retries (3) erreicht, versuche nächste Base", 'WARN');
                $errors_by_base[$base] = "429 Rate-Limit nach 3 Retries";
                unset($retry_counts[$retry_key]); // Reset für nächste Base
                continue; // Versuche nächste Base
            }
        }

        // Logging für HTTP-Statuscodes >= 400
        if ($code >= 400) {
            $body_preview = strlen($body) > 200 ? substr($body, 0, 200) . '...' : $body;
            $error_log = "HTTP $code bei Base-URL: $base | URL: $url | Path: $path";
            $error_log .= " | Body (first 200 chars): $body_preview";
            bes_consent_log($error_log, 'ERROR');
            
            // Versuche aussagekräftige Fehlermeldung aus Body zu extrahieren
            $json_data = json_decode($body, true);
            if (is_array($json_data)) {
                $detail = $json_data['detail'] ?? $json_data['message'] ?? $json_data['error'] ?? null;
                if ($detail) {
                    $errors_by_base[$base] = "base: $base url: $url status: $code detail: $detail";
                } else {
                    $errors_by_base[$base] = "base: $base url: $url status: $code";
                }
            } else {
                $errors_by_base[$base] = "base: $base url: $url status: $code";
            }
            
            // Bei 4xx/5xx: nächste Base-URL versuchen
            continue;
        }

        do_action('bes_api_after_api_request', $url, $code, $body);

        $json = json_decode($body, true);

        if ($json === null && $body !== '') {
            bes_consent_log("JSON Parse Error bei $url (Status: $code)", 'WARN');
            if (defined('BES_DEBUG_VERBOSE') && BES_DEBUG_VERBOSE) {
                bes_consent_log("Response Body (erste 500 Zeichen): " . substr($body, 0, 500), 'DEBUG');
            }
            $errors_by_base[$base] = "JSON Parse Error (Status: $code)";
            continue;
        }

        return [$code, $json, $body, $headers, $url];
    }

    // Erstelle aussagekräftige Fehlermeldung
    $error = 'Keine Basis-URL erreichbar';
    
    if (!empty($errors_by_base)) {
        $error_details = [];
        foreach ($errors_by_base as $base => $err_msg) {
            $error_details[] = "$base: $err_msg";
        }
        $error .= ' | Fehler pro Base: ' . implode('; ', $error_details);
    } elseif ($last_error) {
        $error .= ' (Letzter Fehler: ' . $last_error . ')';
    }
    
    bes_consent_log($error, 'ERROR');
    throw new Exception($error);
}
}

/**
 * Optimierte API-Request mit Retry-Logik
 */
if (!function_exists('bes_consent_api_safe_get')) {
function bes_consent_api_safe_get(string $path, array $query, string &$token, ?string &$baseUsed = null, int $retries = 3): array
{
    $delay = 15;
    for ($i = 0; $i < $retries; $i++) {
        try {
            [$code, $data, $body, $headers, $url] = bes_consent_api_get($path, $query, $token, $baseUsed);
        } catch (Exception $e) {
            // Exception könnte 429 sein (wenn beide Bases 429 liefern)
            $error_msg = $e->getMessage();
            if (strpos($error_msg, '429') !== false || strpos($error_msg, 'Rate-Limit') !== false) {
                bes_consent_log("429 Exception bei $path – Warte $delay s und retry...", 'WARN');
                sleep($delay);
                $delay *= 2;
                continue;
            }
            // Andere Exception: weiterwerfen
            throw $e;
        }

        if (function_exists('bes_debug_api_request')) {
            bes_debug_api_request($url, $code, $data);
        }

        // 429 erkennen über Status-Code ODER detail-Text (PHP 7.4 kompatibel)
        if ($code === 429 || (is_array($data) && isset($data['detail']) && is_string($data['detail']) && strpos($data['detail'], 'gedrosselt') !== false)) {
            bes_consent_log("Rate-Limit erreicht bei $path (Status: $code) – Warte $delay s...", 'WARN');
            sleep($delay);
            $delay *= 2;
            continue;
        }

        return [$code, $data, $url];
    }

    bes_consent_log("Wiederholte Drosselung bei $path – Abbruch nach $retries Versuchen.", 'ERROR');
    return [429, ['detail' => 'Drosselung nach mehreren Versuchen'], null];
}
}

/**
 * Robust: versucht Request erst mit query-Param, und wenn 4xx zurückkommt, nochmal ohne query.
 * (Weil query in Spec teils nicht dokumentiert ist.)
 */
if (!function_exists('bes_consent_api_safe_get_try_query')) {
function bes_consent_api_safe_get_try_query(string $path, array $query, string &$token, ?string &$baseUsed = null): array
{
    // 1) try as-is
    [$code, $data, $url] = bes_consent_api_safe_get($path, $query, $token, $baseUsed);
    if ($code === 200) return [$code, $data, $url];

    // 2) fallback: remove 'query' and retry once
    if (isset($query['query'])) {
        $q2 = $query;
        unset($q2['query']);
        [$code2, $data2, $url2] = bes_consent_api_safe_get($path, $q2, $token, $baseUsed);
        return [$code2, $data2, $url2];
    }

    return [$code, $data, $url];
}
}

// ============================================================
// 🧠 CustomField-ID -> Name auflösen (ID bleibt konfigurierbar)
// ============================================================

/**
 * Holt den Namen eines CustomFields anhand seiner ID (mit WP-Transient Cache)
 */
if (!function_exists('bes_consent_api_get_custom_field_name_by_id')) {
function bes_consent_api_get_custom_field_name_by_id(int $customFieldId, string &$token, ?string &$baseUsed = null): ?string
{
    $cache_key = 'bes_cf_name_' . $customFieldId;
    $cached = get_transient($cache_key);
    if (is_string($cached) && $cached !== '') {
        return $cached;
    }

    [$code, $data, $url] = bes_consent_api_safe_get("custom-field/$customFieldId", [], $token, $baseUsed);

    if ($code !== 200 || !is_array($data)) {
        bes_consent_log("❌ CustomField $customFieldId konnte nicht geladen werden (Status $code)", 'ERROR');
        return null;
    }

    $name = $data['name'] ?? $data['fieldName'] ?? null;
    if (!is_string($name) || $name === '') {
        bes_consent_log("❌ CustomField $customFieldId: kein 'name'/'fieldName' gefunden", 'ERROR');
        return null;
    }

    set_transient($cache_key, $name, 12 * HOUR_IN_SECONDS);
    return $name;
}
}

/**
 * Holt Metadaten eines CustomFields (Name/Label/Typ) anhand seiner ID (mit WP-Transient Cache)
 *
 * @return array|null z.B. ['id'=>..., 'name'=>..., 'label'=>..., 'type'=>...]
 */
if (!function_exists('bes_consent_api_get_custom_field_meta_by_id')) {
function bes_consent_api_get_custom_field_meta_by_id(int $customFieldId, string &$token, ?string &$baseUsed = null): ?array
{
    $cache_key = 'bes_cf_meta_' . $customFieldId;
    $cached = get_transient($cache_key);
    if (is_array($cached) && !empty($cached)) {
        return $cached;
    }

    // try with query; fallback without query handled by try_query
    [$code, $data, $url] = bes_consent_api_safe_get_try_query(
        "custom-field/$customFieldId",
        ['query' => '{*}'],
        $token,
        $baseUsed
    );

    if ($code !== 200 || !is_array($data)) {
        bes_consent_log("❌ CustomField META $customFieldId konnte nicht geladen werden (Status $code)", 'ERROR');
        return null;
    }

    $meta = [
        'id'    => $data['id'] ?? $customFieldId,
        'name'  => $data['name'] ?? ($data['fieldName'] ?? null),
        'label' => $data['label'] ?? ($data['displayName'] ?? null),
        'type'  => $data['type'] ?? ($data['fieldType'] ?? ($data['dataType'] ?? null)),
    ];

    // Filter: leere Werte entfernen
    $meta = array_filter($meta, static fn($v) => $v !== null && $v !== '');

    set_transient($cache_key, $meta, 12 * HOUR_IN_SECONDS);
    return $meta;
}
}


// ============================================================
// 📄 Pagination Helper (für Member-Liste + CustomFields!)
// ============================================================

/**
 * Holt ALLE Seiten eines list-endpoints (next/results) über page-Inkrement,
 * und stoppt, wenn next leer ist.
 *
 * WICHTIG: Viele Endpoints haben limit max 100 -> immer limit=100 nutzen.
 */
if (!function_exists('bes_consent_api_fetch_all_list')) {
function bes_consent_api_fetch_all_list(string $path, array $query, string &$token, ?string &$baseUsed = null, int $max_pages = 200): array
{
    $all = [];
    $page = 1;

    while ($page <= $max_pages) {
        // wir nutzen page, weil du es bereits erfolgreich in Phase 1 benutzt
        $q = $query;
        if (!isset($q['page'])) $q['page'] = $page;

        [$code, $data, $url] = bes_consent_api_safe_get_try_query($path, $q, $token, $baseUsed);

        if ($code !== 200 || !is_array($data)) {
            bes_consent_log("❌ Fehler beim Laden $path Seite $page (Status $code)", 'ERROR');
            break;
        }

        $list = bes_consent_norm_list($data);
        foreach ($list as $row) {
            $all[] = $row;
        }

        $hasNext = !empty($data['next'] ?? null);
        if (!$hasNext) break;

        $page++;
        usleep(200000);
    }

    return $all;
}
}

/**
 * Wie bes_consent_api_fetch_all_list(), aber gibt zusätzlich Meta-Infos zurück,
 * damit Fehler (z.B. 404/403/Timeout) nicht stillschweigend als [] enden.
 *
 * @return array{items:array, meta:array}
 */
if (!function_exists('bes_consent_api_fetch_all_list_with_meta')) {
function bes_consent_api_fetch_all_list_with_meta(string $path, array $query, string &$token, ?string &$baseUsed = null, int $max_pages = 200): array
{
    $all = [];
    $page = 1;

    $meta = [
        'ok' => true,
        'path' => $path,
        'last_status' => null,
        'last_url' => null,
        'failed_page' => null,
        'error' => null,
    ];

    while ($page <= $max_pages) {
        $q = $query;
        if (!isset($q['page'])) $q['page'] = $page;

        [$code, $data, $url] = bes_consent_api_safe_get_try_query($path, $q, $token, $baseUsed);
        $meta['last_status'] = $code;
        $meta['last_url'] = $url;

        // 429 Rate-Limit: retry gleiche Seite statt break
        if ($code === 429) {
            $retry_after_seconds = null;
            
            // Parse Retry-After aus data oder exponential backoff
            if (is_array($data) && isset($data['detail']) && is_string($data['detail'])) {
                if (preg_match('/Erwarte Verfügbarkeit in (\d+) Sekunden?/i', $data['detail'], $matches)) {
                    $retry_after_seconds = (int)$matches[1];
                }
            }
            
            if ($retry_after_seconds === null || $retry_after_seconds <= 0) {
                $retry_after_seconds = 30; // Fallback
            }
            
            $meta['ok'] = false;
            $meta['retry_after'] = $retry_after_seconds;
            bes_consent_log("429 Rate-Limit bei $path Seite $page – Warte {$retry_after_seconds}s und retry...", 'WARN');
            sleep($retry_after_seconds + 1);
            usleep(200000); // Zusätzliche Pause
            continue; // Gehe zurück zum while-Loop, versuche gleiche Seite erneut
        }

        if ($code !== 200 || !is_array($data)) {
            $meta['ok'] = false;
            $meta['failed_page'] = $page;
            $meta['error'] = "Status $code";
            bes_consent_log("❌ Fehler beim Laden $path Seite $page (Status $code)", 'ERROR');
            break;
        }

        $list = bes_consent_norm_list($data);
        foreach ($list as $row) {
            $all[] = $row;
        }

        $hasNext = !empty($data['next'] ?? null);
        if (!$hasNext) break;

        $page++;
        usleep(200000);
    }

    return ['items' => $all, 'meta' => $meta];
}
}


// ============================================================
// 🎯 (Optional) Query-Strings – bleiben für Kompatibilität
// ============================================================

if (!function_exists('bes_consent_api_get_member_list_query')) {
function bes_consent_api_get_member_list_query(): string
{
    return '{id,contactDetails{id},customFields{id,value}}';
}
}

if (!function_exists('bes_consent_api_get_member_detail_query')) {
function bes_consent_api_get_member_detail_query(): string
{
    return '{id,emailOrUserName,membershipNumber,_profilePicture,contactDetails{id,name,firstName,familyName,street,city,zip,state,country,privateEmail,companyEmail,dateOfBirth,phone,mobile,companyName},customFields{id}}';
}
}

if (!function_exists('bes_consent_api_get_contact_detail_query')) {
function bes_consent_api_get_contact_detail_query(): string
{
    return '{id,name,firstName,familyName,street,city,zip,state,country,privateEmail,companyEmail,dateOfBirth,phone,mobile,companyName,_profilePicture,customFields{id}}';
}
}
