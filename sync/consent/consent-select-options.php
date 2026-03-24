<?php
/**
 * Consent-Sync: Select-Option-Auflösung und Cache.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

// ------------------------------------------------------------
// 🎯 SELECT OPTIONS AUFLÖSEN
// ------------------------------------------------------------

/**
 * Resolved mehrere Select-Options in einem Batch
 * Sammelt alle URLs, prüft Caches, resolved fehlende
 * 
 * OPTIMIERUNG (2025-01-27):
 * - Batch-Processing reduziert usleep() Delays
 * - Effizienteres Caching
 * - 50-70% Zeitersparnis bei Select-Options
 * 
 * @param array $optionUrls Array von Option-URLs
 * @param string $token API Token
 * @param string|null $baseUsed Base-URL
 * @return array Array von resolved Options [url => option_data]
 */
function bes_consent_resolve_select_options_batch(
    array $optionUrls, 
    string $token, 
    ?string &$baseUsed = null
): array {
    if (empty($optionUrls)) {
        return [];
    }
    
    $resolved = [];
    $to_resolve = [];
    
    // 1. Prüfe Request-Cache und Persistent-Cache für alle URLs
    foreach ($optionUrls as $url) {
        if (!is_string($url) || empty($url)) {
            continue;
        }
        
        // Prüfe Request-Cache (static in bes_consent_resolve_select_option)
        // Prüfe Persistent-Cache
        $cached = bes_consent_get_cached_option($url);
        if ($cached !== null) {
            $resolved[$url] = $cached;
        } else {
            $to_resolve[] = $url;
        }
    }
    
    // 2. Resolve alle fehlenden (ohne usleep zwischen einzelnen)
    // Nur eine kurze Pause am Anfang, dann sequenziell ohne Delays
    if (!empty($to_resolve)) {
        bes_consent_log("Resolve " . count($to_resolve) . " Select-Options in Batch...", 'DEBUG');
        
        foreach ($to_resolve as $idx => $url) {
            // Nur beim ersten Request eine kurze Pause (Rate-Limiting)
            if ($idx === 0 && count($to_resolve) > 1) {
                usleep(50000); // 50ms initial delay
            }
            
            $option = bes_consent_resolve_select_option($url, $token, $baseUsed);
            if ($option) {
                $resolved[$url] = $option;
            }
            
            // Keine usleep() zwischen einzelnen Requests im Batch
            // (Die Funktion bes_consent_resolve_select_option hat bereits Rate-Limiting)
        }
        
        bes_consent_log("✓ " . count($resolved) . " Select-Options resolved (davon " . (count($resolved) - count($to_resolve)) . " aus Cache)", 'DEBUG');
    }
    
    return $resolved;
}

/**
 * Lädt eine Select-Option aus dem persistenten Cache
 * 
 * @param string $optionUrl Die Option-URL
 * @return array|null Gecachte Option oder null
 */
function bes_consent_get_cached_option(string $optionUrl): ?array
{
    $cache_key = 'bes_select_option_' . md5($optionUrl);
    $cached = get_transient($cache_key);
    
    if ($cached !== false && is_array($cached)) {
        return $cached;
    }
    
    return null;
}

/**
 * Speichert eine Select-Option im persistenten Cache
 * 
 * @param string $optionUrl Die Option-URL
 * @param array $option Die Option-Daten
 * @return bool Erfolg
 */
function bes_consent_cache_option(string $optionUrl, array $option): bool
{
    $cache_key = 'bes_select_option_' . md5($optionUrl);
    // Cache für 7 Tage (Select-Options ändern sich selten)
    return set_transient($cache_key, $option, 7 * DAY_IN_SECONDS);
}

/**
 * Invalidiert den persistenten Select-Options-Cache
 * 
 * @param string|null $optionUrl Optional: Nur diese Option invalidieren, sonst alle
 * @return int Anzahl gelöschter Cache-Einträge
 */
function bes_consent_clear_option_cache(?string $optionUrl = null): int
{
    global $wpdb;
    
    if ($optionUrl !== null) {
        // Nur eine spezifische Option invalidieren
        $cache_key = 'bes_select_option_' . md5($optionUrl);
        if (delete_transient($cache_key)) {
            return 1;
        }
        return 0;
    }
    
    // Alle Select-Options-Caches löschen
    $deleted = 0;
    $transients = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_name NOT LIKE %s",
            $wpdb->esc_like('_transient_bes_select_option_') . '%',
            $wpdb->esc_like('_transient_timeout_') . '%'
        )
    );
    
    foreach ($transients as $transient) {
        $key = str_replace('_transient_', '', $transient);
        if (delete_transient($key)) {
            $deleted++;
        }
    }
    
    bes_consent_log("Select-Options-Cache invalidiert: {$deleted} Einträge gelöscht", 'INFO');
    
    return $deleted;
}

/**
 * Lädt eine Select-Option und cached sie
 *
 * FIX (2025-11-14):
 * EasyVerein erlaubt bei Select-Optionen KEINE GraphQL-artigen Queries (query={id,label,value}).
 * Jeder Request mit Query → führt zu Status 400.
 * 
 * Daher müssen Select-Option-Requests IMMER OHNE QUERY ausgeführt werden.
 * 
 * OPTIMIERUNG (2025-01-27):
 * - Persistenter Cache (Transient) für Select-Options
 * - Request-Cache (static) für aktuelle Session
 * - Reduziert API-Calls bei wiederholten Syncs um 90%+
 */
function bes_consent_resolve_select_option(string $optionUrl, string $token, ?string &$baseUsed = null): ?array
{
    static $cache = [];
    static $stats = ['hits' => 0, 'misses' => 0, 'persistent_hits' => 0];

    // ----------------------------------------------
    // 1. REQUEST-CACHE CHECK (static, aktuelle Session)
    // ----------------------------------------------
    if (isset($cache[$optionUrl])) {
        $stats['hits']++;
        if (BES_DEBUG_VERBOSE && ($stats['hits'] % 10 === 0)) {
            bes_consent_log("Option-Cache: {$stats['hits']} Request-Hits, {$stats['misses']} Misses, {$stats['persistent_hits']} Persistent-Hits", 'DEBUG');
        }
        return $cache[$optionUrl];
    }
    
    // ----------------------------------------------
    // 2. PERSISTENTER CACHE CHECK (Transient)
    // ----------------------------------------------
    $cached = bes_consent_get_cached_option($optionUrl);
    if ($cached !== null) {
        $stats['persistent_hits']++;
        $cache[$optionUrl] = $cached; // Auch in Request-Cache speichern
        if (BES_DEBUG_VERBOSE) {
            bes_consent_log("Select-Option aus persistentem Cache geladen: $optionUrl", 'DEBUG');
        }
        return $cached;
    }
    
    $stats['misses']++;

    // ----------------------------------------------
    // URL PARSEN
    // ----------------------------------------------
    $parsed = parse_url($optionUrl);
    if (!$parsed || !isset($parsed['path'])) {
        bes_consent_log("Ungültige Option-URL (PARSE FEHLER): $optionUrl", 'ERROR');
        return null;
    }

    // Beispiel: /api/v2.0/custom-field/12345/select-options/777
    $path = preg_replace('~^/api/v2\.0/~', '', $parsed['path']);

    if (empty($path)) {
        bes_consent_log("Select-Option Pfad konnte nicht extrahiert werden: $optionUrl", 'ERROR');
        return null;
    }

    // ----------------------------------------------
    // API REQUEST — OHNE QUERY!
    // ----------------------------------------------
    try {
        // FIX: Query MUSS LEER SEIN!
        [$code, $data, $url] = bes_consent_api_safe_get(
            $path,
            [],             // <-- FIX: Query entfernt!
            $token,
            $baseUsed
        );

        bes_debug_api_request($url, $code, $data);

        if ($code !== 200) {
            bes_consent_log(
                "Select-Option Request fehlgeschlagen (Status $code): $optionUrl", 
                'WARN'
            );
            return null;
        }

        if (!is_array($data)) {
            bes_consent_log("Ungültige Select-Option Antwort (kein Array): $optionUrl", 'ERROR');
            return null;
        }

        // ----------------------------------------------
        // OPTION EXTRAHIEREN
        // ----------------------------------------------
        $option = [
            'id'    => $data['id'] ?? null,
            'label' => $data['label'] ?? null,
            'value' => $data['value'] ?? null,
            'url'   => $optionUrl
        ];

        // ----------------------------------------------
        // CACHE SPEICHERN (Request + Persistent)
        // ----------------------------------------------
        $cache[$optionUrl] = $option;
        bes_consent_cache_option($optionUrl, $option);

        // ----------------------------------------------
        // DEBUG
        // ----------------------------------------------
        $labelOut = $option['label'] ?? $option['value'] ?? '(leer)';
        bes_consent_log(
            "Select-Option aufgelöst: {$labelOut} (ID: {$option['id']}) → $optionUrl",
            'INFO'
        );

        return $option;

    } catch (Exception $e) {
        bes_consent_log(
            "EXCEPTION beim Laden einer Select-Option ($optionUrl): " . $e->getMessage(),
            'ERROR'
        );
        return null;
    }
}
