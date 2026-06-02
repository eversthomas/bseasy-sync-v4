<?php
declare(strict_types=1);

final class BesSyncLab_Cli
{
    /** @return array<string, mixed> */
    public static function parseArgs(array $argv): array
    {
        $opts = [
            'strategy' => 'baseline',
            'limit' => 20,
            'offset' => 0,
            'ids' => [],
            'ids_file' => null,
            'wp' => null,
            'token' => null,
            'consent_field_id' => null,
            'sync_all' => false,
            'dry_run' => false,
            'help' => false,
            'against' => null,
            'verbose' => false,
        ];

        foreach (array_slice($argv, 1) as $arg) {
            if ($arg === '--help' || $arg === '-h') {
                $opts['help'] = true;
                continue;
            }
            if ($arg === '--dry-run') {
                $opts['dry_run'] = true;
                continue;
            }
            if ($arg === '--sync-all') {
                $opts['sync_all'] = true;
                continue;
            }
            if ($arg === '--verbose' || $arg === '-v') {
                $opts['verbose'] = true;
                continue;
            }
            if (preg_match('/^--strategy=(.+)$/', $arg, $m)) {
                $opts['strategy'] = $m[1];
                continue;
            }
            if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
                $opts['limit'] = (int) $m[1];
                continue;
            }
            if (preg_match('/^--offset=(\d+)$/', $arg, $m)) {
                $opts['offset'] = (int) $m[1];
                continue;
            }
            if (preg_match('/^--ids=(.+)$/', $arg, $m)) {
                $opts['ids'] = array_values(array_filter(array_map('intval', explode(',', $m[1]))));
                continue;
            }
            if (preg_match('/^--ids-file=(.+)$/', $arg, $m)) {
                $opts['ids_file'] = $m[1];
                continue;
            }
            if (preg_match('/^--wp=(.+)$/', $arg, $m)) {
                $opts['wp'] = $m[1];
                continue;
            }
            if (preg_match('/^--token=(.+)$/', $arg, $m)) {
                $opts['token'] = $m[1];
                continue;
            }
            if (preg_match('/^--consent-field-id=(\d+)$/', $arg, $m)) {
                $opts['consent_field_id'] = (int) $m[1];
                continue;
            }
            if (preg_match('/^--against=(.+)$/', $arg, $m)) {
                $opts['against'] = $m[1];
                continue;
            }
        }

        return $opts;
    }

    public static function printRunHelp(): void
    {
        echo <<<HELP
BSEasy Sync-Lab — isolierte API-Experimente (ohne Plugin-Sync-Code)

Verwendung:
  php dev/sync-lab/run.php [Optionen]

Optionen:
  --strategy=NAME     baseline | optimized | sandbox (Default: baseline)
  --limit=N           Anzahl Mitglieder (Default: 20)
  --offset=N          Offset in ID-Liste (Default: 0)
  --ids=1,2,3         Feste Member-IDs (überschreibt Listen-Abruf)
  --ids-file=PATH     JSON-Datei mit IDs (Array oder { "ids": [...] })
  --wp=PATH           wp-load.php — liest Token + Consent-ID aus WordPress
  --token=TOKEN       Bearer-Token direkt (oder .env EASYVEREIN_TOKEN)
  --consent-field-id=N  Consent Custom-Field-ID
  --sync-all          Alle Mitglieder, kein Consent-Filter
  --dry-run           Kein Schreiben nach output/
  --verbose           Mehr Konsolen-Ausgabe
  --help              Diese Hilfe

Beispiele:
  php dev/sync-lab/run.php --wp=/pfad/zu/wordpress/wp-load.php --strategy=baseline --limit=10
  php dev/sync-lab/run.php --strategy=optimized --ids=12345,67890 --token=xxx

HELP;
    }

    public static function printCompareHelp(): void
    {
        echo <<<HELP
BSEasy Sync-Lab — JSON-Vergleich (Lab vs. Produktion)

Verwendung:
  php dev/sync-lab/compare.php [Optionen]

Optionen:
  --against=PATH      Referenz-JSON (Default: uploads/.../members_consent_v3.json)
  --strategy=NAME     Lab-Datei: output/run_<strategy>_latest.json (Default: baseline)
  --verbose           Alle Abweichungen anzeigen
  --help              Diese Hilfe

HELP;
    }

    public static function printSandboxHelp(): void
    {
        echo <<<HELP
BSEasy Sync-Lab — API-Sandbox (Experimente E1–E4)

Verwendung:
  php dev/sync-lab/sandbox.php [Optionen]

Optionen:
  --experiment=NAME   e1 | e2 | e3 | e4 | all (Default: all)
  --wp=PATH           wp-load.php für Token
  --token=TOKEN       Bearer-Token
  --consent-field-id=N
  --help              Diese Hilfe

HELP;
    }
}
