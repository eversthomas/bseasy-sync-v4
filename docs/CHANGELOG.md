# Changelog — BSEasy Sync V4

Alle wesentlichen Änderungen an diesem Plugin. Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

---

## [Unreleased]

### Geplant

- Select-Feld-Fix aus Main portieren (On-Demand-Abruf, Cache-TTL) — siehe `docs/BACKLOG.md` P0
- Sync-Optimierung (Fehlertoleranz, API-Request-Reduktion)
- Mitglieder-Short-URLs, Netzwerk-Badge, Geomatching-Shortcode

---

## [4.0.0] — 2026

Modulare Neustrukturierung der V3-Codebasis (`bseasy-sync-main`).

### Hinzugefügt

- **Bootstrap-Architektur** — `bootstrap/load.php` als zentraler Einstieg mit definierter Ladereihenfolge
- **Member Repository** — `includes/data/member-repository.php` als einzige Lese-Schnittstelle für Mitgliederdaten
- **Sync Service** — `sync/sync-service.php` als öffentliche Fassade für das Sync-Modul
- **Design-Settings** verschoben nach `includes/design/design-settings.php` (Admin + Frontend)
- **Security-Modul** — `includes/infra/security/token-crypto.php`, `safe-io.php`
- **Frontend-Erweiterungen** — `geo-utils.php`, `country-filter-normalize.php`, `schema.php`, `og-meta.php`
- **PRG-Pattern** — Admin-POST über `bootstrap/admin-page.php` (Post → Redirect → Get)
- **Sync-Admin-JS** — ausgelagert nach `admin/assets/sync/*.js`
- **Sync-Tab-Partials** — `admin/views/partials/sync-*.php`
- **Umkreissuche / Radius-Filter** im Frontend
- **Länderfilter** DE/AT/CH mit ISO-Normalisierung
- Dokumentation: `MODULE_OVERVIEW.md`, `dev/ROADMAP.md`

### Geändert

- Admin-Module werden nur noch unter `is_admin()` geladen (nicht mehr auf Frontend-Requests)
- Sync/Cron-Module nur unter `is_admin() || wp_doing_cron()`
- `@`-Error-Suppressions von 38 auf 7 reduziert (alle verbleibenden dokumentiert)
- Token-Verschlüsselung: WARN-Logging bei Fallback auf unverschlüsselte Speicherung
- consent-audit Implementierung nach `sync/consent/consent-audit.php` verschoben
- `BES_PATH`-Konstante entfernt (nur noch `BES_DIR`)

### Behoben

- Admin HTTP 500 nach Task 2 — Load-Order: `hosting-compatibility.php` vor `debug-log.php`
- Silent Failure in `frontend/ajax/ajax-endpoints.php` bei Config-Lesen
- Toter AJAX-Handler `bes_get_field_intelligence` (Funktion nie definiert)

### Entfernt

- Field Intelligence UI/JS (unvollständig, nie produktiv nutzbar)
- Legacy-Funktion `bes_load_json_versioned()`

### Bekannte Einschränkungen

- Select-Feld-Auflösung bei sehr vielen Optionen: Teile des Main-Fixes fehlen noch (siehe BACKLOG P0)
- Kalender-Shortcode `[bes_kalender]` manuell noch nicht verifiziert
- Vollsync mit allen ~330 Mitgliedern noch nicht getestet

---

## Vorgänger: bseasy-sync-main (3.0.0)

Die V3-Monolith-Version bleibt als Referenz erhalten. Produktionsbewertung: `bseasy-sync-main/PRODUKTIONSBEWERTUNG.md` (Januar 2026, 8,5/10).

Wesentliche Unterschiede zu V4:

- Monolithische `bseasy-sync.php` (~875 Zeilen)
- Admin-Code auf jedem Frontend-Request geladen
- Field Intelligence (experimentell)
- Select-Feld-Fix mit On-Demand-Abruf und Cache-TTL (in V4 teilweise fehlend)
