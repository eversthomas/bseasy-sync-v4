# Umsetzungsplan Datenschutz (minimal-invasiv)

**Stand:** 09.11.2026 · Grundlage: [DATENSCHUTZ-OPTIMIERUNG.md](DATENSCHUTZ-OPTIMIERUNG.md)
**Ziel:** Nur die datenschutzrechtlich nötigen Änderungen am produktiv laufenden Plugin, ohne Risiko für die Grundfunktionen. Kein Refactoring jetzt.

## Rollen und Arbeitsweise

| Wer | Aufgabe |
|-----|---------|
| **Thomas** | Entscheidungen, lokales Testen, Commit/Push, Einspielen der Live-Version |
| **Cursor** | Setzt je einen Schritt um (nur der beschriebene Umfang) |
| **Claude** | Schreibt den Auftrag pro Schritt, prüft den Diff (`git diff`), nennt Testpunkte und sagt, ob committet/gepusht werden soll |

Regeln:
- **Ein Schritt = ein Auftrag = ein Commit.** Claude und Cursor arbeiten nie gleichzeitig im Repo.
- Entwicklung auf einem eigenen Branch `privacy/osm-optin`, **nicht auf `master`**. Live ändert sich erst, wenn du die neue Plugin-Version selbst einspielst.
- Nach jedem Schritt: Diff-Review durch Claude → lokaler Test (wo angegeben) → Commit → erst dann der nächste Schritt.
- Bei Unsicherheit eines Schritts: Rückgabe an Claude, nicht „weiterbauen".

## Stufe 0 – ohne Plugin-Code (parallel, Betreuer der Seite)

Nicht Teil der Plugin-Version, aber dringend. Claude formuliert dafür auf Wunsch eine Nachricht.

- [ ] `info.php` im Webroot löschen.
- [ ] `sync.log` und `fields-config.json` unter `uploads/bseasy-sync/` sperren (Serverregel) oder löschen; DEBUG-Logging ausschalten.
- [ ] Datenschutzerklärung ergänzen (Textbausteine: Abschnitt 9 in DATENSCHUTZ-OPTIMIERUNG.md), insbesondere OpenStreetMap-Karte per Einwilligung, Umkreissuche (Nominatim, ohne Besucher-IP), Emoji-Hinweis (optional).

## Vorab-Entscheidungen (Standardvorschläge)

| Thema | Vorschlag |
|-------|-----------|
| Opt-in-Verfahren | Click-to-load direkt an der Karte, Zustimmung in `localStorage` |
| Merken | Checkbox „Auswahl 6 Monate merken" (standardmäßig **aus**); ohne Haken gilt die Zustimmung nur für den aktuellen Seitenaufruf |
| Ablauf | 180 Tage |
| Notausgang | PHP-Filter `bes_map_optin_enabled` (Standard `true`); `false` stellt das alte Verhalten wieder her |
| Platzhaltertext | „Für die Kartenansicht werden Kacheln von OpenStreetMap geladen. Dabei werden Ihre IP-Adresse und Browserdaten an die OpenStreetMap Foundation übertragen." + Datenschutz-Link |
| Emoji / Koordinaten runden / Nominatim-Sync / Bildformate | **nicht** in diesem Paket (später) |

## Schritt 0 – Vorbereitung

**Thomas:**
1. Lokal prüfen, dass die Seite mit dem Plugin läuft (Kachel, Karte, Toggle, Filter, Umkreissuche).
2. Branch und Rückfallpunkt anlegen:
   ```bash
   git switch -c privacy/osm-optin
   git tag pre-privacy-4.0.2
   ```
3. Zip des aktuellen Live-Plugins (Rollback) aufbewahren.

**Basis-Test (vor jeder Änderung, ca. 10 min):** Seite mit `[bes_members view="toggle"]` laden; Network-Tab: Anzahl Requests an `tile.openstreetmap.org` notieren (heute: 1 Kachel beim Laden, ca. 14 nach Klick auf „Karte"); Marker, Cluster, Popup, Filter und Umkreissuche kurz durchklicken. Das ist die Referenz für die späteren Tests.

**Commit/Push:** nichts zu committen. Tag nur lokal.

## Schritt 1 – Opt-in-Logik (Funktion)

**Ziel:** Ohne Zustimmung entsteht **kein** Request an OSM. Karte, Marker, Cluster, Filter und Popups funktionieren unverändert.

**Umfang (voraussichtlich):**
- `frontend/assets/map.js`: Tile-Layer (Zeilen 71–74) in `loadTiles()` kapseln; nur aufrufen, wenn Zustimmung gilt **und** der Kartencontainer sichtbar ist; nach dem Einblenden `invalidateSize()`. Zustimmung lesen/schreiben mit Schlüssel `bes_osm_tiles` und Ablaufdatum, alle Storage-Zugriffe in `try/catch`.
- `frontend/shortcode/bes-members.php` (Toggle-Logik um Zeile 222): beim Wechsel auf die Karte `loadTiles()` mit auslösen, falls Zustimmung vorliegt.
- Platzhalter-Markup (einfach, noch ohne Feinschliff) und Übergabe der Texte/Datenschutz-URL (`get_privacy_policy_url()`) per `wp_localize_script`.
- PHP-Filter `bes_map_optin_enabled` als Notausgang.

**Nicht anfassen:** Marker-/Filter-/Popup-Logik, Kachelansicht, Radius-Suche, Sync, Admin.

**Prüfung durch Claude (Diff):** Kapselung sauber, kein Request ohne Zustimmung im Code-Pfad, `try/catch` um Storage, keine Änderung an Marker-/Filtercode, kein neues Fremdskript, kein Cookie.

**Test lokal (Thomas) – Pflicht:**
- Toggle-Ansicht laden: Network-Tab zeigt **0** Requests an `tile.openstreetmap.org`.
- Auf „Karte" wechseln, Platzhalter erscheint, Marker/Filter reagieren schon vorher/danach normal.
- „Karte laden" klicken → Kacheln laden, Karte vollständig dargestellt (keine grauen Flächen).
- Reload mit „merken": Karte lädt Kacheln erst, wenn sie sichtbar wird.
- Ohne „merken": nach Reload wieder Platzhalter.
- Privates Fenster / blockierter Speicher: kein JS-Fehler.
- Filter und Umkreissuche funktionieren mit und ohne Kacheln.

**Commit:** ja, nach bestandenem Test („Opt-in: OSM-Kacheln erst nach Zustimmung"). **Push:** ja, auf den Branch (hat keine Wirkung auf Live).

## Schritt 2 – Design, Widerruf, Attribution, Barrierefreiheit

**Ziel:** Das Opt-in passt zur Seite und ist bedienbar.

**Umfang:**
- `frontend/assets/frontend.css`: Platzhalter in Kartengröße (600 px, mobil wie `#bes-members-map`), cremefarbener Hintergrund, feiner oranger Rand, 14 px Rundung, weicher Schatten, Pill-Button im Stil von „Weiter lesen"; lokale SVG-Silhouette als Hintergrund (kein Fremd-Request); Farben aus dem bestehenden Design (Design-Bridge/Design-Einstellungen) übernehmen, keine harten Werte, wo ein Token existiert.
- Link „Kartendienst deaktivieren" unter der Karte (Widerruf: Eintrag löschen, Tile-Layer entfernen, Platzhalter wieder zeigen).
- OSM-Attribution mit Link auf `https://www.openstreetmap.org/copyright`.
- Zugänglichkeit: Button fokussierbar, sichtbarer Fokus, `aria-live` beim Wechsel, `prefers-reduced-motion` beachten.

**Prüfung durch Claude:** CSS nur für neue Klassen (`.bes-map-optin*`), keine Änderungen an bestehenden Kartenregeln, Kontraste, mobile Breite, Widerruf wirklich ohne Reload funktionsfähig.

**Test lokal (Thomas):** Desktop, Tablet, Handy-Breite; Tastaturbedienung (Tab, Enter); Widerruf; Aussehen im Seitenkontext (Astra/Elementor-Seite, wenn lokal vorhanden, sonst nach Live-Test beurteilen); Attributionslink klickbar.

**Commit:** ja. **Push:** ja.

## Schritt 3 – JSON-LD härten (optional, sehr klein)

**Ziel:** Ein `</script>` in einem Profilfeld kann das Skript nicht verlassen.

**Umfang:** `frontend/includes/schema.php:144`: `json_encode` zusätzlich mit `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`. Sonst nichts.

**Prüfung durch Claude:** Nur diese Zeile geändert; Ausgabe bleibt gültiges JSON.

**Test lokal:** Seite aufrufen, JSON-LD im Quelltext mit einem Validator prüfen (z. B. Rich-Results-Test oder JSON-Parser), Sonderzeichen (Umlaute, `&`, Anführungszeichen) im Profil kontrollieren.

**Commit:** ja. **Push:** ja.

## Schritt 4 – Dokumentation und Version

**Umfang (Claude schreibt, Cursor oder Thomas übernimmt):**
- Versionsnummer erhöhen (Plugin-Header, Versionskonstante für Cache-Busting, README-Version; aktuell Header 4.0.2, README 4.0.0), `CHANGELOG.md`.
- README-Abschnitt „Datenschutz": OSM-Kacheln nur nach Zustimmung, Nominatim bei der Umkreissuche, keine Cookies; Hinweis auf Emoji-Skript als WordPress-Thema.
- DATENSCHUTZ-OPTIMIERUNG.md auf den Ist-Stand setzen (erledigt/offen).

**Commit:** ja. **Push:** ja.

## Schritt 5 – Gesamttest und Abschluss

**Test lokal (Thomas), vollständig:** Checkliste aus Schritt 1 und 2 plus:
- Seite mit `view="kachel"`, `view="map"`, `view="toggle"`.
- Zwei Shortcodes auf einer Seite (falls verwendet).
- Seitencache aktiv (falls lokal möglich): Opt-in funktioniert ohne Cache-Abhängigkeit.
- Kalender-Shortcode unverändert.
- Browser: aktuelles Chrome, Safari, Firefox; iOS/Android kurz.

**Abschluss:** Merge in `master`, Tag `v<neue Version>`, Zip bauen, Plugin selbst einspielen, **Seitencache leeren**, Live-Check (Network-Tab: 0 Kachel-Requests vor Zustimmung, Kartenfunktionen). Rollback: alter Zip und Tag `pre-privacy-4.0.2`.

## Später (nicht in diesem Paket)

Koordinaten runden, Nominatim im Sync entfernen, Bildformate (`.png`-Annahme), `uninstall.php`, ICS-Allowlist, Lazy-Loading-Aufräumen, Token-Feld im Admin, Sync-Optimierung, Text-Domain/Versionen vereinheitlichen. Vorher klären: Auf welcher Adresse berechnet EasyVerein die Koordinaten?

---

## Standardblock für jeden Cursor-Auftrag

```text
Arbeite nur im Branch privacy/osm-optin. Setze ausschließlich den unten beschriebenen Schritt um.
Ändere keine anderen Dateien oder Funktionen, kein Refactoring, keine Formatierungsänderungen an unberührtem Code.
Behalte PHP- und JS-Stil des Repos bei. Keine neuen Bibliotheken, keine Fremdhosts, keine Cookies.
Marker-, Filter-, Popup-, Radius-Such-, Karten- (Kachelansicht), Kalender-, Sync- und Admin-Code bleiben unverändert.
Committe nichts. Gib am Ende eine Liste der geänderten Dateien und eine kurze Beschreibung jeder Änderung aus.
Wenn etwas unklar ist oder mehr geändert werden müsste als beschrieben, frage nach, statt zu raten.
```
