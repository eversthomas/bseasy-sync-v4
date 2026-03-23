<?php
if (!defined('ABSPATH')) exit;

/**
 * 📅 KALENDER-HANDLER (Mehrkalender)
 */

add_action('admin_post_bes_save_calendars', function () {
    if (!current_user_can('manage_options')) return;
    check_admin_referer('bes_save_calendars', 'bes_calendars_nonce');

    $input = $_POST['bes_calendars'] ?? [];
    $clean = [];

    foreach ($input as $row) {
        $clean[] = [
            'id'   => sanitize_title($row['id']),
            'name' => sanitize_text_field($row['name']),
            'url'  => esc_url_raw($row['url']),
            'max'  => max(10, intval($row['max']))
        ];
    }

    update_option('bes_calendars', $clean);
    wp_safe_redirect(add_query_arg(['page' => 'bseasy-sync', 'tab' => 'kalender', 'bes_saved' => 1], admin_url('admin.php')));
    exit;
});

