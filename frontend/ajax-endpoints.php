<?php
/**
 * Abwärtskompatibilität: Frontend-AJAX liegt unter frontend/ajax/.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/ajax/ajax-endpoints.php';
