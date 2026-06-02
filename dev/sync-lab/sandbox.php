#!/usr/bin/env php
<?php
/**
 * Sync-Lab — API-Sandbox (Experimente E1–E4).
 */

declare(strict_types=1);

$config = require __DIR__ . '/bootstrap.php';
BesSyncLab_Env::load(BES_SYNC_LAB_DIR);

$experiment = 'all';
$wp = null;
$token = null;
$consentFieldId = null;
$verbose = false;
$help = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        $help = true;
    } elseif (preg_match('/^--experiment=(.+)$/', $arg, $m)) {
        $experiment = $m[1];
    } elseif (preg_match('/^--wp=(.+)$/', $arg, $m)) {
        $wp = $m[1];
    } elseif (preg_match('/^--token=(.+)$/', $arg, $m)) {
        $token = $m[1];
    } elseif (preg_match('/^--consent-field-id=(\d+)$/', $arg, $m)) {
        $consentFieldId = (int) $m[1];
    } elseif ($arg === '--verbose' || $arg === '-v') {
        $verbose = true;
    }
}

if ($help) {
    BesSyncLab_Cli::printSandboxHelp();
    exit(0);
}

try {
    $opts = [
        'wp' => $wp,
        'token' => $token,
        'consent_field_id' => $consentFieldId,
        'sync_all' => false,
    ];

    $auth = BesSyncLab_TokenResolver::resolve($opts, $config);
    $metrics = new BesSyncLab_Metrics();
    $client = new BesSyncLab_ApiClient($auth['token'], $config, $metrics, $verbose);

    $context = [
        'consent_field_id' => $auth['consent_field_id'],
        'sync_all' => false,
        'wp_loaded' => $auth['wp_loaded'],
    ];

    // Eine Sample-ID für E2
    $sampleIds = BesSyncLab_MemberIdResolver::resolve($client, $context, ['limit' => 1, 'ids' => []], $config);
    $sampleIds = array_slice($sampleIds, 0, 1);

    $strategy = new BesSyncLab_SandboxStrategy($client, $metrics, $context, $experiment, $verbose);
    $run = $strategy->run($sampleIds);

    $payload = [
        'lab_version' => BES_SYNC_LAB_VERSION,
        'generated_at' => date('c'),
        'experiment' => $experiment,
        'metrics' => $metrics->toArray(),
        'sandbox' => $run['sandbox'] ?? [],
    ];

    $file = BesSyncLab_ResultWriter::write('sandbox_' . $experiment, $payload, $config, false);
    echo json_encode($payload['sandbox'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    echo "\nGespeichert: $file\n";
    echo "API-Requests: {$metrics->requestCount}\n";

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Fehler: " . $e->getMessage() . "\n");
    exit(1);
}
