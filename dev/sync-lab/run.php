#!/usr/bin/env php
<?php
/**
 * Sync-Lab — Haupt-CLI (isoliert vom Plugin-Sync).
 *
 * Schreibt NUR nach dev/sync-lab/output/ — nie nach uploads/bseasy-sync/.
 */

declare(strict_types=1);

$config = require __DIR__ . '/bootstrap.php';
BesSyncLab_Env::load(BES_SYNC_LAB_DIR);

$opts = BesSyncLab_Cli::parseArgs($argv);
if ($opts['help']) {
    BesSyncLab_Cli::printRunHelp();
    exit(0);
}

try {
    $auth = BesSyncLab_TokenResolver::resolve($opts, $config);
    $metrics = new BesSyncLab_Metrics();
    $client = new BesSyncLab_ApiClient($auth['token'], $config, $metrics, (bool) $opts['verbose']);

    $context = [
        'consent_field_id' => $auth['consent_field_id'],
        'sync_all' => $auth['sync_all'],
        'wp_loaded' => $auth['wp_loaded'],
    ];

    $resolved = BesSyncLab_MemberIdResolver::resolve($client, $context, $opts, $config);
    $allIds = $resolved['ids'];
    $context['server_filtered_ids'] = $resolved['server_filtered'];
    $offset = max(0, (int) $opts['offset']);
    $limit = max(1, (int) $opts['limit']);
    $memberIds = array_slice($allIds, $offset, $limit);

    if ($memberIds === []) {
        fwrite(STDERR, "Keine Member-IDs für offset=$offset limit=$limit (gesamt: " . count($allIds) . ")\n");
        exit(1);
    }

    echo "Sync-Lab: Strategie={$opts['strategy']}, Mitglieder=" . count($memberIds) . " (von " . count($allIds) . " IDs)\n";

    $strategyName = (string) $opts['strategy'];
    switch ($strategyName) {
        case 'baseline':
            $strategy = new BesSyncLab_BaselineStrategy($client, $metrics, $context, (bool) $opts['verbose']);
            break;
        case 'optimized':
            $strategy = new BesSyncLab_OptimizedStrategy($client, $metrics, $context, (bool) $opts['verbose']);
            break;
        case 'sandbox':
            $strategy = new BesSyncLab_SandboxStrategy($client, $metrics, $context, 'all', (bool) $opts['verbose']);
            break;
        default:
            throw new InvalidArgumentException("Unbekannte Strategie: $strategyName");
    }

    $run = $strategy->run($memberIds);
    $members = $run['members'];
    $metricsData = $metrics->toArray(count($memberIds));

    $payload = [
        'lab_version' => BES_SYNC_LAB_VERSION,
        'generated_at' => date('c'),
        'strategy' => $strategy->name(),
        'context' => [
            'consent_field_id' => $context['consent_field_id'],
            'sync_all' => $context['sync_all'],
            'offset' => $offset,
            'limit' => $limit,
            'member_ids' => $memberIds,
            'wp_loaded' => $context['wp_loaded'],
            'server_filtered_ids' => !empty($context['server_filtered_ids']),
        ],
        'member_count' => count($members),
        'metrics' => $metricsData,
        'members' => $members,
    ];

    if (isset($run['sandbox'])) {
        $payload['sandbox'] = $run['sandbox'];
    }

    $outputFile = BesSyncLab_ResultWriter::write($strategy->name(), $payload, $config, (bool) $opts['dry_run']);

    BesSyncLab_ResultWriter::printSummary([
        'strategy' => $strategy->name(),
        'member_count' => count($members),
        'metrics' => $metricsData,
        'output_file' => $outputFile,
    ]);

    exit($metricsData['error_count'] > 0 ? 2 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "Fehler: " . $e->getMessage() . "\n");
    exit(1);
}
