<?php
/**
 * BSEasy Sync – Error Handler & Infrastruktur-Helfer (Loader).
 *
 * Bindet ausgelagerte Module unter includes/infra/ ein (Phase 5).
 *
 * @package BSEasySync
 * @author Tom Evers <https://bezugssysteme.de>
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/infra/logging/debug-log.php';
require_once __DIR__ . '/infra/security/token-crypto.php';
require_once __DIR__ . '/infra/errors/error-handler-class.php';
require_once __DIR__ . '/infra/security/safe-io.php';
