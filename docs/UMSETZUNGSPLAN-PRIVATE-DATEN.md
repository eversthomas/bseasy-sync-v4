# Umsetzungsplan: Keine privaten Daten, Pins aus der Geschäftsadresse

**Stand:** 11.11.2026 · Ergänzt [UMSETZUNGSPLAN-DATENSCHUTZ.md](UMSETZUNGSPLAN-DATENSCHUTZ.md) (Version 4.0.3) um die Folgearbeit (Version 4.0.4).
Arbeitsweise unverändert: Cursor setzt einen Schritt um, Claude prüft den Diff, Thomas testet lokal und committet. Ein Schritt = ein Auftrag = ein Commit, Branch `privacy/osm-optin` (für 4.0.4 ein neuer Branch).

## Entscheidungen (Thomas, 11.11.2026)

1. Das Plugin soll **keine privaten Mitgliederdaten** mehr verwenden oder speichern. Der Sync wird dafür **nicht umgebaut**.
2. **Pins ausschließlich aus der Geschäftsadresse**, nie aus privaten Daten und nicht aus EasyVerein-Koordinaten.
3. Mitglieder ohne Geschäftsadresse bekommen keinen Pin (nicht berücksichtigen). Mitglieder werden separat informiert.
4. Kein globaler Backend-Schalter „Privatadresse erlauben". Falls später Bedarf: Freigabe pro Mitglied über ein EasyVerein-Feld.
5. Zwei Versionen: **4.0.3** (Opt-in, Fixes, AJAX-Korrektur) und **4.0.4** (Daten minimieren, Geschäfts-Pins, Backend-Labels).

## Befunde, auf denen der Plan beruht

- **Pins stammen sehr wahrscheinlich aus der privaten Adresse:** Bei mindestens 5 von 16 Mitgliedern mit abweichendem Geschäfts- und Privatort liegt der Pin am privaten Ort. Das ist aus Ortslagen abgeleitet und noch nicht durch EasyVerein bestätigt. Auch bei gleicher Stadt kann der Pin am privaten Haus liegen (hausgenau, 7–8 Nachkommastellen).
- **Der Sync übernimmt EasyVerein-Koordinaten unbedingt** (`api-core-consent-v3.php:603-614`).
- **Der eigene Nominatim-Pfad läuft nicht:** falsche Bedingung (`needs_geocoding`, Zeile 639) und fehlerhafter Aufruf (`country=de` zusammen mit `q` → HTTP 400, `consent-geocoding.php:97`).
- **Die private Stadt wird mit synchronisiert:** Feld `contact.contact.city` ist in der Live-Konfiguration aktiv (`show=True`, Bereich „unused"). Alle ausgewählten Felder werden gespeichert.
- **Der öffentliche AJAX-Endpunkt `bes_filter_members` gibt die private Stadt zurück** und durchsucht sie (`ajax-endpoints.php:205`, `:341-342`). Bei 16 von 100 Mitgliedern weicht sie von der angezeigten ab. Die Seite selbst ruft den Endpunkt nicht auf.
- **Backend-Labels sind irreführend:** Die Zuordnung in `field-label-generator.php:55-79` nutzt `contact.city` usw., die echten IDs heißen `contact.contact.city`. Deshalb entstehen Labels aus Beispielwerten („Essen", „Deutschland").

---

## Version 4.0.3 – Nachtrag (vor dem Einspielen)

### Schritt A – AJAX-Endpunkt nutzt nur die Geschäftsadresse
- **Datei:** `frontend/ajax/ajax-endpoints.php` (ca. Zeilen 205 und 341–342).
- **Änderung:** `contact.city` → `contact.companyCity`, `contact.zip` → `contact.companyZip` (Suche und Antwort). Der Helfer `$get_field_value` löst `contact.companyCity` korrekt auf (nutzt `$member['contact']['contact.companyCity']`).
- **Prüfung durch Claude:** Diff nur an diesen Stellen; keine Änderung an Rate-Limit, Nonce, Pagination.
- **Test lokal:** Ein POST an `admin-ajax.php?action=bes_filter_members` liefert Name, Geschäftsstadt und -PLZ, keine private Stadt. Suche nach einer Geschäftsstadt findet das Mitglied, Suche nach einer rein privaten Stadt nicht mehr.
- **Commit:** „Fix: AJAX-Filter nutzt Geschäftsadresse statt privater Stadt".
- Danach: Changelog (4.0.3) um die vier Fixes ergänzen, Gesamttest, Merge, Einspielen (siehe Plan 4.0.3).

---

## Version 4.0.4

Neuer Branch von `master` nach dem Merge von 4.0.3.

### Schritt B – Positivliste im Sync (keine privaten Felder speichern)
- **Datei:** `sync/api-core-consent-v3.php`, Funktion `bseasy_v3_extract_selected_fields()` (Zeile 889) plus kleiner Helfer (z. B. `bes_is_allowed_contact_field()`).
- **Regel:** Für `contact.*`-Felder werden nur diese Schlüssel übernommen: `firstName`, `familyName`, `nameAffix`, `companyName`, `companyStreet`, `companyZip`, `companyCity`, `companyState`, `companyCountry`, `company_country_code_invoice`, `companyEmail`. Custom Fields (`cf.*`, `contactcf.*`) und `member.id`/`member.membershipNumber` bleiben. Alles andere (z. B. `city`, `zip`, `street`, `country`, private E-Mail, Telefon, Geburtstag) wird verworfen, auch wenn es im Backend ausgewählt ist.
- **Frontend-Bereinigung:** Private Schlüssel aus `bes_member_collect_zip_values()` (`geo-utils.php`, `contact.zip`, `zip`, `postalCode`) und der Fallback `contact.city` in `map-render.php:480` entfernen.
- **Test lokal:** Vollsync ausführen, danach in `members_consent_v3.json` prüfen, dass nur erlaubte Kontaktschlüssel vorkommen. Karten, Filter, Suche, Umkreissuche unverändert.
- **Hinweis:** Die API liefert die privaten Felder weiterhin an den Server (Abfrage `{*}`), sie werden aber nie gespeichert oder verwendet. Ein echtes „nicht abholen" gehört zur späteren Sync-Optimierung (Abfrage auf eine Feldliste beschränken, vgl. `docs/SYNC_PLAN.md`).

### Schritt C – Pins aus der Geschäftsadresse (Nominatim)
- **Dateien:** `sync/consent/consent-geocoding.php` (Funktion `bes_geocode_nominatim` reparieren/ersetzen), `sync/api-core-consent-v3.php` (Zeilen 603–614 und 637–649).
- **Ablauf:** Nach dem Extrahieren der erlaubten Felder wird aus `companyStreet`, `companyZip`, `companyCity` und dem Ländercode (aus `companyCountry`, Standard DE) eine **strukturierte** Nominatim-Anfrage gebaut (`street`, `postalcode`, `city`, `countrycodes`; nicht `q` zusammen mit `country`). Ergebnis → `contact.geoPositionCoords.lat/lng` (dieselben Schlüssel wie bisher, damit Frontend, Karte, JSON-LD und Umkreissuche unverändert bleiben).
- **EasyVerein-Koordinaten werden nicht mehr übernommen.** Der Block bei Zeile 603–614 entfällt bzw. wird ersetzt.
- **Cache:** Pro Adresse (Hash aus Straße, PLZ, Ort, Land) wird das Ergebnis dauerhaft gespeichert (WordPress-Option, `autoload=false`). Es wird nur bei neuer oder geänderter Adresse angefragt. Nicht gefundene Adressen werden mit Ablaufdatum gemerkt.
- **Nominatim-Regeln:** höchstens 1 Anfrage pro Sekunde, HTTP-Status prüfen (kein stilles Scheitern), identifizierbarer User-Agent mit Seiten-URL (keine private Adresse eintragen), Zeitbudget pro Sync-Lauf beachten (erster Lauf: ca. 160 Adressen ≈ 3 Minuten zusätzlich).
- **Fallbacks:** Straße nicht gefunden → nur PLZ und Ort (grober Pin, Ortsmitte). Keine Geschäftsadresse oder kein Treffer → **kein Pin**, keine private Ersatzadresse.
- **Datenschutz:** Es gehen **Geschäftsadressen** an OSM (stehen ohnehin öffentlich auf der Seite). In README und Datenschutzerklärung nennen.
- **Test lokal:** (1) Geocoder mit Testadressen (öffentliche Adressen, keine Mitgliederdaten) gegen die API. (2) Vollsync lokal: Pins der Mitglieder mit abweichendem Privatort liegen jetzt am Geschäftsort. (3) Ein zweiter Lauf fragt nichts mehr an (Cache). (4) Mitglied ohne Geschäftsadresse hat keinen Pin.
- **Wichtig beim Einspielen:** Nach dem Plugin-Update **einen Vollsync starten**, damit die alten EasyVerein-Pins in den Daten ersetzt werden.

### Schritt D – Backend-Labels und Kennzeichnung
- **Datei:** `admin/fields/includes/field-label-generator.php` (Zuordnung auf die echten IDs `contact.contact.*` umstellen), ggf. `admin/assets/ui-felder-sidebar.js` (Anzeige).
- **Namen:** „Geschäftlich: Straße / PLZ / Stadt / Land / E-Mail / Name", private Felder als „Privat: … (wird nicht übernommen)" kennzeichnen.
- **Bestehende Labels** („Essen") bleiben in der gespeicherten Konfiguration, bis sie im Backend zurückgesetzt oder beim Anzeigen überschrieben werden; im Test klären, welche Variante einfacher ist.

### Schritt E – Doku, Version
- Version 4.0.4, Changelog, README (Nominatim nennt jetzt Geschäftsadressen für Pins), `DATENSCHUTZ-OPTIMIERUNG.md`, Textbausteine für die Datenschutzerklärung.

### Schritt F – Gesamttest, Merge, Einspielen, Mitglieder informieren
- Gesamttest wie bei 4.0.3 plus: Pins, Sync-Lauf mit Cache, Umkreissuche (sie nutzt die Koordinaten), JSON-LD `geo`.
- Squash-Merge, Tag `v4.0.4`, Backup, Einspielen, **Vollsync**, Seitencache leeren.
- **Mitglieder informieren**, bevor die neuen Pins live gehen: Veröffentlicht werden Geschäftsadresse und ein Pin daraus; wer eine Privatadresse gezeigt haben möchte, trägt sie bewusst als Geschäftsadresse ein.

## Risiken und Gegenmaßnahmen

| Risiko | Gegenmaßnahme |
|--------|---------------|
| Nominatim nicht erreichbar oder drosselt | Cache, Fehlerbehandlung mit Statuscode, Abbruch ohne Datenverlust, vorhandene Pins bleiben |
| Pins verschieben sich oder verschwinden | Ankündigung an Mitglieder, Vorher/Nachher-Vergleich im lokalen Test |
| Falsche Treffer bei unvollständiger Adresse | Fallback nur auf PLZ+Ort, grobe Pins akzeptieren, Länderbeschränkung |
| Sync-Laufzeit steigt einmalig | Zeitbudget, Cache, erster Lauf bewusst als Vollsync |
| Positivliste verwirft ein benötigtes Feld | Test mit lokaler Vollkopie, Vergleich der Schlüssel vor/nach |

## Rückfall
Tag `pre-privacy-4.0.2` (Live-Stand vor 4.0.3), Tag `v4.0.3` (nach Merge) und Zip des laufenden Plugins. Der Cache der Adress-Geocodierung ist eine eigene Option und kann gelöscht werden.
