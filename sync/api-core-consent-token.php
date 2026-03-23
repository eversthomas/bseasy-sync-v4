<?php
/**
 * BSEasy Sync - Token-Refresh
 * 
 * Enthält die Token-Refresh-Funktionalität für die EasyVerein API.
 * Diese Funktion wird sowohl von V2 als auch V3 verwendet.
 * 
 * @package BSEasySync
 * @subpackage Consent
 */

if (!defined('ABSPATH')) exit;

// Lade Basis-Konstanten
if (!defined('BES_DATA')) {
    $main_file = plugin_dir_path(__DIR__) . 'bseasy-sync.php';
    if (file_exists($main_file)) {
        require_once $main_file;
    } else {
        $upload_dir = wp_upload_dir();
        define('BES_UPLOADS_DIR', trailingslashit($upload_dir['basedir']) . 'bseasy-sync/');
        define('BES_UPLOADS_URL', trailingslashit($upload_dir['baseurl']) . 'bseasy-sync/');
        define('BES_DATA', BES_UPLOADS_DIR);
        define('BES_IMG', BES_UPLOADS_DIR . 'img/');
    }
}

// api-core-consent.php wird von api-core-consent-requests.php geladen (falls benötigt)
// Hier nicht laden, um zirkuläre Abhängigkeiten zu vermeiden

// ============================================================
// 🔄 TOKEN-REFRESH MECHANISMUS
// ============================================================

/**
 * Aktualisiert den API-Token wenn Refresh benötigt wird
 *
 * @param string $token Aktueller Token
 * @return string Neuer Token oder alter Token bei Fehler
 */
function bes_consent_api_refresh_token(string $token): string
{
    bes_consent_log("Token-Refresh wird durchgeführt...", 'INFO');

    foreach (BES_API_BASES as $base) {
        $url = rtrim($base, '/') . '/' . BES_API_VERSION . '/refresh-token';

        $timeout = defined('BES_API_TIMEOUT') ? BES_API_TIMEOUT : 45;
        if (function_exists('bes_get_safe_timeout')) {
            $timeout = bes_get_safe_timeout($timeout);
        }

        $args = [
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'User-Agent' => 'BSEasy-Sync/' . BES_VERSION . '; ' . home_url(),
            ],
            'sslverify' => true,
            'compress' => true,
        ];

        $res = wp_remote_get($url, $args);

        if (is_wp_error($res)) {
            bes_consent_log("Token-Refresh Fehler bei $base: " . $res->get_error_message(), 'WARN');
            continue;
        }

        $code = wp_remote_retrieve_response_code($res);
        if ($code === 200) {
            $body = wp_remote_retrieve_body($res);
            $data = json_decode($body, true);

            if (isset($data['Bearer']) && !empty($data['Bearer'])) {
                $new_token = $data['Bearer'];

                // Speichere neuen Token verschlüsselt
                if (function_exists('bes_encrypt_token')) {
                    $encrypted = bes_encrypt_token($new_token);
                    update_option('bes_api_token', $encrypted);
                } else {
                    update_option('bes_api_token', $new_token); // Fallback
                }

                bes_consent_log("✓ Token erfolgreich aktualisiert", 'INFO');
                return $new_token;
            }
        }
    }

    bes_consent_log("⚠️ Token-Refresh fehlgeschlagen, verwende alten Token", 'WARN');
    return $token;
}
