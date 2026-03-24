<?php
/**
 * Abwärtskompatibilität: Tab-Shell liegt unter admin/views/.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/views/ui-main.php';
