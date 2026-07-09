# BSEasy Sync V4

**DE:** WordPress-Plugin zur Synchronisation von EasyVerein-Mitgliedsdaten (API v2.0) und zur Ausgabe als Berater-/Mitglieder-Cards im Frontend — inkl. Filter, Karte, Umkreissuche und Toggle-Ansicht.

**EN:** WordPress plugin to synchronise EasyVerein member data (API v2.0) and display consultant/member cards on the frontend — including filters, map view, radius search, and a tile/map toggle.

**Version:** 4.0.0  
**Text Domain:** `besync`  
**Repository:** [github.com/eversthomas/bseasy-sync-v4](https://github.com/eversthomas/bseasy-sync-v4)

---

## ⚠️ Wichtiger Hinweis: Version 4 ist kein Update von Version 3

**DE**

BSEasy Sync **V4** ist **keine** direkte Fortsetzung oder ein automatisches Update der älteren **Version 3**. Es handelt sich um eine neu strukturierte Plugin-Version mit eigenem Code und eigenem Setup.

**Empfohlener Wechsel von V3 auf V4:**

1. **Vollständiges Backup** anlegen (Datenbank + `wp-content/uploads/bseasy-sync/`)
2. **Version 3 deaktivieren** — aber vorerst **nicht löschen**, falls der Wechsel Probleme bereitet und Sie zurückkehren müssen
3. **Version 4 installieren** und aktivieren
4. **Nicht parallel betreiben:** Version 3 und Version 4 dürfen **nicht gleichzeitig aktiv** sein
5. **Alles neu einrichten:**
   - EasyVerein API-Token neu hinterlegen
   - Explorer ausführen, Felder konfigurieren, Sync starten
   - Frontend-Einstellungen neu festlegen (sichtbare Felder, Filter, Design, Kartenansicht)

Hinweis: Dateien im Ordner `wp-content/uploads/bseasy-sync/` können auf derselben Installation physisch erhalten bleiben. Plugin-Einstellungen, Token und Sync müssen in V4 dennoch **neu geprüft und konfiguriert** werden.

Technische Migrationsdetails (falls vorhanden): [docs/MIGRATION.md](docs/MIGRATION.md)

**EN**

BSEasy Sync **V4** is **not** a direct continuation of or automatic upgrade from the older **Version 3**. It is a restructured plugin version with its own codebase and setup process.

**Recommended switch from V3 to V4:**

1. Create a **full backup** (database + `wp-content/uploads/bseasy-sync/`)
2. **Deactivate Version 3** — but do **not delete it yet**, in case something goes wrong and you need to roll back
3. **Install and activate Version 4**
4. **Do not run in parallel:** Version 3 and Version 4 must **not** be active at the same time
5. **Reconfigure everything from scratch:**
   - Re-enter the EasyVerein API token
   - Run the Explorer, configure fields, start a sync
   - Reconfigure frontend settings (visible fields, filters, design, card layout)

Note: Files in `wp-content/uploads/bseasy-sync/` may physically remain on the same installation. However, plugin settings, token, and sync must still be **reviewed and reconfigured** in V4.

Technical migration notes (if applicable): [docs/MIGRATION.md](docs/MIGRATION.md)

---

## Voraussetzungen / Requirements

**DE**

- WordPress 5.8+ (getestet mit WordPress 7.x)
- PHP 7.4+ (8.x empfohlen)
- EasyVerein-Account mit API-Token
- Schreibrechte für `wp-content/uploads/` (Daten in `uploads/bseasy-sync/`)

**EN**

- WordPress 5.8+ (tested with WordPress 7.x)
- PHP 7.4+ (PHP 8.x recommended)
- EasyVerein account with API token
- Write access to `wp-content/uploads/` (data stored in `uploads/bseasy-sync/`)

---

## Installation

**DE**

1. Plugin-Ordner `bseasy-sync-v4/` nach `wp-content/plugins/` kopieren
2. Falls **Version 3** installiert ist: zuerst **deaktivieren** (nicht parallel aktivieren)
3. Im WordPress-Backend unter **Plugins** aktivieren
4. **BSEasy Sync** im Admin-Menü öffnen
5. EasyVerein API-Token hinterlegen
6. Explorer ausführen → Felder konfigurieren → Sync starten
7. Shortcode auf einer Seite einbinden (siehe unten)

**EN**

1. Copy the `bseasy-sync-v4/` folder to `wp-content/plugins/`
2. If **Version 3** is installed: **deactivate it first** (do not run both in parallel)
3. Activate the plugin in the WordPress admin under **Plugins**
4. Open **BSEasy Sync** in the admin menu
5. Enter your EasyVerein API token
6. Run the Explorer → configure fields → start sync
7. Add a shortcode to a page (see below)

---

## Shortcodes

### Mitgliederliste / Member directory

```
[bes_members view="kachel"]   ← Kachelansicht / tile view (default)
[bes_members view="map"]      ← Nur Karte / map only
[bes_members view="toggle"]   ← Umschaltbar Kachel ↔ Karte / tile ↔ map toggle
```

### Kalender / Calendar

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

## Datenschutz / Privacy

**DE**

Dieses Plugin synchronisiert **nur Daten, die in EasyVerein für die Veröffentlichung freigegeben sind** (Consent-basierter Sync). Welche Felder im Frontend sichtbar werden, legen Sie in den Plugin-Einstellungen fest.

**Verantwortlichkeit:** Als Betreiber Ihrer WordPress-Website sind **Sie** für die datenschutzrechtliche Zulässigkeit der Verarbeitung und Veröffentlichung verantwortlich — insbesondere für Informationspflichten (z. B. Datenschutzerklärung), Rechtsgrundlagen und die Einwilligungen Ihrer Mitglieder in EasyVerein.

**Gespeicherte Daten (lokal auf Ihrem Server):**

- synchronisierte Mitgliederdaten in `wp-content/uploads/bseasy-sync/`
- Feld-Konfiguration, Sync-Status und Design-Einstellungen
- API-Token (verschlüsselt in der WordPress-Datenbank)

**Externe Dienste (bei Nutzung der jeweiligen Funktion):**

- **EasyVerein API** — Abruf der Mitgliederdaten
- **OpenStreetMap Nominatim** — Geocoding für die Umkreissuche (Suchanfragen werden an `nominatim.openstreetmap.org` übermittelt; Ergebnisse werden temporär gecacht)

Der Plugin-Autor stellt die Software bereit; die **rechtliche Verantwortung für den Einsatz** auf Ihrer Website liegt beim Seitenbetreiber.

**EN**

This plugin synchronises **only data that members have approved for publication in EasyVerein** (consent-based sync). You control which fields are visible on the frontend via the plugin settings.

**Responsibility:** As the operator of your WordPress site, **you** are responsible for ensuring that processing and publication comply with applicable privacy laws — including transparency obligations (e.g. privacy policy), legal bases, and member consents managed in EasyVerein.

**Data stored locally on your server:**

- synchronised member data in `wp-content/uploads/bseasy-sync/`
- field configuration, sync status, and design settings
- API token (encrypted in the WordPress database)

**Third-party services (when the respective feature is used):**

- **EasyVerein API** — retrieval of member data
- **OpenStreetMap Nominatim** — geocoding for radius search (queries are sent to `nominatim.openstreetmap.org`; results are temporarily cached)

The plugin author provides the software; **legal responsibility for deployment** on your website lies with the site operator.

---

## Haftungsausschluss / Disclaimer

**DE**

Dieses Plugin wird in der vorliegenden Form („wie besehen“ / *as is*) bereitgestellt.

Der Autor übernimmt **keine Gewährleistung und keine Garantie** — weder ausdrücklich noch stillschweigend — für:

- die **Funktionsfähigkeit** oder dauerhafte Verfügbarkeit des Plugins
- die **Richtigkeit, Vollständigkeit oder Aktualität** synchronisierter Daten
- die **Sicherheit** Ihrer Website, Server oder Daten
- **Datenverlust**, Ausfallzeiten oder sonstige Schäden jeder Art

Die Nutzung erfolgt auf **eigene Verantwortung**. Vor Installation, Updates oder Migrationen wird dringend empfohlen, **vollständige Backups** (Datenbank und Dateien) anzulegen.

Der Autor haftet nicht für Schäden, die durch die Installation, Nutzung oder Nichtnutzung dieses Plugins entstehen, soweit gesetzlich zulässig.

Dieser Hinweis ersetzt keine individuelle Rechtsberatung.

**EN**

This plugin is provided *as is*, without any warranty of any kind.

The author makes **no warranties or guarantees**, whether express or implied, regarding:

- the **functionality** or ongoing availability of the plugin
- the **accuracy, completeness, or timeliness** of synchronised data
- the **security** of your website, server, or data
- **data loss**, downtime, or any other damages of any kind

Use is entirely **at your own risk**. Before installing, updating, or migrating, you are strongly advised to create **full backups** (database and files).

To the extent permitted by applicable law, the author shall not be liable for damages arising from the installation, use, or non-use of this plugin.

This notice does not constitute legal advice.

---

## Support & Mitwirkung / Support & Contributing

**Repository:** [github.com/eversthomas/bseasy-sync-v4](https://github.com/eversthomas/bseasy-sync-v4)

**DE:** Fehlerberichte und Verbesserungsvorschläge bitte über [GitHub Issues](https://github.com/eversthomas/bseasy-sync-v4/issues) einreichen. Pull Requests sind willkommen.

**EN:** Please report bugs and feature requests via [GitHub Issues](https://github.com/eversthomas/bseasy-sync-v4/issues). Pull requests are welcome.

---

## Dokumentation

| Dokument | Beschreibung |
|----------|--------------|
| [docs/README.md](docs/README.md) | Dokumentations-Übersicht |
| [docs/MANUAL_TESTS.md](docs/MANUAL_TESTS.md) | Manuelle Test-Checkliste |
| [docs/MIGRATION.md](docs/MIGRATION.md) | Migration Main → V4 |
| [docs/BACKLOG.md](docs/BACKLOG.md) | Priorisierte Aufgaben |
| [docs/SYNC_PLAN.md](docs/SYNC_PLAN.md) | Sync-Fahrplan (Lab, Optimized, Auto-Sync) |
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

## Lizenz / License

**DE:** Dieses Plugin ist freie Software und steht unter der **GNU General Public License v2 oder später (GPL-2.0+)**. Siehe [LICENSE](LICENSE) und [GPL v2](https://www.gnu.org/licenses/gpl-2.0.html).

**EN:** This plugin is free software, licensed under the **GNU General Public License v2 or later (GPL-2.0+)**. See [LICENSE](LICENSE) and [GPL v2](https://www.gnu.org/licenses/gpl-2.0.html).

Copyright © Tom Evers
