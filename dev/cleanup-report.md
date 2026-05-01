# BSEasy Sync V4 — Cleanup Report

> Analysiert: 2026-05-01  
> Basis: Plugin-Verzeichnis `/bseasy-sync-v4/` inkl. `.claude/worktrees/`  
> Keine Dateien wurden verändert oder gelöscht.

---

## ✅ Wird benötigt — Kerndateien

### Plugin-Einstieg
| Datei | Funktion |
|---|---|
| `bseasy-sync.php` | Haupt-Plugin-Datei, WP-Registrierung, definiert Konstanten |
| `bootstrap/load.php` | Zentraler Lade-Orchestrator (6 Phasen) |
| `bootstrap/admin-assets.php` | Enqueued Admin-CSS/JS |
| `bootstrap/admin-menu.php` | WP Admin-Menü |
| `bootstrap/admin-page.php` | Admin-Seiten-Ausgabe |
| `bootstrap/debug-log.php` | Logging-Initialisierung |
| `bootstrap/legacy-data-paths.php` | Abwärtskompatibilität Datenpfade |
| `bootstrap/plugin-lifecycle.php` | Aktivierung/Deaktivierung-Hooks |

### Frontend (aktive Dateien)
| Datei | Funktion |
|---|---|
| `frontend/views/renderer.php` | Mitglieder-Card-Rendering |
| `frontend/views/map-render.php` | Karten-Rendering (Leaflet) |
| `frontend/views/calendar-render.php` | Kalender-Rendering |
| `frontend/shortcode/bes-members.php` | Shortcode `[bes_members]`, enqueued Assets |
| `frontend/ajax/ajax-endpoints.php` | Frontend-AJAX (Radius-Suche etc.) |
| `frontend/includes/filter-helpers.php` | Filter-Logik + Länder-Normalisierung |
| `frontend/includes/country-filter-normalize.php` | Wird von filter-helpers.php geladen |
| `frontend/includes/design-bridge.php` | Design-Token-Brücke Frontend↔Admin |
| `frontend/includes/geo-utils.php` | Geocoding-Hilfsfunktionen |
| `frontend/includes/schema.php` | Schema.org Markup |
| `frontend/includes/og-meta.php` | Open Graph Meta-Tags |
| `frontend/assets/frontend.css` | Frontend-Stylesheet |
| `frontend/assets/frontend.js` | Frontend-Logik (Filter, Toggle, Scroll) |
| `frontend/assets/map.js` | Leaflet-Kartenlogik |
| `frontend/assets/calendar.js` | Kalender-Logik |
| `frontend/assets/libs/leaflet/` | Leaflet-Library (CSS + JS + Icons) |
| `frontend/assets/libs/leaflet-cluster/` | Marker-Cluster-Plugin |

### Admin (aktive Dateien)
| Datei | Funktion |
|---|---|
| `admin/ajax/ajax-v3.php` | Admin-AJAX (Sync, Explorer) |
| `admin/ajax/ajax-cache.php` | Cache-Verwaltung via AJAX |
| `admin/ajax/ajax-debug.php` | Debug-Endpoints |
| `admin/calendar-handler.php` | Kalender-Verwaltung |
| `admin/fields/fields-handler.php` | Feld-Konfiguration Handler |
| `admin/fields/includes/field-label-generator.php` | Auto-Label-Generator |
| `admin/fields/includes/fields-template.php` | Feld-Template-Rendering |
| `admin/fields/includes/design-settings.php` | Design-Einstellungen (Stub → includes/design/) |
| `admin/views/ui-main.php` | Haupt-Admin-UI |
| `admin/views/ui-sync.php` | Sync-Tab |
| `admin/views/ui-felder.php` | Felder-Tab |
| `admin/views/ui-map.php` | Karten-Einstellungen-Tab |
| `admin/views/ui-kalender.php` | Kalender-Tab |
| `admin/views/partials/sync-*.php` | Admin-Partials (Sync-UI-Blöcke) |
| `admin/views/partials/sync-onboarding-hint.php` | Onboarding |
| `admin/assets/admin.css` | Admin-Stylesheet |
| `admin/assets/bes-sidebar.css` | Sidebar-Styles |
| `admin/assets/ui-felder.css` | Felder-UI-Styles |
| `admin/assets/ui.js` | Admin-UI-Script |
| `admin/assets/ui-felder-sidebar.js` | Felder-Sidebar-Script |
| `admin/vendor/Sortable.min.js` | Drag-and-Drop Library |
| `admin/templates/fields-config-default.json` | Standard-Feldkonfiguration |

### Includes / Infrastruktur
| Datei | Funktion |
|---|---|
| `includes/constants/constants.php` | Alle Plugin-Konstanten |
| `includes/constants-v3.php` | V3-spezifische Konstanten |
| `includes/data/member-repository.php` | Datenzugriffsschicht (Mitglieder) |
| `includes/design/design-settings.php` | Design-Einstellungen (Originaldatei) |
| `includes/api-error-user-hints.php` | Benutzerfreundliche API-Fehlertexte |
| `includes/infra/cache/cache-utils.php` | Cache-Utility-Funktionen |
| `includes/infra/errors/error-handler-class.php` | Error-Handler-Klasse |
| `includes/infra/filters/filters.php` | WordPress-Filter-Hooks |
| `includes/infra/hosting/hosting-compatibility.php` | Hosting-Kompatibilität |
| `includes/infra/logging/debug-log.php` | Logging-Klasse |
| `includes/infra/security/safe-io.php` | Sichere Datei-I/O |
| `includes/infra/security/token-crypto.php` | Token-Verschlüsselung |

### Sync-Modul (aktive Dateien)
| Datei | Funktion |
|---|---|
| `sync/sync-service.php` | Haupt-Sync-Orchestrator |
| `sync/runtime/cron-v3.php` | WP-Cron-Adapter (aktive Version) |
| `sync/client/api-core-consent-requests.php` | HTTP-Transport (aktive Version) |
| `sync/client/api-core-consent-member-fetch.php` | Mitglieder-Abruf (aktive Version) |
| `sync/client/api-core-consent-token.php` | Token-Refresh (aktive Version) |
| `sync/api-core-consent.php` | Consent-Kern-Logik |
| `sync/api-core-consent-v3.php` | V3-Consent-Erweiterung |
| `sync/api-explorer-v3.php` | API-Explorer |
| `sync/consent/` | Alle Consent-Module (bootstrap, config, extraction, geocoding, etc.) |
| `sync/v3-helpers.php` | V3-Hilfsfunktionen (3x referenziert) |
| `sync/v3-consent-audit.php` | Consent-Audit-Log |
| `sync/v3/v3-field-options.php` | Feld-Optionen V3 |
| `sync/v3/v3-logging.php` | Sync-Logging |
| `sync/v3/v3-persistence.php` | Datenpersistenz |

### Backward-Compatibility Stubs (werden benötigt solange bootstrap/load.php sie lädt)
Diese Stubs sind intentional und delegieren per `require_once` an den neuen Pfad:

| Stub-Datei | Ziel (aktive Datei) |
|---|---|
| `frontend/renderer.php` | `frontend/views/renderer.php` |
| `frontend/calendar-render.php` | `frontend/views/calendar-render.php` |
| `frontend/map-render.php` | `frontend/views/map-render.php` |
| `frontend/ajax-endpoints.php` | `frontend/ajax/ajax-endpoints.php` |
| `frontend/filter-helpers.php` | `frontend/includes/filter-helpers.php` |
| `frontend/shortcode.php` | `frontend/shortcode/bes-members.php` |
| `includes/constants.php` | `includes/constants/constants.php` |
| `includes/cache-utils.php` | `includes/infra/cache/cache-utils.php` |
| `includes/error-handler.php` | `includes/infra/errors/error-handler-class.php` |
| `includes/filters.php` | `includes/infra/filters/filters.php` |
| `includes/hosting-compatibility.php` | `includes/infra/hosting/hosting-compatibility.php` |
| `sync/cron-v3.php` | `sync/runtime/cron-v3.php` |
| `sync/api-core-consent-requests.php` | `sync/client/api-core-consent-requests.php` |
| `sync/api-core-consent-member-fetch.php` | `sync/client/api-core-consent-member-fetch.php` |
| `sync/api-core-consent-token.php` | `sync/client/api-core-consent-token.php` |

> **Langfristiges Ziel:** Wenn alle direkten Referenzen in `bootstrap/load.php` und anderen Dateien auf die neuen Pfade zeigen, können die Stubs entfernt werden. Bis dahin: nicht anfassen.

---

## 🗑️ Kann gelöscht werden

### 1. `sync/_backup-v2/` — Leeres Verzeichnis
- **Inhalt:** 0 Bytes, keine Dateien
- **Begründung:** Leeres Backup-Verzeichnis ohne Inhalt — reiner Ballast
- **Risiko:** Keins

### 2. `admin/cleanup-duplicate-fields.php`
- **Referenzen:** 0 — wird von keiner PHP-Datei per `require`/`include` eingebunden
- **Begründung:** Verwaiste Datei, kein Einstiegspunkt im Plugin
- **Risiko:** Keins (nicht im Lade-Pfad)

### 3. `admin/assets/field-intelligence.css`
- **Referenzen:** Kein `wp_enqueue_style`-Aufruf gefunden
- **Begründung:** Stylesheet wird nicht geladen
- **Risiko:** Keins — prüfen ob es je aktiv war

### 4. `admin/assets/field-intelligence.js`
- **Referenzen:** Kein `wp_enqueue_script`-Aufruf gefunden
- **Begründung:** Script wird nicht geladen
- **Risiko:** Keins — prüfen ob es je aktiv war

### 5. `admin/fields/includes/field-intelligence.php`
- **Referenzen:** Kein `require`/`include`-Aufruf in irgendeiner PHP-Datei gefunden
- **Begründung:** Verwaiste PHP-Datei, kein Einstiegspunkt
- **Risiko:** Keins

> **Hinweis zu #3–5:** Die drei `field-intelligence`-Dateien scheinen zusammenzugehören (ein Feature das nicht eingebunden wurde). Alle drei können zusammen entfernt werden.

### 6. `admin/README-FIELD-INTELLIGENCE.md` + `admin/README-FIELDS-CLEANUP.md`
- **Begründung:** Entwicklungsdokumentation die keinen produktiven Zweck erfüllt und nicht ins Repository gehört
- **Risiko:** Keins

### 7. `.claude/worktrees/clever-banach/` (1.3 MB)
- **Inhalt:** Älterer Claude-Code-Worktree — vollständige Kopie des Plugin-Codes zu einem früheren Zeitpunkt
- **Unique Dateien:** Keine (alle Dateien sind auch im aktuellen Stand oder im `jolly-haibt-dd1a29`-Worktree vorhanden)
- **Begründung:** Nicht mehr benötigt
- **Risiko:** Keins — aber erst löschen wenn `jolly-haibt-dd1a29` gemergt/abgeschlossen ist
- **Befehl:** `git worktree remove .claude/worktrees/clever-banach --force` (oder manuell via `rm -rf` nach `git worktree list` prüfen)

---

## ⚠️ Unklar — Braucht manuelle Prüfung

### 1. `Archiv.zip` (im Plugin-Root)
- **Status:** `.gitignore` enthält `*.zip` — die Datei ist dennoch vorhanden (wurde möglicherweise vor dem .gitignore-Eintrag committed)
- **Frage:** Wird das Archiv noch benötigt? Ist es eine Sicherungskopie des Plugins?
- **Empfehlung:** Prüfen, dann löschen und `git rm --cached Archiv.zip` ausführen falls sie im Git-Index ist

### 2. `.claude/worktrees/jolly-haibt-dd1a29/` (1.4 MB) — **Aktueller Worktree**
- **Status:** Das ist der **aktive** Claude-Code-Arbeitskontext dieser Sitzung
- **Frage:** Enthält neue Dateien (`admin/views/partials/sync-data-loader.php`, `sync-tab-explorer.php`, `sync-tab-sync.php`) die noch nicht in den `master`-Branch gemergt sind?
- **Empfehlung:** Erst nach vollständigem Merge löschen. Worktree-Bereinigung via `git worktree remove` (nicht manuell löschen)

### 3. `debug/debug.log`
- **Status:** `debug/` ist in `.gitignore` eingetragen — die Datei wird (sollte) nicht getrackt sein
- **Frage:** Wird die Log-Datei aktiv befüllt? Soll sie dauerhaft angelegt werden oder nur bei Bedarf?
- **Empfehlung:** Prüfen ob `debug/` im Git-Index existiert (`git ls-files debug/`). Falls ja: `git rm -r --cached debug/`

### 4. `MODULE_OVERVIEW.md` + `ROADMAP.md` (im Plugin-Root)
- **Status:** Entwicklungsdokumentation direkt im Plugin-Root
- **Frage:** Sollen diese Docs versioniert und für den Endnutzer/Entwickler sichtbar sein?
- **Empfehlung:** Falls nur intern: in `dev/` verschieben oder in `.gitignore` aufnehmen

### 5. `sync/api-core-consent.php` + `sync/api-core-consent-v3.php`
- **Status:** Diese Dateien liegen im `sync/`-Root (nicht in `sync/client/`) und werden direkt von anderen Dateien eingebunden
- **Frage:** Sind diese bewusst nicht in `sync/client/` verschoben worden? Sie passen nicht zum Muster der anderen Stub-Dateien
- **Empfehlung:** Klären ob diese in `sync/client/` verschoben und durch Stubs ersetzt werden sollen

### 6. `dev/card-template-new.html` (33 KB)
- **Status:** Nicht in .gitignore, wird von keiner PHP-Datei referenziert
- **Frage:** Ist das ein aktives Entwicklungsreferenz-Dokument oder verwaist?
- **Empfehlung:** In `.gitignore` aufnehmen (`dev/`) und lokal behalten, oder löschen

---

## 📝 .gitignore-Ergänzungen empfohlen

Die aktuelle `.gitignore` ist solide. Folgende Einträge fehlen:

```gitignore
# Claude Code Arbeitsverzeichnisse
.claude/

# Development-Referenz-Dateien
dev/

# Plugin-interne Backups
sync/_backup-v2/

# macOS Root-Artefakte (bereits ._* aber nicht explizit .DS_Store am Root)
.DS_Store
```

### Erläuterung:

| Eintrag | Begründung |
|---|---|
| `.claude/` | Claude Code legt bei jeder Nutzung Worktrees an (je ~1.4 MB). Diese gehören nicht ins Repository |
| `dev/` | Entwicklungsreferenz-HTML-Templates (kein produktiver Code) |
| `sync/_backup-v2/` | Explizit benanntes Backup-Verzeichnis — selbst wenn es mal Inhalt hatte |
| `.DS_Store` | macOS-Artefakt existiert bereits im Plugin-Root — `.gitignore` hat `._*` aber nicht `.DS_Store` explizit für Root |

> **Wichtig:** `.gitignore`-Einträge verhindern nur das _künftige_ Tracken. Bereits getrackte Dateien müssen zusätzlich mit `git rm --cached <datei>` aus dem Index entfernt werden. Gilt insbesondere für `Archiv.zip` und `.DS_Store` falls diese committed sind.

---

## Zusammenfassung

| Kategorie | Anzahl | Aktion |
|---|---|---|
| Kerndateien (produktiv) | ~90 | Keine Aktion |
| Backward-Compatibility Stubs | 15 | Behalten bis bootstrap/load.php refaktoriert |
| Sicher löschbar | 7 Dateien/Ordner | Löschen |
| Unklar (manuelle Prüfung) | 6 Punkte | Review nötig |
| .gitignore-Ergänzungen | 4 Einträge | Hinzufügen |
| Platzeinsparung durch Worktree-Cleanup | ~1.3 MB (clever-banach) | Nach Merge löschen |
