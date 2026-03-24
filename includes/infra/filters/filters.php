<?php
/**
 * BSEasy Sync - Filter Hooks für Entwickler
 * 
 * @package BSEasySync
 * @author Tom Evers <https://bezugssysteme.de>
 * @since 3.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Filter: Erlaubt Anpassung der Cache-Dauer
 * 
 * @param int $duration Cache-Dauer in Sekunden
 * @return int Gefilterte Cache-Dauer
 */
function bes_filter_cache_duration(int $duration): int
{
    return apply_filters('bes_cache_duration', $duration);
}

/**
 * Filter: Erlaubt Anpassung der Batch-Größe
 * 
 * @param int $batch_size Batch-Größe
 * @return int Gefilterte Batch-Größe
 */
function bes_filter_batch_size(int $batch_size): int
{
    return apply_filters('bes_batch_size', $batch_size);
}

/**
 * Filter: Erlaubt Anpassung der API-Timeout
 * 
 * @param int $timeout Timeout in Sekunden
 * @return int Gefilterter Timeout
 */
function bes_filter_api_timeout(int $timeout): int
{
    return apply_filters('bes_api_timeout', $timeout);
}

/**
 * Filter: Erlaubt Anpassung der erlaubten HTML-Tags im Shortcode
 * 
 * @param array $allowed_html Erlaubte HTML-Tags
 * @return array Gefilterte erlaubte HTML-Tags
 */
function bes_filter_allowed_html(array $allowed_html): array
{
    return apply_filters('bes_allowed_html', $allowed_html);
}

/**
 * Filter: Erlaubt Anpassung der Marker-Daten vor Rendering
 * 
 * @param array $markers Marker-Daten
 * @return array Gefilterte Marker-Daten
 */
function bes_filter_map_markers(array $markers): array
{
    return apply_filters('bes_map_markers', $markers);
}

/**
 * Filter: Erlaubt Anpassung der Mitglieder-Daten vor Rendering
 * 
 * @param array $members Mitglieder-Daten
 * @return array Gefilterte Mitglieder-Daten
 */
function bes_filter_members_data(array $members): array
{
    return apply_filters('bes_members_data', $members);
}

/**
 * Action: Wird nach erfolgreichem Sync ausgelöst
 * 
 * @param array $result Sync-Ergebnis
 */
function bes_do_after_sync(array $result): void
{
    do_action('bes_after_sync', $result);
}

/**
 * Action: Wird vor Sync-Start ausgelöst
 */
function bes_do_before_sync(): void
{
    do_action('bes_before_sync');
}

