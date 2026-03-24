<?php
/**
 * Abwärtskompatibilität: Felder-Handler liegt unter admin/fields/.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/fields/fields-handler.php';
