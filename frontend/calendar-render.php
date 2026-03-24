<?php
/**
 * Abwärtskompatibilität: Kalender-Rendering liegt unter frontend/views/.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/views/calendar-render.php';
