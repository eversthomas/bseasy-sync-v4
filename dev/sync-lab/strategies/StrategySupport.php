<?php
declare(strict_types=1);

/**
 * Gemeinsame Hilfsfunktionen für Lab-Strategien.
 */
final class BesSyncLab_StrategySupport
{
    /** @param list<array<string,mixed>> $customFields */
    public static function hasConsent(array $customFields, int $consentFieldId): bool
    {
        foreach ($customFields as $cf) {
            if (!is_array($cf)) {
                continue;
            }
            if (!self::matchesConsentField($cf, $consentFieldId)) {
                continue;
            }
            if (self::isTruthyConsentValue($cf['value'] ?? null)) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $cf */
    public static function matchesConsentField(array $cf, int $consentFieldId): bool
    {
        if ($consentFieldId <= 0) {
            return true;
        }
        $ref = $cf['customField'] ?? null;
        if (is_array($ref) && isset($ref['id'])) {
            return (int) $ref['id'] === $consentFieldId;
        }
        if (is_numeric($ref)) {
            return (int) $ref === $consentFieldId;
        }
        return strpos((string) $ref, (string) $consentFieldId) !== false;
    }

    public static function isTruthyConsentValue(mixed $val): bool
    {
        if ($val === true || $val === 1) {
            return true;
        }
        if (is_string($val)) {
            $v = strtolower(trim($val));
            return in_array($v, ['true', '1', 'yes', 'ja'], true);
        }
        return false;
    }

    /** @param array<string,mixed> $memberData */
    public static function extractContactId(array $memberData): ?int
    {
        $cd = $memberData['contactDetails'] ?? null;
        if (is_array($cd) && !empty($cd['id'])) {
            return (int) $cd['id'];
        }
        if (is_string($cd) && $cd !== '') {
            return (int) basename($cd);
        }
        return null;
    }

    /**
     * @param array<string,mixed> $memberData
     * @param list<array<string,mixed>> $customFields
     * @return array<string,mixed>
     */
    public static function buildMemberRecord(array $memberData, array $customFields, string $strategy): array
    {
        return [
            'id' => $memberData['id'] ?? null,
            'emailOrUserName' => $memberData['emailOrUserName'] ?? null,
            'membershipNumber' => $memberData['membershipNumber'] ?? null,
            'custom_fields' => self::simplifyCustomFields($customFields),
            '_lab' => ['strategy' => $strategy],
        ];
    }

    /** @param list<array<string,mixed>> $items */
    public static function simplifyCustomFields(array $items): array
    {
        $out = [];
        foreach ($items as $cf) {
            if (is_string($cf)) {
                $out[] = ['id' => null, 'customField' => $cf, 'value' => null];
                continue;
            }
            if (!is_array($cf)) {
                continue;
            }
            $out[] = [
                'id' => $cf['id'] ?? null,
                'customField' => $cf['customField'] ?? null,
                'value' => $cf['value'] ?? null,
            ];
        }
        return $out;
    }

    /** @param array<string,mixed> $contact */
    public static function pickContactFields(array $contact): array
    {
        $keys = [
            'id', 'name', 'firstName', 'familyName', 'street', 'city', 'zip', 'country',
            'privateEmail', 'companyEmail', 'phone', 'mobile', 'companyName', 'geoPositionCoords',
        ];
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $contact)) {
                $out[$key] = $contact[$key];
            }
        }
        return $out;
    }

    public static function optimizedMemberQuery(): string
    {
        return '{id,emailOrUserName,membershipNumber,_profilePicture,'
            . 'contactDetails{id,name,firstName,familyName,street,city,zip,country,'
            . 'privateEmail,companyEmail,phone,mobile,companyName,geoPositionCoords{lat,lng},'
            . 'customFields{id,value,customField}},'
            . 'customFields{id,value,customField}}';
    }
}
