<?php
/**
 * Abwärtskompatibilität: Shortcode [bes_members] liegt unter frontend/shortcode/.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/shortcode/bes-members.php';
