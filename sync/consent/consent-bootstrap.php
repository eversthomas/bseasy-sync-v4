<?php
/**
 * Consent-Sync: Basis-Pfade für BES_DATA (Cron-übergreifend).
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('BES_DATA')) {
    $main_file = plugin_dir_path(dirname(__DIR__)) . 'bseasy-sync.php';
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
