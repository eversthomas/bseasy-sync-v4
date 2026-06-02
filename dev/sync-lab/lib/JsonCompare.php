<?php
declare(strict_types=1);

final class BesSyncLab_JsonCompare
{
    /**
     * @param array<string, mixed> $lab
     * @param array<string, mixed> $reference
     * @return array<string, mixed>
     */
    public static function compareMembersFiles(array $lab, array $reference, bool $verbose = false): array
    {
        $labMembers = self::indexMembers($lab);
        $refMembers = self::indexMembers($reference);

        $commonIds = array_values(array_intersect(array_keys($labMembers), array_keys($refMembers)));
        $onlyLab = array_values(array_diff(array_keys($labMembers), array_keys($refMembers)));
        $onlyRef = array_values(array_diff(array_keys($refMembers), array_keys($labMembers)));

        $fieldDiffs = [];
        $valueDiffs = [];

        foreach ($commonIds as $id) {
            $labRow = $labMembers[$id];
            $refRow = $refMembers[$id];
            $labFlat = self::flatten($labRow);
            $refFlat = self::flatten($refRow);

            $allKeys = array_unique(array_merge(array_keys($labFlat), array_keys($refFlat)));
            sort($allKeys);

            foreach ($allKeys as $key) {
                $inLab = array_key_exists($key, $labFlat);
                $inRef = array_key_exists($key, $refFlat);
                if (!$inLab || !$inRef) {
                    $fieldDiffs[] = ['member_id' => $id, 'field' => $key, 'issue' => !$inLab ? 'missing_in_lab' : 'missing_in_reference'];
                    continue;
                }
                if (!self::valuesEqual($labFlat[$key], $refFlat[$key])) {
                    $valueDiffs[] = [
                        'member_id' => $id,
                        'field' => $key,
                        'lab' => $labFlat[$key],
                        'reference' => $refFlat[$key],
                    ];
                }
            }
        }

        $summary = [
            'lab_count' => count($labMembers),
            'reference_count' => count($refMembers),
            'common_ids' => count($commonIds),
            'only_in_lab' => count($onlyLab),
            'only_in_reference' => count($onlyRef),
            'field_presence_diffs' => count($fieldDiffs),
            'value_diffs' => count($valueDiffs),
            'match_rate_pct' => count($commonIds) > 0
                ? round(100 - (count($valueDiffs) / max(1, count($commonIds) * 10)) * 100, 1)
                : 0,
        ];

        $report = [
            'generated_at' => date('c'),
            'summary' => $summary,
            'only_in_lab_sample' => array_slice($onlyLab, 0, 20),
            'only_in_reference_sample' => array_slice($onlyRef, 0, 20),
        ];

        if ($verbose) {
            $report['field_presence_diffs'] = array_slice($fieldDiffs, 0, 100);
            $report['value_diffs'] = array_slice($valueDiffs, 0, 100);
        } else {
            $report['value_diffs_sample'] = array_slice($valueDiffs, 0, 20);
        }

        return $report;
    }

    /** @return array<int, array<string, mixed>> */
    private static function indexMembers(array $data): array
    {
        $rows = [];
        if (isset($data['members']) && is_array($data['members'])) {
            $list = $data['members'];
        } elseif (bes_sync_lab_is_list($data)) {
            $list = $data;
        } else {
            return [];
        }

        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = $row['id'] ?? $row['member_id'] ?? null;
            if ($id === null) {
                continue;
            }
            $rows[(int) $id] = $row;
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private static function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : "$prefix.$key";
            if (is_array($value)) {
                if (bes_sync_lab_is_list($value)) {
                    $out[$path] = json_encode($value, JSON_UNESCAPED_UNICODE);
                } else {
                    $out += self::flatten($value, $path);
                }
            } else {
                $out[$path] = $value;
            }
        }
        return $out;
    }

    private static function valuesEqual(mixed $a, mixed $b): bool
    {
        if ($a === $b) {
            return true;
        }
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }
        return (string) $a === (string) $b;
    }
}
