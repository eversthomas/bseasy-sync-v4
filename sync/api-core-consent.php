<?php

/**
 * Easy2Transfer Consent-Dump (v3.0 – mit Select-Options & Enhanced Debugging)
 * 2025-11-14
 *
 * ✨ NEU IN V3.0:
 * - Unterstützung für Select-Option Custom Fields
 * - Automatisches Auflösen von selectedOptions URLs
 * - Umfassendes Debugging-System
 * - Besseres Error-Handling
 *
 * Loader: Implementierung liegt unter sync/consent/ (modular).
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

// api-core-consent-requests.php wird NICHT hier geladen, um zirkuläre Abhängigkeiten zu vermeiden
// Die Dateien, die beide benötigen, müssen api-core-consent-requests.php ZUERST laden

require_once __DIR__ . '/consent/consent-bootstrap.php';
require_once __DIR__ . '/consent/consent-config.php';
require_once __DIR__ . '/consent/consent-logging-debug.php';
require_once __DIR__ . '/consent/consent-select-options.php';
require_once __DIR__ . '/consent/consent-extraction.php';
require_once __DIR__ . '/consent/consent-health-json.php';
require_once __DIR__ . '/consent/consent-geocoding.php';
require_once __DIR__ . '/consent/consent-debug-member.php';
