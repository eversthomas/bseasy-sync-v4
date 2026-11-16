# Changelog — BSEasy Sync V4

Alle wesentlichen Änderungen an diesem Plugin. Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

---

## [4.0.3] — in Arbeit (Datum beim Einspielen eintragen)

### Datenschutz
- **OpenStreetMap-Kacheln erst nach Zustimmung:** Die Karte lädt Kacheln von `tile.openstreetmap.org` nur noch nach Klick auf „Karte laden". Zustimmung optional für 6 Monate im Browser (`localStorage`, Schlüssel `bes_osm_tiles`), jederzeit widerrufbar über „Kartendienst deaktivieren". Notausgang: Filter `bes_map_optin_enabled` (Standard `true`).
- **OSM-Attribution** mit Link auf `openstreetmap.org/copyright`.
- **JSON-LD** wird mit Hex-Escaping ausgegeben (kein Ausbruch aus dem `<script>`-Block durch Sonderzeichen in Profilfeldern).
- **AJAX-Filter** (`bes_filter_members`) durchsucht und liefert nur noch Geschäftsstadt und Geschäfts-PLZ statt der privaten Stadt.

### Sicherheit
- **Datenordner geschützt:** Das Plugin legt in `uploads/bseasy-sync/` automatisch eine `.htaccess` (Apache) und eine leere `index.html` an, damit `sync.log` und `fields-config.json` nicht per URL abrufbar sind. Der Unterordner `img/` (Mitgliederbilder) bleibt abrufbar. Unter Nginx ist zusätzlich eine Serverregel nötig.

- **`sync.log` nur noch bei Bedarf:** Die Datei wird ohne `WP_DEBUG` nur noch für Warnungen und Fehler geschrieben (bisher bei jedem Sync mit allen Meldungen).

### Behoben
- **Karten-Filter:** Jeder Filter ließ alle Marker verschwinden (die Umkreis-Auswahlen wurden fälschlich als Filter gezählt); auch „Zurücksetzen" half nicht.
- **Suche auf der Karte:** Namen und Orte der Marker waren leer, die Suche nach Name oder Stadt fand nichts. Namen und Geschäftsstadt werden jetzt korrekt gelesen.
- **Karten-Popup:** Der Schließen-Button war zur Hälfte von der Karte verdeckt.
- **Karten-Filterleiste auf dem Handy:** Die Filterfelder standen alle untereinander in voller Breite; sie umbrechen jetzt kompakt wie in der Kachelansicht.
- **Kalender-Karten:** Titel, Ortszeile und Link waren auf dem gelben Hintergrund schlecht lesbar (Theme-Farben überschrieben die Plugin-Farben, Link-Hover gelb auf gelb); sie sind jetzt dunkel und kontrastreich.

## [4.0.4] — in Arbeit

### Datenschutz
- **Positivliste im Sync:** Für Kontaktdaten werden nur noch Namen, Geschäftsfelder und Koordinaten gespeichert. Private Felder (z. B. `city`, `country`, `zip`, `street`, private E-Mail, Telefon, Geburtstag) werden verworfen, auch wenn sie im Backend ausgewählt sind. Wirkt ab dem nächsten Vollsync.
- **Private Custom Fields gesperrt:** „Straße/PLZ/Stadt persönlich" (`cf.395501031`, `cf.395500980`, `cf.395501019`) werden nie gespeichert (erweiterbar per Filter `bes_blocked_private_custom_fields`).
- **Pins aus der Geschäftsadresse:** Der Sync berechnet Pins per Nominatim (OpenStreetMap) aus Geschäftsstraße, PLZ, Ort und Land (strukturierte Anfrage, Fallback auf PLZ und Ort). EasyVerein-Koordinaten werden nicht mehr übernommen. Mitglieder ohne Geschäftsadresse bekommen keinen Pin. Es werden nur Geschäftsadressen an OSM gesendet.
- **Adress-Cache:** Ergebnisse werden pro Adresse dauerhaft gespeichert (Option `bes_geocode_cache`); nur neue oder geänderte Adressen werden angefragt. Nicht gefundene Adressen werden nach 30 Tagen erneut versucht.
- **Nominatim-Regeln:** höchstens 1 Anfrage pro Sekunde, HTTP-Status wird geprüft, Pause bei Fehlern oder nach 240 s (Filter `bes_geocode_time_budget`); der Rest wird beim nächsten Sync nachgeholt.
- **Vergleichsbericht:** `uploads/bseasy-sync/v3/geocode_report.json` (ohne Koordinaten, ohne Namen) zeigt je Mitglieds-ID, ob der Pin neu, verschoben (mit Entfernung in Metern), entfallen oder nicht vorhanden ist.
- **Wichtig nach dem Update:** Einen **Vollsync** starten, damit die alten Pins ersetzt werden. Der erste Lauf braucht für ca. 160 Adressen etwa 3 Minuten länger.
- **Backend-Labels:** Im Felder-Tab heißen Geschäftsfelder jetzt „Geschäftlich: Straße/PLZ/Stadt/Land/E-Mail/Firma", Pins „Pin: Breitengrad/Längengrad (aus Geschäftsadresse)", private Felder „Privat: … (wird nicht übernommen)". Die Zuordnung nutzt die echten IDs (`contact.contact.*`); bisher entstanden Labels wie „Essen" aus Beispielwerten. Gespeicherte Alt-Labels, die einem Beispielwert entsprechen, werden beim Anzeigen ersetzt, selbst gewählte Labels bleiben.
- **Frontend:** Private PLZ-/Stadt-Schlüssel entfernt (Umkreissuche, Karten-Marker).

## [Unreleased]

### Entfernt

- **Dark Mode** — systembasiertes CSS (`prefers-color-scheme`) und Admin-Kartenstil Hell/Dunkel; Frontend folgt nur noch den Design-Einstellungen im Backend

### Hinzugefügt

- **Sync-Dauer-Anzeige** — nach erfolgreichem Sync in Admin-Meldung und Sync-Tab (`6 Min 25 Sek` bei 156 Membern, Juni 2026)
- **Sync-Lab** — isoliertes CLI unter `dev/sync-lab/` (baseline vs. optimized)

### Geplant

Siehe [`docs/SYNC_PLAN.md`](SYNC_PLAN.md) — empfohlene Reihenfolge:

1. Select-Feld-Fix aus Main portieren
2. Sync-Lab validieren (20–50 Member)
3. Optimized-Sync + Feature-Flag (nur bei Lab-Erfolg)
4. Automatischer Sync (WP-Cron recurring)
5. Migration + Vollsync 330
6. Mitglieder-Short-URLs, Netzwerk-Badge, Geomatching

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
