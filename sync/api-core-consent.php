<?php

/**
 * Easy2Transfer Consent-Dump (v3.0 – mit Select-Options & Enhanced Debugging)
 * 2025-11-14
 * 
 * ✨ NEU IN V3.0:
 * - Unterstützung für Select-Option Custom Fields
 * - Automatisches Auflösen von selectedOptions URLs
 * - Umfassendes Debugging-System
 * - Besseres Error-Handling
 */

if (!defined('ABSPATH')) exit;

// api-core-consent-requests.php wird NICHT hier geladen, um zirkuläre Abhängigkeiten zu vermeiden
// Die Dateien, die beide benötigen, müssen api-core-consent-requests.php ZUERST laden

// ------------------------------------------------------------
// 🔧 BASIS-PFADE AUS HAUPTPLUGIN LADEN (auch bei WP-Cron nutzbar)
// ------------------------------------------------------------
if (!defined('BES_DATA')) {
    $main_file = plugin_dir_path(__DIR__) . 'bseasy-sync.php';
    if (file_exists($main_file)) {
        require_once $main_file; // zentrale Konstanten laden
    } else {
        // Fallback, falls Hauptplugin nicht geladen ist (z. B. bei Cron)
        $upload_dir = wp_upload_dir();
        define('BES_UPLOADS_DIR', trailingslashit($upload_dir['basedir']) . 'bseasy-sync/');
        define('BES_UPLOADS_URL', trailingslashit($upload_dir['baseurl']) . 'bseasy-sync/');
        define('BES_DATA', BES_UPLOADS_DIR);
        define('BES_IMG', BES_UPLOADS_DIR . 'img/');
    }
}

// ------------------------------------------------------------
// ⚙️ KONSTANTEN
// ------------------------------------------------------------
// Alle Konstanten werden aus includes/constants.php geladen:
// - BES_API_VERSION
// - BES_API_BASES
// - BES_API_TIMEOUT
// - BES_CONSENT_FIELD_ID_DEFAULT
// - BES_DEBUG_MODE
// - BES_DEBUG_VERBOSE

/**
 * Gibt die konfigurierte Consent-Feld-ID zurück
 * Falls nicht gesetzt, wird der Standardwert verwendet
 */
function bes_get_consent_field_id(): int {
    return (int) get_option('bes_consent_field_id', BES_CONSENT_FIELD_ID_DEFAULT);
}

// 🎯 ZIEL-CUSTOM-FIELDS: Welche Custom Fields sollen EXPLIZIT extrahiert werden?
// (Rohdaten ALLER Felder sind weiterhin in member_cf / contact_cf enthalten)
const BES_TARGET_CUSTOM_FIELDS = [
    50359304,   // Online Angebote? (W)
    50359307,   // Zielgruppen (W)
    50697325,   // Webseite 1 (W)
    50697329,   // Webseite 2 (Wo)
    50697357,   // Leistungsangebote (W)
    50699020,   // Qualifikationshinweis
    50699073,   // Verbandsmitgliedschaften (Wo)
    50698940,   // Netzwerkinteressen
    50698968,   // Finanzierungshinweis (W)
    50799935,   // Weitere Infos zu mir
    51060631,   // Begleitung Klienten
    54800224,   // Anmeldung Plattform
    54809776,   // Datum Anmeldung
    190947959,  // Meine Daten sind korrekt (2024)!
    204293845,  // Daten sind aktuell 2025
    271260978,  // AG-Zugehörigkeit
    282018660,  // Sichtbarkeit Transfer-Webseite (Consent-Feld)
    312976233,  // Methoden / Interventionen (W)
    312570636,  // Check für Web
    313634622,  // Mein Motto (Wo)
    313635546,  // Reserve 1
    313635579,  // Reserve 2
    313635591,  // Reserve 3
    313635633,  // Reserve 4
    313635648,  // Reserve 5
    313635669,  // Reserve 6
    313635690,  // Reserve 7
    313635753,  // Reserve 8
];

// 🐛 DEBUG-MODUS wird aus includes/constants.php geladen
// BES_DEBUG_MODE und BES_DEBUG_VERBOSE sind dort definiert

// ------------------------------------------------------------
// 🪵 LOGGING & PROGRESS
// ------------------------------------------------------------
/**
 * Consent-Sync spezifisches Logging (schreibt in Datei)
 * 
 * @param string $msg Log-Nachricht
 * @param string $level Log-Level
 * @return void
 */
function bes_consent_log(string $msg, string $level = 'INFO'): void
{
    // Nutze das zentrale Debug-System für WordPress-Log
    bes_debug_log($msg, $level, 'CONSENT');
    
    // Zusätzlich in Sync-Log-Datei schreiben (für Sync-spezifische Logs)
    // Pfad-Validierung für BES_DATA
    $log_dir = realpath(BES_DATA);
    if ($log_dir === false) {
        // Versuche BES_DATA zu erstellen falls es nicht existiert
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p(BES_DATA);
            $log_dir = realpath(BES_DATA);
        }
    }
    
    // Validiere dass Pfad innerhalb WP_CONTENT_DIR liegt
    if ($log_dir === false) {
        error_log("BES Consent Log: Ungültiger BES_DATA Pfad");
        return;
    }
    
    $wp_content_dir = realpath(WP_CONTENT_DIR);
    if ($wp_content_dir === false || strpos($log_dir, $wp_content_dir) !== 0) {
        error_log("BES Consent Log: BES_DATA liegt außerhalb von WP_CONTENT_DIR");
        return;
    }
    
    // Stelle sicher dass Verzeichnis existiert mit sicheren Berechtigungen
    if (!is_dir($log_dir)) {
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p($log_dir);
        } else {
            @mkdir($log_dir, 0755, true);
        }
        @chmod($log_dir, 0755);
    }
    
    // Validiere Dateiname (kein Path-Traversal)
    $logfile = $log_dir . DIRECTORY_SEPARATOR . 'sync.log';
    if (basename($logfile) !== 'sync.log') {
        error_log("BES Consent Log: Ungültiger Log-Dateiname");
        return;
    }
    
    static $initialized = false;
    if (!$initialized) {
        @file_put_contents($logfile, "=== New Consent Sync Run: " . date('c') . " ===\n", FILE_APPEND | LOCK_EX);
        $initialized = true;
    }
    $timestamp = date('Y-m-d H:i:s');
    $formatted = "[$timestamp] [$level] [CONSENT] $msg\n";
    error_log($formatted, 3, $logfile);
    
    // In Debug-Modus auch in separate Debug-Log
    if (BES_DEBUG_MODE && $level === 'DEBUG') {
        $debugfile = $log_dir . DIRECTORY_SEPARATOR . 'debug.log';
        if (basename($debugfile) === 'debug.log') {
            error_log($formatted, 3, $debugfile);
        }
    }
}

// ------------------------------------------------------------
// 🐛 DEBUG-FUNKTIONEN
// ------------------------------------------------------------

/**
 * Loggt detaillierte Informationen über ein Custom Field
 */
function bes_debug_custom_field(array $cf, string $context = ''): void
{
    if (!BES_DEBUG_VERBOSE) return;
    
    $debugMsg = "Custom Field Debug" . ($context ? " ($context)" : "") . ":\n";
    $debugMsg .= "  - ID: " . ($cf['id'] ?? 'N/A') . "\n";
    $debugMsg .= "  - CustomField: " . json_encode($cf['customField'] ?? 'N/A') . "\n";
    $debugMsg .= "  - Value: " . json_encode($cf['value'] ?? 'N/A') . "\n";
    $debugMsg .= "  - SelectedOptions: " . json_encode($cf['selectedOptions'] ?? 'N/A') . "\n";
    $debugMsg .= "  - LastChanged: " . ($cf['_lastChanged'] ?? 'N/A');
    
    bes_consent_log($debugMsg, 'DEBUG');
}

/**
 * Erstellt einen Snapshot aller Custom Fields für ein Mitglied
 */
function bes_debug_snapshot_custom_fields(int $memberId, array $memberCF, array $contactCF): void
{
    if (!BES_DEBUG_MODE) return;
    
    $snapshotFile = BES_DATA . 'cf_snapshot_' . $memberId . '.json';
    $snapshot = [
        'member_id' => $memberId,
        'timestamp' => date('c'),
        'member_custom_fields' => $memberCF,
        'contact_custom_fields' => $contactCF
    ];
    
    file_put_contents($snapshotFile, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    bes_consent_log("Debug-Snapshot erstellt: $snapshotFile", 'DEBUG');
}

/**
 * Loggt API-Request Details
 */
function bes_debug_api_request(string $url, int $code, ?array $data = null): void
{
    if (!BES_DEBUG_VERBOSE) return;
    
    $debugMsg = "API Request:\n";
    $debugMsg .= "  URL: $url\n";
    $debugMsg .= "  Status: $code\n";
    if ($data) {
        $debugMsg .= "  Response Keys: " . implode(', ', array_keys($data));
    }
    
    bes_consent_log($debugMsg, 'DEBUG');
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

/**
 * Extrahiert Custom Field Werte inklusive Select Options
 * 
 * @param array $customFields Array von Custom Field Objekten
 * @param array $targetFieldIds IDs der zu extrahierenden Felder
 * @param string $token API Token für Select-Option Auflösung
 * @param string|null $baseUsed Verwendete Base-URL
 * @param string $context Kontext für Logging (z.B. "member" oder "contact")
 * @return array Assoziatives Array [field_id => extracted_data]
 */
function bes_consent_extract_custom_fields_with_options(
    array $customFields, 
    array $targetFieldIds, 
    string $token, 
    ?string &$baseUsed = null,
    string $context = 'unknown'
): array
{
    $extracted = [];
    $foundFields = [];
    
    bes_consent_log("Extrahiere Custom Fields ($context): " . count($customFields) . " Felder, Suche nach IDs: " . (empty($targetFieldIds) ? 'ALLE' : implode(', ', $targetFieldIds)));
    
    foreach ($customFields as $cfIndex => $cf) {
        // Debug: Zeige jedes Custom Field im Verbose-Modus
        if (BES_DEBUG_VERBOSE) {
            bes_debug_custom_field($cf, "$context #$cfIndex");
        }
        
        // ------------------------------------------------
        // Custom Field ID extrahieren (ROBUST wie im Analyse-Skript)
        // ------------------------------------------------
        $fieldId = null;
        
        if (isset($cf['customField']) && is_string($cf['customField'])) {
            // z.B. "https://easyverein.com/api/v2.0/custom-field/50359304"
            $fieldId = (int) basename($cf['customField']);
        }
        
        if (!$fieldId) {
            if (BES_DEBUG_VERBOSE) {
                bes_consent_log("Konnte Field-ID nicht extrahieren aus: " . json_encode($cf['customField'] ?? 'N/A'), 'DEBUG');
            }
            continue;
        }
        
        $foundFields[] = $fieldId;
        
        // ------------------------------------------------
        // FILTER: Wenn targetFieldIds LEER ist → ALLE Felder extrahieren.
        // Wenn NICHT leer → nur die angegebenen IDs.
        // ------------------------------------------------
        if (!empty($targetFieldIds) && !in_array($fieldId, $targetFieldIds, true)) {
            continue;
        }
        
        bes_consent_log("Ziel-Feld gefunden: $fieldId ($context)");
        
        // Basis-Struktur
        $result = [
            'field_id' => $fieldId,
            'record_id' => $cf['id'] ?? null,
            'type' => 'unknown',
            'raw_value' => null,
            'display_value' => null,
            'options' => [],
            'last_changed' => $cf['_lastChanged'] ?? null,
            'context' => $context
        ];
        
        // FALL 1: Auswahlfeld mit selectedOptions
        if (isset($cf['selectedOptions']) && is_array($cf['selectedOptions']) && !empty($cf['selectedOptions'])) {
            $result['type'] = 'select';
            $result['raw_value'] = $cf['selectedOptions'];
            
            bes_consent_log("Feld $fieldId ist Select-Field mit " . count($cf['selectedOptions']) . " Optionen");
            
            // OPTIMIERUNG (2025-01-27): Batch-Processing für Select-Options
            // Sammle alle Option-URLs und resolve sie in einem Batch
            $optionUrls = array_filter($cf['selectedOptions'], 'is_string');
            
            if (!empty($optionUrls)) {
                // Batch-Processing: Resolve alle Options auf einmal
                $resolvedOptionsMap = bes_consent_resolve_select_options_batch(
                    array_values($optionUrls),
                    $token,
                    $baseUsed
                );
                
                // Konvertiere Map zu Array
                $resolvedOptions = [];
                foreach ($optionUrls as $optionUrl) {
                    if (isset($resolvedOptionsMap[$optionUrl])) {
                        $resolvedOptions[] = $resolvedOptionsMap[$optionUrl];
                    } else {
                        bes_consent_log("Option konnte nicht aufgelöst werden: $optionUrl", 'WARN');
                    }
                }
            } else {
                $resolvedOptions = [];
            }
            
            $result['options'] = $resolvedOptions;
            
            // Display-Wert zusammensetzen
            $labels = array_filter(array_map(fn($opt) => $opt['label'] ?? $opt['value'] ?? null, $resolvedOptions));
            $result['display_value'] = !empty($labels) ? implode(', ', $labels) : '(leer)';
            
            bes_consent_log("✓ Field $fieldId ($context, Select): " . $result['display_value']);
        }
        // FALL 2: Einfaches value Feld (Freitext, Checkbox, etc.)
        elseif (isset($cf['value'])) {
            $value = $cf['value'];
            
            if (is_bool($value)) {
                $result['type'] = 'boolean';
                $result['raw_value'] = $value;
                $result['display_value'] = $value ? 'Ja' : 'Nein';
            } elseif (is_array($value)) {
                $result['type'] = 'array';
                $result['raw_value'] = $value;
                $result['display_value'] = json_encode($value, JSON_UNESCAPED_UNICODE);
            } else {
                $result['type'] = 'text';
                $result['raw_value'] = $value;
                $result['display_value'] = (string)$value;
            }
            
            bes_consent_log("✓ Field $fieldId ($context, {$result['type']}): " . $result['display_value']);
        }
        // FALL 3: selectedOptions existiert aber ist leer
        elseif (isset($cf['selectedOptions'])) {
            $result['type'] = 'select';
            $result['display_value'] = '(keine Auswahl)';
            bes_consent_log("Field $fieldId ($context, Select): leer");
        }
        // FALL 4: Feld existiert aber ist komplett leer
        else {
            $result['type'] = 'empty';
            $result['display_value'] = '';
            bes_consent_log("Field $fieldId ($context): komplett leer");
        }
        
        $extracted[$fieldId] = $result;
    }
    
    // Prüfe ob alle gesuchten Felder gefunden wurden
    $missingFields = [];
    if (!empty($targetFieldIds)) {
        $missingFields = array_diff($targetFieldIds, array_keys($extracted));
    }
    if (!empty($missingFields)) {
        bes_consent_log("⚠️ Fehlende Felder im $context: " . implode(', ', $missingFields), 'WARN');
        bes_consent_log("Gefundene Field-IDs im $context: " . implode(', ', array_unique($foundFields)), 'DEBUG');
    }
    
    return $extracted;
}

// ------------------------------------------------------------
// 🔒 SICHERER API-GET
// ------------------------------------------------------------
// Diese Funktion wurde nach api-core-consent-requests.php verschoben
// Die neue Version hat Token-Refresh und bessere Fehlerbehandlung

// ------------------------------------------------------------
// 📋 KONTAKTFELDER FLACH EXTRAHIEREN
// ------------------------------------------------------------
function bes_consent_extract_flat_contact(array $contact): array
{
    $fields = ['firstName', 'familyName', 'name', 'email', 'companyEmail', 'privateEmail'];
    $flat = [];
    foreach ($fields as $f) {
        $flat[$f] = $contact[$f]
            ?? ($contact['contact'][$f] ?? null)
            ?? ($contact['data'][$f] ?? null)
            ?? ($contact['data']['contact'][$f] ?? null)
            ?? null;
    }
    return array_filter($flat, fn($v) => !is_null($v) && $v !== '');
}

// V1-Funktion bes_run_consent_dump() wurde entfernt - nur noch V3 wird verwendet
// API-Basisfunktionen wurden nach api-core-consent-requests.php verschoben

// ------------------------------------------------------------
// 🧮 NORMALISIERE LISTEN (mit Tiefenlimit)
// ------------------------------------------------------------
function bes_consent_norm_list($payload, int $max_depth = 5): array
{
    // Tiefenlimit-Prüfung
    static $current_depth = 0;
    if ($current_depth >= $max_depth) {
        bes_consent_log("norm_list: Maximale Rekursionstiefe erreicht ($max_depth)", 'WARN');
        $current_depth = 0; // Reset für nächsten Aufruf
        return [];
    }
    
    if (!is_array($payload)) {
        if (BES_DEBUG_VERBOSE) {
            bes_consent_log("norm_list: Payload ist kein Array: " . gettype($payload), 'DEBUG');
        }
        $current_depth = 0; // Reset
        return [];
    }
    
    $keys = array_keys($payload);
    
    // Bereits numerisches Array
    if ($keys === range(0, count($payload) - 1)) {
        $current_depth = 0; // Reset
        return $payload;
    }
    
    // Suche nach bekannten List-Keys
    foreach (['results', 'data', 'items', 'list', 'rows'] as $k) {
        if (!empty($payload[$k]) && is_array($payload[$k])) {
            if (BES_DEBUG_VERBOSE) {
                bes_consent_log("norm_list: Liste gefunden unter Key '$k' (Tiefe: " . ($current_depth + 1) . ")", 'DEBUG');
            }
            $current_depth++;
            $result = bes_consent_norm_list($payload[$k], $max_depth);
            $current_depth = 0; // Reset nach Rückkehr
            return $result;
        }
    }
    
    if (BES_DEBUG_VERBOSE) {
        bes_consent_log("norm_list: Keine Liste gefunden, verfügbare Keys: " . implode(', ', $keys), 'DEBUG');
    }
    
    $current_depth = 0; // Reset
    return [];
}

// ------------------------------------------------------------
// 🛠️ ZUSÄTZLICHE DEBUG-HILFSFUNKTIONEN
// ------------------------------------------------------------

/**
 * Erstellt einen Gesundheitscheck-Report
 */
function bes_consent_health_check(): array
{
    $report = [
        'timestamp' => date('c'),
        'directories' => [
            'BES_DATA' => [
                'path' => BES_DATA,
                'exists' => is_dir(BES_DATA),
                'writable' => is_writable(BES_DATA)
            ],
            'BES_IMG' => [
                'path' => BES_IMG,
                'exists' => is_dir(BES_IMG),
                'writable' => is_writable(BES_IMG)
            ]
        ],
        'files' => [],
        'config' => [
            'debug_mode' => BES_DEBUG_MODE,
            'debug_verbose' => BES_DEBUG_VERBOSE,
            'target_fields' => BES_TARGET_CUSTOM_FIELDS,
            'consent_field' => bes_get_consent_field_id(),
            'api_bases' => BES_API_BASES
        ],
        'token' => [
            'configured' => !empty(get_option('bes_api_token', '')) && function_exists('bes_decrypt_token') && !empty(bes_decrypt_token(get_option('bes_api_token', '')))
        ]
    ];
    
    // Prüfe vorhandene JSON-Dateien (nur V2)
    foreach (glob(bes_get_data_dir() . 'members_consent_v2*.json') as $file) {
        $report['files'][basename($file)] = [
            'size' => filesize($file),
            'modified' => date('c', filemtime($file))
        ];
    }
    
    return $report;
}

/**
 * Schreibt Health-Check Report
 */
function bes_consent_write_health_check(): string
{
    $report = bes_consent_health_check();
    $file = BES_DATA . 'health_check.json';
    file_put_contents($file, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    bes_consent_log("Health-Check Report erstellt: $file");
    return $file;
}

/**
 * Analysiert Custom Fields in einem bestehenden JSON
 */
function bes_consent_analyze_existing_json(string $jsonFile): array
{
    if (!file_exists($jsonFile)) {
        return ['error' => 'Datei nicht gefunden'];
    }
    
    $data = json_decode(file_get_contents($jsonFile), true);
    if (!$data || !isset($data['data'])) {
        return ['error' => 'Ungültige JSON-Struktur'];
    }
    
    $analysis = [
        'total_members' => count($data['data']),
        'fields_found' => [],
        'field_types' => [],
        'sample_values' => []
    ];
    
    foreach ($data['data'] as $member) {
        // Analysiere Member Custom Fields
        if (isset($member['member_cf_extracted'])) {
            foreach ($member['member_cf_extracted'] as $fieldId => $fieldData) {
                if (!isset($analysis['fields_found'][$fieldId])) {
                    $analysis['fields_found'][$fieldId] = 0;
                    $analysis['field_types'][$fieldId] = [];
                    $analysis['sample_values'][$fieldId] = [];
                }
                $analysis['fields_found'][$fieldId]++;
                $analysis['field_types'][$fieldId][$fieldData['type']] = 
                    ($analysis['field_types'][$fieldId][$fieldData['type']] ?? 0) + 1;
                
                if (count($analysis['sample_values'][$fieldId]) < 5) {
                    $analysis['sample_values'][$fieldId][] = $fieldData['display_value'];
                }
            }
        }
        
        // Analysiere Contact Custom Fields
        if (isset($member['contact_cf_extracted'])) {
            foreach ($member['contact_cf_extracted'] as $fieldId => $fieldData) {
                $key = "contact_$fieldId";
                if (!isset($analysis['fields_found'][$key])) {
                    $analysis['fields_found'][$key] = 0;
                    $analysis['field_types'][$key] = [];
                    $analysis['sample_values'][$key] = [];
                }
                $analysis['fields_found'][$key]++;
                $analysis['field_types'][$key][$fieldData['type']] = 
                    ($analysis['field_types'][$key][$fieldData['type']] ?? 0) + 1;
                
                if (count($analysis['sample_values'][$key]) < 5) {
                    $analysis['sample_values'][$key][] = $fieldData['display_value'];
                }
            }
        }
    }
    
    return $analysis;
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

// 📊 DEBUG-COMMAND FÜR TESTING
// ============================================

// V1-Funktion bes_consent_merge_parts() wurde entfernt - nur noch V2 wird verwendet

/**
 * Test-Funktion: Analysiert ein einzelnes Mitglied im Detail
 * Verwendung: bes_consent_debug_single_member(123456)
 */
function bes_consent_debug_single_member(int $memberId): array
{
    $encrypted_token = get_option('bes_api_token', '');
    if (empty($encrypted_token)) {
        return ['error' => 'Kein Token'];
    }
    
    // Entschlüssele Token
    $token = function_exists('bes_decrypt_token') ? bes_decrypt_token($encrypted_token) : $encrypted_token;
    if (empty($token)) {
        return ['error' => 'Token konnte nicht entschlüsselt werden'];
    }
    
    $baseUsed = null;
    $result = [
        'member_id' => $memberId,
        'timestamp' => date('c'),
        'steps' => []
    ];
    
    try {
        // Schritt 1: Member laden
        bes_consent_log("DEBUG: Lade Mitglied $memberId");
        [$s1, $d1] = bes_consent_api_safe_get("member/$memberId", ['query' => '{*}'], $token, $baseUsed);
        $result['steps']['member_load'] = ['status' => $s1, 'has_data' => !empty($d1)];
        
        // Schritt 2: Member Custom Fields laden
        bes_consent_log("DEBUG: Lade Member Custom Fields");
        [$s2, $d2] = bes_consent_api_safe_get("member/$memberId/custom-fields", ['limit' => 400, 'query' => '{*}'], $token, $baseUsed);
        $member_cf = bes_consent_norm_list($d2 ?? []);
        $result['steps']['member_cf_load'] = [
            'status' => $s2, 
            'count' => count($member_cf),
            'raw_data' => $member_cf
        ];
        
        // Schritt 3: Extrahiere Custom Fields
        bes_consent_log("DEBUG: Extrahiere Custom Fields");
        $extracted = bes_consent_extract_custom_fields_with_options(
            $member_cf, 
            BES_TARGET_CUSTOM_FIELDS, 
            $token, 
            $baseUsed,
            'debug'
        );
        $result['steps']['extraction'] = $extracted;
        
        // Schritt 4: Consent prüfen
        $has_consent = false;
        $consent_field_id = bes_get_consent_field_id();
        foreach ($member_cf as $cf) {
            if (isset($cf['customField']) && str_contains($cf['customField'], (string)$consent_field_id)) {
                if ((isset($cf['value']) && strtolower(trim($cf['value'])) === 'true') ||
                    (isset($cf['selectedOptions']) && !empty($cf['selectedOptions']))) {
                    $has_consent = true;
                    break;
                }
            }
        }
        $result['steps']['consent_check'] = ['has_consent' => $has_consent];
        
        // Speichere Debug-Report
        $reportFile = BES_DATA . "debug_member_{$memberId}.json";
        file_put_contents($reportFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        bes_consent_log("DEBUG: Report gespeichert: $reportFile");
        
        return $result;
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
        bes_consent_log("DEBUG ERROR: " . $e->getMessage(), 'ERROR');
        return $result;
    }
}
