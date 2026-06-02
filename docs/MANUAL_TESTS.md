# Manuelle Tests — BSEasy Sync V4

**Stand:** Juni 2026  
**Tester:** Tom Evers  
**Plugin-Version:** 4.0.0

---

## Testumgebungen

| Umgebung | Details |
|----------|---------|
| Lokal | WordPress 7.x, verschiedene Themes |
| Staging | Subdomain bei All-inkl.com |
| API | Echter EasyVerein-Token mit echten Mitgliedsdaten |
| Datenumfang | ca. 150 von 330 Mitgliedern synchronisiert |

---

## Legende

| Symbol | Bedeutung |
|--------|-----------|
| ✅ | Getestet, funktioniert |
| ⚠️ | Getestet mit Einschränkung / unklar |
| ⬜ | Noch nicht getestet |
| ❓ | Unbekannt (nicht bewusst geprüft) |

---

## Admin — Allgemein

| # | Test | Status | Anmerkung |
|---|------|--------|-----------|
| 1 | Admin-Seite lädt ohne HTTP 500 | ✅ | Inkl. Fix nach Admin-500-Vorfall (Mai 2026) |
| 2 | Alle vier Tabs erreichbar (Sync, Felder, Kalender, Karte) | ✅ | |
| 3 | PRG: kein Doppel-Speichern nach Browser-Reload | ❓ | Nicht bewusst geprüft |
| 4 | Ungültige Eingaben (leerer Token, falsche Consent-ID, Batch außerhalb Range) | ⬜ | |

---

## Sync-Tab

| # | Test | Status | Anmerkung |
|---|------|--------|-----------|
| 5 | Explorer starten und Feldkatalog erzeugen | ✅ | |
| 6 | Feldauswahl laden und speichern | ✅ | |
| 7 | Feldauswahl auf Pflichtfelder zurücksetzen | ⬜ | |
| 8 | Vollständiger Sync-Durchlauf | ✅ | |
| 9 | Teilsync (Batch) | ✅ | |
| 10 | Sync stoppen | ⬜ | |
| 11 | Sync zurücksetzen | ⬜ | |
| 12 | Part-Dateien zusammenführen (Merge) | ⬜ | |
| 13 | Consent-Audit | ✅ | |
| 14 | Sync-Modus „nur mit Consent“ | ✅ | Standard-Setup |
| 15 | Sync-Modus „alle Mitglieder“ | ⬜ | |
| 16 | Cache-Karte (Leeren / Statistiken) | ❓ | |
| 17 | Sync läuft im Hintergrund bei geschlossener Admin-Seite | ✅ | |
| 18 | Automatische Fortsetzung (`bes_auto_continue`) | ✅ | |
| 19 | WP-Cron manuell via CLI | ⬜ | Nur über Admin-UI getestet |

---

## Felder-Tab

| # | Test | Status | Anmerkung |
|---|------|--------|-----------|
| 20 | Felder aus synchronisierten Daten laden | ✅ | |
| 21 | Drag & Drop (Sortierung above/below) | ✅ | |
| 22 | Filter pro Feld aktivieren/deaktivieren | ✅ | |
| 23 | Badge-Checkbox → Anzeige im Frontend | ✅ | |
| 24 | Speichern und Reload — Konfiguration bleibt | ✅ | |
| 25 | Config exportieren / Template importieren | ⬜ | Funktion vorhanden, manuell nicht geprüft |

---

## Kalender-Tab

| # | Test | Status | Anmerkung |
|---|------|--------|-----------|
| 26 | iCal-URL hinzufügen und speichern | ⬜ | |
| 27 | `[bes_kalender]` im Frontend | ⬜ | |

---

## Karten-Tab

| # | Test | Status | Anmerkung |
|---|------|--------|-----------|
| 28 | Center, Zoom, Stil speichern | ✅ | |
| 29 | Leaflet-Karte mit Markern und Clustering | ✅ | |
| 30 | Filter auf der Karte | ✅ | |

---

## Frontend — Shortcodes

| # | Test | Status | Anmerkung |
|---|------|--------|-----------|
| 31 | `[bes_members view="kachel"]` | ✅ | Inkl. Filter, Suche, Pagination |
| 32 | `[bes_members view="map"]` | ✅ | |
| 33 | `[bes_members view="toggle"]` | ✅ | |
| 34 | Länderfilter DE/AT/CH | ✅ | |
| 35 | Umkreissuche / Radius | ✅ | |
| 36 | Freitextsuche via AJAX | ✅ | |
| 37 | Responsive: Mobile | ✅ | |
| 38 | Responsive: Tablet | ✅ | |
| 39 | Ausgabe als Gast (nicht eingeloggt) | ⬜ | |
| 40 | Ausgabe als eingeloggter Nutzer | ⬜ | |

---

## Fehlerfälle & Lifecycle

| # | Test | Status | Anmerkung |
|---|------|--------|-----------|
| 41 | Sync ohne / mit ungültigem Token → verständliche Meldung | ✅ | |
| 42 | Leere `members.json` / vor erstem Sync → kein Fatal Error | ✅ | |
| 43 | Vollsync mit allen 330 Mitgliedern | ⬜ | Bisher nur ~150 |
| 44 | Plugin deaktivieren / reaktivieren → Daten intakt | ✅ | |

---

## Bekannte offene Prüfpunkte

### Select-Felder mit vielen Optionen

In **bseasy-sync-main** wurde ein Bug behoben: Auswahlfelder mit sehr vielen Optionen lieferten nicht alle Labels. In V4 fehlen Teile dieses Fixes (On-Demand-Abruf, Cache-TTL). **Vor Produktiv-Migration gezielt testen** — siehe `docs/BACKLOG.md` P0.

---

## Verifikations-Standard (Admin)

Admin-Tests sollten einen authentifizierten HTTP-Request gegen `wp-admin/admin.php?page=bseasy-sync` durchführen. Details siehe `MODULE_OVERVIEW.md` → Abschnitt „Verifikations-Standard für Admin-Bereich“.

---

## Test-Historie

| Datum | Änderung |
|-------|----------|
| 2026-05-24 | Task 1: Admin-Load-Order, consent-audit Pfad, Doku-Update |
| 2026-05-24 | Task 2: `@`-Suppressions reduziert (38 → 7), Admin-500-Fix |
| 2026-06 | Manuelle Tests dokumentiert (Tom Evers) |
