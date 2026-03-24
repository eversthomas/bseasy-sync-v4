<?php
/**
 * Consent-Sync: Konfiguration (Feld-ID, Ziel-CFs).
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

// ------------------------------------------------------------
// ⚙️ KONSTANTEN
// ------------------------------------------------------------
// Alle Konstanten werden aus includes/constants.php geladen:
// - BES_API_VERSION
// - BES_API_BASES
// - BES_API_TIMEOUT
// - BES_CONSENT_FIELD_ID_DEFAULT
// - BES_DEBUG_MODE
// - BES_DEBUG_VERBOSE

/**
 * Gibt die konfigurierte Consent-Feld-ID zurück
 * Falls nicht gesetzt, wird der Standardwert verwendet
 */
function bes_get_consent_field_id(): int {
    return (int) get_option('bes_consent_field_id', BES_CONSENT_FIELD_ID_DEFAULT);
}

// 🎯 ZIEL-CUSTOM-FIELDS: Welche Custom Fields sollen EXPLIZIT extrahiert werden?
// (Rohdaten ALLER Felder sind weiterhin in member_cf / contact_cf enthalten)
const BES_TARGET_CUSTOM_FIELDS = [
    50359304,   // Online Angebote? (W)
    50359307,   // Zielgruppen (W)
    50697325,   // Webseite 1 (W)
    50697329,   // Webseite 2 (Wo)
    50697357,   // Leistungsangebote (W)
    50699020,   // Qualifikationshinweis
    50699073,   // Verbandsmitgliedschaften (Wo)
    50698940,   // Netzwerkinteressen
    50698968,   // Finanzierungshinweis (W)
    50799935,   // Weitere Infos zu mir
    51060631,   // Begleitung Klienten
    54800224,   // Anmeldung Plattform
    54809776,   // Datum Anmeldung
    190947959,  // Meine Daten sind korrekt (2024)!
    204293845,  // Daten sind aktuell 2025
    271260978,  // AG-Zugehörigkeit
    282018660,  // Sichtbarkeit Transfer-Webseite (Consent-Feld)
    312976233,  // Methoden / Interventionen (W)
    312570636,  // Check für Web
    313634622,  // Mein Motto (Wo)
    313635546,  // Reserve 1
    313635579,  // Reserve 2
    313635591,  // Reserve 3
    313635633,  // Reserve 4
    313635648,  // Reserve 5
    313635669,  // Reserve 6
    313635690,  // Reserve 7
    313635753,  // Reserve 8
];

// 🐛 DEBUG-MODUS wird aus includes/constants.php geladen
// BES_DEBUG_MODE und BES_DEBUG_VERBOSE sind dort definiert
