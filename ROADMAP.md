# BS Easy Sync — Roadmap & Gesamtplan

## Ausgangslage

Das Plugin ist vollständig modularisiert. Die Architektur ist in `MODULE_OVERVIEW.md` beschrieben. Die wichtigsten Regeln:

- `includes/` → immer geladen, keine Abhängigkeit zu anderen Modulen
- `sync/` → nur in Admin/Cron; Einstiegspunkt von außen: `sync/sync-service.php`
- `admin/` → nur in Admin/Cron
- `frontend/` → immer geladen
- Mitgliederdaten → ausschließlich über `includes/data/member-repository.php`
- Design-Einstellungen → ausschließlich über `includes/design/design-settings.php`

**Was die Modularisierung für zukünftige Arbeiten bedeutet:**
Jede Änderung betrifft ein klar abgegrenztes Modul. Frontend-Arbeiten (SEO, UX) berühren `frontend/` und `includes/` — der Sync bleibt unangetastet. Backend-UX-Arbeiten betreffen `admin/` und `bootstrap/` — das Frontend bleibt unangetastet. Das reduziert Risiken deutlich.

---

## Übersicht aller geplanten Verbesserungen

| # | Thema | Modul(e) | Priorität | Aufwand |
|---|-------|----------|-----------|---------|
| **A** | Strukturelle Restarbeiten | `sync/`, `frontend/`, `admin/` | Jetzt | Klein |
| **B** | Frontend-SEO-Optimierung | `frontend/`, `includes/` | Hoch | Mittel |
| **C** | Backend-UX-Verbesserung | `admin/`, `bootstrap/` | Hoch | Mittel–Groß |
| **D** | Logging vereinheitlichen | `includes/infra/` | Mittel | Klein |
| **E** | Accessibility (WCAG) | `frontend/` | Mittel | Mittel |
| **F** | Performance & Caching | `frontend/`, `includes/` | Mittel | Mittel |
| **G** | Sync-Optimierung | `sync/` | Mittel | Groß |
| **H** | Datenspeicher (JSON → DB) | `includes/data/`, `sync/` | Langfristig | Groß |
| **I** | Sicherheits-Audit | alle | Langfristig | Mittel |
| **J** | Testbarkeit / PHP-Namespaces | alle | Optional | Groß |

---

## A — Strukturelle Restarbeiten
*Abschluss der Modularisierung, bevor neue Features gebaut werden.*

### A1 — Renderer und Map-Render auf Repository umstellen
**Was:** `frontend/views/renderer.php` und `map-render.php` lesen `members_consent_v3.json` noch direkt per `bes_safe_file_get_contents()`. Das ist inkonsistent mit dem neuen Repository-Prinzip.

**Warum jetzt:** Solange zwei Codepfade für dieselbe Datei existieren, riskieren künftige Änderungen am Repository-Format, dass nur einer der Pfade aktualisiert wird.

**Betroffene Dateien:**
- `frontend/views/renderer.php` → `bes_members_get_all()` statt direktem Dateilesen
- `frontend/views/map-render.php` → ebenso

**Testkriterium:** `[bes_members]` mit `view="kachel"`, `view="map"` und `view="toggle"` funktionierne alle korrekt.

---

### A2 — ajax-v3.php auf `bes_sync_*`-Wrapper umstellen
**Was:** `admin/ajax/ajax-v3.php` ruft noch direkt `bseasy_v3_update_status()`, `bseasy_v3_read_json()` etc. auf, obwohl `sync/sync-service.php` bereits stabile Wrapper bereitstellt (`bes_sync_cancel()`, `bes_sync_get_status()` etc.).

**Warum:** Die Fassade hat nur halben Wert, wenn die AJAX-Handler sie umgehen. Außerdem enthält `ajax-v3.php` noch viel Logik (Part-Dateien löschen, Optionen setzen), die in den Service-Layer gehört.

**Betroffene Dateien:**
- `admin/ajax/ajax-v3.php` → kürzer, nur noch HTTP-Schicht
- `sync/sync-service.php` → ggf. neue Wrapper ergänzen

**Testkriterium:** Alle 13 Admin-AJAX-Actions funktionieren wie zuvor.

---

### A3 — Stub-Dateien dokumentieren und schrittweise entfernen
**Was:** ~12 Stub-Dateien delegieren auf neue Pfade. Einige davon werden nicht mehr aktiv genutzt.

**Entscheidung pro Stub:**
| Stub | Behalten bis | Grund |
|------|-------------|-------|
| `admin/fields-handler.php` | Kann jetzt entfernt werden | Nichts lädt ihn noch |
| `admin/ui-main.php` | Kann jetzt entfernt werden | Nichts lädt ihn noch |
| `frontend/renderer.php` | Nach A1 entfernen | Dann nicht mehr nötig |
| `frontend/map-render.php` | Nach A1 entfernen | Dann nicht mehr nötig |
| `includes/constants.php` | Langfristig behalten | Externe Abhängigkeiten möglich |
| `includes/cache-utils.php` | Langfristig behalten | Externe Nutzung möglich |
| `sync/api-core-consent-requests.php` | Langfristig behalten | Kritischer Pfad |
| `sync/cron-v3.php` | Langfristig behalten | Externe Cron-Referenzen |

---

## B — Frontend-SEO-Optimierung
*Betroffene Module: `frontend/`, `includes/data/`*
*Risikoarm dank Modularisierung: Sync und Admin bleiben vollständig unangetastet.*

### B1 — Semantisches HTML
**Was:** Die aktuelle Ausgabe der Mitgliederkarten nutzt vermutlich generische `div`-Container. Für SEO und Screenreader sind semantische Elemente wichtig.

**Maßnahmen:**
- Mitgliederkarten als `<article>` statt `<div>`
- Überschriften in korrekter Hierarchie (`<h2>` für Namen, `<h3>` für Abschnitte)
- Kontaktdaten in `<address>` kapseln
- Listen als `<ul>`/`<li>` wo mehrere Einträge ausgegeben werden

**Betroffene Dateien:** `frontend/views/renderer.php`, `frontend/views/map-render.php`, `frontend/assets/frontend.css`

---

### B2 — Strukturierte Daten (Schema.org)
**Was:** Suchmaschinen können Mitgliederprofile als strukturierte Daten verstehen, wenn Schema.org-Markup eingebettet ist.

**Maßnahmen:**
- `Person`-Schema für individuelle Mitglieder (Name, Adresse, URL)
- `Organization`-Schema falls Mitglieder Unternehmen repräsentieren
- JSON-LD im `<head>` oder inline in der Ausgabe
- Steuerbar über Feld-Konfiguration (welches Feld → welches Schema-Property)

**Betroffene Dateien:** `frontend/views/renderer.php`, ggf. neues `frontend/includes/schema.php`

---

### B3 — Performance-Optimierung der Frontend-Assets
**Was:** Blockierende Assets, unkomprimierte Bilder und unnötiges JS können das Ranking verschlechtern.

**Maßnahmen:**
- Leaflet.js nur laden wenn tatsächlich eine Karte auf der Seite vorhanden ist (bereits teilweise umgesetzt — prüfen und absichern)
- Bilder: `loading="lazy"` + `width`/`height`-Attribute für alle Mitgliederbilder
- CSS/JS: Minified-Versionen ausliefern (oder Build-Prozess einführen)
- Render-Blocking vermeiden: Frontend-CSS im `<head>`, JS mit `defer`

**Betroffene Dateien:** `frontend/shortcode/bes-members.php`, `frontend/views/renderer.php`

---

### B4 — Open Graph / Social Sharing
**Was:** Wenn Mitgliederseiten direkt verlinkt werden, sollten OG-Tags korrekte Vorschaubilder und Beschreibungen liefern.

**Maßnahmen:**
- `og:title`, `og:description`, `og:image` für Seiten mit `[bes_members]`-Shortcode
- Konfigurierbar: welches Feld wird als OG-Beschreibung genutzt?

**Betroffene Dateien:** `frontend/shortcode/bes-members.php`, ggf. neues `frontend/includes/og-meta.php`

---

### B5 — Saubere URL-Struktur für gefilterte Ansichten
**Was:** Aktuell werden Filter per AJAX gesetzt — die URL ändert sich nicht. Das bedeutet: Gefilterte Ansichten sind nicht verlinkbar und nicht von Suchmaschinen indexierbar.

**Maßnahmen:**
- Filter-State in URL-Hash oder Query-Parameter schreiben (JavaScript)
- Beim Laden der Seite: URL-Parameter auslesen und Filter vorbelegen
- Optionale serverseitige Vorfilterung via Shortcode-Attribut (`[bes_members filter_region="Bayern"]`)

**Betroffene Dateien:** `frontend/assets/frontend.js`, `frontend/shortcode/bes-members.php`, `frontend/ajax/ajax-endpoints.php`

---

### B6 — Radius-Suche (PLZ / Stadtname im Umkreis)
**Was:** Die aktuelle PLZ- und Stadtfilterung ist punktgenau: Gibt es in einer Stadt oder zu einer Postleitzahl keinen Berater, erscheint das Ergebnis leer — obwohl 5 km entfernt mehrere Berater verfügbar wären. Die Radius-Suche zeigt immer alle Berater innerhalb eines konfigurierbaren Umkreises.

**Empfohlener Standard-Radius:** 25 km
- Städtische Gebiete: 10–15 km reichen meist aus
- Ländliche Gebiete: 25–50 km nötig
- 25 km ist ein guter Kompromiss; der Besucher kann selbst wählen (10 / 25 / 50 / 100 km)

**Technischer Ansatz:**
1. Sucheingabe (PLZ oder Stadtname) per Nominatim geocodieren → `lat`/`lng` der gesuchten Position (Nominatim wird im Plugin bereits für die Map-Markierung genutzt)
2. Haversine-Formel in PHP anwenden: für jedes Mitglied die Entfernung zum Suchpunkt berechnen
3. Mitglieder innerhalb des gewählten Radius zurückgeben — sortiert nach Entfernung
4. Entfernungsangabe optional in der Kachelansicht anzeigen ("ca. 12 km entfernt")

**Voraussetzung:** A1 (Renderer nutzt Repository) muss abgeschlossen sein — die Geo-Koordinaten (`geoPositionCoords`) sind bereits im synced members.json vorhanden.

**UI-Erweiterung:**
- Radius-Dropdown neben dem PLZ/Ort-Eingabefeld (10 / 25 / 50 / 100 km)
- Nur sichtbar, wenn PLZ oder Stadtname eingegeben ist
- "Radius-Suche deaktivieren" zurück zur exakten Übereinstimmung

**Betroffene Dateien:** `frontend/ajax/ajax-endpoints.php`, `frontend/assets/frontend.js`, neues `frontend/includes/geo-utils.php` (Haversine), `frontend/views/renderer.php` (Entfernungsanzeige)

---

## C — Backend-UX-Verbesserung
*Betroffene Module: `admin/`, `bootstrap/`*
*Risikoarm: Frontend und Sync bleiben vollständig unangetastet.*

### C1 — Sync-Tab vereinfachen
**Was:** Der Sync-Tab ist funktional, aber komplex. Für einen Vereinsadmin ohne technischen Hintergrund ist der Ablauf (Explorer → Feldauswahl → Sync → Merge) nicht intuitiv.

**Maßnahmen:**
- Geführter Ablauf als nummerierte Schritte (Schritt 1: API-Token, Schritt 2: Explorer, Schritt 3: Sync)
- Klare Statusanzeige: "Zuletzt synchronisiert: vor 3 Stunden, 247 Mitglieder"
- Fortschrittsbalken während Sync lesbarer gestalten
- Fehlermeldungen in Klartext (nicht nur Status-JSON)
- "Jetzt synchronisieren"-Button prominenter platzieren

**Betroffene Dateien:** `admin/views/ui-sync.php`, `admin/assets/ui.js`, `admin/assets/admin.css`

---

### C2 — Feld-Tab (Felder) vereinfachen
**Was:** Der Felder-Tab mit Drag-Drop-Sortierung und Sidebar ist mächtig, aber für neue Nutzer schwer zu verstehen.

**Maßnahmen:**
- Onboarding-Hinweis für Erstnutzer (kollabierbar)
- Schnellzugriff: "Empfohlene Standard-Felder aktivieren"-Button
- Klare Unterscheidung: Pflichtfelder (nicht deaktivierbar) vs. optionale Felder
- Vorschau-Panel: kleines Vorschaubild wie eine Karte mit aktuellen Einstellungen aussieht
- Gruppen-Labels verbessern (Bereich "Oben" / "Unten" ist nicht selbsterklärend)

**Betroffene Dateien:** `admin/views/ui-felder.php`, `admin/assets/ui-felder.css`, `admin/assets/ui-felder-sidebar.js`

---

### C3 — Design-Tab verbessern
**Was:** Farbauswahl für Cards ist vorhanden, aber die Vorschau fehlt.

**Maßnahmen:**
- Live-Vorschau einer Musterkarte direkt im Design-Tab
- Farbpicker statt Freitext-Eingabe (HTML `<input type="color">`)
- "Zurück zu Standard"-Button
- Preset-Schemata anbieten (z.B. "Klassisch", "Modern", "Kontrastreich")

**Betroffene Dateien:** `admin/views/` (Design-Abschnitt), `admin/assets/`, `includes/design/design-settings.php`

---

### C4 — Kalender- und Karten-Tab überarbeiten
**Was:** Diese Tabs sind funktional minimal dokumentiert.

**Maßnahmen:**
- Erklärungstexte für Konfigurationsoptionen
- Karten-Tab: Vorschau des Standard-Kartenausschnitts
- Kalender-Tab: Hinweis auf iCal-URL-Format mit Beispiel

**Betroffene Dateien:** `admin/views/ui-kalender.php`, `admin/views/ui-map.php`

---

### C5 — Dashboard-Tab einführen (neu)
**Was:** Es gibt keine Übersichtsseite. Wer das Plugin öffnet, landet direkt im Felder-Tab.

**Maßnahmen:**
- Neuer erster Tab "Übersicht" mit:
  - Zuletzt synchronisiert (Datum + Mitgliederzahl)
  - Schnellzugriff auf häufige Aktionen
  - Status der Konfiguration (Token gesetzt? Consent-Feld gesetzt? Felder konfiguriert?)
  - Warnungen bei Problemen (Token abgelaufen, Sync > 24h alt)

**Betroffene Dateien:** neues `admin/views/ui-dashboard.php`, `admin/views/ui-main.php`

---

### C6 — Formularverarbeitung aus Bootstrap auslagern
**Was:** `bootstrap/admin-page.php` verarbeitet POST-Formulare (Token speichern, Einstellungen setzen). Das ist Fachlogik im Bootstrap.

**Maßnahmen:**
- Form-Handler nach `admin/form-handler.php` verschieben
- `bootstrap/admin-page.php` enthält nur noch Routing (welcher Tab wird angezeigt?)

**Betroffene Dateien:** `bootstrap/admin-page.php` → neues `admin/form-handler.php`

---

## D — Logging vereinheitlichen
*Betroffenes Modul: `includes/infra/logging/`*

**Was:** 4 parallele Logging-Kanäle mit inkonsistenten Signaturen:
- `bes_debug_log()` → WP `error_log`
- `bes_write_debug_log()` → `debug.log`-Datei
- `bseasy_v3_log()` → `sync-v3.log`
- `bes_consent_log()` → `consent.log`

**Maßnahmen:**
- Zentraler Logger in `includes/infra/logging/` mit Kanal-Parameter: `bes_log($msg, $level, $channel)`
- Kanäle: `debug`, `sync`, `consent`, `system`
- Alte Funktionen als dünne Wrapper davor → kein Breaking Change
- Konfigurierbar: welche Kanäle aktiv sind (im wp-config.php oder Plugin-Einstellungen)

---

## E — Accessibility (WCAG 2.1 AA)
*Betroffenes Modul: `frontend/`*

**Was:** Barrierefreiheit ist EU-rechtlich seit 2025 für öffentliche Stellen und zunehmend auch für private Organisationen relevant.

**Maßnahmen:**
- Alle Bilder: beschreibende `alt`-Texte (aus Mitgliederdaten generiert)
- Filter-Elemente: korrekte `<label>`-Zuordnung, ARIA-Attribute
- Karte: Tastaturnavigation ermöglichen, Screenreader-Alternative (Liste als Fallback)
- Farbkontraste prüfen (mindestens 4.5:1 für normalen Text)
- Fokus-Styles nicht entfernen (CSS `outline: none` vermeiden)
- Skip-Links für Screenreader

**Betroffene Dateien:** `frontend/views/renderer.php`, `frontend/views/map-render.php`, `frontend/assets/frontend.css`

---

## F — Performance & Caching-Verbesserungen
*Betroffene Module: `frontend/`, `includes/infra/cache/`*

**F1 — Cache-Invalidierung verbessern**
Aktuell wird der Cache bei jeder Design-Änderung geleert. Sinnvoller wäre ein versionierter Cache-Key, der nur bei tatsächlichen Datenänderungen (neuer Sync, Feldkonfig-Änderung) invalidiert wird.

**F2 — Partial Rendering**
Statt die gesamte Mitgliederliste zu cachen, könnten einzelne Karten gecacht und kombiniert werden. Das würde gefilterte Ansichten effizienter machen.

**F3 — Lazy Loading für Karte**
Die Karte wird auch geladen, wenn sie außerhalb des Viewports ist. Intersection Observer API würde die Initialisierung verzögern bis die Karte sichtbar wird.

---

## G — Sync-Optimierung (Phase 7 aus ursprünglichem Plan)
*Betroffenes Modul: `sync/`*
*Erst angehen wenn A–D abgeschlossen sind.*

**G1 — API-Requests reduzieren**
Pro Mitglied werden aktuell mehrere API-Calls gemacht (Member + Contact + Custom Fields). EasyVerein v2.0 unterstützt `expand`-Parameter — Member- und Contact-Daten könnten in einem Request geholt werden.

**G2 — Inkrementeller Sync**
Aktuell wird immer die komplette Mitgliederliste neu synchronisiert. Ein Delta-Sync (nur geänderte Mitglieder seit letztem Sync) würde die Sync-Zeit und API-Last massiv reduzieren — falls EasyVerein einen `modified_since`-Parameter anbietet.

**G3 — Fehlerbehandlung verbessern**
Wenn ein einzelnes Mitglied einen API-Fehler auslöst, bricht der gesamte Batch ab. Sinnvoller wäre: Fehler loggen, Mitglied überspringen, Batch fortsetzen.

---

## H — Datenspeicher überdenken (JSON → DB)
*Betroffene Module: `includes/data/`, `sync/`*
*Langfristig, nur wenn Mitgliederzahlen es erfordern.*

**Was:** Bei 1.000+ Mitgliedern mit vielen Custom-Feldern kann `members_consent_v3.json` mehrere MB groß werden. Jede Filteranfrage lädt und parsed die gesamte Datei.

**Maßnahmen:**
- Custom DB-Tabelle `{prefix}_bes_members` mit indizierten Feldern
- `member-repository.php` ist bereits die einzige Zugriffsschicht → der Wechsel ist nach außen unsichtbar
- Migration: beim nächsten Sync in DB schreiben statt JSON

**Voraussetzung:** A1 muss abgeschlossen sein (alle lesen über Repository).

---

## I — Sicherheits-Audit
*Betrifft alle Module*

**Regelmäßige Prüfpunkte:**
- Nonce-Validierung: alle AJAX-Actions geprüft?
- Capability-Checks: überall `current_user_can('manage_options')`?
- Sanitierung: alle `$_POST`/`$_GET`-Eingaben durchgehend sanitized?
- Rate-Limiting: auch für Admin-AJAX aktiv?
- Token-Sicherheit: AES-256-CBC + HMAC korrekt implementiert? (prüfen ob Schlüssellänge stimmt)
- Datei-Berechtigungen: `.htaccess` in Datenverzeichnis vorhanden?
- Abhängigkeiten: Leaflet.js + Marker-Clustering auf aktuellem Stand?

---

## J — Testbarkeit & PHP-Namespaces
*Optional, erst relevant wenn Team wächst oder Plugin deutlich komplexer wird.*

**J1 — Unit-Tests mit PHPUnit**
Kandidaten für erste Tests: `bes_encrypt_token`/`bes_decrypt_token`, `bes_validate_color`, Filterlogik in `ajax-endpoints.php`.

**J2 — PHP-Namespaces**
Alle ~180 globalen Funktionen könnten in Namespaces (`BSEasySync\Sync\`, `BSEasySync\Frontend\`) organisiert werden. Erfordert PSR-4-Autoloader. Großer Umbau — nur wenn das Plugin deutlich wächst.

---

## Empfohlene Umsetzungsreihenfolge

```
┌─────────────────────────────────────────────────────────────┐
│  JETZT — Strukturelle Restarbeiten (klein, risikoarm)       │
│  A1: Renderer/Map → Repository                              │
│  A2: ajax-v3.php → bes_sync_*-Wrapper                      │
│  A3: Stubs bereinigen                                       │
└───────────────────────┬─────────────────────────────────────┘
                        │
          ┌─────────────┴─────────────┐
          ▼                           ▼
┌─────────────────────┐   ┌─────────────────────────────────┐
│  DANACH             │   │  DANACH (parallel möglich)      │
│  B: Frontend-SEO    │   │  C: Backend-UX                  │
│  (B1→B3 zuerst,     │   │  (C1→C3 zuerst, C4–C6 danach)  │
│   B4–B5 später)     │   │                                 │
└─────────────────────┘   └─────────────────────────────────┘
          │                           │
          └─────────────┬─────────────┘
                        ▼
┌─────────────────────────────────────────────────────────────┐
│  DANACH — Querschnittsthemen                                │
│  D: Logging vereinheitlichen                                │
│  E: Accessibility                                           │
│  F: Performance & Caching                                   │
└───────────────────────┬─────────────────────────────────────┘
                        ▼
┌─────────────────────────────────────────────────────────────┐
│  LANGFRISTIG — Fachliche Modernisierung                     │
│  G: Sync-Optimierung                                        │
│  H: Datenspeicher (bei Bedarf)                              │
│  I: Sicherheits-Audit (regelmäßig)                         │
│  J: Testbarkeit / Namespaces (bei Teamwachstum)             │
└─────────────────────────────────────────────────────────────┘
```

### Warum diese Reihenfolge?

**A vor B/C:** Die Restarbeiten schließen die Architektur wirklich sauber ab. Renderer auf das Repository umstellen bedeutet: wenn später in B die Ausgabe geändert wird, gibt es nur einen Codepfad.

**B und C unabhängig:** SEO-Arbeiten berühren ausschließlich `frontend/`. Backend-UX-Arbeiten berühren ausschließlich `admin/`. Dank Modularisierung können diese parallel laufen oder in beliebiger Reihenfolge.

**D vor G:** Wenn bei der Sync-Optimierung (G) Fehler auftreten, braucht man ein verlässliches Logging. Das vereinheitlichte Logging macht die Fehleranalyse deutlich einfacher.

**G erst nach A–D:** Die Sync-Engine ist die risikoreichste Komponente. Je stabiler und aufgeräumter der Rest ist, desto weniger können Sync-Änderungen unbemerkt andere Teile beschädigen.

**H nur bei Bedarf:** JSON-Datei ist für kleine Vereine (< 500 Mitglieder) völlig ausreichend. Die Entscheidung erst treffen wenn Performance-Probleme konkret auftreten — dank Repository-Pattern ist der Wechsel dann isoliert.
