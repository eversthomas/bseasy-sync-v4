<?php
/**
 * Abwärtskompatibilität: Karten-Renderer liegt unter frontend/views/.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/views/map-render.php';
