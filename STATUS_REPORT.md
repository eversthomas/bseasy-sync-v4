# BSEasy Sync — Status-Report (Stand: 2026-05-24)

---

## 1. Architektur-Snapshot

```
bseasy-sync-v4/
├── bseasy-sync.php            Haupt-Einstieg (Plugin-Header, require bootstrap/load.php)
├── bootstrap/                 Plugin-Init, Ladereihenfolge, Lifecycle (7 Dateien, ~900 LOC)
│   ├── load.php               Einziger Einstiegspunkt; lädt alle Module
│   ├── admin-page.php         PRG-Handler: POST → Transient → Redirect → GET
│   ├── admin-menu.php         WP-Menü-Registrierung
│   ├── admin-assets.php       CSS/JS Enqueue
│   ├── debug-log.php          Fehler-Handler + Debug-Log-Writer
│   ├── legacy-data-paths.php  V2-Pfad-Fallback
│   └── plugin-lifecycle.php   Activate / Deactivate / Uninstall
│
├── includes/                  Infrastruktur, immer geladen (13 Dateien, ~1 100 LOC)
│   ├── constants.php          Stub → includes/constants/constants.php
│   ├── constants-v3.php       V3-Konstanten (BES_DATA_V3, BES_V3_MEMBERS_PART_PREFIX, …)
│   ├── cache-utils.php        Stub → includes/infra/cache/cache-utils.php
│   ├── error-handler.php      Stub → includes/infra/errors/error-handler-class.php
│   ├── filters.php            Stub → includes/infra/filters/filters.php
│   ├── hosting-compatibility.php Stub → includes/infra/hosting/…
│   ├── api-error-user-hints.php  API-Fehler-Anzeige im Admin
│   ├── data/member-repository.php  Einziger Lesezugriff auf members_consent_v3.json
│   ├── design/design-settings.php  Design-Einstellungen (Admin + Frontend)
│   └── infra/
│       ├── cache/cache-utils.php       Transient-Cache, wpdb-Statistiken
│       ├── errors/error-handler-class.php  BES_Error_Handler-Klasse
│       ├── filters/filters.php         Developer-API-Filter
│       ├── hosting/hosting-compatibility.php  Host-Erkennung, safe-timeouts
│       ├── logging/debug-log.php       bes_debug_log()
│       └── security/
│           ├── token-crypto.php        AES-256-CBC + HMAC-SHA256
│           └── safe-io.php             Path-Traversal-Schutz, Rate-Limiting
│
├── sync/                      EasyVerein-API, Sync-Engine (22 Dateien, ~4 400 LOC)
│   ├── sync-service.php       Öffentliche Fassade (einziger erlaubter Einstieg von außen)
│   ├── api-core-consent-v3.php  Haupt-Sync-Engine (1 081 LOC)
│   ├── api-explorer-v3.php    Feld-Discovery (782 LOC)
│   ├── v3-consent-audit.php   Consent-Audit (377 LOC) — liegt im Root statt in consent/
│   ├── api-core-consent.php   Loader für sync/consent/ (32 LOC, veralterter Kommentar-Header)
│   ├── v3-helpers.php         Loader für v3/ (Logging, Persistenz, Feldoptionen)
│   ├── [3 Stubs]              api-core-consent-member-fetch/requests/token.php → client/
│   ├── cron-v3.php            Stub → runtime/cron-v3.php
│   ├── client/                HTTP-Transport-Schicht (3 Dateien)
│   ├── consent/               Consent-Extraktion, Geocoding, Logging (8 Dateien)
│   ├── runtime/               WP-Cron-Hooks (cron-v3.php, 364 LOC)
│   ├── v3/                    Persistenz, Logging, Feldoptionen (3 Dateien)
│   └── _backup-v2/            Leeres Verzeichnis (Artefakt)
│
├── admin/                     Admin-UI, AJAX, Feldkonfiguration (12 Dateien, ~2 600 LOC)
│   ├── ajax/
│   │   ├── ajax-v3.php        13 Sync-AJAX-Actions (594 LOC)
│   │   ├── ajax-cache.php     Cache-AJAX (wp_ajax_bes_clear_cache, bes_cache_stats)
│   │   └── ajax-debug.php     Debug-Log-AJAX
│   ├── calendar-handler.php   Kalender-Speichern (admin_post)
│   ├── fields/                Feldkonfiguration (3 Dateien + 1 Stub)
│   └── views/
│       ├── ui-main.php / ui-felder.php / ui-kalender.php / ui-map.php
│       ├── ui-sync.php        Sync-Tab Layout (1 242 LOC — JS eingebettet)
│       └── partials/          5 Partials für Sync-Tab
│
└── frontend/                  Shortcode, Rendering, AJAX (15 Dateien, ~2 300 LOC)
    ├── [4 Stubs]              renderer/shortcode/map-render/ajax-endpoints/calendar-render/filter-helpers → views/ / shortcode/ / ajax/ / includes/
    ├── ajax/ajax-endpoints.php    Filter-AJAX (370 LOC)
    ├── shortcode/bes-members.php  [bes_members]-Shortcode + Asset-Enqueue (291 LOC)
    ├── includes/
    │   ├── design-bridge.php
    │   ├── filter-helpers.php (325 LOC)
    │   ├── country-filter-normalize.php (286 LOC) — nicht in MODULE_OVERVIEW.md
    │   ├── geo-utils.php (440 LOC)
    │   ├── schema.php / og-meta.php
    └── views/
        ├── renderer.php (713 LOC)
        ├── map-render.php (669 LOC)
        └── calendar-render.php (179 LOC)
```

**Gesamtgröße:** ~16 870 LOC PHP (ohne Assets/Vendor)

---

## 2. Abweichungen zu MODULE_OVERVIEW.md

| # | Datei | Befund |
|---|-------|--------|
| A | `admin/views/ui-main.php`, `ui-felder.php`, `ui-kalender.php`, `ui-map.php` | Nicht in MODULE_OVERVIEW.md aufgeführt. Vorhanden und aktiv (Load: über admin-page.php). |
| B | `frontend/includes/country-filter-normalize.php` (286 LOC) | Nicht in MODULE_OVERVIEW.md. Lädt im Frontend-Stack via filter-helpers. |
| C | `sync/consent/consent-bootstrap.php` | Nicht in MODULE_OVERVIEW.md. Wird von `sync/api-core-consent.php` geladen. |
| D | `sync/v3-consent-audit.php` (377 LOC) | MODULE_OVERVIEW.md beschreibt die Funktionen (`bseasy_v3_audit_consent` etc.), platziert sie aber konzeptionell im consent-Modul — die Datei liegt im sync-Root statt in `sync/consent/`. |
| E | `sync/api-core-consent.php` | Kommentar-Header "Easy2Transfer Consent-Dump (v3.0 – 2025-11-14)" überholt. Datei ist heute ein Loader für `sync/consent/`, MODULE_OVERVIEW.md beschreibt sie nicht explizit. |
| F | Stub-Schicht `frontend/*.php` (renderer, shortcode, map-render, ajax-endpoints, calendar-render, filter-helpers) | In MODULE_OVERVIEW.md nicht als Stubs markiert — Leser nimmt an, die Dateien enthielten die Implementierung. |
| G | `frontend/ajax-endpoints.php` und `frontend/ajax/ajax-endpoints.php` | `load.php:138` lädt den Root-Stub `frontend/ajax-endpoints.php`, welcher auf `frontend/ajax/ajax-endpoints.php` weiterleitet. MODULE_OVERVIEW.md nennt nur `frontend/ajax/ajax-endpoints.php`. Konsistent. |

---

## 3. Fortschritt offene Checkliste

| # | Punkt | Status | Beleg |
|---|-------|--------|-------|
| 1 | `BES_PATH` entfernt | ✅ erledigt | `grep -rn "BES_PATH"` → kein Treffer |
| 2 | `@`-Error-Suppression (28 Stellen) | ✅ erledigt (Task 2) | **7 verbleibende** Stellen (alle Kategorie C, kommentiert) — war 36 vor Task 2 |
| 3 | Silent Base64-Fallback WARN-Logging | ✅ erledigt | `token-crypto.php:32,39,49,58,67` — alle 5 Fallback-Zweige loggen `bes_debug_log(..., 'WARN', 'security')` |
| 4 | PRG-Pattern im Admin-Handler | ✅ erledigt | `admin-page.php:120-121` — `set_transient('settings_errors', ...)` + `wp_safe_redirect(...)` |
| 5 | `calendar-handler.php` Lade-Reihenfolge | ✅ erledigt | `load.php:96` `is_admin()` Block öffnet, `load.php:106-107` calendar-handler darin |
| 6 | Hardcoded Part-Filenames | ✅ erledigt | `includes/constants-v3.php:48-49` — `BES_V3_MEMBERS_PART_PREFIX = 'members_consent_v3_part'` |
| 7 | Splitting `ui-sync.php` | 🟡 teilweise | Alle 5 Partials existieren (`sync-data-loader.php`, `sync-tab-cache-card.php`, `sync-onboarding-hint.php`, `sync-tab-explorer.php`, `sync-tab-sync.php`). `ui-sync.php` ist aber noch **1 242 Zeilen** — ~900 davon sind eingebettetes JavaScript |

**Detail zu #2 — `@`-Suppressions (echte, keine PHPDoc):**

| Datei | Anzahl | Betrifft |
|-------|--------|---------|
| `sync/v3/v3-field-options.php` | 6 | mkdir×2, chmod×2, file_put_contents×2 |
| `sync/v3/v3-logging.php` | 4 | chmod×2, mkdir×2, file_put_contents×2 |
| `includes/infra/hosting/hosting-compatibility.php` | 5 | set_time_limit, ini_set, chmod×2, file_put_contents |
| `admin/fields/fields-handler.php` | 4 | file_put_contents, rename, unlink×2 |
| `bootstrap/debug-log.php` | 4 | wp_mkdir_p, mkdir, chmod, file_put_contents |
| `sync/consent/consent-logging-debug.php` | 3 | mkdir, chmod, file_put_contents |
| `sync/runtime/cron-v3.php` | 3 | set_time_limit×3 |
| `admin/ajax/ajax-debug.php` | 2 | chmod, file_put_contents |
| `includes/infra/security/safe-io.php` | 1 | file_get_contents |
| `frontend/ajax/ajax-endpoints.php` | 1 | file_get_contents (s. Auffälligkeiten) |
| `bootstrap/legacy-data-paths.php` | 1 | file_get_contents |
| `sync/sync-service.php` | 1 | unlink |
| `sync/api-core-consent-v3.php` | 1 | set_time_limit |
| **Gesamt** | **36** | |

---

## 4. Neue Auffälligkeiten

### Kritisch

*Keine SQL-Injection, XSS, unsanitisierten Eingaben oder ungeprepared-SQL gefunden.*  
Alle `$wpdb`-Queries nutzen `$wpdb->prepare()` (`cache-utils.php:23-28`, `consent-select-options.php:138-143`).  
Keine ungeguardeten `echo $var` in View-Dateien gefunden.  
Keine direkten `$_GET`/`$_POST` ohne Sanitize/Nonce gefunden.

### Hoch

**H1 — Admin-Module werden auf jedem Frontend-Request geladen** ✅ *behoben in Task 1 (2026-05-24)*  
`bootstrap/load.php` lädt Admin-Module nur noch unter `if (is_admin())` (Felder, Cache/Debug-AJAX, Assets). Sync/Cron bleiben im Block `is_admin() || wp_doing_cron()`.

**H2 — `@`-Suppression gewachsen (36 statt 28)** ✅ *behoben in Task 2 (2026-05-24)*  
38 Stellen analysiert; 31 entfernt/refaktoriert; **7 legitim** (Tmp-Cleanup, Safe-Wrapper-Kern) mit Inline-Kommentar.

**H3 — `ui-sync.php` enthält ~900 Zeilen JavaScript**  
Partials wurden extrahiert (HTML), aber das gesamte JS (Explorer-Polling, Sync-Steuerung, Feldauswahl) ist als `<script>`-Block in `ui-sync.php` eingebettet. Das macht Browser-Caching, Code-Review und Linting unmöglich.

### Mittel

**M1 — Silent Failure in `frontend/ajax/ajax-endpoints.php:96`** ✅ *behoben in Task 2 (2026-05-24)*  
Fallback mit `@file_get_contents` entfernt; nur noch `bes_safe_file_get_contents()`.

**M2 — `sync/v3-consent-audit.php` im falschen Verzeichnis** ✅ *behoben in Task 1 (2026-05-24)*  
Implementierung liegt in `sync/consent/consent-audit.php`; Stub `sync/v3-consent-audit.php` delegiert dorthin. `sync-service.php` lädt den neuen Pfad.

**M3 — `sync/api-core-consent.php` — veralteter Header**  
Zeile 4: `"Easy2Transfer Consent-Dump (v3.0 – mit Select-Options & Enhanced Debugging) 2025-11-14"`.  
Die Datei ist heute ein reiner Loader für `sync/consent/`, der Kommentar spiegelt das nicht wider und kann Verwirrung stiften.

**M4 — MODULE_OVERVIEW.md unvollständig** ✅ *behoben in Task 1 (2026-05-24)*  
Ergänzt: Admin-Views, `country-filter-normalize.php`, `consent-bootstrap.php`, Stub-Layer, Lade-Reihenfolge, `consent-audit.php`.

**M5 — Dateien über 800 LOC (Splitting-Kandidaten)**  
| Datei | LOC |
|-------|-----|
| `admin/views/ui-sync.php` | 1 242 |
| `sync/api-core-consent-v3.php` | 1 081 |
| `admin/fields/fields-handler.php` | 912 |
| `sync/api-explorer-v3.php` | 782 |
| `frontend/views/renderer.php` | 713 |
| `frontend/views/map-render.php` | 669 |
| `sync/client/api-core-consent-requests.php` | 635 |
| `admin/ajax/ajax-v3.php` | 594 |

### Niedrig

**N1 — `sync/_backup-v2/` leeres Verzeichnis**  
Dateipfad existiert, Inhalt: leer. Kann entfernt werden.

**N2 — `dev/card-template-new.html` nicht in `.gitignore`**  
Dev-Artefakt wird versioniert. Nicht produktionsrelevant.

**N3 — Leaflet lokal gebundled, Version nicht verifizierbar**  
`frontend/assets/libs/leaflet/leaflet.min.js` (Datei-Timestamp: 2025-01-15) und `leaflet.markercluster.min.js` sind lokal vorhanden. Die Versionsnummer ist im minifizierten Bundle nicht lesbar. Aktuell: Leaflet 1.9.4 (Nov 2023), MarkerCluster 1.5.3. Stand Januar 2025 war 1.9.4 aktuell — wahrscheinlich kein Update nötig.

**N4 — Keine TODO/FIXME/XXX Marker**  
`grep -rn "TODO|FIXME|XXX" --include="*.php"` → kein Treffer. Positiv, aber ggf. werden Baustellen per Kommentar statt Marker dokumentiert.

**N5 — `sync/api-core-consent-v3.php:132` — `@set_time_limit(0)` ohne Log**  
Unterschied zu `sync/runtime/cron-v3.php`: im Cron wird `bes_safe_set_time_limit()` aus `hosting-compatibility.php` verwendet, in der Engine direkt `@set_time_limit(0)`. Inkonsistenz.

---

## 5. Empfohlene nächste Schritte für Cursor AI

### Task 1 — Admin-Module in `is_admin()`-Block verschieben (Hoch, ~30 min)
**Datei:** `bootstrap/load.php`  
`bootstrap/admin-page.php` (Zeile 150), `bootstrap/admin-menu.php` (Zeile 151), `admin/fields/fields-handler.php` (Zeile 158–165), `admin/ajax/ajax-cache.php` (Zeile 176–178), `admin/ajax/ajax-debug.php` (Zeile 179–181), `bootstrap/admin-assets.php` (Zeile 183) in den bestehenden `if (is_admin() || wp_doing_cron())` Block (Zeile 96–109) verschieben oder mit einem neuen `if (is_admin())` Block ohne `wp_doing_cron()` wrappen (Cron braucht keine Views/Assets). Danach testen: Admin-Seite lädt, Frontend-Shortcode lädt, Cron-Sync läuft.

### Task 2 — JS aus `ui-sync.php` in externe Datei auslagern (Hoch, ~1–2 h)
**Datei:** `admin/views/ui-sync.php`  
Den `<script>`-Block (ca. Zeile 300–1200) in `admin/assets/sync.js` extrahieren. In `bootstrap/admin-assets.php` per `wp_enqueue_script('bes-sync-admin', BES_URL . 'admin/assets/sync.js', ['jquery'], BES_VERSION, true)` laden. `wp_localize_script` für PHP-Variablen nutzen. Ziel: `ui-sync.php` unter 350 Zeilen.

### Task 3 — Silent Failure in `ajax-endpoints.php` beseitigen (Mittel, ~15 min)
**Datei:** `frontend/ajax/ajax-endpoints.php:96`  
`@file_get_contents($config_file)` ersetzen durch `bes_safe_file_get_contents($config_file, BES_DATA)` aus `includes/infra/security/safe-io.php`. Schlägt das fehl, `bes_debug_log('fields-config.json nicht lesbar: ' . $config_file, 'WARN', 'ajax')` schreiben und mit leerem Config-Array fortfahren statt still zu scheitern.

### Task 4 — `sync/v3-consent-audit.php` nach `sync/consent/` verschieben (Niedrig, ~20 min)
**Datei:** `sync/v3-consent-audit.php` → `sync/consent/consent-audit.php`  
Stub `sync/v3-consent-audit.php` (10 Zeilen, wie die anderen Stubs) zurücklassen, der auf neue Position zeigt. `sync/sync-service.php:45-46` auf neuen Pfad anpassen. MODULE_OVERVIEW.md aktualisieren.

### Task 5 — `@`-Suppressions reduzieren (Mittel, ~1 h)
Priorität: `sync/v3/v3-field-options.php` (6 Stellen) und `sync/v3/v3-logging.php` (4 Stellen).  
Pattern: `@mkdir(...)` → `bes_ensure_writable_directory()` aus `hosting-compatibility.php` nutzen (diese Funktion existiert bereits und loggt korrekt). `@chmod(...)` → ebenfalls in `bes_ensure_writable_directory()` enthalten. `@file_put_contents(...)` → `bes_safe_file_put_contents()` aus `hosting-compatibility.php`.

### Task 6 — MODULE_OVERVIEW.md vervollständigen (Niedrig, ~30 min)
Fehlende Einträge ergänzen: `admin/views/ui-main.php`, `ui-felder.php`, `ui-kalender.php`, `ui-map.php`; `frontend/includes/country-filter-normalize.php`; `sync/consent/consent-bootstrap.php`. Stub-Layer in frontend/ und sync/ explizit als solche markieren.

### Task 7 — Aufräumen (Niedrig, ~10 min)
- `sync/_backup-v2/` Verzeichnis löschen (`rmdir`)
- `dev/card-template-new.html` in `.gitignore` aufnehmen oder löschen
- Kommentar-Header in `sync/api-core-consent.php:4-15` auf aktuellen Stand bringen ("Loader für sync/consent/")

---

## 6. Offene Fragen an Tom

1. **Load-Reihenfolge Admin-Module (Task 1):** Gibt es absichtlich Admin-Funktionen, die auch auf Frontend-Requests verfügbar sein müssen? (z. B. `bes_load_fields_config()` aus `fields-handler.php` im Shortcode?) Das würde die Migration komplizieren und müsste in `includes/` verschoben werden.

2. **`sync/v3-consent-audit.php` — Stub oder direkter Pfad?** Soll die Datei nach `sync/consent/` verschoben werden (Task 4), oder bleibt der Sync-Root bewusst der Ort für "direkt nutzbare" Audit-Dateien (neben dem consent/-Modul)?

3. **`admin/views/ui-sync.php` JS-Extraktion (Task 2):** Das eingebettete JS verwendet PHP-Variablen direkt (z. B. `<?php echo esc_js($token_status); ?>`). Diese müssen via `wp_localize_script()` übergeben werden — gibt es Variablen, die aus Sicherheitsgründen nicht ins Frontend-JS übergeben werden sollten?

4. **Leaflet-Version:** Soll Leaflet auf die aktuelle Version geprüft/aktualisiert werden, oder ist die gebundelte Version (Stand Jan 2025) als "eingefroren" zu behandeln?

5. **`frontend/includes/country-filter-normalize.php`:** ✅ Beantwortet in Task 1 — aktiv genutzt für Länderfilter (DE/AT/CH), eingebunden via `filter-helpers.php`.

---

## Task 1 erledigt (Stand: 2026-05-24)

### Änderungen
- `bootstrap/load.php`: Admin-Module nur noch unter `is_admin()`-Guard (~1.850 LOC Parse-Overhead auf Frontend entfernt)
- `sync/v3-consent-audit.php` → `sync/consent/consent-audit.php` (mit Compatibility-Stub)
- `MODULE_OVERVIEW.md`: vollständig aktualisiert (Lade-Reihenfolge, Stubs, Views, country-filter, consent-bootstrap)
- `country-filter-normalize.php` in Doku aufgenommen

### Beantwortete offene Fragen
- **A) Admin-Code im Frontend:** 0 Treffer — saubere Trennung ohne includes/-Migration möglich
- **B) consent-audit Pfad:** nach `sync/consent/` verschoben (war historisch im Sync-Root seit Initial Commit)
- **C) country-filter-normalize.php:** aktiv genutzt für Länderfilter DE/AT/CH

### Aktualisierter Stand offene Checkliste
| # | Punkt | Status nach Task 1 |
|---|-------|-------------------|
| 1 | `BES_PATH` entfernt | ✅ unverändert erledigt |
| 2 | `@`-Error-Suppression (28 Stellen) | 🟡 unverändert (36 Stellen) |
| 3 | Silent Base64-Fallback WARN-Logging | ✅ unverändert erledigt |
| 4 | PRG-Pattern im Admin-Handler | ✅ unverändert erledigt |
| 5 | `calendar-handler.php` Lade-Reihenfolge | ✅ unverändert erledigt |
| 6 | Hardcoded Part-Filenames | ✅ unverändert erledigt |
| 7 | Splitting `ui-sync.php` | 🟡 unverändert teilweise (~900 Zeilen JS eingebettet) |

**Neu erledigt durch Task 1:**
- Admin-Load-Order (ehem. H1 / Task 1 in Abschnitt 5)
- consent-audit Pfad (ehem. M2 / Task 4)
- MODULE_OVERVIEW-Vollständigkeit (ehem. M4 / Task 6)

### Verifikation (automatisiert)
- [x] `grep`: Keine Admin-Funktionsaufrufe aus `frontend/` (0 Treffer außer Doku)
- [x] PHP-Syntax: `bootstrap/load.php`, `sync/sync-service.php`, `sync/v3-consent-audit.php`, `sync/consent/consent-audit.php` — `php -l` ohne Fehler
- [ ] Frontend-Shortcode `[bes_members]` ohne PHP-Notices — manuell in WP prüfen
- [ ] Admin-Seite: alle 4 Tabs (Sync, Felder, Kalender, Map) — manuell prüfen
- [ ] Cron-Sync / Audit: `wp cron event run bes_run_audit_consent_v3` — manuell prüfen
- [ ] Karten-Rendering: Leaflet + Cluster — manuell prüfen
- [ ] Länderfilter DE/AT/CH — manuell prüfen

### Cleanup-Backlog

**Erledigt (2026-05-24):**
- ✅ `bes_analyze_field_intelligence()` / `wp_ajax_bes_get_field_intelligence` — toter AJAX-Handler entfernt (`admin/fields/fields-handler.php`); Funktion war nie definiert, kein JS/UI-Bezug
- ✅ `bes_load_json_versioned()` — ungenutzte Legacy-Funktion entfernt (`bootstrap/legacy-data-paths.php`); `bes_get_data_dir()` bleibt (wird von Sync/Explorer genutzt)

**Offen:**
- Leaflet **1.9.4** / MarkerCluster **1.5.1** als Baseline dokumentiert (Stand Mai 2026: aktuelle Versionen prüfen vor Update)

---

## Task 2 erledigt (Stand: 2026-05-24)

### Änderungen
- 38 `@`-Suppressions analysiert und klassifiziert (`cursor-task2-analyse.md`)
- 14 Anti-Pattern-Stellen (Kategorie A): explizite Prüfung + `bes_debug_log`
- 17 Stellen (Kategorie B): konsequent auf `bes_safe_*`-Wrapper umgestellt
- 7 Stellen (Kategorie C): bleiben mit Inline-Kommentar (Legitim)
- M1-Silent-Failure in `frontend/ajax/ajax-endpoints.php` behoben
- `@set_time_limit`-Fallbacks: tote `else`-Zweige entfernt (4 Stellen)

### Aktualisierter Stand offene Checkliste
- Punkt 2 (@-Suppressions reduzieren): ✅ von 28→36→**7** (alle verbleibenden begründet)

### Verbleibende Suppressions (7, alle Kategorie C)

| # | Datei:Zeile | Funktion | Begründung |
|---|-------------|----------|------------|
| 1 | `admin/fields/fields-handler.php:122` | unlink | Tmp-Cleanup nach fehlgeschlagenem Write |
| 2 | `admin/fields/fields-handler.php:136` | unlink | Tmp-Cleanup nach fehlgeschlagenem rename |
| 3 | `sync/sync-service.php:121` | unlink | Sync-Reset nach `file_exists()` |
| 4 | `includes/infra/hosting/hosting-compatibility.php:294` | chmod | Best-Effort in `bes_ensure_writable_directory` |
| 5 | `includes/infra/hosting/hosting-compatibility.php:324` | chmod | Best-Effort in `bes_ensure_writable_file` |
| 6 | `includes/infra/hosting/hosting-compatibility.php:354` | file_put_contents | Retry in `bes_safe_file_put_contents` |
| 7 | `includes/infra/security/safe-io.php:86` | file_get_contents | Nach Path-Check in `bes_safe_file_get_contents` |

### Verifikation (automatisiert)
- [x] `grep` File-IO-`@`: **7 Treffer** (siehe Liste oben)
- [x] `php -l` auf alle geänderten Dateien — ohne Syntaxfehler
- [ ] Frontend-Shortcode, Admin-Tabs, Cron — manuell (analog Task 1, vor Push)

---

## Admin-500-Vorfall (Stand: 2026-05-24)

### Was passierte
Task 2 / Commit b9196b7 ersetzte `@wp_mkdir_p` + `@file_put_contents` in
`bes_write_debug_log()` durch `bes_ensure_writable_directory()` und
`bes_safe_file_put_contents()`. Damit wurde eine versteckte Load-Order-
Abhängigkeit aktiv: Der Admin-Error-Handler ruft das Logging
unmittelbar nach Registrierung auf, aber `hosting-compatibility.php`
wurde erst danach geladen → Fatal in jedem Admin-Request.

### Wie es entdeckt wurde
Verifikation von Task 2 mit echtem authentifiziertem curl
(`wordpress_logged_in_*`-Cookie) — Markup-Tests aus Task 1 hätten
es nicht aufgedeckt.

### Wie es behoben wurde
1. `hosting-compatibility.php` in `load.php` vor `debug-log.php` verschoben
2. Defense-in-Depth in `bes_write_debug_log()`: `function_exists()`-Prüfung
   + `error_log()`-Fallback bei nicht geladenem Hosting

### Was wir gelernt haben
- `@`-Suppressions können latente Bootstrap-Bugs verstecken.
  Ihre Eliminierung deckt versteckte Abhängigkeiten auf — das ist
  ein Feature, kein Bug der Suppression-Eliminierung.
- Verifikation per HTML-Markup reicht nicht. Admin-Tests brauchen
  echten authentifizierten HTTP-Request mit Response-Status-Check.
- Logging-Code muss crash-sicher sein, auch gegen Load-Order-Fehler.
