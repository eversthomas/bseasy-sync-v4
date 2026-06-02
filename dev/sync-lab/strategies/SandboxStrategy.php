<?php
declare(strict_types=1);

/**
 * API-Sandbox — führt Einzelexperimente aus (E1–E4).
 */
final class BesSyncLab_SandboxStrategy implements BesSyncLab_StrategyInterface
{
    private string $experiment;

    /** @param array<string, mixed> $context */
    public function __construct(
        private BesSyncLab_ApiClient $client,
        private BesSyncLab_Metrics $metrics,
        private array $context,
        string $experiment = 'all',
        private bool $verbose = false
    ) {
        $this->experiment = $experiment;
    }

    public function name(): string
    {
        return 'sandbox_' . $this->experiment;
    }

    public function run(array $memberIds): array
    {
        $results = [];
        $sampleId = $memberIds[0] ?? null;

        if ($this->experiment === 'all' || $this->experiment === 'e1') {
            $results['e1_member_list'] = $this->experimentE1();
        }
        if ($this->experiment === 'all' || $this->experiment === 'e2') {
            $results['e2_nested_query'] = $sampleId ? $this->experimentE2((int) $sampleId) : ['skipped' => 'keine Member-ID'];
        }
        if ($this->experiment === 'all' || $this->experiment === 'e3') {
            $results['e3_select_options'] = $this->experimentE3();
        }
        if ($this->experiment === 'all' || $this->experiment === 'e4') {
            $results['e4_consent_filter'] = $this->experimentE4();
        }

        return [
            'members' => [],
            'metrics' => $this->metrics,
            'sandbox' => $results,
        ];
    }

    /** @return array<string, mixed> */
    private function experimentE1(): array
    {
        [$code, $data, $url] = $this->client->safeGetTryQuery('member', [
            'limit' => 5,
            'page' => 1,
            'showCount' => 'true',
            'query' => '{id,contactDetails{id}}',
        ]);

        return [
            'description' => 'Pagination: limit, page, showCount, results, next',
            'http_status' => $code,
            'url' => $url,
            'count' => is_array($data) ? ($data['count'] ?? null) : null,
            'has_next' => is_array($data) ? !empty($data['next']) : null,
            'result_count' => is_array($data) ? count($this->client->normalizeList($data)) : 0,
            'sample' => is_array($data) ? array_slice($this->client->normalizeList($data), 0, 2) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function experimentE2(int $memberId): array
    {
        $query = BesSyncLab_StrategySupport::optimizedMemberQuery();
        [$code, $data, $url] = $this->client->safeGetTryQuery("member/$memberId", ['query' => $query]);

        return [
            'description' => 'Nested query auf Member-Detail',
            'member_id' => $memberId,
            'http_status' => $code,
            'url' => $url,
            'has_contactDetails' => is_array($data) && isset($data['contactDetails']),
            'has_customFields' => is_array($data) && isset($data['customFields']),
            'nested_contact_cf' => is_array($data)
                && isset($data['contactDetails']['customFields'])
                && is_array($data['contactDetails']['customFields']),
            'keys' => is_array($data) ? array_keys($data) : [],
        ];
    }

    /** @return array<string, mixed> */
    private function experimentE3(): array
    {
        $fieldId = (int) ($this->context['consent_field_id'] ?? 0);
        if ($fieldId <= 0) {
            return ['skipped' => 'keine consent_field_id'];
        }

        [$code, $data, $url] = $this->client->safeGetTryQuery("custom-field/$fieldId/select-options", [
            'limit' => 10,
            'page' => 1,
        ]);

        return [
            'description' => 'Select-Options Liste',
            'custom_field_id' => $fieldId,
            'http_status' => $code,
            'url' => $url,
            'result_count' => is_array($data) ? count($this->client->normalizeList($data)) : 0,
            'sample' => is_array($data) ? array_slice($this->client->normalizeList($data), 0, 3) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function experimentE4(): array
    {
        $fieldId = (int) ($this->context['consent_field_id'] ?? 0);
        [$cCode, $cData] = $this->client->safeGetTryQuery("custom-field/$fieldId", ['query' => '{id,name}']);
        $fieldName = is_array($cData) ? ($cData['name'] ?? null) : null;

        if (!$fieldName) {
            return ['skipped' => 'Consent-Feldname nicht auflösbar', 'field_id' => $fieldId];
        }

        [$code, $data, $url] = $this->client->safeGetTryQuery('member', [
            'limit' => 5,
            'custom_field_name' => $fieldName,
            'custom_field_value__in' => 'true,True',
            '_isApplication' => 'false',
            'resignationDate__isnull' => 'true',
        ]);

        return [
            'description' => 'Serverseitiger Consent-Filter',
            'custom_field_name' => $fieldName,
            'http_status' => $code,
            'url' => $url,
            'result_count' => is_array($data) ? count($this->client->normalizeList($data)) : 0,
        ];
    }
}
