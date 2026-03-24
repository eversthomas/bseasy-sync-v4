# BS Easy Sync v4 – Architektur und Modulbeschreibung

Technische Beschreibung der **fertig modularisierten** Plugin-Linie `bseasy-sync-v4`.  
Diese Datei ist die **Referenz für Struktur, Verantwortlichkeiten und bewusste Übergänge** – keine Umsetzungs-Roadmap.

**Stand:** Reine Modularisierung (Phasen 1–5) abgeschlossen; **Abschluss-/Stabilisierungs-/Dokumentationsphase** (finale Modulbeschreibung).

---

## A. Kurze Einordnung

### Was ist `bseasy-sync-v4`?

WordPress-Plugin **BS Easy Sync** in der **v4-Arbeitslinie**: gleiche fachliche Grundlage wie das produktive System, aber mit **klarerer Ordner- und Dateistruktur**, um Sync, Admin, Frontend und technische Hilfen getrennter bearbeiten zu können.

### Ziel der v4-Modularisierung

- **Zuerst Struktur, dann Verhalten:** Verantwortlichkeiten sichtbar trennen, ohne die fachliche Sync-/UI-Logik vorschnell zu ändern.
- **Modularisierung vor Optimierung:** Spätere fachliche oder Performance-Verbesserungen sollen **lokaler** und **risikoärmer** möglich sein.
- **Kein Full Rewrite:** Bewährte Logik, Workarounds und Domänenwissen bleiben erhalten; Umbau erfolgte **schrittweise** (Phasen 1–5).

### Was bewusst *nicht* Ziel war

- Keine Sync-Request-/Batch-/Consent-**Optimierung** im Rahmen der Modularisierung.
- Kein UI-Redesign, keine neuen Features.
- Keine vollständige Vereinheitlichung aller Logging-Kanäle oder eine „perfekte“ technische Endarchitektur.
- Keine Umbenennung aller `v3`-Bezeichner in der Codebasis (Dateinamen/Funktionen) nur aus Symbolik.

---

## B. Finale Modulstruktur (Überblick)

| Bereich | Hauptort im Repo | Kurzbeschreibung |
|--------|-------------------|------------------|
| **Bootstrap / Plugin-Kern** | `bseasy-sync.php`, `bootstrap/` | Einstieg, Konstanten, Loader, Lifecycle, Admin-Shell, Debug-Dateilog-Handler |
| **Admin-Domäne** | `admin/` | Einstellungs-UI, Tabs, Felder, Admin-AJAX, Assets |
| **Frontend-Domäne** | `frontend/` | Shortcode, Views, Filter, öffentliches AJAX, statische Assets |
| **Sync-Domäne** | `sync/` | V3-Sync, API-Client, Consent-Hilfen, Explorer, Cron, Audit |
| **Infrastruktur / Querschnitt** | `includes/` | Konstanten, Fehler/Logging-Helfer, Krypto, Cache, Hosting, Filter |
| **Kompatibilität** | Stubs unter `admin/`, `frontend/`, `sync/`, `includes/` | Alte `require`-Pfade bleiben gültig |

---

## C. Verantwortlichkeiten je Modulbereich

### C.1 Bootstrap / Plugin-Kern

| | |
|---|--|
| **Zweck** | Technische Initialisierung: was geladen wird, in welcher Reihenfolge; Admin-Menü und -Seite „einhängen“; Aktivierung/Deaktivierung/Uninstall. |
| **Zentrale Dateien / Ordner** | `bseasy-sync.php`; `bootstrap/load.php` (zentrale Include-Kette); `bootstrap/debug-log.php` (`bes_write_debug_log`, PHP-Error-/Shutdown-Handler im Admin); `bootstrap/legacy-data-paths.php`; `bootstrap/admin-page.php`, `admin-menu.php`, `admin-assets.php`; `bootstrap/plugin-lifecycle.php`. |
| **Liegt hier bewusst** | Globale Ladereihenfolge; frühe Pfadkonstanten in der Hauptdatei; WP-Hooks für Lifecycle mit `__FILE__`. |
| **Liegt hier bewusst *nicht*** | Fachliche Sync-Pipeline, Explorer-Logik, Frontend-Rendering (nur Einbindung). |

### C.2 Admin-Domäne

| | |
|---|--|
| **Zweck** | Konfigurationsoberfläche, Tabs, Feldkonfiguration, kalender-/kartenbezogene Admin-Screens, **HTTP-Schicht** zum Sync (Admin-AJAX). |
| **Zentrale Dateien / Ordner** | `admin/views/` (u. a. `ui-sync.php`, `ui-felder.php`, …); `admin/fields/` inkl. `fields-handler.php` und `admin/fields/includes/`; `admin/ajax/` (`ajax-v3.php`, `ajax-cache.php`, `ajax-debug.php`); `admin/assets/`, `admin/calendar-handler.php`, `admin/templates/`. |
| **Liegt hier bewusst** | UI-Templates und Formular-/AJAX-Anbindung; Sync-Tab-Oberfläche (inhaltlich groß, teilweise nur Partials ausgelagert). |
| **Liegt hier bewusst *nicht*** | Produktiver Sync-Kern (liegt unter `sync/`); zentrale API-Transport-Implementierung (liegt unter `sync/client/`). |

**Stubs:** `admin/ui-main.php`, `admin/fields-handler.php` → kanonische Pfade unter `admin/views/`, `admin/fields/`.

### C.3 Frontend-Domäne

| | |
|---|--|
| **Zweck** | Öffentliche Ausgabe: Shortcode, Listen-/Karten-/Kalender-Rendering, Filter, nicht-administratives AJAX. |
| **Zentrale Dateien / Ordner** | `frontend/includes/design-bridge.php`, `filter-helpers.php`; `frontend/views/`; `frontend/shortcode/bes-members.php`; `frontend/ajax/ajax-endpoints.php`; `frontend/assets/`. |
| **Liegt hier bewusst** | Design-**Fassade** (`bes_frontend_*`) als Delegation zur Admin-Design-API; Trennung Shortcode vs. Renderer. |
| **Liegt hier bewusst *nicht*** | Persistente Sync-Datenlogik; EasyVerein-API-Client (Sync-Domäne). |

**Stubs:** `frontend/renderer.php`, `map-render.php`, `shortcode.php`, `ajax-endpoints.php`, `calendar-render.php`, `filter-helpers.php` → jeweils Weiterleitung auf `views/`, `ajax/`, `includes/`, `shortcode/`.

### C.4 Sync-Domäne

| | |
|---|--|
| **Zweck** | EasyVerein-Anbindung für den V3-Sync: Requests, Token, Member-IDs, Consent-Hilfen, Orchestrierung, Persistenz/Status, Explorer, Cron, Audit. |
| **Zentrale Dateien / Ordner** | `sync/v3/` (Logging, Persistenz, CF-Helfer); `sync/v3-helpers.php` (Loader); `sync/client/` (Requests, Token, Member-Fetch); **Stubs** im `sync/`-Root für stabile Pfade; `sync/consent/` + Loader `sync/api-core-consent.php`; `sync/api-core-consent-v3.php` (Orchestrierung); `sync/api-explorer-v3.php`; `sync/runtime/cron-v3.php` + Stub `sync/cron-v3.php`; `sync/v3-consent-audit.php`. |
| **Liegt hier bewusst** | API- und sync-nahe Logik gebündelt; Consent-Hilfen modular unter `sync/consent/`. |
| **Liegt hier bewusst *nicht*** | Breite Admin-HTML-Layouts (Admin-Domäne); öffentliches Mitgliederkarten-Rendering (Frontend). |

**Kritisch / bewusst nicht fachlich modernisiert:** `sync/api-core-consent-v3.php` (Sync-Kern), `sync/client/api-core-consent-requests.php` (Transport – nur strukturell verschoben).

### C.5 Infrastruktur / Querschnitt (`includes/`)

| | |
|---|--|
| **Zweck** | Wiederverwendbare technische Hilfen: Konstanten, Debug-Log über `error_log`, Token-Krypto, Fehlerklasse, sichere I/O-Helfer, Cache-, Hosting- und Entwickler-Filter. |
| **Zentrale Dateien / Ordner** | `includes/constants/constants.php` (Stub: `includes/constants.php`); `includes/constants-v3.php` (direkt unter `includes/`); `includes/error-handler.php` (Loader); `includes/infra/logging/debug-log.php`; `includes/infra/security/` (Token, Safe-I/O); `includes/infra/errors/error-handler-class.php`; `includes/infra/cache/`, `hosting/`, `filters/` (Stubs im Root von `includes/`). |
| **Liegt hier bewusst** | Querschnitts-Code ohne Screens; klare physische Trennung nach Thema unter `includes/infra/`. |
| **Liegt hier bewusst *nicht*** | `bes_write_debug_log` und Admin-Error-Handler (bleiben in `bootstrap/debug-log.php`, produktiv sensibel). |

### C.6 Kompatibilitäts-Stubs / Übergangsschichten

| Bereich | Stubs (Beispiele) | Zweck |
|--------|-------------------|--------|
| `includes/` | `constants.php`, `error-handler.php`, `cache-utils.php`, `hosting-compatibility.php`, `filters.php` | Alte `BES_DIR . 'includes/…'`-Pfade unverändert gültig |
| `sync/` | `api-core-consent-requests.php`, `api-core-consent-token.php`, `api-core-consent-member-fetch.php`, `cron-v3.php` | Weiterleitung zu `sync/client/`, `sync/runtime/` |
| `admin/` | `ui-main.php`, `fields-handler.php` | Weiterleitung zu `admin/views/`, `admin/fields/` |
| `frontend/` | `renderer.php`, `map-render.php`, … | Weiterleitung zu `views/`, `ajax/`, … |

---

## D. Wichtige Restkopplungen und bewusste Übergänge

- **Stubs:** Bewusst dünne Schicht, um dynamische oder historische Includes nicht zu brechen.
- **Große, nur teilweise entlastete Dateien:** u. a. `admin/views/ui-sync.php`, `sync/api-core-consent-v3.php`, `frontend/views/renderer.php` / `map-render.php` – fachlich unverändert, strukturell nur wo nötig angefasst.
- **Getrennte Logging-Kanäle** (nicht zusammengeführt): u. a. `bes_debug_log` (`includes/infra/logging/`), `bes_write_debug_log` (`bootstrap/debug-log.php`), `bseasy_v3_log` (`sync/v3/`), `bes_consent_log` (`sync/consent/`).
- **V3-Bezeichner:** Sync- und Konstanten-Dateien nutzen weiterhin `v3` im Namen/Inhalt – Umbenennung war kein Modularisierungsziel.
- **Design / Frontend:** Frontend nutzt die Bridge; Admin-`design-settings.php` wird nach dem Frontend-Block geladen – Kopplung zur Laufzeit wie zuvor, bewusst dokumentiert.
- **Ladereihenfolge-Infrastruktur:** `bootstrap/load.php` lädt zuerst `includes/constants.php` (Stub), dann `includes/error-handler.php` (Loader), damit `BES_DEBUG_*` vor `bes_debug_log` gesetzt ist.

---

## E. Definition des modularisierten Zielzustands

### Warum die Modularisierung als abgeschlossen gelten kann

- Die vorgesehenen **Phasen 1–5** (Bootstrap, Admin, Frontend, Sync-Struktur, Infrastruktur) sind **umgesetzt**; die Struktur ist im Repository **nachvollziehbar** und in dieser Datei **beschrieben**.
- Es wurde **keine parallele fachliche Modernisierung** erzwungen; das Verhalten soll dem **Referenzsystem** entsprechen (Regressionstests durch den Betrieb).

### In welchem Sinne ist das Plugin besser bearbeitbar?

- **Lokale Änderungen:** Sync-, Admin-, Frontend- und Infra-Code sind in **eigenen Ordnern** auffindbar; weniger „alles in einer Datei“ ohne erkennbare Grenze.
- **Geringeres Risiko:** Stubs erlauben **schrittweise** Anpassungen und klare **kanonische Pfade** für neue Arbeiten.
- **Klare nächste Schritte:** Fachliche Themen (siehe Abschnitt F) können **einzeln** und **ohne** erneute Gesamt-Umstrukturierung angegangen werden.

### Welche Arten von Änderungen sind künftig typischerweise lokaler?

- **Admin-UI** am Sync-Tab → vor allem `admin/views/`, `admin/ajax/`.
- **Öffentliche Karte/Liste** → vor allem `frontend/views/`, `frontend/ajax/`.
- **API-Transport / Retry** → vor allem `sync/client/`.
- **Consent-Hilfen / Extraktion** → vor allem `sync/consent/`, ohne den Kern in `api-core-consent-v3.php` unnötig mitzuziehen.
- **Technische Querschnitts-Helfer** → vor allem `includes/infra/`.

---

## F. Geeignete nächste *Analysefelder* für spätere Einzelchats

Keine Roadmap – nur **Themen**, die sich für **separate**, fokussierte Unterhaltungen eignen, *nachdem* die Modularisierung steht:

| Thema | Warum separat sinnvoll |
|-------|-------------------------|
| **Sync-Orchestrierung / Request-Strategie** | Hohe fachliche und Risikokomplexität; berührt API-Verhalten. |
| **Consent- / Extraktionslogik** | Domänenlogik, eng mit Datenformaten verknüpft. |
| **Explorer / Feldkatalog** | Eigene Nutzungs- und Datenflüsse neben dem linearen Sync. |
| **Admin-Sync-UI** | UX und JS-Einbettungen; große Dateien wie `ui-sync.php`. |
| **Frontend-Filter / Kartenlogik** | Performance und UX; getrennt vom Sync-Kern. |
| **Logging-Vereinheitlichung** | Querschnitt; viele Kanäle, risikoarm nur mit Tests. |
| **Technische Altlasten / Stubs** | Entscheidung pro Stub, ob langfristig Kanon-Pfad reicht. |
| **Hosting / Cache / Transients** | Betriebsnahe Themen, getrennt von Sync-Fachlogik. |

---

## Anhang: Phasen der Umsetzung (Kurzreferenz)

| Phase | Inhalt |
|-------|--------|
| **1** | Bootstrap entlastet: `bootstrap/*`, schlankes `bseasy-sync.php` |
| **2** | Admin gruppiert: `admin/views/`, `admin/fields/`, Stubs |
| **3** | Frontend gruppiert: `frontend/views/`, Bridge, Stubs |
| **4** | Sync strukturiert: `sync/v3/`, `sync/client/`, `sync/consent/`, `sync/runtime/`, Stubs |
| **5** | Infrastruktur: `includes/infra/`, `includes/constants/`, Stubs |

Die **Detailbeschreibung** der aktuellen Architektur ist die **Hauptdokumentation** in den Abschnitten A–F; dieser Anhang dient nur der **Nachverfolgbarkeit** der Umsetzungsreihenfolge.

---

*Dokumentversion: finale Modulbeschreibung nach Abschluss der reinen Modularisierung (v4).*
