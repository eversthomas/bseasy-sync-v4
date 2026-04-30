<?php
/**
 * Zusatz-Hinweise für Admin-Fehlermeldungen (EasyVerein-API / Token).
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prüft, ob eine Fehlermeldung typischerweise auf ein ungültiges oder abgelaufenes
 * EasyVerein-API-Token bzw. Auth-Probleme hindeutet.
 */
function bes_error_suggests_easyverein_token_issue(string $message): bool
{
    $m = strtolower($message);

    if ($m === '') {
        return false;
    }

    if (strpos($m, 'hinweis: prüfen sie in easyverein') !== false) {
        return false;
    }

    if (preg_match('/\bstatus:\s*(401|402|403)\b/', $m)) {
        return true;
    }
    if (preg_match('/\bhttp\s+(401|402|403)\b/', $m)) {
        return true;
    }

    $needles = [
        'unauthorized', 'unauthoris', 'forbidden', 'nicht autorisiert',
        'authentifizierung', 'authentication', 'invalid token', 'ungültiges token',
        'token ungültig', 'access denied', 'zugriff verweigert',
        'keine basis-url erreichbar',
        'konnte keine mitglieder-daten abrufen',
        'kein api-token konfiguriert',
        'token konnte nicht entschlüsselt werden',
        'token-refresh', 'tokenrefresh',
    ];
    foreach ($needles as $n) {
        if (strpos($m, $n) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Hängt bei passenden Fehlern einen Hinweis zur Token-Prüfung in EasyVerein an.
 */
function bes_append_easyverein_token_renewal_hint(string $message): string
{
    if (!bes_error_suggests_easyverein_token_issue($message)) {
        return $message;
    }

    $hint = ' ' . __('Hinweis: Prüfen Sie in EasyVerein, ob Ihr API-Token noch gültig ist. Erstellen oder erneuern Sie das Token bei Bedarf und tragen Sie es im Sync-Tab unter „API-Zugangsdaten“ ein.', BES_TEXT_DOMAIN);

    return $message . $hint;
}
