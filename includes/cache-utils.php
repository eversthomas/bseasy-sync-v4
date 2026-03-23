<?php
/**
 * BSEasy Sync - Cache Utilities
 * 
 * @package BSEasySync
 * @author Tom Evers <https://bezugssysteme.de>
 * @since 3.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Invalidiert alle Render-Caches
 * 
 * @return int Anzahl gelöschter Caches
 */
function bes_clear_render_cache(): int
{
    global $wpdb;
    
    $deleted = 0;
    $transients = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             OR option_name LIKE %s",
            $wpdb->esc_like('_transient_bes_members_render_') . '%',
            $wpdb->esc_like('_transient_bes_members_map_') . '%'
        )
    );
    
    foreach ($transients as $transient) {
        $key = str_replace(['_transient_', '_transient_timeout_'], '', $transient);
        if (delete_transient($key)) {
            $deleted++;
        }
    }
    
    bes_debug_log("Render-Cache invalidiert: {$deleted} Einträge gelöscht", 'INFO', 'cache');
    
    return $deleted;
}

/**
 * Generiert einen Cache-Key basierend auf Datei-Modifikationszeiten
 * 
 * @param array $files Array von Dateipfaden
 * @param string $prefix Optionaler Prefix für den Cache-Key
 * @return string Cache-Key
 */
function bes_generate_cache_key(array $files, string $prefix = 'bes'): string
{
    $key_parts = [];
    foreach ($files as $file) {
        $key_parts[] = $file;
        $key_parts[] = file_exists($file) ? filemtime($file) : 0;
    }
    
    return $prefix . '_' . md5(implode('|', $key_parts));
}

/**
 * Prüft ob Cache gültig ist
 * 
 * @param string $cache_key Cache-Key
 * @return bool|string Cached-Daten oder false
 */
function bes_get_cached(string $cache_key)
{
    // Cache deaktiviert? (z.B. in Dev-Mode)
    if (defined('BES_CACHE_DISABLED') && BES_CACHE_DISABLED) {
        return false;
    }
    
    return get_transient($cache_key);
}

/**
 * Speichert Daten im Cache
 * 
 * @param string $cache_key Cache-Key
 * @param mixed $data Zu speichernde Daten
 * @param int|null $duration Cache-Dauer in Sekunden (Standard: BES_CACHE_DURATION)
 * @return bool Erfolg
 */
function bes_set_cached(string $cache_key, $data, ?int $duration = null): bool
{
    // Cache deaktiviert? (z.B. in Dev-Mode)
    if (defined('BES_CACHE_DISABLED') && BES_CACHE_DISABLED) {
        bes_debug_log("Cache deaktiviert - überspringe Speicherung: $cache_key", 'DEBUG', 'cache');
        return false;
    }
    
    if ($duration === null) {
        $duration = BES_CACHE_DURATION;
    }
    
    // Filter für Cache-Dauer
    if (function_exists('bes_filter_cache_duration')) {
        $duration = bes_filter_cache_duration($duration);
    }
    
    $result = set_transient($cache_key, $data, $duration);
    
    if ($result && defined('BES_DEV_MODE') && BES_DEV_MODE) {
        bes_debug_log("Cache gespeichert: $cache_key (Dauer: {$duration}s)", 'DEBUG', 'cache');
    }
    
    return $result;
}

/**
 * Gibt Cache-Statistiken zurück
 * 
 * @return array Array mit Cache-Informationen
 */
function bes_get_cache_stats(): array
{
    global $wpdb;
    
    $stats = [
        'total_entries' => 0,
        'render_cache' => 0,
        'map_cache' => 0,
        'select_options_cache' => 0,
        'rate_limit_cache' => 0,  // NEU: Rate-Limit-Transients
        'other_cache' => 0,        // NEU: Sonstige BES-Transients
        'total_size' => 0,
        'cache_enabled' => !(defined('BES_CACHE_DISABLED') && BES_CACHE_DISABLED),
        'cache_duration' => defined('BES_CACHE_DURATION') ? BES_CACHE_DURATION : 0,
        'dev_mode' => defined('BES_DEV_MODE') && BES_DEV_MODE,
    ];
    
    // Zähle alle BES-Transients
    $transients = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT option_name, option_value, LENGTH(option_value) as size 
             FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_name NOT LIKE %s",
            $wpdb->esc_like('_transient_bes_') . '%',
            $wpdb->esc_like('_transient_timeout_') . '%'
        ),
        ARRAY_A
    );
    
    foreach ($transients as $transient) {
        $key = str_replace('_transient_', '', $transient['option_name']);
        $categorized = false;
        
        if (strpos($key, 'bes_members_render_') === 0) {
            $stats['render_cache']++;
            $categorized = true;
        } elseif (strpos($key, 'bes_members_map_') === 0) {
            $stats['map_cache']++;
            $categorized = true;
        } elseif (strpos($key, 'bes_select_option_') === 0) {
            $stats['select_options_cache']++;
            $categorized = true;
        } elseif (strpos($key, 'bes_rate_limit_') === 0) {
            $stats['rate_limit_cache']++;
            $categorized = true;
        }
        
        // Wenn nicht kategorisiert, zähle als "sonstige"
        if (!$categorized) {
            $stats['other_cache']++;
        }
        
        $stats['total_entries']++;
        $stats['total_size'] += intval($transient['size']);
    }
    
    // Größe in lesbarem Format
    $stats['total_size_mb'] = round($stats['total_size'] / 1024 / 1024, 2);
    
    return $stats;
}

