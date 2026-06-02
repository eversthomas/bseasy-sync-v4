# Backlog — BSEasy Sync V4

Priorisierte Aufgabenliste. Technische Roadmap-Details siehe `dev/ROADMAP.md`.

**Stand:** Juni 2026

---

## P0 — Vor Produktiv-Migration (Blocker)

| # | Task | Aufwand | Status |
|---|------|---------|--------|
| 1 | **Select-Feld-Fix aus Main portieren** — On-Demand-Abruf fehlender Optionen, Cache-TTL, `bseasy_v3_fetch_cf_select_option_label()` | 2–4 Std | ⬜ offen |
| 2 | Test: Mehrfach-Auswahlfeld mit vielen Optionen (Label vs. ID in JSON) | 30 Min | ⬜ offen |
| 3 | Produktiv-Migration durchführen (siehe `docs/MIGRATION.md`) | 45 Min | ⬜ offen |
| 4 | Offene Testlücken schließen: Kalender, Gast/eingeloggt, Stop/Reset/Merge, PRG | 1–2 Std | ⬜ offen |

### Select-Feld-Fix — technischer Hintergrund

In **Main** (`sync/v3-helpers.php`):

- `bseasy_v3_fetch_cf_select_option_label()` — Einzelabruf pro Option
- On-Demand in `bseasy_v3_resolve_selected_options_labels()` wenn Option nicht in Map
- `bseasy_v3_merge_cf_select_options_map_entry()` — nachträgliches Caching
- Cache-TTL (`bseasy_v3_is_cf_select_options_cache_valid`, 7 Tage)

In **V4** (`sync/v3/v3-field-options.php`):

- Fallback nur auf ID-String, kein Einzelabruf
- Cache läuft nie ab

---

## P1 — Kurzfristig (1–2 Wochen)

| # | Task | Aufwand | Modul |
|---|------|---------|-------|
| 5 | Sync G3: Fehlertoleranz pro Mitglied (Batch bricht nicht ab) | 1 Tag | `sync/` |
| 6 | Sync G1: API-Requests reduzieren (`expand`-Parameter) | 2–3 Tage | `sync/` |
| 7 | Vollständiger Settings-Export/Import im Admin (Token ausgenommen) | 4–8 Std | `admin/` |
| 8 | Vollsync mit allen ~330 Mitgliedern testen | 1 Std | — |
| 9 | Strukturelle Restarbeiten A1–A2 (Repository-Konsistenz, Sync-Service-Fassade) | 1–2 Tage | siehe ROADMAP |

---

## P2 — Mittelfristig (1–2 Monate)

| # | Task | Aufwand | Beschreibung |
|---|------|---------|--------------|
| 10 | **Mitglieder-Short-URLs** `/m/{easyverein-id}` | 4–6 Tage | Einzelprofil, Rewrite-Rules, SEO |
| 11 | **Netzwerk-Badge** (statisches HTML/CSS-Snippet) | 1–2 Tage | „Mitglied im SysTelios Transfer Netzwerk“ |
| 12 | **Geomatching Startseite** — Shortcode `[bes_members_nearby count="5"]` | 2–4 Tage | Browser-Geolocation + Fallback-Button |
| 13 | Backend-UX (ROADMAP C1–C3): Sync-Tab vereinfachen, Feld-Onboarding | 3–5 Tage | `admin/` |
| 14 | Frontend-SEO vervollständigen (ROADMAP B1–B5) | 3–5 Tage | `frontend/` |

### Feature-Notizen

**Geomatching (P2 #12):**

- Browser-Geolocation (`navigator.geolocation`) — DSGVO-Hinweis, HTTPS nötig
- Ohne Erlaubnis: Button zur Mitglieder-Unterseite
- IP-Geolocation nur mit externem Dienst — Datenschutz beachten
- Basis vorhanden: `frontend/includes/geo-utils.php`

**Short-URLs (P2 #10):**

- Grundlage für E-Mail-Signatur, LinkedIn, Badge-Link
- EasyVerein-ID bereits in Sync-Daten

**Netzwerk-Badge (P2 #11):**

- Admin generiert statisches Snippet pro Mitglied
- Optional dynamisch (iframe) — höherer Aufwand

---

## P3 — Langfristig

| # | Task | Modul | Referenz |
|---|------|-------|----------|
| 15 | Inkrementeller Sync (Delta) | `sync/` | ROADMAP G2 |
| 16 | Datenspeicher JSON → DB | `includes/data/` | ROADMAP H |
| 17 | Logging vereinheitlichen | `includes/infra/` | ROADMAP D |
| 18 | Accessibility WCAG 2.1 AA | `frontend/` | ROADMAP E |
| 19 | Performance & Caching (Partial Render, Lazy Map) | `frontend/` | ROADMAP F |
| 20 | Sicherheits-Audit | alle | ROADMAP I |
| 21 | PHPUnit / Namespaces | alle | ROADMAP J |

---

## Erledigt (Archiv)

| Task | Datum | Details |
|------|-------|---------|
| Admin-Module nur unter `is_admin()` laden | 2026-05-24 | ~1.850 LOC weniger auf Frontend-Requests |
| consent-audit nach `sync/consent/` verschoben | 2026-05-24 | Stub für Abwärtskompatibilität |
| `@`-Suppressions reduziert (38 → 7) | 2026-05-24 | Alle verbleibenden dokumentiert (Kategorie C) |
| Admin-500-Fix (Load-Order hosting → debug-log) | 2026-05-24 | `bes_write_debug_log()` crash-sicher |
| PRG-Pattern im Admin-Handler | 2026-05-24 | `bootstrap/admin-page.php` |
| Sync-JS in externe Dateien ausgelagert | 2026-05-24 | `admin/assets/sync/*.js` |
| Toter Field-Intelligence-AJAX entfernt | 2026-05-24 | Funktion war nie definiert |
| Silent Failure in `ajax-endpoints.php` behoben | 2026-05-24 | Nur noch `bes_safe_file_get_contents()` |
| MODULE_OVERVIEW.md vervollständigt | 2026-05-24 | Stubs, Views, country-filter |
