<?php
declare(strict_types=1);

final class BesSyncLab_MemberIdResolver
{
    /**
     * @param array<string, mixed> $context
     * @return array{ids:list<int>,server_filtered:bool}
     */
    public static function resolve(
        BesSyncLab_ApiClient $client,
        array &$context,
        array $opts,
        array $config
    ): array {
        if (!empty($opts['ids'])) {
            return ['ids' => array_values(array_unique(array_map('intval', $opts['ids']))), 'server_filtered' => false];
        }

        if (!empty($opts['ids_file'])) {
            $raw = file_get_contents((string) $opts['ids_file']);
            if ($raw === false) {
                throw new RuntimeException('ids-file nicht lesbar');
            }
            $json = json_decode($raw, true);
            if (isset($json['ids']) && is_array($json['ids'])) {
                return ['ids' => array_values(array_map('intval', $json['ids'])), 'server_filtered' => false];
            }
            if (is_array($json) && bes_sync_lab_is_list($json)) {
                return ['ids' => array_values(array_map('intval', $json)), 'server_filtered' => false];
            }
            throw new RuntimeException('ids-file: erwartet Array oder {"ids":[...]}');
        }

        // WordPress: bestehende Plugin-Funktion nutzen (nur lesen)
        if (!empty($context['wp_loaded']) && function_exists('bes_consent_api_fetch_member_ids')) {
            $token = $client->getToken();
            $baseUsed = null;
            $stats = [];
            $ids = bes_consent_api_fetch_member_ids($token, $baseUsed, $stats, (bool) $context['sync_all']);
            $serverFiltered = !empty($stats['server_filtered']);
            $context['server_filtered_ids'] = $serverFiltered;
            return ['ids' => $ids, 'server_filtered' => $serverFiltered];
        }

        return self::fetchViaLabClient($client, $context, $config);
    }

    /** @param array<string, mixed> $context
     * @return array{ids:list<int>,server_filtered:bool}
     */
    private static function fetchViaLabClient(BesSyncLab_ApiClient $client, array &$context, array $config): array
    {
        if (!empty($context['sync_all'])) {
            $rows = $client->fetchAllList('member', [
                'limit' => 100,
                'showCount' => 'true',
                'query' => '{id}',
            ]);
            return ['ids' => self::pluckIds($rows), 'server_filtered' => false];
        }

        $consentFieldId = (int) ($context['consent_field_id'] ?? 0);
        $fieldName = self::resolveCustomFieldName($client, $consentFieldId);

        if ($fieldName) {
            $rows = $client->fetchAllList('member', [
                'limit' => 100,
                'ordering' => 'id',
                'custom_field_name' => $fieldName,
                'custom_field_value__in' => implode(',', $config['consent_values']),
                '_isApplication' => 'false',
                'resignationDate__isnull' => 'true',
            ]);
            $context['server_filtered_ids'] = true;
            return ['ids' => self::pluckIds($rows), 'server_filtered' => true];
        }

        // Fallback: alle IDs
        $rows = $client->fetchAllList('member', [
            'limit' => 100,
            'showCount' => 'true',
            'query' => '{id}',
        ]);
        return ['ids' => self::pluckIds($rows), 'server_filtered' => false];
    }

    private static function resolveCustomFieldName(BesSyncLab_ApiClient $client, int $fieldId): ?string
    {
        if ($fieldId <= 0) {
            return null;
        }
        [$code, $data] = $client->safeGetTryQuery("custom-field/$fieldId", ['query' => '{id,name}']);
        if ($code === 200 && is_array($data) && !empty($data['name'])) {
            return (string) $data['name'];
        }
        return null;
    }

    /** @param list<array<string,mixed>> $rows */
    private static function pluckIds(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if (!empty($row['id'])) {
                $ids[] = (int) $row['id'];
            }
        }
        return $ids;
    }
}
