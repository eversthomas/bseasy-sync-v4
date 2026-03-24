<?php
/**
 * Abwärtskompatibilität: Filter-Helfer liegen unter frontend/includes/.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/filter-helpers.php';
