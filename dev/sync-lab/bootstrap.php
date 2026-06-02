<?php
/**
 * Sync-Lab Bootstrap — isoliert vom Plugin, kein ABSPATH erforderlich.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Sync-Lab ist nur für die Kommandozeile (CLI) gedacht.\n");
    exit(1);
}

define('BES_SYNC_LAB_DIR', __DIR__);
define('BES_SYNC_LAB_VERSION', '1.0.0');

$config = require BES_SYNC_LAB_DIR . '/config.php';

foreach (['output', 'cache'] as $subdir) {
    $path = $config['paths'][$subdir];
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

// Produktions-JSON-Pfad (nur lesen, nie überschreiben)
$uploadsGuess = dirname(BES_SYNC_LAB_DIR, 4) . '/uploads/bseasy-sync/v3/members_consent_v3.json';
$config['production_members_file'] = $uploadsGuess;

require_once BES_SYNC_LAB_DIR . '/lib/Env.php';
require_once BES_SYNC_LAB_DIR . '/lib/PhpCompat.php';
require_once BES_SYNC_LAB_DIR . '/lib/Cli.php';
require_once BES_SYNC_LAB_DIR . '/lib/Metrics.php';
require_once BES_SYNC_LAB_DIR . '/lib/ApiClient.php';
require_once BES_SYNC_LAB_DIR . '/lib/TokenResolver.php';
require_once BES_SYNC_LAB_DIR . '/lib/JsonCompare.php';
require_once BES_SYNC_LAB_DIR . '/lib/ResultWriter.php';
require_once BES_SYNC_LAB_DIR . '/lib/MemberIdResolver.php';
require_once BES_SYNC_LAB_DIR . '/strategies/StrategySupport.php';
require_once BES_SYNC_LAB_DIR . '/strategies/StrategyInterface.php';
require_once BES_SYNC_LAB_DIR . '/strategies/BaselineStrategy.php';
require_once BES_SYNC_LAB_DIR . '/strategies/OptimizedStrategy.php';
require_once BES_SYNC_LAB_DIR . '/strategies/SandboxStrategy.php';

return $config;
