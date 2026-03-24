<?php
/**
 * Sync-Tab: statischer Hinweisblock „Empfohlener Ablauf“.
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<!-- Minibedienungsanleitung - Übersicht -->
<div class="bes-card bes-card-info" style="margin-bottom: 20px; border-left: 4px solid #2271b1;">
    <h3 class="bes-card-title">📖 Empfohlener Ablauf (Ersteinrichtung)</h3>
    <div class="bes-card-block">
        <ol style="margin-left: 20px; padding-left: 0;">
            <li style="margin-bottom: 8px;"><strong>API-Zugangsdaten</strong> konfigurieren (Token + Consent-Feld-ID)</li>
            <li style="margin-bottom: 8px;"><strong>V3 Explorer</strong> ausführen (erstellt Feldkatalog)</li>
            <li style="margin-bottom: 8px;"><strong>V3 Feldauswahl</strong> öffnen und gewünschte Felder auswählen</li>
            <li style="margin-bottom: 8px;"><strong>V3 Sync</strong> starten (synchronisiert die ausgewählten Felder)</li>
        </ol>
        <p class="bes-card-text" style="margin-top: 15px; margin-bottom: 0;">
            <strong>Hinweis:</strong> Detaillierte Erklärungen zu jeder Funktion finden Sie direkt in den jeweiligen Bereichen.
        </p>
    </div>
</div>
