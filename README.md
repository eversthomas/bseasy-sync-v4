# BSEasy Sync V4

WordPress-Plugin zur Synchronisation von EasyVerein-Mitgliedsdaten (API v2.0) und zur Ausgabe als Berater-/Mitglieder-Cards im Frontend — inkl. Filter, Karte, Umkreissuche und Toggle-Ansicht.

**Version:** 4.0.0  
**Text Domain:** `besync`

---

## Voraussetzungen

- WordPress 5.8+ (getestet mit WordPress 7.x)
- PHP 7.4+ (8.x empfohlen)
- EasyVerein-Account mit API-Token
- Schreibrechte für `wp-content/uploads/` (Daten in `uploads/bseasy-sync/`)

---

## Installation

1. Plugin-Ordner `bseasy-sync-v4/` nach `wp-content/plugins/` kopieren
2. Im WordPress-Backend unter **Plugins** aktivieren
3. **BSEasy Sync** im Admin-Menü öffnen
4. EasyVerein API-Token hinterlegen
5. Explorer ausführen → Felder konfigurieren → Sync starten

Upgrade von **bseasy-sync-main**: siehe [docs/MIGRATION.md](docs/MIGRATION.md)

---

## Shortcodes

### Mitgliederliste

```
[bes_members view="kachel"]   ← Kachelansicht (Standard)
[bes_members view="map"]      ← Nur Karte
[bes_members view="toggle"]   ← Umschaltbar Kachel ↔ Karte
```

### Kalender

```
[bes_kalender]
```

---

## Features

- **Sync** — EasyVerein Consent-API, Explorer, Batch-Sync, Consent-Audit
- **Feldverwaltung** — Sortierung (above/below), Filter, Badges, Drag & Drop
- **Card-Design** — Farben als CSS Custom Properties
- **Karte** — Leaflet.js mit Clustering und Filtern
- **Umkreissuche** — Radius-Filter über PLZ/Ort
- **Länderfilter** — DE/AT/CH mit ISO-Normalisierung
- **Sicherheit** — AES-256-CBC Token-Verschlüsselung, Nonces, Rate-Limiting

---

## Dokumentation

| Dokument | Beschreibung |
|----------|--------------|
| [docs/README.md](docs/README.md) | Dokumentations-Übersicht |
| [docs/MANUAL_TESTS.md](docs/MANUAL_TESTS.md) | Manuelle Test-Checkliste |
| [docs/MIGRATION.md](docs/MIGRATION.md) | Migration Main → V4 |
| [docs/BACKLOG.md](docs/BACKLOG.md) | Priorisierte Aufgaben |
| [docs/CHANGELOG.md](docs/CHANGELOG.md) | Versionshistorie |
| [MODULE_OVERVIEW.md](MODULE_OVERVIEW.md) | Architektur & Module |

---

## Entwicklung

Architekturregeln (Kurzfassung):

- `includes/` — immer geladen, keine Abhängigkeit zu sync/admin/frontend
- `sync/` — nur Admin/Cron; Einstieg: `sync/sync-service.php`
- `frontend/` — Shortcode, Rendering, AJAX
- Mitgliederdaten — ausschließlich über `includes/data/member-repository.php`

Details: [MODULE_OVERVIEW.md](MODULE_OVERVIEW.md)

---

## Lizenz

GPL v2 or later — Copyright Tom Evers
