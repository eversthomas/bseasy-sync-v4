<?php
declare(strict_types=1);

final class BesSyncLab_ResultWriter
{
    /** @param array<string, mixed> $config */
    public static function write(string $strategy, array $payload, array $config, bool $dryRun = false): ?string
    {
        $dir = $config['paths']['output'];
        $stamp = date('Y-m-d_His');
        $file = "$dir/run_{$strategy}_{$stamp}.json";
        $latest = "$dir/run_{$strategy}_latest.json";

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('JSON-Encoding fehlgeschlagen');
        }

        if ($dryRun) {
            fwrite(STDOUT, "[dry-run] Würde schreiben: $file\n");
            return null;
        }

        file_put_contents($file, $json);
        file_put_contents($latest, $json);

        return $file;
    }

    public static function printSummary(array $result): void
    {
        $metrics = $result['metrics'] ?? [];
        echo "\n=== Sync-Lab Ergebnis ===\n";
        echo "Strategie:      " . ($result['strategy'] ?? '?') . "\n";
        echo "Mitglieder:     " . ($result['member_count'] ?? 0) . "\n";
        echo "API-Requests:   " . ($metrics['request_count'] ?? 0) . "\n";
        echo "429-Fehler:     " . ($metrics['http_429_count'] ?? 0) . "\n";
        echo "Requests/Member:" . ($metrics['requests_per_member'] ?? '-') . "\n";
        echo "Dauer (s):      " . ($metrics['duration_sec'] ?? 0) . "\n";
        echo "Fehler:         " . ($metrics['error_count'] ?? 0) . "\n";
        if (!empty($result['output_file'])) {
            echo "Output:         " . $result['output_file'] . "\n";
        }
        echo "=========================\n\n";
    }
}
