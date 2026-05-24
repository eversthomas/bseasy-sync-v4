# Task 2 — Analyse-Bericht: `@`-Error-Suppressions

**Datum:** 2026-05-24  
**Phase:** 1 (Analyse only — keine Code-Änderungen)  
**Plugin:** BSEasy Sync V4  
**Baseline:** STATUS_REPORT.md meldete 36 Stellen; aktuelle Zählung: **38 echte Suppressions** (siehe Methodik)

---

## Methodik

```bash
grep -rn '@\(file_get_contents\|file_put_contents\|unlink\|mkdir\|chmod\|rename\|set_time_limit\|ini_set\|wp_mkdir_p\)' \
  --include="*.php" .
```

Docblock-Tags (`@param`, `@return`, `@package`, …) und JSON-LD-Keys (`'@context'`, `'@type'`) wurden ausgeschlossen — sie sind **keine** Error-Suppressions.

Für jede Stelle: Code-Kontext gelesen, vorhandene `bes_safe_*`- / `bes_ensure_*`-Wrapper in `includes/infra/hosting/hosting-compatibility.php` und `includes/infra/security/safe-io.php` abgeglichen.

### Verfügbare Wrapper (Referenz)

| Funktion | Datei | Abdeckung |
|----------|-------|-----------|
| `bes_safe_file_get_contents($path, $allowed_dir)` | `safe-io.php` | Path-Traversal-Schutz + Log bei Fehler |
| `bes_safe_file_put_contents($file, $data, $flags, $retries)` | `hosting-compatibility.php` | Verzeichnis-Check, Retry, Log bei Fehler |
| `bes_ensure_writable_directory($path)` | `hosting-compatibility.php` | `wp_mkdir_p` + Schreibbarkeits-Check + Log |
| `bes_ensure_writable_file($file)` | `hosting-compatibility.php` | Parent-Dir + Schreibbarkeit |
| `bes_safe_set_time_limit($seconds)` | `hosting-compatibility.php` | Hosting-Limits prüfen, dann `set_time_limit` |
| `bes_safe_increase_memory($limit)` | `hosting-compatibility.php` | Memory-Limit mit Verifikation |

**Hinweis:** Die Wrapper enthalten selbst noch `@`-Suppressions (5 Stellen) — sie sind die **kanonische Kapselung**, sollten in Phase 2 aber ebenfalls gehärtet werden (Logging bei Host-Einschränkungen).

---

## Ergebnistabelle

| # | Datei:Zeile | Code-Snippet (1 Zeile) | Funktion | Kat. | Empfohlene Aktion |
|---|---|---|---|---|---|
| 1 | `admin/fields/fields-handler.php:114` | `$result = @file_put_contents($tmp_file, $json_content, LOCK_EX);` | file_put_contents | 🔴 A | Explizite Prüfung + `bes_debug_log(..., 'ERROR', 'fields')` bei `false`; langfristig atomares Schreiben über Wrapper/Helper |
| 2 | `admin/fields/fields-handler.php:119` | `@unlink($tmp_file);` | unlink | 🟢 C | Bleibt — Cleanup nach fehlgeschlagenem Write, `file_exists()` davor |
| 3 | `admin/fields/fields-handler.php:125` | `$rename_result = @rename($tmp_file, $path);` | rename | 🔴 A | `@` entfernen; bei `false` → `bes_debug_log("rename fehlgeschlagen: $path", 'ERROR', 'fields')` |
| 4 | `admin/fields/fields-handler.php:130` | `@unlink($tmp_file);` | unlink | 🟢 C | Bleibt — Cleanup nach fehlgeschlagenem rename, `file_exists()` davor |
| 5 | `sync/api-core-consent-v3.php:132` | `@set_time_limit(0);` | set_time_limit | 🔴 A | `else`-Fallback entfernen — `bes_safe_set_time_limit(0)` ist immer verfügbar (includes immer geladen) |
| 6 | `sync/runtime/cron-v3.php:43` | `@set_time_limit(0);` | set_time_limit | 🔴 A | Wie #5 — nur `bes_safe_set_time_limit(0)`, kein `@`-Fallback |
| 7 | `sync/runtime/cron-v3.php:107` | `@set_time_limit(0);` | set_time_limit | 🔴 A | Wie #5 |
| 8 | `sync/runtime/cron-v3.php:298` | `@set_time_limit(0);` | set_time_limit | 🔴 A | Wie #5 |
| 9 | `bootstrap/debug-log.php:45` | `@wp_mkdir_p($debug_dir);` | wp_mkdir_p | 🟡 B | `bes_ensure_writable_directory($debug_dir)` |
| 10 | `bootstrap/debug-log.php:47` | `@mkdir($debug_dir, 0755, true);` | mkdir | 🟡 B | Entfällt mit #9 (wp_mkdir_p-Pfad); sonst `bes_ensure_writable_directory` |
| 11 | `bootstrap/debug-log.php:49` | `@chmod($debug_dir, 0755);` | chmod | 🟡 B | Entfällt mit `bes_ensure_writable_directory` |
| 12 | `bootstrap/debug-log.php:68` | `$result = @file_put_contents($log_file, $log_entry, FILE_APPEND \| LOCK_EX);` | file_put_contents | 🟡 B | `bes_safe_file_put_contents($log_file, $log_entry, FILE_APPEND \| LOCK_EX)` — `error_log`-Fallback bei Fehler behalten |
| 13 | `includes/infra/hosting/hosting-compatibility.php:175` | `@set_time_limit($seconds);` | set_time_limit | 🔴 A | Innerhalb `bes_safe_set_time_limit`: `@` entfernen, bei Fehlschlag `bes_debug_log(..., 'WARN', 'hosting')` |
| 14 | `includes/infra/hosting/hosting-compatibility.php:207` | `@ini_set('memory_limit', $target_string);` | ini_set | 🔴 A | Innerhalb `bes_safe_increase_memory`: bei `$new_bytes < $target_limit` → `bes_debug_log(..., 'WARN', 'hosting')` |
| 15 | `includes/infra/hosting/hosting-compatibility.php:281` | `@chmod($path, $permissions);` | chmod | 🟢 C | Bleibt in Wrapper — Best-Effort nach `wp_mkdir_p`, danach `is_writable()`-Check + Log |
| 16 | `includes/infra/hosting/hosting-compatibility.php:311` | `@chmod($file, $permissions);` | chmod | 🟢 C | Wie #15 — in `bes_ensure_writable_file` |
| 17 | `includes/infra/hosting/hosting-compatibility.php:341` | `$result = @file_put_contents($file, $data, $flags);` | file_put_contents | 🟢 C | Bleibt in `bes_safe_file_put_contents` — Retry-Schleife + `bes_debug_log` bei finalem Fehler |
| 18 | `includes/infra/security/safe-io.php:86` | `$content = @file_get_contents($real_path);` | file_get_contents | 🟢 C | Bleibt in `bes_safe_file_get_contents` — Vorprüfungen + Log bei `false` (optional `@` später entfernen) |
| 19 | `bootstrap/legacy-data-paths.php:43` | `$content = @file_get_contents($path);` | file_get_contents | 🔴 A | `@` entfernen; bei `false` → `bes_debug_log(..., 'WARN', 'legacy')`; Funktion ist `@deprecated` — ggf. nur Log + dokumentieren |
| 20 | `sync/sync-service.php:121` | `@unlink($status_file);` | unlink | 🟢 C | Bleibt — `file_exists()` davor, Reset-Cleanup, Fehler irrelevant |
| 21 | `sync/v3/v3-field-options.php:95` | `@chmod($cache_dir, 0755);` | chmod | 🟡 B | Cache-Dir-Setup → `bes_ensure_writable_directory($cache_dir)` |
| 22 | `sync/v3/v3-field-options.php:97` | `@mkdir($cache_dir, 0755, true);` | mkdir | 🟡 B | Wie #21 |
| 23 | `sync/v3/v3-field-options.php:103` | `@file_put_contents($index_file, "<?php\n// Silence is golden.\n");` | file_put_contents | 🟡 B | `bes_safe_file_put_contents($index_file, '...')` |
| 24 | `sync/v3/v3-field-options.php:205` | `@chmod($cache_dir, 0755);` | chmod | 🟡 B | Duplikat von #21 — gleiche Hilfsfunktion extrahieren oder #21-Pattern wiederverwenden |
| 25 | `sync/v3/v3-field-options.php:207` | `@mkdir($cache_dir, 0755, true);` | mkdir | 🟡 B | Wie #24 |
| 26 | `sync/v3/v3-field-options.php:213` | `@file_put_contents($index_file, ...);` | file_put_contents | 🟡 B | Wie #23 |
| 27 | `sync/consent/consent-logging-debug.php:55` | `@mkdir($log_dir, 0755, true);` | mkdir | 🟡 B | `bes_ensure_writable_directory($log_dir)` |
| 28 | `sync/consent/consent-logging-debug.php:57` | `@chmod($log_dir, 0755);` | chmod | 🟡 B | Entfällt mit #27 |
| 29 | `sync/consent/consent-logging-debug.php:69` | `@file_put_contents($logfile, "=== New Consent Sync Run: ...", FILE_APPEND \| LOCK_EX);` | file_put_contents | 🔴 A | `bes_safe_file_put_contents` + Log bei Fehler (aktuell kein Check) |
| 30 | `frontend/ajax/ajax-endpoints.php:96` | `$config_raw = @file_get_contents($config_file);` | file_get_contents | 🔴 A | Fallback-Zweig entfernen oder `@` + `bes_debug_log(..., 'WARN', 'ajax')` — **bekannter Silent Failure** (STATUS_REPORT M1) |
| 31 | `sync/v3/v3-logging.php:37` | `@chmod(BES_DATA_V3, 0755);` | chmod | 🟡 B | `bes_ensure_writable_directory(BES_DATA_V3)` vor Log-Schreiben |
| 32 | `sync/v3/v3-logging.php:39` | `@mkdir(BES_DATA_V3, 0755, true);` | mkdir | 🟡 B | Wie #31 |
| 33 | `sync/v3/v3-logging.php:47` | `return @file_put_contents($log_file, $log_entry, FILE_APPEND \| LOCK_EX) !== false;` | file_put_contents | 🔴 A | `bes_safe_file_put_contents` + bei `false` → `bes_debug_log(..., 'ERROR', 'v3-log')` |
| 34 | `sync/v3/v3-logging.php:71` | `@chmod(BES_DATA_V3, 0755);` | chmod | 🟡 B | Duplikat #31 in `bseasy_v3_debug_log` |
| 35 | `sync/v3/v3-logging.php:73` | `@mkdir(BES_DATA_V3, 0755, true);` | mkdir | 🟡 B | Wie #34 |
| 36 | `sync/v3/v3-logging.php:81` | `return @file_put_contents($log_file, $log_entry, ...) !== false;` | file_put_contents | 🔴 A | Wie #33 |
| 37 | `admin/ajax/ajax-debug.php:58` | `@chmod($debug_dir, 0755);` | chmod | 🟡 B | `bes_ensure_writable_directory($debug_dir)` |
| 38 | `admin/ajax/ajax-debug.php:71` | `$result = @file_put_contents($log_file, $log_entry, FILE_APPEND \| LOCK_EX);` | file_put_contents | 🟡 B | `bes_safe_file_put_contents` — JSON-Error-Response bei Fehler bleibt |

---

## Verteilung

| Kategorie | Anzahl | Anteil |
|-----------|--------|--------|
| 🔴 **A — Anti-Pattern** (muss weg) | **14** | 37 % |
| 🟡 **B — Refactoring zu `bes_safe_*`** | **17** | 45 % |
| 🟢 **C — Legitim** (bleibt + Kommentar) | **7** | 18 % |
| **Gesamt** | **38** | 100 % |

Nach Phase 2 sollten **7 `@`-Suppressions** verbleiben (Kategorie C, ggf. 5 in Wrapper-Kern + 2 Cleanup-`unlink`).

---

## Hotspots

| Datei / Bereich | Anzahl | Muster |
|-----------------|--------|--------|
| `sync/v3/v3-logging.php` | 6 | Verzeichnis anlegen (mkdir/chmod) + `@file_put_contents` ohne Log |
| `sync/v3/v3-field-options.php` | 6 | Identisches Cache-Dir-Setup (2× dupliziert) |
| `bootstrap/debug-log.php` | 4 | Debug-Dir-Erstellung + Log-Schreiben |
| `sync/runtime/cron-v3.php` | 3 | `@set_time_limit`-Fallback in `else`-Zweigen |
| `includes/infra/hosting/hosting-compatibility.php` | 5 | Wrapper-Implementierung (Kern der Safe-IO-Schicht) |
| `admin/fields/fields-handler.php` | 4 | Atomisches JSON-Schreiben (tmp + rename) |
| `admin/ajax/ajax-debug.php` | 2 | Debug-Log AJAX |
| `sync/consent/consent-logging-debug.php` | 3 | Consent-Log-Initialisierung |
| Einzelstellen | 5 | sync-service, api-core-consent-v3, legacy-data-paths, ajax-endpoints, safe-io |

**Top-2 Hotspot-Dateien:** `sync/v3/` mit **12** Suppressions (32 % aller Stellen).

---

## Auffälligkeiten

1. **Doppeltes Cache-Dir-Pattern** in `v3-field-options.php` (Zeilen 92–104 und 202–214) — Kandidat für eine interne Hilfsfunktion `bseasy_v3_ensure_cache_dir()` auf Basis von `bes_ensure_writable_directory()` (Phase 2 optional, nicht zwingend).

2. **Identisches V3-Log-Dir-Pattern** in `bseasy_v3_log()` und `bseasy_v3_debug_log()` — gleiche 3 Zeilen mkdir/chmod/put; Refactoring zu einem gemeinsamen `bseasy_v3_ensure_log_dir()`.

3. **`@set_time_limit`-Fallback ist totter Code:** `bes_safe_set_time_limit()` wird über `includes/hosting-compatibility.php` **immer** geladen. Die vier `else`-Zweige (api-core-consent-v3 + cron-v3 ×3) können entfallen — kein `@` nötig.

4. **`frontend/ajax/ajax-endpoints.php:96`** ist der einzige **Frontend-Pfad** mit `@file_get_contents` — und nur im Fallback-Zweig, wenn `bes_safe_file_get_contents` fehlt (in V4 nie der Fall). Entfernen des Fallbacks eliminiert die Suppression und den Silent Failure aus STATUS_REPORT M1.

5. **Wrapper paradox:** `bes_safe_file_get_contents` und `bes_safe_file_put_contents` existieren bereits, werden aber an vielen Sync-Stellen umgangen — stattdessen direktes `@file_put_contents` in v3-logging/consent-logging.

6. **`bes_save_json()` in fields-handler** nutzt eigenes tmp+rename statt `bes_safe_file_put_contents` — atomares Rename ist korrekt, aber Logging bei Fehler fehlt (🔴 #1, #3).

7. **Abweichung STATUS_REPORT (36 vs. 38):** +2 vermutlich durch `includes/infra/hosting/hosting-compatibility.php` chmod-Zeilen (281, 311), die im Report unter „hosting-compatibility.php \| 5“ subsumiert waren, plus Zählung `@rename` in fields-handler.

---

## Geschätzter Umsetzungsaufwand

| Kategorie | Stellen | min/Stelle | Summe |
|-----------|---------|------------|-------|
| 🔴 A | 14 | ~8 min | ~112 min (~1,9 h) |
| 🟡 B | 17 | ~5 min | ~85 min (~1,4 h) |
| 🟢 C | 7 | ~1 min | ~7 min |
| Verifikation + Doku | — | — | ~30 min |
| **Gesamt** | **38** | — | **~3,5 h** |

Optional (+30 min): Duplikat-Hilfsfunktionen in `sync/v3/` (Cache-Dir, Log-Dir) — reduziert Wartungsaufwand, nicht zwingend für DoD.

---

## Empfohlener Umsetzungsplan (Phase 2, nach Freigabe)

### Commit 1 — 🔴 Kategorie A (14 Stellen)

Reihenfolge nach Risiko:
1. `frontend/ajax/ajax-endpoints.php:96` — Fallback entfernen (Frontend, bekannter Silent Failure)
2. `sync/v3/v3-logging.php:47,81` — Log-Schreiben mit Wrapper + Error-Log
3. `sync/consent/consent-logging-debug.php:69` — Init-Zeile absichern
4. `admin/fields/fields-handler.php:114,125` — atomisches JSON + rename-Log
5. `sync/runtime/cron-v3.php` + `sync/api-core-consent-v3.php` — `@set_time_limit`-Fallbacks entfernen
6. `bootstrap/legacy-data-paths.php:43` — deprecated Pfad
7. `includes/infra/hosting/hosting-compatibility.php:175,207` — Wrapper-Innenleben härten

### Commit 2 — 🟡 Kategorie B (17 Stellen)

Dateiweise:
- `bootstrap/debug-log.php` (4)
- `sync/v3/v3-logging.php` mkdir/chmod (4)
- `sync/v3/v3-field-options.php` (6)
- `sync/consent/consent-logging-debug.php` mkdir/chmod (2)
- `admin/ajax/ajax-debug.php` (2)

### Commit 3 — 🟢 Kategorie C (7 Stellen)

Inline-Kommentare an:
- `fields-handler.php:119,130`
- `sync/sync-service.php:121`
- `hosting-compatibility.php:281,311,341`
- `safe-io.php:86`

### Commit 4 — Verifikation + Dokumentation

- `grep`-Check: nur noch ~7 `@`-Suppressions
- `php -l` auf geänderte Dateien
- Kurztest: Frontend-Shortcode, Admin-Tabs, Cron (wie Task 1)
- `STATUS_REPORT.md` + `MODULE_OVERVIEW.md` (Coding-Regel: keine neuen `@`-Suppressions)

---

## Offene Entscheidung für Tom (vor Phase 2)

**Atomares Schreiben in `bes_save_json()`:** Soll tmp+rename beibehiten werden (empfohlen), mit explizitem Logging bei Fehler (#1, #3)? Oder soll eine neue `bes_safe_atomic_write_json()` in `includes/` eingeführt werden?  
→ Empfehlung: **Erst Logging + `@` entfernen**, Wrapper-Extraktion optional als Follow-up — kein Blocker.

---

**🛑 STOP — Warte auf Freigabe für Phase 2.**
