#!/usr/bin/env php
<?php
/**
 * Sync-Lab — Vergleich Lab-Output vs. Produktions-JSON (nur lesen).
 */

declare(strict_types=1);

$config = require __DIR__ . '/bootstrap.php';
BesSyncLab_Env::load(BES_SYNC_LAB_DIR);

$opts = BesSyncLab_Cli::parseArgs($argv);
if ($opts['help']) {
    BesSyncLab_Cli::printCompareHelp();
    exit(0);
}

$strategy = preg_replace('/[^a-z0-9_-]/', '', (string) $opts['strategy']) ?: 'baseline';
$labFile = BES_SYNC_LAB_DIR . "/output/run_{$strategy}_latest.json";
$refFile = $opts['against'] ?? $config['production_members_file'];

if (!is_readable($labFile)) {
    fwrite(STDERR, "Lab-Datei nicht gefunden: $labFile\nFühre zuerst run.php --strategy=$strategy aus.\n");
    exit(1);
}

if (!is_readable($refFile)) {
    fwrite(STDERR, "Referenz-Datei nicht lesbar: $refFile\nNutze --against=/pfad/zu/members_consent_v3.json\n");
    exit(1);
}

$labJson = json_decode((string) file_get_contents($labFile), true);
$refJson = json_decode((string) file_get_contents($refFile), true);

if (!is_array($labJson) || !is_array($refJson)) {
    fwrite(STDERR, "JSON konnte nicht gelesen werden.\n");
    exit(1);
}

$report = BesSyncLab_JsonCompare::compareMembersFiles($labJson, $refJson, (bool) $opts['verbose']);
$report['lab_file'] = $labFile;
$report['reference_file'] = $refFile;

$outFile = BES_SYNC_LAB_DIR . '/output/compare_' . $strategy . '_latest.json';
file_put_contents($outFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$s = $report['summary'];
echo "\n=== Sync-Lab Vergleich ===\n";
echo "Lab:         $labFile\n";
echo "Referenz:    $refFile\n";
echo "Lab-Member:  {$s['lab_count']}\n";
echo "Ref-Member:  {$s['reference_count']}\n";
echo "Gemeinsam:   {$s['common_ids']}\n";
echo "Nur Lab:     {$s['only_in_lab']}\n";
echo "Nur Ref:     {$s['only_in_reference']}\n";
echo "Feld-Diffs:  {$s['field_presence_diffs']}\n";
echo "Wert-Diffs:  {$s['value_diffs']}\n";
echo "Report:      $outFile\n";
echo "==========================\n\n";

exit($s['value_diffs'] > 0 ? 2 : 0);
