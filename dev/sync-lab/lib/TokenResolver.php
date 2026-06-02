<?php
declare(strict_types=1);

final class BesSyncLab_TokenResolver
{
    /**
     * @param array<string, mixed> $opts
     * @return array{token:string,consent_field_id:int,sync_all:bool,wp_loaded:bool}
     */
    public static function resolve(array $opts, array $config): array
    {
        $wpLoaded = false;
        $token = null;
        $consentFieldId = $opts['consent_field_id'] ?? null;
        $syncAll = (bool) ($opts['sync_all'] ?? false);

        if (!empty($opts['wp'])) {
            $wpLoad = (string) $opts['wp'];
            if (!is_readable($wpLoad)) {
                throw new RuntimeException("wp-load.php nicht lesbar: $wpLoad");
            }

            // WordPress laden — liest nur Optionen, schreibt nichts in den Sync
            require_once $wpLoad;
            $wpLoaded = true;

            if (empty($opts['token'])) {
                $encrypted = get_option('bes_api_token', '');
                if ($encrypted === '') {
                    throw new RuntimeException('Kein bes_api_token in WordPress gefunden.');
                }
                if (function_exists('bes_decrypt_token')) {
                    $token = bes_decrypt_token($encrypted);
                } else {
                    $token = $encrypted;
                }
            }

            if ($consentFieldId === null && function_exists('bes_get_consent_field_id')) {
                $consentFieldId = (int) bes_get_consent_field_id();
            }

            if (!$syncAll) {
                $syncAll = (bool) get_option('bes_sync_all_members', false);
            }
        }

        if ($token === null || $token === '') {
            $token = $opts['token'] ?? BesSyncLab_Env::get('EASYVEREIN_TOKEN');
        }

        if ($token === null || $token === '') {
            throw new RuntimeException(
                "Kein API-Token. Setze EASYVEREIN_TOKEN in .env, --token=... oder --wp=.../wp-load.php"
            );
        }

        if ($consentFieldId === null || $consentFieldId <= 0) {
            $envId = BesSyncLab_Env::get('BES_CONSENT_FIELD_ID');
            $consentFieldId = $envId !== null ? (int) $envId : (int) $config['consent_field_id_default'];
        }

        return [
            'token' => $token,
            'consent_field_id' => $consentFieldId,
            'sync_all' => $syncAll,
            'wp_loaded' => $wpLoaded,
        ];
    }
}
