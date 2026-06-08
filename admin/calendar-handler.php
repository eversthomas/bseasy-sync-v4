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
