<?php
declare(strict_types=1);

require_once BES_SYNC_LAB_DIR . '/strategies/StrategySupport.php';

/**
 * Weniger API-Calls durch nested query auf Member-Detail.
 */
final class BesSyncLab_OptimizedStrategy implements BesSyncLab_StrategyInterface
{
    /** @param array<string, mixed> $context */
    public function __construct(
        private BesSyncLab_ApiClient $client,
        private BesSyncLab_Metrics $metrics,
        private array $context,
        private bool $verbose = false
    ) {
    }

    public function name(): string
    {
        return 'optimized';
    }

    public function run(array $memberIds): array
    {
        $members = [];

        foreach ($memberIds as $memberId) {
            try {
                $row = $this->processMember((int) $memberId);
                if ($row !== null) {
                    $members[] = $row;
                }
            } catch (Throwable $e) {
                $this->metrics->addError("Member $memberId: " . $e->getMessage());
            }
            usleep(200000);
        }

        return ['members' => $members, 'metrics' => $this->metrics];
    }

    /** @return array<string, mixed>|null */
    private function processMember(int $memberId): ?array
    {
        $query = BesSyncLab_StrategySupport::optimizedMemberQuery();

        // Primär: ein Request mit nested query
        [$code, $data] = $this->client->safeGetTryQuery("member/$memberId", ['query' => $query]);

        if ($code === 429) {
            $this->metrics->addError("Member $memberId: 429 nested");
            return null;
        }

        // Fallback: wie Baseline wenn nested query nicht unterstützt
        if ($code !== 200 || !is_array($data)) {
            if ($this->verbose) {
                fwrite(STDERR, "[optimized] nested query fehlgeschlagen ($code) — Fallback baseline für $memberId\n");
            }
            return $this->processMemberFallback($memberId);
        }

        $cfItems = [];
        if (isset($data['customFields']) && is_array($data['customFields'])) {
            $cfItems = $data['customFields'];
        }

        if (!$this->context['sync_all'] && empty($this->context['server_filtered_ids'])) {
            if (!BesSyncLab_StrategySupport::hasConsent($cfItems, (int) $this->context['consent_field_id'])) {
                return null;
            }
        }

        $member = BesSyncLab_StrategySupport::buildMemberRecord($data, $cfItems, 'optimized');

        if (isset($data['contactDetails']) && is_array($data['contactDetails'])) {
            $member['contact'] = BesSyncLab_StrategySupport::pickContactFields($data['contactDetails']);
            if (isset($data['contactDetails']['customFields']) && is_array($data['contactDetails']['customFields'])) {
                $member['contact']['custom_fields'] = BesSyncLab_StrategySupport::simplifyCustomFields(
                    $data['contactDetails']['customFields']
                );
            }
        }

        $member['_lab'] = [
            'strategy' => 'optimized',
            'api_calls_estimate' => 1,
            'nested_query' => true,
            'consent_from_server_filter' => !empty($this->context['server_filtered_ids']),
        ];

        return $member;
    }

    /** @return array<string, mixed>|null */
    private function processMemberFallback(int $memberId): ?array
    {
        [$s1, $d1] = $this->client->safeGetTryQuery("member/$memberId", ['query' => '{*}']);
        if ($s1 !== 200 || !is_array($d1)) {
            return null;
        }

        $cfItems = $this->client->fetchAllList("member/$memberId/custom-fields", [
            'limit' => 100,
            'query' => '{id,value,customField}',
        ]);

        if (!$this->context['sync_all'] && !BesSyncLab_StrategySupport::hasConsent($cfItems, (int) $this->context['consent_field_id'])) {
            return null;
        }

        $member = BesSyncLab_StrategySupport::buildMemberRecord($d1, $cfItems, 'optimized-fallback');
        $member['_lab']['nested_query'] = false;
        $member['_lab']['api_calls_estimate'] = 2;

        return $member;
    }
}
