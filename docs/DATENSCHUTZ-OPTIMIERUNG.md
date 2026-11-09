# Datenschutz & Performance – Optimierungspotenzial

**Stand:** 09.11.2026 (aktualisiert; erste Fassung 06.11.2026)
**Geprüft an:** https://systelios-transfer.de (Live, anonymer Besuch, nur Lesezugriffe) und am Plugin-Code, dazu eine externe Codeanalyse (Cursor, Quelltext-Review) mit eigener Gegenprüfung der Kernbefunde.
**Zweck:** Entscheidungsgrundlage für Datenschutzerklärung, Opt-in für die Karte und Maßnahmenplan. Keine Rechtsberatung.

Die Einwilligungen der Mitglieder (Profildaten, Adressen) liegen vor. Es geht ausschließlich um den Schutz der **Besucher**. Mitglieder, die nur eine Privatadresse gepflegt haben, werden nicht berücksichtigt; sie sollen ihre Geschäftsadresse in EasyVerein ergänzen.

**Änderungen gegenüber der ersten Fassung:**
- Neu: `sync.log` ist öffentlich abrufbar (Abschnitt 2).
- Korrigiert: Der Nominatim-Abruf im Sync hat laut Log nie stattgefunden; Mitgliederadressen sind dort nie an OSM gegangen (Abschnitt 3).
- Korrigiert: Das Emoji-Skript lädt nur bei Browsern ohne Emoji-Unterstützung Bilder von `s.w.org` (Abschnitt 4).
- Neu: Bewertung der externen Codeanalyse und Maßnahmenplan (Abschnitte 7 und 8).

---

## 1. Ergebnis in Kürze

| # | Befund | Betroffen | Priorität |
|---|--------|-----------|-----------|
| 1 | `sync.log` (7 MB, DEBUG-Modus, seit 24.01.2026) ist per URL öffentlich abrufbar | Plugin + Server | **Sofort** |
| 2 | OSM-Kacheln werden **schon beim Seitenaufruf** geladen, auch bei ausgeblendeter Karte → IP/Browserdaten an `*.tile.openstreetmap.org` | Plugin (`map.js:71`) | **Hoch** |
| 3 | JSON-LD wird ohne Hex-Escaping in ein `<script>` geschrieben; ein `</script>` in einem Profilfeld bricht aus dem Skript aus | Plugin (`schema.php:144`) | **Hoch** |
| 4 | Koordinaten stehen ungerundet (hausgenau) und doppelt im HTML | Plugin (`map-render.php:547`, `:612`) | **Hoch** |
| 5 | WordPress-Emoji-Skript kann Bilder von `s.w.org` nachladen (nur bei Browsern ohne Emoji-Unterstützung) | WP-Core + Plugin | Mittel |
| 6 | Nominatim-Umkreissuche sendet PLZ/Ort serverseitig an OSM | Plugin (`geo-utils.php:46`) | Mittel (nur Datenschutzerklärung) |
| 7 | Nominatim-Sync-Aufruf ist fehlerhaft und praktisch tot (siehe Abschnitt 3) | Plugin (`consent-geocoding.php`) | Mittel (entfernen) |
| 8 | OSM-Attribution ohne Link auf `openstreetmap.org/copyright` | Plugin (`map.js:72`) | Niedrig |
| 9 | Kontaktformular (Elementor Pro): Speicherung von Einsendungen prüfen | Theme/Elementor | Zu prüfen |

---

## 2. Öffentlich erreichbare Dateien (neu)

Live geprüft mit Statusabfragen (Pfad `/wp-content/uploads/bseasy-sync/`):

| Datei | Status | Bewertung |
|-------|--------|-----------|
| `sync.log` | **200 (offen)** | Muss gesperrt werden |
| `fields-config.json` | 200 (offen) | Konfiguration, geringe Sensibilität, trotzdem sperren |
| `v3/members_consent_v3.json` | 403 | Geschützt (`.htaccess` greift) |
| `v3/fields-config.json`, `img/` | 403 | Geschützt bzw. Listing gesperrt |

**Inhalt von `sync.log`** (nur statistisch ausgewertet, ca. 42.800 Zeilen, 24.01.2026 bis 09.11.2026): API-URLs mit Mitglieds-IDs, Statuscodes, Feldnamen der API-Antworten, ca. 850 Rate-Limit-Warnungen (EasyVerein-Limit), Token-Erneuerungsmeldungen. **Nicht gefunden:** Tokens im Klartext, E-Mail-Adressen, IP-Adressen, Namen, Adress- oder Geocoding-Zeilen. Fehlerzeilen enthalten teils den Anfang von Antworttexten; diese wurden bewusst nicht gelesen.

**Maßnahmen:**
1. Datei durch den Betreuer der Seite per Serverregel sperren (gesamter Ordner `bseasy-sync/` außer `img/`) oder löschen.
2. DEBUG-Logging im Produktivbetrieb ausschalten bzw. auf Warnungen/Fehler begrenzen, Log rotieren.
3. Plugin: `.htaccess` für Apache 2.4 (`Require all denied`) und auf Ebene des Upload-Ordners anlegen; Log-Datei besser außerhalb des öffentlichen Webverzeichnisses speichern. Unter Nginx wirkt keine `.htaccess`, dort ist eine Serverregel nötig.

---

## 3. Karte & externe Dienste (Plugin)

### Was passiert heute
- **Browser → OSM (Kacheln):** Leaflet lädt `https://{a,b,c}.tile.openstreetmap.org/{z}/{x}/{y}.png`. Übertragen werden IP-Adresse, User-Agent, Referer, Kachelkoordinaten.
  - Gemessen: 1 Kachel direkt beim Seitenaufruf (Karte unsichtbar, 0×0 px), nach Klick auf „Karte" +14 Kacheln.
  - Betrifft `view="map"` und `view="toggle"`.
- **Server → OSM (Nominatim, Umkreissuche):** Der vom Besucher eingegebene Ort/PLZ wird serverseitig an Nominatim gesendet, Ergebnis 7 Tage gecacht. Besucher-IP wird nicht übertragen (Server-IP). Der Endpunkt ist ohne Login erreichbar.
- **Sync → OSM (Nominatim):** **Praktisch nie ausgeführt.** Gründe:
  - Die Bedingung `needs_geocoding` prüft den Schlüssel `contact.geoPositionCoords`, die Feldauswahl enthält aber `contact.geoPositionCoords.lat`/`.lng` (`api-core-consent-v3.php:639`, `fields-config-default.json:219`).
  - Der Aufruf selbst wäre fehlerhaft: `q` zusammen mit `country=de` ergibt bei Nominatim HTTP 400 (geprüft mit einer neutralen öffentlichen Adresse). Richtig wäre `countrycodes`.
  - Im `sync.log` (DEBUG) gibt es keine einzige Geocoding-/Nominatim-Zeile.
  - **Folge:** Es wurden keine Mitgliederadressen (weder geschäftlich noch privat) an OSM übertragen. Alle 125 Koordinaten stammen aus EasyVerein (`geoPositionCoords`).
- Lokal eingebunden (keine Fremdverbindung): Leaflet, MarkerCluster, Marker-Icons, Profilbilder (`/uploads/bseasy-sync/img/`).

### Lösungsoptionen für die Kacheln
| Option | Aufwand | Anmerkung |
|--------|---------|-----------|
| **A: Externer Cookie-Banner/CMP** (Borlabs, Real Cookie Banner, Complianz) mit Content-Blocker | gering–mittel | Plugin muss Kacheln erst nach Consent laden; Blocker darf nicht das ganze `map.js` sperren, sonst fehlen auch Marker und Filter |
| **B: Eigenes Click-to-load-Opt-in im Plugin** | ca. 0,5–1 Tag | Platzhalter im Seitendesign, Zustimmung in `localStorage` (mit Ablauf), Widerruf, Datenschutz-URL via `get_privacy_policy_url()`, Attribution mit Link |
| **C: Kein Opt-in nötig** durch eigenen Tile-Server oder EU-Anbieter mit AV-Vertrag | mittel–hoch | Laufende Kosten/Betrieb |

**Empfehlung:** B, da die Seite sonst keine Cookies setzt und ein Banner nur wegen der Karte eine unnötige Hürde wäre. Unabhängig von A/B/C: Kachel-Ladung vom Seitenaufruf entkoppeln (`loadTiles()` erst nach Zustimmung **und** sichtbarer Karte).

Konzept B im Detail:
- `L.tileLayer` in `loadTiles()` kapseln; Karte, Marker und Cluster bleiben lokal und funktionieren ohne Kacheln.
- Aufruf nur bei Zustimmung und sichtbarem Container; nach dem Anzeigen `invalidateSize()` (Einhängepunkt: Toggle-Logik in `bes-members.php:222`).
- Speicherung unter `bes_osm_tiles` mit Ablaufdatum (z. B. 180 Tage), alle Zugriffe in `try/catch`; Widerruf entfernt den Eintrag und den Tile-Layer.
- Kein Cookie, kein Einfluss auf Seitencache.
- Gestaltung: Platzhalter in Kartengröße, cremefarbener Hintergrund mit feinem orangem Rand und 14-px-Rundung wie die Box „Teil des Netzwerks werden", orange Pill-Button wie „Weiter lesen", lokale SVG-Silhouette, Datenschutz-Link, „Auswahl merken".

---

## 4. Emojis / `s.w.org`

- Das WordPress-Emoji-Skript ersetzt Emojis nur dann durch Bilder von `s.w.org`, wenn der Browser sie nicht selbst darstellen kann (in meinem Test-Browser der Fall). Auf modernen Geräten entsteht meist kein Request, auf älteren schon.
- Die Emojis stammen aus zwei Quellen: den Umschalt-Buttons (🎴 🗺️, `bes-members.php:246`) und Profiltexten aus EasyVerein (z. B. 🙂). Nur die Buttons zu ändern reicht daher nicht.
- Entscheidung: Option im Plugin (Standard **aus**), die das Emoji-Skript seitenweit abschaltet, weil kein Child-Theme möglich ist und die Seite von Dritten betreut wird.
- Hooks: `print_emoji_detection_script` (wp_head, admin_print_scripts), `print_emoji_styles` (wp_print_styles, admin_print_styles), `wp_staticize_emoji` (Feeds), `wp_staticize_emoji_for_email`, Filter `emoji_svg_url` → false, TinyMCE-Plugin `wpemoji` entfernen, `s.w.org` aus den dns-prefetch-Hinweisen filtern.
- Nebenwirkungen: gilt für die ganze Website (auch Elementor-Texte, Editor); ältere Systeme können Kästchen statt Emoji zeigen; Seitencache nach dem Speichern leeren.
- Die Button-Emojis zusätzlich durch Inline-SVG-Icons mit sichtbarem Text ersetzen (einheitliche Darstellung, passt zum Design).

---

## 5. Cookies & Speicher der Seite (unabhängig vom Plugin)

Geprüft auf Startseite, Kontaktseite und Therapeuten-Seite (HTTP-Header per `curl`, im Browser `document.cookie`, `localStorage`, `sessionStorage`, IndexedDB, Netzwerk-Requests).

| Prüfpunkt | Ergebnis |
|-----------|----------|
| `Set-Cookie`-Header | **keine** |
| Cookies (`document.cookie`) | **keine** |
| `localStorage` / IndexedDB | leer |
| `sessionStorage` | 1 Eintrag `wpEmojiSettingsSupports` (WP-Core Emoji-Test, technisch, ohne Personendaten) |
| Externe Skripte/iFrames | **keine** (kein Analytics, kein Facebook-Pixel, kein reCAPTCHA, keine Embeds) |
| Schriften | **lokal** (Astra Local Fonts) |
| Fremd-Hosts bei Aufruf der Therapeutenseite | nur `tile.openstreetmap.org` und `s.w.org` |
| Facebook/Google-Treffer im HTML | nur Elementor-Konfigurationstext, keine Requests |
| Externe Links (Mitgliederwebseiten, LinkedIn, therapie.de) | nur Links |

Das Plugin selbst setzt keine Cookies und nutzt kein Web-Storage im Frontend (nur im Admin `localStorage`-Schlüssel `bes_active_tab`).

**Fazit:** Die Seite setzt derzeit keine Cookies und nutzt kein Tracking. Ein Cookie-Banner wäre nach heutigem Stand nur wegen der OSM-Karte nötig.

### Noch zu klären (nicht geprüft)
- **Elementor-Pro-Kontaktformular** (`/kontaktaufnahme/`): Werden Einsendungen in der Datenbank gespeichert (inkl. IP/User-Agent)? Löschfrist? Eine separate Checkbox für die Datenschutzeinwilligung wurde nicht gefunden.
- **Eingeloggte Nutzer/Backend:** Login-Cookies nur bei Anmeldung, betrifft Besucher nicht.
- **Server-Logfiles/Hoster:** Standardthema der Datenschutzerklärung.
- Der Webserver-Typ (Apache laut Header; Live-Serverregeln unbekannt) und die PHP-Version sind offen.

---

## 6. Lazy Loading der Mitgliederbilder (Befund: funktioniert)

Gemessen auf der Live-Seite (161 Karten im HTML):

| Zeitpunkt | Geladene Bilder |
|-----------|-----------------|
| Seitenaufruf | 1–3 |
| Scroll auf 3000 px | 18 |
| Nach den ersten 50 Karten | 36 |

- Nur 25 Karten sind zunächst sichtbar (Infinite Scroll in 25er-Schritten), alle außer der ersten haben `loading="lazy"`. Scanner, die nur das HTML lesen, melden trotzdem „alle Bilder laden sofort" (Messartefakt).
- Schwächen: Alle Karten stehen im HTML und werden erst per JS ausgeblendet, nicht per CSS. Die erste Karte ist `eager`, auch wenn sie unter der Falz liegt. Der Eager-Code in `frontend.js:636` nutzt den veralteten Selektor `.bes-member-image img` (richtig: `.card-image img`). `fetchpriority` fällt durch die `wp_kses`-Liste, `width`/`height` fehlen (Layoutsprung).
- **Bildformat (offen):** Renderer und Karte erwarten nur `<id>.png` (`renderer.php:551`, `map-render.php:479`), der Sync speichert je nach Content-Type `png`, `jpg`, `webp` oder `gif`. Andere Formate würden im Frontend nicht gefunden. Prüfen, welche Dateien unter `img/` liegen.

---

## 7. Bewertung der externen Codeanalyse (Cursor)

Der Bericht ist insgesamt fundiert (Inventar, Datenflüsse, Maßnahmenplan). Kernbefunde wurden am Code nachgeprüft.

**Bestätigt:** F03 (JSON-LD), F04 (Koordinaten ungerundet und doppelt), F05/F06 (Nominatim-Fehler, falscher Feldschlüssel), F07 (Profilbild-URL ohne Host-Prüfung, Token geht mit), F08 (PHP-8-Funktionen, 52 Treffer), F09 (keine `uninstall.php`), F13 (Gäste ohne Nonce bei `bes_filter_members`), Verhalten der Kacheln (F01).

**Zu korrigieren / herabzustufen:**
- **F02 (Karten-Popups, „Hoch"):** Der beschriebene Mechanismus stimmt nicht. Ein `${…}` oder Backtick im Mitgliedertext wird nicht ausgeführt. Name, Ort und Alt-Text sind serverseitig escaped, das Popup-HTML läuft durch `wp_kses_post`. Das Muster mit `innerHTML` bleibt unschön → **Niedrig** (Defense in Depth).
- **F10 (.htaccess):** Auf Apache 2.4 ohne Kompatibilitätsmodul wäre `v3/` nicht offen, sondern liefert einen Serverfehler (fail closed). Offen wäre es nur unter Nginx. Live ist `v3/` mit 403 geschützt. Die eigentliche Lücke ist der übergeordnete Ordner (Abschnitt 2), den der Bericht nicht als öffentlich erkannt hat.
- **F06 „erklärt die Lücken":** Nein. Selbst bei korrektem Feldschlüssel würde der Fallback an HTTP 400 scheitern. Die 36 Mitglieder ohne Marker haben schlicht keine EasyVerein-Koordinaten (im Export: 34 mit Geschäftsadresse, davon 12 mit vollständiger Straße, 23 ohne Straße).
- **F08 (PHP-Anforderung):** WordPress liefert `str_contains` und Verwandte seit 5.9 nach. Meist genügt `Requires at least: 5.9`; eine Anhebung auf PHP 8.1 ist nicht zwingend. Weitere PHP-8-Syntax im Code ist nicht geprüft.
- **Nicht gegengeprüft:** F11, F14 bis F22 und die Sync-Details (F16).

Offen bleibt, **welche Adresse EasyVerein für `geoPositionCoords` verwendet** (privat oder geschäftlich). Einfacher Test: In EasyVerein ein Mitglied mit unterschiedlichem privatem und geschäftlichem Ort wählen und prüfen, wo der Pin auf der Live-Karte sitzt, oder den EasyVerein-Support fragen. Bei privater Basis läge bei diesen Mitgliedern ein hausgenauer Pin an der Privatadresse im öffentlichen HTML.

---

## 8. Maßnahmenplan

### Sofort
| Nr. | Maßnahme | Aufwand |
|-----|----------|---------|
| 1 | `sync.log` und `fields-config.json` sperren (Server), Log auf Warnungen begrenzen/rotieren | S |
| 2 | JSON-LD härten: `JSON_HEX_TAG`/`HEX_AMP`/`HEX_APOS`/`HEX_QUOT`, `script` aus der `wp_kses`-Liste, Straße und Geo nur bei Bedarf und gerundet | S |
| 3 | Koordinaten auf 3 Nachkommastellen runden (ca. 100 m, bei Bedarf 2 = ca. 1 km), doppelte Marker-JSON (`data-map-markers`) streichen | S |
| 4 | OSM-Opt-in mit `loadTiles()`, Attribution mit Link | S–M |
| 5 | Nominatim-Sync-Pfad entfernen (Variante A), private Adressfelder dort nicht mehr lesen | S |
| 6 | `Requires at least: 5.9` setzen, README-Version (4.0.0) an Header (4.0.2) angleichen | S |

### Bald
| Nr. | Maßnahme | Aufwand |
|-----|----------|---------|
| 7 | Emoji-Option im Plugin (Standard aus) und Inline-SVG-Icons in den Buttons | S–M |
| 8 | Profilbild-URL auf EasyVerein-Hosts begrenzen, Endung speichern, Renderer auf die tatsächliche Datei umstellen (Bildformat-Problem) | M |
| 9 | Umkreissuche: Eingabe auf PLZ/Ortsname begrenzen, Tagesbudget, einheitlicher User-Agent mit Kontakt; in der Datenschutzerklärung nennen | S |
| 10 | Nonce auch für Gäste prüfen, private PLZ-Schlüssel im Filter nicht mehr lesen | S |
| 11 | Lazy Loading: erste Karten per CSS zeigen, Rest mit `content-visibility`, Selektor korrigieren, Bildmaße setzen | M |
| 12 | Deinstallationsroutine (`uninstall.php`) mit Option „Daten behalten" | M |
| 13 | Token-Feld im Admin nicht mehr mit Klartextwert befüllen; Capability-Checks an den Feld-AJAX-Handlern | S |
| 14 | ICS-URLs: nur https, Host-Prüfung, Cache-Treffer nicht erneut laden | M |
| 15 | Fehlende Koordinaten klären: Warum liefert EasyVerein bei 12 vollständigen Adressen nichts? Betroffene Mitglieder ggf. anschreiben | S |

### Später
- Sync-Abfrage statt `{*}` auf die Feldauswahl verengen, 429-Mitglieder in die nächste Runde, Lock um den ganzen Batch.
- Text-Domain und Versionen vereinheitlichen, `languages/`, minimale Tests (Geocoding-URL, Nonce, Pfadprüfung), CI.
- ARIA-Attribute in der `wp_kses`-Liste ergänzen, `prefers-reduced-motion` auf die aktuellen Klassen ausweiten, `console.log` hinter ein Debug-Flag.
- Nur wenn nötig: abschaltbarer Geschäftsadress-Geocoder (Variante B) statt stillem Sync-Schritt.

---

## 9. Textbausteine für die Datenschutzerklärung (Stichpunkte)

- **OpenStreetMap-Karte:** Anbieter OpenStreetMap Foundation, St John’s Innovation Centre, Cowley Road, Cambridge CB4 0WS, UK. Übermittelt werden IP-Adresse und Browserdaten erst nach Klick auf „Karte laden". Rechtsgrundlage Art. 6 Abs. 1 lit. a DSGVO (Einwilligung), Widerruf jederzeit.
- **Umkreissuche:** Eingegebene PLZ/Ort wird zur Standortbestimmung serverseitig an Nominatim (OSM) übermittelt und 7 Tage zwischengespeichert. Die IP-Adresse des Besuchers wird nicht übertragen.
- **Mitgliederprofile:** Veröffentlichung auf Grundlage der Einwilligung der Mitglieder in EasyVerein. Adressen werden nicht an Dritte zur Geocodierung übertragen (nach Entfernung des Sync-Pfads).
- **Cookies:** Keine. Nach Abschalten des Emoji-Skripts auch kein `sessionStorage`-Eintrag.

---

## 10. Offene Entscheidungen und Fragen

1. Eigenes Opt-in (Option B) oder externer CMP? Empfehlung B, solange keine Cookies gesetzt werden.
2. Emoji-Option im Plugin: beschlossen (Standard aus); Hinweistext und Platz im Admin festlegen.
3. Nominatim im Sync: Variante A (entfernen) empfohlen; Variante B (reparieren, nur Geschäftsadresse) nur bei fachlichem Bedarf.
4. Welche Adresse nutzt EasyVerein für `geoPositionCoords`?
5. Liefert der Webserver `sync.log` und `fields-config.json` nur wegen fehlender Serverregeln aus? Welche Server-Software (Apache/Nginx) und PHP-Version laufen produktiv?
6. Welche Bildformate liegen unter `uploads/bseasy-sync/img/`?
7. Elementor-Formular: Speicherung und Löschfrist klären.
8. Sollen Mitgliederdaten bei der Deinstallation gelöscht werden oder bewusst bleiben?
