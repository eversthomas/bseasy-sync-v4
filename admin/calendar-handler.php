<?php
if (!defined('ABSPATH')) exit;

/**
 * 📅 KALENDER-HANDLER (Mehrkalender)
 */

add_action('admin_post_bes_save_calendars', function () {
    if (!current_user_can('manage_options')) {
        wp_safe_redirect(add_query_arg([
            'page'      => 'bseasy-sync',
            'tab'       => 'kalender',
            'bes_error' => 'no_permission',
        ], admin_url('admin.php')));
        exit;
    }

    check_admin_referer('bes_save_calendars', 'bes_calendars_nonce');

    $input = $_POST['bes_calendars'] ?? [];
    if (!is_array($input)) {
        $input = [];
    }

    $clean = [];

    foreach ($input as $row) {
        if (!is_array($row)) {
            continue;
        }

        $clean[] = [
            'id'   => sanitize_title($row['id'] ?? ''),
            'name' => sanitize_text_field($row['name'] ?? ''),
            'url'  => esc_url_raw($row['url'] ?? ''),
            'max'  => min(5000, max(10, intval($row['max'] ?? 10))),
        ];
    }

    update_option('bes_calendars', $clean);

    if (defined('BES_DATA') && BES_DATA) {
        foreach ($clean as $cal) {
            if (empty($cal['id'])) {
                continue;
            }
            $cache_file = BES_DATA . 'calendar-cache-' . $cal['id'] . '.json';
            if (file_exists($cache_file)) {
                @unlink($cache_file);
            }
        }
    }

    wp_safe_redirect(add_query_arg(['page' => 'bseasy-sync', 'tab' => 'kalender', 'bes_saved' => 1], admin_url('admin.php')));
    exit;
});

/**
 * Manuellen ICS-Refresh auslösen (ein Kalender oder alle).
 * Holt den aktuellen Stand direkt von der ICS-Quelle und schreibt den Cache neu,
 * damit Redakteure nach einer Änderung in easyverein nicht auf den Cache-Ablauf warten müssen.
 */
add_action('admin_post_bes_refresh_calendar', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung.');
    }

    $id = isset($_GET['id']) ? sanitize_title(wp_unslash($_GET['id'])) : '';
    check_admin_referer('bes_refresh_calendar_' . $id);

    $calendars = get_option('bes_calendars', []);
    $targets = [];

    if ($id === '') {
        $targets = $calendars;
    } else {
        foreach ($calendars as $cal) {
            if (($cal['id'] ?? '') === $id) {
                $targets[] = $cal;
                break;
            }
        }
    }

    $results = [];

    foreach ($targets as $cal) {
        if (empty($cal['id']) || empty($cal['url'])) {
            continue;
        }

        $response = wp_remote_get($cal['url'], ['timeout' => 15]);
        $http_ok = !is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200;

        if (!$http_ok) {
            $results[] = [
                'name'   => $cal['name'],
                'status' => 'error',
                'count'  => 0,
            ];
            continue;
        }

        $events = function_exists('bes_parse_ics') ? bes_parse_ics($cal['url'], $cal['max']) : [];

        // Nur bei tatsächlichen Treffern überschreiben, analog zum Frontend-Verhalten
        // (bes_render_calendar_shortcode) – ein leerer Abruf löscht keinen bestehenden Cache.
        if (!empty($events) && defined('BES_DATA') && BES_DATA && function_exists('bes_calendar_write_cache')) {
            $cache_file = BES_DATA . 'calendar-cache-' . $cal['id'] . '.json';
            bes_calendar_write_cache($cache_file, wp_json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $results[] = [
            'name'   => $cal['name'],
            'status' => empty($events) ? 'empty' : 'ok',
            'count'  => count($events),
        ];
    }

    set_transient('bes_calendar_refresh_result_' . get_current_user_id(), $results, MINUTE_IN_SECONDS);

    wp_safe_redirect(add_query_arg([
        'page'          => 'bseasy-sync',
        'tab'           => 'kalender',
        'bes_refreshed' => 1,
    ], admin_url('admin.php')));
    exit;
});
