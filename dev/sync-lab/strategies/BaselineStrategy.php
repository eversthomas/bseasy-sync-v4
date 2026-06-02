<?php
declare(strict_types=1);

require_once BES_SYNC_LAB_DIR . '/strategies/StrategySupport.php';

/**
 * Nachbildung des aktuellen V4-Sync-Ablaufs (Request-Muster).
 * Ziel: Request-Anzahl und Datenqualität messen, nicht 1:1 Feld-Extraktion.
 */
final class BesSyncLab_BaselineStrategy implements BesSyncLab_StrategyInterface
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
        return 'baseline';
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
        // Call 1: Member-Detail (wie V4: query {*})
        [$s1, $d1] = $this->client->safeGetTryQuery("member/$memberId", ['query' => '{*}']);
        if ($s1 === 429) {
            $this->metrics->addError("Member $memberId: 429 Detail");
            return null;
        }
        if ($s1 !== 200 || !is_array($d1)) {
            $this->metrics->addError("Member $memberId: Detail HTTP $s1");
            return null;
        }

        // Call 2: Custom Fields (Consent + Daten)
        $cfItems = $this->client->fetchAllList("member/$memberId/custom-fields", [
            'limit' => 100,
            'query' => '{id,value,customField}',
        ]);

        if (!$this->context['sync_all'] && !BesSyncLab_StrategySupport::hasConsent($cfItems, (int) $this->context['consent_field_id'])) {
            return null;
        }

        $member = BesSyncLab_StrategySupport::buildMemberRecord($d1, $cfItems, 'baseline');

        // Call 3: Contact-Details (immer, wie V4)
        $contactId = BesSyncLab_StrategySupport::extractContactId($d1);
        if ($contactId) {
            [$s3, $d3] = $this->client->safeGetTryQuery("contact-details/$contactId", ['query' => '{*}']);
            if ($s3 === 200 && is_array($d3)) {
                $member['contact'] = BesSyncLab_StrategySupport::pickContactFields($d3);

                // Call 4: Contact Custom Fields (optional, wie V4 bei Contact-Feldern)
                $contactCf = $this->client->fetchAllList("contact-details/$contactId/custom-fields", [
                    'limit' => 100,
                    'query' => '{id,value,customField}',
                ]);
                if ($contactCf) {
                    $member['contact']['custom_fields'] = BesSyncLab_StrategySupport::simplifyCustomFields($contactCf);
                }
            }
        }

        $member['_lab'] = [
            'strategy' => 'baseline',
            'api_calls_estimate' => 3 + (isset($member['contact']['custom_fields']) ? 1 : 0),
        ];

        return $member;
    }
}
