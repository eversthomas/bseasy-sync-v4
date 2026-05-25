# Admin HTTP 500 — Root-Cause-Analyse

**Datum:** 2026-05-24  
**Anlass:** Verifikation von Task 2 deckte HTTP 500 auf `wp-admin/admin.php?page=bseasy-sync` auf  
**Methode:** Code-Review, `git log -S`, minimaler Bootstrap-Repro, historische Commit-Tests (Worktrees)

---

## 1. Konkreter Bug

### Symptom (Log-Auszug)

```
PHP Fatal error: Call to undefined function bes_ensure_writable_directory()
  in bootstrap/debug-log.php:42
  ← bes_write_debug_log() in bes_bootstrap_register_admin_error_handlers():164
  ← bootstrap/load.php:54
  ← bseasy-sync.php:118 → wp-settings.php → wp-admin/admin.php
```

### Was `bes_bootstrap_register_admin_error_handlers()` tut

Die Funktion in `bootstrap/debug-log.php:150–167` macht **beides** — Registrierung **und** sofortiges Logging:

```150:167:bootstrap/debug-log.php
function bes_bootstrap_register_admin_error_handlers() {
    if (!defined('BES_ERROR_HANDLER_REGISTERED')) {
        if (is_admin() && !wp_doing_ajax() && function_exists('bes_write_debug_log')) {
            set_error_handler('bes_wp_error_handler', E_ALL);
            register_shutdown_function('bes_wp_shutdown_handler');
            define('BES_ERROR_HANDLER_REGISTERED', true);

            // Logge dass Error Handler registriert wurde
            bes_write_debug_log("WordPress Error Handler registriert", "INFO", "bseasy-sync.php");
        }
    }
}
```

| Schritt | Zeile | Verhalten |
|---|---|---|
| 1 | 155 | `set_error_handler('bes_wp_error_handler')` — Handler ist ab hier aktiv |
| 2 | 158 | `register_shutdown_function('bes_wp_shutdown_handler')` |
| 3 | 161 | `BES_ERROR_HANDLER_REGISTERED` setzen |
| 4 | **164** | **`bes_write_debug_log(...)` — synchron, nicht „später“** |

**Antwort auf die Hauptfrage:** Der Fatal entsteht primär durch **Schritt 4 (Symptom = Trigger)**. Es ist kein zufälliger PHP-Notice, der den Handler auslöst — die Registrierungsfunktion schreibt selbst unmittelbar ins Log.

### Warum Zeile 42 scheitert

`bes_write_debug_log()` (Task 2) delegiert an Infrastruktur, die zu diesem Zeitpunkt noch nicht geladen ist:

```40:57:bootstrap/debug-log.php
    $debug_dir = trailingslashit($plugin_dir) . 'debug/';

    if (!bes_ensure_writable_directory($debug_dir)) {
        ...
    }
    ...
    $result = bes_safe_file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
```

`bes_ensure_writable_directory()` und `bes_safe_file_put_contents()` liegen in `includes/infra/hosting/hosting-compatibility.php`, eingebunden über `includes/hosting-compatibility.php` — erst in `bootstrap/load.php:61–63`, **nach** Zeile 54.

### Aufruf-Stack (rekonstruiert)

```
wp-admin/admin.php          → WP_ADMIN=true, wp-load.php
  wp-settings.php           → Plugin laden
    bseasy-sync.php:118     → require bootstrap/load.php
      load.php:53           → require bootstrap/debug-log.php
      load.php:54           → bes_bootstrap_register_admin_error_handlers()
        debug-log.php:164   → bes_write_debug_log("WordPress Error Handler registriert", ...)
          debug-log.php:42  → bes_ensure_writable_directory($debug_dir)  ← UNDEFINED → Fatal
```

Shutdown-Handler (Zeile 134) versucht erneut `bes_write_debug_log()` → zweiter Fatal (im Log sichtbar).

### Sekundäres Risiko (Handler-Fenster Zeile 55–60)

Ab Zeile 155 ist `bes_wp_error_handler` aktiv, **bevor** `hosting-compatibility.php` geladen ist. Jede PHP-Warning/Notice zwischen `load.php:55` und `:63` würde `bes_wp_error_handler` → `bes_write_debug_log()` → denselben Fatal auslösen — auch wenn Zeile 164 entfernt würde.

---

## 2. Bug seit wann?

### Git-Belege

| Ereignis | Commit | Datum (Author) |
|---|---|---|
| `bes_bootstrap_register_admin_error_handlers()` + sofortiges Log Zeile 164 | `f3ac9f7` „Modularisierung abgeschlossen“ | 2026-03-24 |
| Load-Order: Handler-Registrierung **vor** `hosting-compatibility.php` | `f3ac9f7` (unverändert bis HEAD) | — |
| `bes_ensure_writable_directory()` eingeführt | `f3ac9f7` / `07d7f03` | — |
| **`bes_write_debug_log()` nutzt `bes_ensure_writable_directory()`** | **`b9196b7`** „Suppressions Kategorie B“ | Task 2 |
| Task 1 Admin-Guard (`is_admin()`) | `b576a67` | Task 1 — **ohne** Änderung an `debug-log.php` |

```bash
git log --oneline -S 'bes_ensure_writable_directory' -- bootstrap/debug-log.php
# b9196b7 Suppressions Kategorie B: bes_safe_*-Wrapper konsequent nutzen

git diff b576a67 b9196b7 -- bootstrap/debug-log.php
# Ersetzt inline @wp_mkdir_p/@mkdir/@chmod/@file_put_contents
# durch bes_ensure_writable_directory + bes_safe_file_put_contents
```

### Repro-Tests (normaler Plugin-Pfad)

Minimaler Test mit `BES_DIR = wp-content/plugins/bseasy-sync-v4/` (Pfad innerhalb `WP_PLUGIN_DIR`, wie Produktion):

| Version | `debug-log.php` | Ergebnis bei `bes_bootstrap_register_admin_error_handlers()` |
|---|---|---|
| `b576a67` (nach Task 1, vor Task 2) | inline mkdir/chmod/file_put_contents | **SURVIVED** |
| `b9196b7` (Task 2 Kategorie B) | `bes_ensure_writable_directory` | **FAIL: undefined function** |
| HEAD (master) | identisch zu `b9196b7` | **FAIL: undefined function** |

Vollständiger WP-Bootstrap (`define('WP_ADMIN', true); require wp-load.php`) auf HEAD: HTTP-kritischer Fehler / Fatal im Log.

### Zeitleiste

| Phase | Admin-Status | Erklärung |
|---|---|---|
| Vor `f3ac9f7` | — | Kein `bes_bootstrap_register_admin_error_handlers` in dieser Form |
| `f3ac9f7` … `b576a67` | **Funktional** | Sofort-Log nutzte self-contained I/O ohne Hosting-Abhängigkeit |
| **`b9196b7` … HEAD** | **HTTP 500** | Sofort-Log hängt von `bes_ensure_writable_directory()` ab, die noch nicht geladen ist |

### Verdikt zur Bug-Geschichte

| Aussage | Bewertung |
|---|---|
| „Bug war schon vor Task 2 da“ (Tom) | **Für diesen Fatal: nein.** Belegt durch Diff + Repro: Admin funktionierte bei `b576a67`. |
| „Task 2 hat den Bug eingeführt“ | **Ja — Commit `b9196b7`.** |
| „Strukturelle Fragilität seit Modularisierung“ | **Ja — seit `f3ac9f7`.** Load-Order war immer riskant; erst Task 2 aktivierte sie. |
| „Task 1 hat den Bug eingeführt“ | **Nein.** Task 1 (`b576a67`) änderte `load.php`-Guards, nicht `debug-log.php`. |

**Warum wirkte es „schon länger kaputt“?** Tom testete vermutlich erst nach Task-2-Commits lokal; die Task-1-Verifikation hatte den Admin nie per authentifiziertem HTTP geprüft (siehe Abschnitt 5) und lief auf Code **vor** `b9196b7`.

---

## 3. Strukturproblem?

### Ist es nur Load-Order — oder tiefer?

**Primär:** Lokaler Load-Order-Fehler mit **architektonischer Komponente**:

- Zwei Logging-Kanäle mit unterschiedlichen Abhängigkeiten:
  - `bes_debug_log()` → `includes/infra/logging/debug-log.php` → nur `error_log()` (früh verfügbar via `error-handler.php:16`)
  - `bes_write_debug_log()` → `bootstrap/debug-log.php` → Datei-I/O via Hosting-Wrapper (spät, wenn Load-Order falsch)
- Bootstrap registriert Error-Handler und schreibt **während** des Plugin-Ladevorgangs — ein Anti-Pattern für Abhängigkeiten, die erst Zeilen später geladen werden.

**Kein Zirkelproblem** bei korrekter Reihenfolge:

```
error-handler.php  →  bes_debug_log (für hosting-compatibility)
hosting-compatibility.php  →  bes_ensure_writable_directory, bes_safe_file_put_contents
debug-log.php  →  bes_write_debug_log (darf hosting nutzen)
```

`hosting-compatibility.php` braucht **nicht** `bes_write_debug_log` — nur `bes_debug_log`.

### Aufruf-Matrix (Bootstrap-relevante Funktionen)

| Funktion | Definiert in | Aufgerufen von | Zeitpunkt vs. hosting-compatibility (Z. 61) |
|---|---|---|---|
| `bes_ensure_writable_directory` | `hosting-compatibility.php:280` | `bootstrap/debug-log.php:42` | **VOR** (Fatal) |
| `bes_safe_file_put_contents` | `hosting-compatibility.php:344` | `bootstrap/debug-log.php:57` | **VOR** (würde nach Fix von Z. 42 folgen) |
| `bes_write_debug_log` | `bootstrap/debug-log.php:21` | `load.php:54` via Handler-Reg. | **VOR** |
| `bes_debug_log` | `error-handler.php:16` | `legacy-data-paths.php:46` (mit `function_exists`) | `bes_debug_log` **nach** error-handler (Z. 46); Aufruf nur zur Laufzeit |
| `bes_safe_file_get_contents` | `safe-io.php:48` | Frontend/Sync (Runtime) | **nach** vollem Bootstrap |
| `bes_ensure_writable_directory` | hosting | `admin/ajax/ajax-debug.php:56` | Admin-AJAX, nach vollem Bootstrap |

### Weitere latente Bugs

| Risiko | Schwere | Beschreibung |
|---|---|---|
| Error-Handler-Fenster Z. 55–60 | Mittel | Nach Fix von Zeile 164 bleibt Handler aktiv vor Hosting-Load; PHP-Notice in `filters.php`/`legacy-data-paths.php` könnte erneut Fatal triggern |
| Zwei Logging-Systeme | Niedrig | Verwechslungsgefahr `bes_debug_log` vs. `bes_write_debug_log`; unterschiedliche Abhängigkeiten |
| `bes_write_debug_log` Pfad-Guard | Niedrig | Wenn `realpath(BES_DIR)` außerhalb `WP_PLUGIN_DIR` liegt (Symlink/Worktree), bricht Funktion früh ab **ohne** Fatal — erklärt irreführende „grüne“ Tests mit `/tmp`-Worktrees |
| `plugin-lifecycle.php` | Keins | Nutzt `bes_debug_log` mit Guards; wird am Ende von `load.php` geladen |

**Risikobewertung:** Aktiver **P0-Blocker** (Admin tot). Nach reinem Verschieben von Zeile 164 ohne Hosting-Reorder: **P0 bleibt**. Nach Hosting-Reorder: **gelöst**, Handler-Fenster optional absichern.

---

## 4. Empfohlene Load-Order

### Aktuell (fehlerhaft)

```
constants.php → error-handler.php → cache-utils.php
→ debug-log.php + Handler-Registrierung (Zeile 53–54)   ← ZU FRÜH
→ legacy-data-paths.php
→ filters.php
→ hosting-compatibility.php                              ← ZU SPÄT
```

### Empfohlen (robust)

```
1. includes/constants.php
2. includes/error-handler.php          # bes_debug_log, safe-io
3. includes/cache-utils.php
4. includes/filters.php                # optional; vor hosting OK
5. includes/hosting-compatibility.php  # bes_ensure_writable_directory, bes_safe_*
6. bootstrap/debug-log.php             # bes_write_debug_log darf Wrapper nutzen
7. bes_bootstrap_register_admin_error_handlers()
8. bootstrap/legacy-data-paths.php
9. … Rest unverändert (constants-v3, sync-Guard, frontend, admin-Guard)
```

### Begründung

- **Infrastruktur vor Bootstrap-Seiteneffekten:** Alles mit Datei-I/O-Wrappern steht vor Code, der beim `require` sofort loggt.
- **Kein Zirkel:** `error-handler` vor `hosting` erfüllt `bes_debug_log`-Abhängigkeit von Hosting.
- **Zukunftssicher:** Neue Aufrufe von `bes_safe_*` in `bootstrap/*` scheitern nicht erneut an derselben Stelle.
- **`bseasy-sync.php:96–108`:** Nutzt bereits `function_exists('bes_ensure_writable_directory')` — bleibt korrekt, profitiert vom früheren Load.

### Optionale Härtung (Fix-Phase)

1. Zeile 164 auf `add_action('plugins_loaded', ...)` deferieren **oder** entfernen (Handler-Registrierung braucht kein Bestätigungs-Log).
2. In `bes_write_debug_log()`: Fallback auf `error_log()` wenn `!function_exists('bes_ensure_writable_directory')` — Defense-in-depth für Handler-Fenster.
3. Langfristig: `bootstrap/debug-log.php` und `includes/infra/logging/debug-log.php` dokumentieren oder konvergieren.

---

## 5. Verifikations-Methodik

### Was Task 1 tatsächlich prüfte

Laut `cursor-task1-verifikation.md`:

- Skript `dev/verify-task1-admin.php` mit `define('WP_ADMIN', true)` **vor** `wp-load.php`
- Prüfung: `function_exists('bes_admin_page')`, AJAX-Hooks, HTML-Markup (~102 KB), Tabs im Markup
- Skripte wurden **nicht** committet (Commit `2ee5b2d` enthält nur die Markdown-Datei)

### Warum Task 1 den Fatal nicht fand

| Grund | Detail |
|---|---|
| **Code-Stand** | Task-1-Verifikation lief auf Stand **vor** `b9196b7`; `bes_write_debug_log` war noch self-contained |
| **Kein HTTP-Auth-Test** | Kein `curl` mit `wordpress_logged_in_*`-Cookie → kein echter Admin-Page-Render unter Produktionsbedingungen |
| **Synthetischer Bootstrap** | `WP_ADMIN` vor `wp-load` simuliert Admin-Laden, prüft aber primär „Funktionen existieren nach vollem Load“ — nicht „Bootstrap überlebt Zeile 54“ |
| **Erfolgskriterium zu schwach** | „HTML enthält `data-tab=`“ ≠ HTTP 200 ohne Fatal |

### Task 2 fand es korrekt

- Authentifizierter `curl` auf `admin.php?page=bseasy-sync` → HTTP 500
- Log-Auszug mit exaktem Fatal

### Empfohlenes Verifikations-Pattern (künftig)

**Pflicht-Check „Admin smoke“** (in jedem Verifikations-Lauf):

```bash
# 1. Auth-Cookie erzeugen
COOKIE=$(wp eval 'echo wp_generate_auth_cookie(1, time()+3600, "logged_in");')
COOKIEHASH=$(wp eval 'echo COOKIEHASH;')

# 2. HTTP-Request
HTTP=$(curl -sS -o /tmp/bes-admin.html -w '%{http_code}' \
  -H "Cookie: wordpress_logged_in_${COOKIEHASH}=${COOKIE}" \
  'http://localhost/wordpress/wp-admin/admin.php?page=bseasy-sync')

# 3. Assertions
test "$HTTP" = "200" || fail "Admin HTTP $HTTP"
grep -qi 'kritischer Fehler' /tmp/bes-admin.html && fail "WP fatal page"
grep -q 'data-tab="felder"' /tmp/bes-admin.html || fail "Tab markup missing"
grep 'Fatal error.*bes_ensure_writable_directory' wp-content/debug.log && fail "Bootstrap fatal"
```

**Zusätzlich:** Minimal-Repro (ohne volles WP) für Bootstrap-Reihenfolge — wie in dieser Analyse — bei jedem Refactoring von `bootstrap/load.php` oder `bootstrap/debug-log.php`.

**Nicht als Admin-Ersatz:** `wp eval` ohne Admin-Kontext (lädt Admin-Module nicht); Worktree-Symlinks außerhalb `WP_PLUGIN_DIR` (maskiert Pfad-Guards).

---

## 6. Empfohlener Fix-Plan

> Wird separat umgesetzt — hier nur Skizze für Tom.

### Schritt 1 — Load-Order korrigieren (minimal, P0)

In `bootstrap/load.php`: Block `includes/hosting-compatibility.php` **vor** `bootstrap/debug-log.php` + Zeile 54 verschieben.

**Erwartung:** Admin-HTTP 200; Zeile 164 loggt erfolgreich in `debug/debug.log`.

### Schritt 2 — Verifikation

- Admin smoke (curl + Cookie) — alle 4 Tabs
- Felder speichern (`bes_save_json` via UI)
- Regression: Frontend-Shortcode, AJAX, DOING_CRON-Sync/Audit
- `grep 'Fatal error' wp-content/debug.log` nach Admin-Aufruf = 0

### Schritt 3 — Optionale Härtung

- Zeile 164 deferieren/entfernen
- `bes_write_debug_log()` Early-Bootstrap-Fallback auf `error_log()`
- `cursor-task2-verifikation.md` Check 3 aktualisieren

### Schritt 4 — Doku

- `MODULE_OVERVIEW.md`: Bootstrap-Ladereihenfolge-Regel („Hosting vor bootstrap/debug-log“)
- Verifikations-Checkliste: Admin-HTTP-200 als Pflichtpunkt

---

## Antworten auf die 5 Hauptfragen

| # | Frage | Kurzantwort |
|---|---|---|
| 1 | Root Cause | Sofort-Aufruf `bes_write_debug_log()` in Zeile 164 während `load.php:54`, **`bes_ensure_writable_directory()` erst ab Zeile 61** — eingeführt durch Task-2-Refactoring in `bes_write_debug_log()` |
| 2 | Seit wann | **Manifest seit `b9196b7` (Task 2).** Latente Load-Order-Schwäche seit `f3ac9f7`. **Nicht** pre-Task-2 in dieser Form. |
| 3 | Latente Bugs | Handler-Fenster Zeile 55–60; Worktree-Pfad maskiert Fehler; zwei Logging-Kanäle |
| 4 | Korrekte Load-Order | `error-handler` → `hosting-compatibility` → `debug-log` → Handler-Registrierung |
| 5 | Verifikation | Task 1 prüfte Markup ohne HTTP-Auth auf Code vor `b9196b7`; künftig curl+Cookie+HTTP-200 Pflicht |

---

## Empfehlung für Tom

**Task 2 ist der unmittelbare Verursacher des Admin-500** (Commit `b9196b7`), nicht ein unabhängiger Pre-Existing-Bug. Die strukturelle Anfälligkeit ist älter, war aber bis Task 2 harmlos.

**Nächster Schritt:** Load-Order-Fix (Abschnitt 6, Schritt 1) — kleiner, klar abgegrenzter Patch — danach Task-2-Verifikation Check 3+5 wiederholen, dann Push-Freigabe.
