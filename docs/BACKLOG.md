# Backlog — BSEasy Sync V4

Priorisierte Aufgabenliste. **Operativer Umsetzungsplan inkl. Sync-Automatisierung:** [`docs/SYNC_PLAN.md`](SYNC_PLAN.md) (für neuen Chat ohne Kontext geeignet).

Technische Roadmap-Details: `dev/ROADMAP.md`

**Stand:** Juni 2026

---

## Umsetzungsreihenfolge (Kurz)

| Phase | Inhalt | Aufwand | Status |
|-------|--------|---------|--------|
| **1** | Select-Feld-Fix (Main → V4) | 0,5–1 Tag | ⬜ |
| **2** | Sync-Lab validieren (`--limit=20/50`) | ~1 Tag | ⬜ |
| **3** | Optimized-Sync + Feature-Flag (nur wenn Lab OK) | 2–3 Tage | ⬜ |
| **4** | Automatischer Sync (WP-Cron recurring) | 1–2 Tage | ⬜ |
| **5** | Migration + Vollsync 330 | 0,5–1 Tag | ⬜ |
| **6** | P2-Features (URLs, Badge, Geomatching) | siehe unten | ⬜ |

Messwerte & Entscheidungsgrundlage: [`SYNC_PLAN.md`](SYNC_PLAN.md) Abschnitt 3.

---

## P0 — Vor Produktiv-Migration (Blocker)

| # | Task | Aufwand | Status |
|---|------|---------|--------|
| 1 | **Select-Feld-Fix aus Main portieren** — On-Demand-Abruf, Cache-TTL, `bseasy_v3_fetch_cf_select_option_label()` | 0,5–1 Tag | ⬜ offen |
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

## P1 — Sync-Optimierung & Automatisierung

| # | Task | Aufwand | Modul | Details |
|---|------|---------|-------|---------|
| 5 | **Sync-Lab Phase 2** — baseline vs. optimized, 20–50 Member | ~1 Tag | `dev/sync-lab/` | [`SYNC_PLAN.md`](SYNC_PLAN.md) |
| 6 | **Optimized-Sync** — nested Query, Feature-Flag | 2–3 Tage | `sync/` | Nur nach #5 |
| 7 | **Automatischer Sync** — WP-Cron recurring + Admin-UI | 1–2 Tage | `sync/runtime/`, `admin/` | [`SYNC_PLAN.md`](SYNC_PLAN.md) Phase 4 |
| 8 | Sync G3: Fehlertoleranz pro Mitglied | 1 Tag | `sync/` | Parallel zu #6 möglich |
| 9 | Vollsync ~330 Mitglieder (Baseline + nach Optimized) | 1 Std | — | Dauer dokumentieren |
| 10 | Vollständiger Settings-Export/Import (Token ausgenommen) | 4–8 Std | `admin/` | |
| 11 | Strukturelle Restarbeiten A1–A2 | 1–2 Tage | siehe ROADMAP | |

### Automatischer Sync — Ist vs. Soll

**Bereits vorhanden:**

- Manueller Sync startet **WP-Cron** (`bes_run_consent_v3_single`)
- **Auto-Fortsetzung** (`bes_v3_auto_continue`) für Multi-Batch
- Kein **wiederkehrender** Sync (täglich/wöchentlich)

**Geplant:** Recurring Event, Admin-Einstellungen (Intervall, Lock, Fehler-Benachrichtigung), All-inkl-Fallback via System-Cron — siehe [`SYNC_PLAN.md`](SYNC_PLAN.md) Phase 4.

### Sync-Optimierung — erwarteter Nutzen (156 / 330 Member)

| Metrik | Aktuell (ca.) | Mit optimized (ca.) |
|--------|---------------|---------------------|
| 156 Member | 6:25 Min | 4:00–4:30 Min |
| 330 Member | 13–15 Min | 8–10 Min |
| API-Requests | ~3–4 / Member | ~1 / Member |

Lab-Referenz (5 Member): baseline 4,6 vs. optimized 2,6 Requests/Member.

---

## P2 — Mittelfristig (1–2 Monate)

| # | Task | Aufwand | Beschreibung |
|---|------|---------|--------------|
| 12 | **Mitglieder-Short-URLs** `/m/{easyverein-id}` | 4–6 Tage | Einzelprofil, Rewrite-Rules, SEO |
| 13 | **Netzwerk-Badge** (statisches HTML/CSS-Snippet) | 1–2 Tage | „Mitglied im SysTelios Transfer Netzwerk“ |
| 14 | **Geomatching Startseite** — `[bes_members_nearby count="5"]` | 2–4 Tage | Browser-Geolocation + Fallback |
| 15 | Backend-UX (ROADMAP C1–C3) | 3–5 Tage | `admin/` |
| 16 | Frontend-SEO vervollständigen (ROADMAP B1–B5) | 3–5 Tage | `frontend/` |

### Feature-Notizen

**Geomatching (P2 #14):** `frontend/includes/geo-utils.php` vorhanden; DSGVO/HTTPS beachten.

**Short-URLs (P2 #12):** EasyVerein-ID in Sync-Daten vorhanden.

**Netzwerk-Badge (P2 #13):** Statisches Snippet aus Admin.

---

## P3 — Langfristig

| # | Task | Modul | Referenz |
|---|------|-------|----------|
| 17 | Inkrementeller Sync (Delta) | `sync/` | ROADMAP G2 |
| 18 | Datenspeicher JSON → DB | `includes/data/` | ROADMAP H |
| 19 | Logging vereinheitlichen | `includes/infra/` | ROADMAP D |
| 20 | Accessibility WCAG 2.1 AA | `frontend/` | ROADMAP E |
| 21 | Performance & Caching | `frontend/` | ROADMAP F |
| 22 | Sicherheits-Audit | alle | ROADMAP I |
| 23 | PHPUnit / Namespaces | alle | ROADMAP J |

---

## Erledigt (Archiv)

| Task | Datum | Details |
|------|-------|---------|
| **Sync-Dauer-Anzeige** (Admin + Sync-Tab) | 2026-06 | `v3-persistence.php`, `sync-controller.js` |
| **Sync-Lab** (isoliertes CLI) | 2026-06 | `dev/sync-lab/` |
| Admin-Module nur unter `is_admin()` | 2026-05-24 | ~1.850 LOC weniger auf Frontend |
| consent-audit nach `sync/consent/` | 2026-05-24 | |
| `@`-Suppressions reduziert | 2026-05-24 | |
| Admin-500-Fix (Load-Order) | 2026-05-24 | |
| PRG-Pattern Admin | 2026-05-24 | |
| Sync-JS ausgelagert | 2026-05-24 | `admin/assets/sync/*.js` |
| MODULE_OVERVIEW.md | 2026-05-24 | |
