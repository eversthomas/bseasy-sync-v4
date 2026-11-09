# Prompt für die externe Gesamtanalyse (Cursor)

**Stand:** 09.11.2026 · Dieses Dokument enthält (1) eine Checkliste für die Vorbereitung und (2) den Prompt zum Kopieren.

## Vorbereitung (bitte vor dem Start)

- Cursor im Repo-Ordner öffnen, Agent-/Ask-Modus **ohne Schreibrechte** bzw. mit Hinweis „nur lesen" nutzen.
- **Nicht** in den Chat/Kontext geben: `debug/debug.log` (ca. 600 KB, in `.gitignore`, kann Tokens und Personendaten enthalten), `members_consent_v3.json` und alle Dateien unter `wp-content/uploads/bseasy-sync/` (enthalten Namen und E-Mail-Adressen von Mitgliedern).
- Keine echten Zugangsdaten bereitstellen (EasyVerein-Token, `.env` aus `dev/sync-lab`).
- Wenn möglich ein starkes Modell mit langem Kontext wählen. Bei ca. 18.000 PHP-Zeilen sollte die Analyse in mehreren Durchgängen erfolgen (siehe Prompt, Abschnitt „Vorgehen").
- Ergebnis nach `docs/` zurückkopieren oder als Datei ablegen lassen, dann gemeinsam bewerten.

---

## Prompt (ab hier kopieren)

```text
Du bist ein erfahrener WordPress-Plugin-Entwickler, Security-Reviewer und Datenschutz-Auditor.
Führe eine unabhängige, vollständige Analyse des Plugins in diesem Repository durch.

## Rahmenbedingungen
- NUR LESEN UND ANALYSIEREN. Ändere keine Dateien, committe nichts, installiere nichts dauerhaft.
- Nutze lokale Prüfwerkzeuge, wenn verfügbar und ohne Nebenwirkungen (z. B. `php -l`, PHPCS mit WordPress-Coding-Standards, PHPStan/Psalm, ESLint). Wenn ein Werkzeug fehlt, sage das und arbeite manuell weiter.
- Sende keine realen Mitglieder-, Token- oder Kundendaten an externe Dienste. Rufe keine produktiven APIs auf.
- Lies nicht `debug/debug.log`, keine `.env`-Dateien und keine Exportdateien mit Mitgliedsdaten. Falls du dennoch solche Daten siehst, gib sie NICHT im Bericht wieder, sondern melde nur, dass sie vorhanden sind.
- Belege jede Aussage mit Datei und Zeile. Erfinde keine Fundstellen. Unterscheide klar zwischen
  (a) BESTÄTIGT (im Code nachvollzogen), (b) PLAUSIBEL (Hinweis, nicht verifiziert) und (c) OFFEN (braucht Laufzeit- oder Serverkenntnis).
- Prüfe auch das, was gut gelöst ist, und nenne es kurz.

## Kontext
Das Plugin „BSEasy Sync V4" (WordPress, Text-Domain `besync`, Header-Version 4.0.2, Requires PHP 7.4, WP 5.8) synchronisiert Mitgliedsdaten aus EasyVerein (API v2.0) und zeigt sie als Berater-/Mitgliederkarten (Kachelansicht), als Leaflet-Karte (Marker, Cluster) mit Umkreissuche und als Toggle der beiden Ansichten per Shortcode `[bes_members]`. Es gibt außerdem einen Kalender (ICS) und einen Admin-Bereich (Explorer, Feldauswahl, Design, Sync). Die Daten liegen als JSON und Bilder unter `wp-content/uploads/bseasy-sync/`. Das Plugin läuft produktiv auf einer Elementor-/Astra-Seite (ca. 160 Mitglieder, Seitenpflege durch Dritte, kein Child-Theme möglich). Die Mitglieder haben der Veröffentlichung ihrer Daten zugestimmt; zu schützen sind insbesondere die Website-BESUCHER.
Verzeichnisse: `admin/`, `bootstrap/`, `frontend/` (Renderer, Karte, Kalender, AJAX), `includes/`, `sync/` (API-Clients, Consent-Pipeline V3, Cron, Persistenz), `dev/` (Sync-Lab), `docs/`.

## Vorgehen (bitte in dieser Reihenfolge, jeweils mit Zwischenfazit)
1. Überblick: Einstiegspunkte (`bseasy-sync.php`, `bootstrap/`), Hooks, Shortcodes, AJAX-Actions, Cron-Jobs, Optionen, Dateisystem-Zugriffe, externe HTTP-Aufrufe. Erstelle daraus ein kurzes Inventar.
2. Sync-Pipeline (`sync/`), Persistenz und Datenmodell.
3. Frontend (Renderer, Karte, Kalender, AJAX, JS, CSS).
4. Admin-Bereich.
5. Querschnittsthemen (Security, Datenschutz, Performance, Wartbarkeit).
6. Gezielte Beantwortung der Fragen im Abschnitt „Konkrete Fragen".

## Prüfbereiche
A. Security (WordPress-spezifisch)
   - Nonces und Capability-Checks in allen Admin-/AJAX-Handlern; besonders die `wp_ajax_nopriv_*`-Endpunkte (`bes_filter_members`, `bes_radius_search`).
   - Eingabe-Sanitizing und Ausgabe-Escaping (esc_*, wp_kses-Listen, z. B. `frontend/shortcode/bes-members.php`), XSS-Risiken in Karten-Popups (JSON in HTML, `innerHTML`/Template-Strings in `map.js`).
   - SQL (prepared statements), Dateizugriffe (Pfad-Traversal, Schreibrechte, `BES_IMG`/`BES_DATA_V3`, `.htaccess`-Schutz, Auslieferung von JSON-Dateien mit Personendaten), Upload- und Bildverarbeitung.
   - SSRF/Open-Redirect-Risiken bei serverseitigen Abrufen (z. B. ICS-URLs in `admin/calendar-handler.php` und `frontend/views/calendar-render.php`).
   - Umgang mit dem EasyVerein-Token (Speicherung, Verschlüsselung, Logging, Anzeige im Admin, Übertragung).
   - Debug-/Log-Ausgaben (`bes_debug_log`, `bes_consent_log`, `console.log`): Gelangen Tokens oder Personendaten in Logs, Konsole oder HTML?
   - Direktaufrufe (`ABSPATH`-Guard), Rate-Limiting/DoS-Potenzial der öffentlichen Endpunkte, Cache-/Transient-Missbrauch.
B. Datenschutz (DSGVO)
   - Erstelle ein vollständiges Inventar ALLER Datenflüsse nach außen: wohin (Host), wer löst aus (Besucher-Browser, Server, Sync), welche Daten (IP, Suchbegriffe, Adressen, Namen), wann, mit welcher Rechtsgrundlage-Annahme, Caching-Dauer.
   - Welche personenbezogenen Daten liegen wo (JSON, Transients, Optionen, Logs, Bilder, HTML-Quelltext, `data-*`-Attribute, JSON-LD/Schema)? Ist die Menge nötig (Datenminimierung)? Was ist im HTML-Quelltext öffentlich sichtbar (z. B. Marker mit hausgenauen Koordinaten, E-Mail-Adressen)?
   - Gibt es Cookies, localStorage/sessionStorage oder Fingerprinting durch das Plugin?
   - Lösch-/Deinstallationsverhalten (es gibt anscheinend keine `uninstall.php`): bleiben Daten, Bilder, Optionen, Transients, Cron-Events zurück?
C. Korrektheit und Robustheit
   - Fehlerbehandlung, Timeouts, Teilfehler beim Sync, Idempotenz, Rate-Limits, Cron-Überschneidungen, Wiederaufnahme, atomares Schreiben, Locking.
   - API-Pagination, Feldauswahl, Merge von Teilergebnissen, Konsistenz zwischen V2-Resten (`sync/_backup-v2`), V3-Pipeline und Frontend.
   - PHP-Kompatibilität: Der Plugin-Header verlangt PHP 7.4 und WordPress 5.8, der Code scheint aber PHP-8-Funktionen/Syntax zu nutzen (z. B. `str_contains`). Prüfe das vollständig (str_contains/str_starts_with/str_ends_with, `match`, `?->`, benannte Argumente, Union Types, `fn`), und ob die Mindestversionen stimmen oder angehoben werden müssen.
   - Mehrsprachigkeit/i18n (Text-Domain, hartcodierte deutsche Strings), Mehrsprachigkeit der Länderwerte, Multisite-Verhalten.
D. Performance
   - Was wird bei jedem Seitenaufruf geladen/geparst (große JSON-Dateien, Repository, Filteroptionen)? Gibt es Caching (Transients/Object Cache) und sinnvolle Invalidierung?
   - Frontend: Größe des inline eingebetteten Karten-JSON (Popups pro Marker), DOM-Größe der Kartenliste (alle Karten im HTML), Infinite Scroll, Lazy Loading, Bildgrößen (srcset/width/height), CLS/LCP, JS/CSS-Umfang, jQuery-Abhängigkeit, Skriptladung nur bei Bedarf.
   - Sync: Anzahl der API-Aufrufe (N+1), künstliche Pausen, Laufzeitlimits.
E. Architektur und Wartbarkeit
   - Tote/duplizierte Dateien und Funktionen, z. B. `frontend/renderer.php` vs. `frontend/views/renderer.php`, `frontend/filter-helpers.php` vs. `frontend/includes/filter-helpers.php`, `frontend/calendar-render.php` vs. `frontend/views/…`, `includes/constants.php` vs. `includes/constants-v3.php`, `sync/_backup-v2`, `dev/`. Was wird tatsächlich geladen (include-/require-Graph), was ist toter Code?
   - Versionsangaben (README sagt 4.0.0, Plugin-Header 4.0.2, Cache-Version), globale Variablen, Namespaces/Präfixe, Kopplung, Testbarkeit, vorhandene Tests (`docs/MANUAL_TESTS.md`) und CI.
   - Einhaltung der WordPress-Coding-Standards und der WordPress.org-Plugin-Richtlinien (falls eine Veröffentlichung denkbar ist).
F. Barrierefreiheit und Frontend-Qualität
   - Semantik/ARIA der Karten (z. B. `aria-hidden` auf dem Bildbereich bei sinnvollem `alt`), Tastaturbedienung von Filtern, Toggle und Karte, Fokus-Management, Kontraste, `prefers-reduced-motion`, Screenreader-Verhalten der Leaflet-Karte.

## Konkrete Fragen (bitte jeweils eigenes Kapitel mit klarer Antwort)
1. OpenStreetMap-Kacheln (Datenschutz der Besucher)
   - `frontend/assets/map.js` lädt Kacheln von `https://{s}.tile.openstreetmap.org` bereits beim Seitenaufruf (auch in der ausgeblendeten Kachelansicht, Container 0×0). Bestätige das im Code und finde ALLE Stellen, an denen Besucher-Browser Dritthosts kontaktieren (Kacheln, Schriften, Icons, Skripte, CDN, Emoji).
   - Entwirf (nur als Konzept, ohne Änderung) ein einfaches eigenes Click-to-load-Opt-in direkt an der Karte: Wo gehört der Einhängepunkt hin (`loadTiles()`-Kapselung in `map.js`, Toggle-Logik in `bes-members.php`, `invalidateSize`)? Wie lassen sich Zustimmung (localStorage mit Ablauf, try/catch), Widerruf, Datenschutz-Link (`get_privacy_policy_url()`) und OSM-Attribution mit Link lösen? Welche Fallstricke gibt es mit Caching-Plugins, dem Toggle, den Filtern, dem `view="map"` ohne Toggle und mit einer späteren Anbindung eines externen Consent-Management-Tools?
   - Bewerte Alternativen (externer CMP, eigener Tile-Server/EU-Anbieter) kurz mit Aufwand und Risiken.
2. Emojis / `s.w.org`
   - Die Umschalt-Buttons enthalten Emojis (`frontend/shortcode/bes-members.php`), und Profiltexte aus EasyVerein können beliebige Emojis enthalten. Das WordPress-Emoji-Skript lädt auf Browsern ohne Emoji-Unterstützung Bilder von `s.w.org`.
   - Wir wollen eine optionale Einstellung im Plugin (Standard: aus), die das Emoji-Skript seitenweit abschaltet, weil kein Child-Theme möglich ist. Welche Hooks/Filter sind dafür nötig und korrekt (`print_emoji_detection_script`, `print_emoji_styles`, `wp_staticize_emoji`, `emoji_svg_url`, TinyMCE, `wp_resource_hints` usw.), wohin gehört die Einstellung, welche Nebenwirkungen (Elementor, Caching, Editor, andere Plugins) sind zu erwarten, und wie sollte der Hinweistext lauten? Prüfe zusätzlich, ob die Emojis in den Buttons besser durch Inline-SVG ersetzt werden.
3. Geocoding
   - `sync/consent/consent-geocoding.php` ruft Nominatim mit `q=…&country=de` auf. Nominatim antwortet darauf mit HTTP 400 („Structured query parameters … cannot be used together with 'q'"), mit `countrycodes=de` funktioniert die Abfrage. Bestätige den Fehler im Code. Prüfe, wann `bes_geocode_contact_fallback()` aufgerufen wird (Bedingung `needs_geocoding`, Feldauswahl), welche Daten dabei in der URL stehen, wo Ergebnisse landen und ob Fehler unbemerkt bleiben.
   - Ebenso `frontend/includes/geo-utils.php` (`bes_geocode_location_query`, Umkreissuche, AJAX `bes_radius_search`): Datenfluss, Cache, Missbrauchspotenzial (öffentlicher Endpunkt ohne Login, ungebremste Nominatim-Anfragen, User-Agent mit Kontaktadresse), Genauigkeit der Distanzberechnung.
   - Die private Ausweichadresse (zip/city/street) soll NICHT mehr verwendet werden; Mitglieder ohne Geschäftsadresse werden nicht berücksichtigt. Zeige alle Stellen, an denen private Adressfelder gelesen, übertragen oder gespeichert werden, und welche davon entfallen können.
   - Variante A: Nominatim im Sync entfernen und nur EasyVerein-Koordinaten (`geoPositionCoords`) nutzen. Variante B: Aufruf reparieren und nur auf die Geschäftsadresse beschränken. Bewerte beide (Datenschutz, Marker-Abdeckung, Aufwand, Risiko, Nominatim-Nutzungsrichtlinie inkl. Rate-Limit und identifizierbarem User-Agent) und gib eine begründete Empfehlung.
   - Prüfe anhand der API-Felddefinitionen im Repository (`easyverein_api_v2_live.json`, `easyverein-doku-api-2.html`, `dev/sync-lab`), ob erkennbar ist, auf welcher Adresse (privat oder geschäftlich) EasyVerein `geoPositionCoords` berechnet. Falls nicht erkennbar: als OFFEN markieren und eine einfache Methode vorschlagen, das ohne Offenlegung von Personendaten zu klären. Beachte: Koordinaten liegen mit 7–8 Nachkommastellen (hausgenau) im öffentlichen HTML. Ist das angemessen, und wie könnte man bei Bedarf runden oder den Marker versetzen?
   - Ursache für fehlende Koordinaten: Von ca. 160 Mitgliedern fehlen bei ca. 36 die Koordinaten, obwohl fast alle eine Geschäftsadresse haben. Mögliche Ursachen im Code (Extraktion, Feldauswahl, Fallback, Länderbeschränkung `country=de` bei AT/CH-Mitgliedern, Caching)?
4. Lazy Loading der Mitgliederbilder
   - Auf der Live-Seite funktioniert es (nur wenige Bilder beim Aufruf, danach beim Scrollen). Prüfe die Implementierung trotzdem auf Schwächen: alle Karten stehen im HTML und werden erst per JS (`setTimeout` in `frontend.js`) ausgeblendet; die erste Karte ist `eager` mit `fetchpriority="high"` auch dann, wenn sie unter der Falz liegt; der Eager-Code in `frontend.js` (Selektor `.bes-member-image img`) passt nicht zum aktuellen Markup (`.card-image`); fehlende `width`/`height` (CLS); Bildgrößen/Format. Schlage Verbesserungen vor (z. B. serverseitiges Paginieren oder Ausblenden per CSS, `content-visibility`, Bild-Dimensionen), ohne die Filter-/Suchfunktion zu brechen.
   - Bildformat: `renderer.php` und `map-render.php` erwarten `<id>.png`, der Sync speichert je nach Content-Type `png`, `jpg`, `webp` oder `gif`. Bestätige oder widerlege, dass hier Bilder verloren gehen können.
5. Cookies und Speicher: Setzt das Plugin Cookies oder nutzt Web-Storage? Gibt es Tracking oder Fremdskripte? Belege mit Fundstellen.

## Ausgabeformat
1. Kurzfazit (max. 15 Zeilen): Gesamteindruck, die 10 wichtigsten Befunde, Release-Tauglichkeit.
2. Befundtabelle, sortiert nach Schweregrad (Kritisch/Hoch/Mittel/Niedrig/Hinweis) mit Spalten: ID, Bereich, Befund, Datei:Zeile, Status (Bestätigt/Plausibel/Offen), Auswirkung, Empfehlung (in Worten, ggf. kurzer Pseudocode, keine Änderung am Repo), geschätzter Aufwand (S/M/L).
3. Datenfluss-Inventar (Abschnitt B) als Tabelle.
4. Antworten auf die „Konkreten Fragen" 1–5 mit klarer Empfehlung und Reihenfolge der Umsetzung.
5. Was gut gelöst ist.
6. Offene Fragen, die nur der Betreiber/Server/EasyVerein beantworten kann.
7. Vorschlag für einen priorisierten Maßnahmenplan (Phasen mit Aufwand), getrennt nach „sofort", „bald", „später".
Schreibe auf Deutsch, sachlich, ohne Füllsätze. Gib keine Personendaten, Tokens oder Passwörter im Bericht wieder.
```

---

## Hinweise zur Auswertung

- Befunde mit Status **Bestätigt** zuerst prüfen, **Plausibel** gezielt am Live-Stand verifizieren, **Offen** gehen an den Betreiber bzw. EasyVerein.
- Bereits bekannt und im Prompt vorgegeben (damit Cursor sie unabhängig nachprüft): Kachel-Request beim Seitenaufruf, Nominatim-HTTP-400, Emoji-Skript, `str_contains` bei PHP-7.4-Angabe, doppelte Renderer-/Helper-Dateien, fehlende `uninstall.php`, öffentliche `nopriv`-AJAX-Endpunkte.
- Ergebnisse dieser Analyse fließen anschließend in [DATENSCHUTZ-OPTIMIERUNG.md](DATENSCHUTZ-OPTIMIERUNG.md) und in die Entscheidungen zu Opt-in, Emoji-Option und Geocoding ein.
