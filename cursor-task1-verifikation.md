# Task 1 — Verifikations-Ergebnis (Stand: 2026-05-24)

**Umgebung:** `http://localhost/wordpress` · PHP 8.5.6 · WP-CLI 2.12.0  
**Testseite:** `/berater/` (Post ID 1705, Shortcode `[bes_members view="toggle"]`)  
**Vorbereitung:** `WP_DEBUG=true`, `WP_DEBUG_LOG=true`, `WP_DEBUG_DISPLAY=false` in `wp-config.php`; Logs vor Tests geleert.

| Check | Status | Befund |
|---|---|---|
| 1. Frontend-Shortcode | ✅ | HTTP 200, ~1,18 MB HTML; `bes-members`, `bes-filter`, 227× „Deutschland“ im Filter; keine PHP-Fehler im HTML; `wp eval do_shortcode()` → 1,16 MB Output |
| 2. Admin-Tabs (4) | ✅ | Skript `dev/verify-task1-admin.php` (WP_ADMIN vor wp-load): alle Admin-Funktionen + AJAX-Hooks OK; HTML 102 KB; Tabs Felder/Kalender/Map/Sync im Markup |
| 3. Cron-Hook Audit | ✅ | Skript `dev/verify-task1-cron.php` (DOING_CRON vor wp-load): `bseasy_v3_audit_consent=OK`, Hook ausgeführt (Exit 0, ~200 s); `audit_consent_v3.json` neu geschrieben (183 KB, 12:43) |
| 4. Karten-Rendering | ✅ | Seite enthält `leaflet.min.js/css`, `leaflet.markercluster.min.js`, MarkerCluster-CSS, `bes-map-script`, `L.Icon.Default`-Inline-Script |
| 5. Länderfilter DE/AT/CH | ✅ | Filter-Dropdown mit `<option value="DE">Deutschland</option>`; JS-Lokalisierung `country_filter_labels` / `country_filter_alias` im HTML |

## Hinweise zur Testmethodik

### Check 2 & 3 — WP-CLI-Limitierung
`wp eval` und `wp cron event run` bootstrappen WordPress **ohne** `WP_ADMIN` bzw. `DOING_CRON` zum Plugin-Ladezeitpunkt. Dadurch greifen die Guards in `bootstrap/load.php` korrekt — Admin- und Sync-Module werden nicht geladen (erwartetes Verhalten, kein Bug).

- **`wp cron event run bes_run_audit_consent_v3`:** schlägt fehl (`Invalid cron event` / Hook nicht registriert), weil `sync/runtime/cron-v3.php` in WP-CLI-Kontext nicht geladen wird.
- **Produktions-Äquivalent:** `wp-cron.php` setzt `DOING_CRON` **vor** `wp-load.php` — dort lädt das Sync-Modul korrekt. Verifiziert via `dev/verify-task1-cron.php`.

### Check 1 — wp eval Warnung
Bei `wp eval do_shortcode()` erscheinen vier `Array to string conversion` in `wp-includes/formatting.php` (WP-Core). Diese tauchen **nicht** im HTTP-Frontend-Request und **nicht** in `debug.log` auf — vermutlich WP-CLI-Artefakt, kein Task-1-Regression.

## Log-Auszüge (nur bei ❌)

*Keine — alle Checks grün.*

## Empfehlung

**✅ Alle grün → bereit zum Push**

Task-1-Änderungen (Admin-Guard, consent-audit-Move) verhalten sich im laufenden Plugin funktional unverändert.
