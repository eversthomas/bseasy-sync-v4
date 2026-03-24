<?php
/**
 * BSEasy Sync V3 - Shared Helper Functions (Loader)
 *
 * Bindet die ausgelagerten V3-Hilfsmodule ein (Logging, Persistenz, CF-Optionen).
 *
 * @package BSEasySync
 * @subpackage V3
 * @author Tom Evers <https://bezugssysteme.de>
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/v3/v3-logging.php';
require_once __DIR__ . '/v3/v3-persistence.php';
require_once __DIR__ . '/v3/v3-field-options.php';
