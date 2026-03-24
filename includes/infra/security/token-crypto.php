<?php
/**
 * API-Token Verschlüsselung / Entschlüsselung.
 *
 * Aus includes/error-handler.php ausgelagert (Phase 5). Inhalt unverändert.
 *
 * @package BSEasySync
 * @author Tom Evers <https://bezugssysteme.de>
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Verschlüsselt einen API-Token für sichere Speicherung
 * Verwendet AES-256-CBC mit HMAC für Integritätsprüfung
 *
 * @param string $token Der zu verschlüsselnde Token
 * @return string Verschlüsselter Token (Base64-kodiert: IV|Ciphertext|HMAC)
 */
function bes_encrypt_token(string $token): string
{
    if (empty($token)) {
        return '';
    }

    // Prüfe ob OpenSSL verfügbar ist
    if (!function_exists('openssl_encrypt') || !function_exists('openssl_random_pseudo_bytes') || !function_exists('openssl_cipher_iv_length')) {
        // Fallback: Einfache Base64-Kodierung wenn OpenSSL nicht verfügbar
        return base64_encode($token);
    }

    // Verwende WordPress Salt für Verschlüsselung
    if (!function_exists('wp_salt')) {
        // Fallback: Einfache Base64-Kodierung wenn wp_salt nicht verfügbar
        return base64_encode($token);
    }

    $key = wp_salt('auth');
    $iv_length = openssl_cipher_iv_length('AES-256-CBC');

    if ($iv_length === false) {
        // Fallback: Einfache Base64-Kodierung wenn IV-Länge nicht ermittelt werden kann
        return base64_encode($token);
    }

    $iv = openssl_random_pseudo_bytes($iv_length);

    if ($iv === false) {
        // Fallback: Einfache Base64-Kodierung wenn IV nicht generiert werden kann
        return base64_encode($token);
    }

    $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);

    if ($encrypted === false) {
        // Fallback: Einfache Base64-Kodierung bei Fehler
        return base64_encode($token);
    }

    // HMAC für Integritätsprüfung (verhindert Manipulation)
    $hmac_key = wp_salt('secure_auth'); // Anderes Salt für HMAC
    $hmac_data = $iv . $encrypted;

    if (function_exists('hash_hmac')) {
        $hmac = hash_hmac('sha256', $hmac_data, $hmac_key, true);
    } else {
        // Fallback ohne HMAC (weniger sicher)
        $hmac = '';
    }

    // Kombiniere IV, verschlüsselten Text und HMAC
    return base64_encode($iv . $encrypted . $hmac);
}

/**
 * Entschlüsselt einen API-Token
 * Prüft HMAC für Integritätsprüfung
 *
 * @param string $encrypted_token Der verschlüsselte Token
 * @return string Entschlüsselter Token oder leerer String bei Fehler
 */
function bes_decrypt_token(string $encrypted_token): string
{
    if (empty($encrypted_token)) {
        return '';
    }

    // Prüfe ob OpenSSL verfügbar ist
    if (!function_exists('openssl_decrypt') || !function_exists('openssl_cipher_iv_length')) {
        // OpenSSL nicht verfügbar, versuche Base64-Decode
        $decoded = base64_decode($encrypted_token, true);
        return $decoded !== false ? $decoded : $encrypted_token;
    }

    // Prüfe ob Token bereits unverschlüsselt ist (Migration)
    $test_decode = base64_decode($encrypted_token, true);
    if ($test_decode === false) {
        // Nicht Base64-kodiert, vermutlich altes Format
        return $encrypted_token;
    }

    $decoded = $test_decode;

    // Prüfe ob IV vorhanden ist (neues Format)
    $iv_length = openssl_cipher_iv_length('AES-256-CBC');
    if ($iv_length === false || strlen($decoded) < $iv_length) {
        // Kein IV vorhanden, vermutlich altes Format oder Base64-kodiert
        return $decoded;
    }

    // Prüfe ob HMAC vorhanden ist (neues Format mit HMAC)
    $hmac_length = 32; // SHA-256 HMAC ist 32 Bytes
    $has_hmac = strlen($decoded) >= ($iv_length + $hmac_length);

    if ($has_hmac && function_exists('hash_hmac')) {
        // Neues Format mit HMAC: IV|Ciphertext|HMAC
        $iv = substr($decoded, 0, $iv_length);
        $encrypted = substr($decoded, $iv_length, -$hmac_length);
        $hmac_received = substr($decoded, -$hmac_length);

        // Prüfe HMAC
        if (!function_exists('wp_salt')) {
            // Fallback: Versuche als unverschlüsselt zu behandeln
            return $encrypted_token;
        }

        $hmac_key = wp_salt('secure_auth');
        $hmac_data = $iv . $encrypted;
        $hmac_calculated = hash_hmac('sha256', $hmac_data, $hmac_key, true);

        // Vergleiche HMAC (timing-safe)
        if (!hash_equals($hmac_received, $hmac_calculated)) {
            // HMAC stimmt nicht überein - Token wurde manipuliert!
            if (function_exists('bes_debug_log')) {
                bes_debug_log('Token-HMAC-Prüfung fehlgeschlagen - mögliche Manipulation erkannt', 'ERROR', 'security');
            }
            return ''; // Leerer String bei Manipulation
        }
    } else {
        // Altes Format ohne HMAC: IV|Ciphertext
        $iv = substr($decoded, 0, $iv_length);
        $encrypted = substr($decoded, $iv_length);
    }

    if (!function_exists('wp_salt')) {
        // Fallback: Versuche als unverschlüsselt zu behandeln
        return $encrypted_token;
    }

    $key = wp_salt('auth');
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);

    if ($decrypted === false) {
        // Fallback: Versuche als unverschlüsselt zu behandeln
        return $encrypted_token;
    }

    return $decrypted;
}
