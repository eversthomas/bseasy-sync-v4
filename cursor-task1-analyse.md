# Task 1 — Analyse-Bericht

**Datum:** 2026-05-24  
**Phase:** 1 (Analyse only — keine Code-Änderungen)  
**Plugin:** BSEasy Sync V4

---

## A) Admin-Code im Frontend

### Methodik

Für jede Datei im Scope wurden definierte Funktionen, Klassen und Konstanten erfasst. Anschließend `grep` über die gesamte Codebase — Treffer außerhalb von `admin/` und `bootstrap/admin-*.php` wurden klassifiziert. Frontend = `frontend/` inkl. Shortcode-Renderpfad.

**Aktueller Zustand in `bootstrap/load.php`:** Die Admin-Dateien (Zeilen 150–183) werden **unconditional** geladen — ~1.855 Zeilen Parse-Overhead auf jedem Request.

### Ergebnistabelle

| Funktion/Klasse/Konstante | Definiert in | Frontend-Aufrufe? | Empfohlene Aktion |
|---|---|---|---|
| `bes_admin_handle_post()` | bootstrap/admin-page.php:20 | nein | bleibt in Admin (`is_admin()`) |
| `bes_admin_page()` | bootstrap/admin-page.php:135 | nein (nur String-Callback in admin-menu.php) | bleibt in Admin (`is_admin()`) |
| `bes_bootstrap_register_admin_menu()` | bootstrap/admin-menu.php:15 | nein | bleibt in Admin (`is_admin()`) |
| `bes_bootstrap_admin_enqueue_scripts()` | bootstrap/admin-assets.php:17 | nein | bleibt in Admin (`is_admin()` — reine UI-Assets, kein Cron) |
| `BES_DATA` (Fallback-Define) | admin/fields/fields-handler.php:27 | nein (wird in `bseasy-sync.php` bereits vor `load.php` gesetzt) | bleibt in Admin; Define ist faktisch tot |
| `bes_load_json()` | admin/fields/fields-handler.php:43 | nein | bleibt in Admin |
| `bes_load_json_from_path()` | admin/fields/fields-handler.php:54 | 🟡 indirekt: `bootstrap/legacy-data-paths.php:40` via `function_exists()` — **Caller `bes_load_json_versioned()` wird nirgends aufgerufen** | bleibt in Admin; optional: Legacy-Fallback in `legacy-data-paths.php` entkoppeln (separater Mini-Task) |
| `bes_save_json()` | admin/fields/fields-handler.php:89 | nein | bleibt in Admin |
| `bes_load_fields_config()` | admin/fields/fields-handler.php:147 | nein — Frontend liest `fields-config.json` direkt via `bes_safe_file_get_contents()` in `frontend/views/renderer.php:77`, `frontend/views/map-render.php:97`, `frontend/ajax/ajax-endpoints.php:93` | bleibt in Admin |
| `bes_norm_id()` | admin/fields/fields-handler.php:205 | nein | bleibt in Admin |
| `bes_extract_all_fields()` | admin/fields/fields-handler.php:213 | nein | bleibt in Admin |
| `bes_extract_fields_from_v3()` | admin/fields/fields-handler.php:452 | nein | bleibt in Admin |
| `bes_merge_fields()` | admin/fields/fields-handler.php:516 | nein | bleibt in Admin |
| `bes_load_fields_template()` | admin/fields/includes/fields-template.php:19 | nein | bleibt in Admin |
| `bes_fields_config_exists()` | admin/fields/includes/fields-template.php:57 | nein | bleibt in Admin |
| `bes_fields_template_exists()` | admin/fields/includes/fields-template.php:68 | nein | bleibt in Admin |
| `bes_init_fields_config_from_template()` | admin/fields/includes/fields-template.php:79 | nein | bleibt in Admin |
| `bes_export_config_as_template()` | admin/fields/includes/fields-template.php:104 | nein | bleibt in Admin |
| `bes_merge_template_with_fields()` | admin/fields/includes/fields-template.php:146 | nein | bleibt in Admin |
| `bes_generate_field_label()` | admin/fields/includes/field-label-generator.php:22 | nein | bleibt in Admin |
| `bes_get_known_field_mappings()` | admin/fields/includes/field-label-generator.php:53 | nein | bleibt in Admin |
| `bes_extract_label_from_example()` | admin/fields/includes/field-label-generator.php:94 | nein | bleibt in Admin |
| `bes_generate_label_from_id()` | admin/fields/includes/field-label-generator.php:157 | nein | bleibt in Admin |
| `bes_camelcase_to_label()` | admin/fields/includes/field-label-generator.php:199 | nein | bleibt in Admin |
| `bes_auto_generate_labels()` | admin/fields/includes/field-label-generator.php:232 | nein | bleibt in Admin |
| *(Stub, keine eigenen Funktionen)* | admin/fields/includes/design-settings.php | nein | bleibt in Admin (Stub auf `includes/design/design-settings.php`) |
| `wp_ajax_bes_clear_cache` (anonym) | admin/ajax/ajax-cache.php:29 | nein (nur `wp_ajax_*`, Admin-Kontext) | bleibt in Admin (`is_admin()`) |
| `wp_ajax_bes_cache_stats` (anonym) | admin/ajax/ajax-cache.php:80 | nein | bleibt in Admin (`is_admin()`) |
| `wp_ajax_bes_debug_log` (anonym) | admin/ajax/ajax-debug.php:28 | nein | bleibt in Admin (`is_admin()`) |

### Zusatz-Hinweise

- **`bes_analyze_field_intelligence()`** wird in `fields-handler.php:770` per AJAX aufgerufen, ist aber **nirgends definiert** — toter Code-Pfad im Admin-UI (Field-Intelligence-Tab). Kein Frontend-Bezug; bei Gelegenheit bereinigen.
- Das Frontend nutzt für Feldkonfiguration **keine** Admin-Helper, sondern direkten Dateizugriff + `includes/data/member-repository.php` + `includes/design/design-settings.php` (bereits korrekt geteilt).

**Fazit:** **0 / 28 Symbole müssen nach `includes/` umziehen** vor dem Refactoring. Der Admin-Block kann ohne Vor-Migration in den `is_admin()`-Guard verschoben werden.

---

## B) v3-consent-audit.php

### Git-History

```
git log --follow -- sync/v3-consent-audit.php
```

| Commit | Datum | Aktion |
|---|---|---|
| `07d7f03` | 2026-03-23 | **Initial commit** — Datei angelegt als `sync/v3-consent-audit.php` |
| `a506fe1` | 2026-04-30 | Modifiziert (Token-Hinweis-Erweiterung) |

**Ergebnis:** Die Datei lag **immer im Sync-Root**. Es gibt **keine** Git-History, die eine Verschiebung nach `sync/consent/` und zurück zeigt. `sync/consent/consent-audit.php` existiert nicht.

### Aufrufer (außerhalb der Datei selbst)

| Aufrufer | Kontext |
|---|---|
| `sync/sync-service.php:45-47` | Lädt die Datei beim Sync-Modul-Bootstrap |
| `sync/runtime/cron-v3.php:326` | WP-Cron-Hook `bes_run_audit_consent_v3` → `bseasy_v3_audit_consent()` |
| `admin/ajax/ajax-v3.php:542` | Admin-AJAX plant Cron-Hook `bes_run_audit_consent_v3` |

**Nicht** aufgerufen von: Frontend, `frontend/`, öffentlichen Shortcodes.

### Semantische Einordnung

Die drei exportierten Funktionen (`bseasy_v3_audit_consent`, `bseasy_v3_audit_fetch_ids`, `bseasy_v3_audit_local_consent_check`) sind **rein consent-bezogen** — API-Consent-Vergleich, Serverfilter A/B/C, lokaler Consent-Check. Sie hängen an `sync/api-core-consent-*.php` und `sync/v3-helpers.php`.

Der Sync-Root-Platzierung ist historisch (V3-Monolith-Naming `v3-*`), nicht architektonisch begründet. Es gibt **keinen** technischen Grund, die Datei außerhalb von `sync/consent/` zu halten.

### Empfehlung

**Verschieben** → `sync/consent/consent-audit.php`  
- Stub `sync/v3-consent-audit.php` zurücklassen (Pattern wie andere Stubs)  
- `sync/sync-service.php:45-47` auf neuen Pfad anpassen  
- Kein Frontend-Impact; Cron + Admin-AJAX unverändert funktional

---

## C) country-filter-normalize.php

### Zweck (2–3 Sätze)

Die Datei normalisiert Länderwerte aus EasyVerein-Mitgliedsdaten auf **ISO-3166-1-alpha-2-Codes** (DE/AT/CH etc.) und stellt deutsche Anzeigenamen bereit. Sie erkennt automatisch „Land“-Filterfelder anhand von Feld-ID/Label (`bes_field_is_country_filter`) und dedupliziert/sortiert Filteroptionen für die Filterleiste. Die Maps werden auch an JavaScript übergeben (`bes_country_filter_labels_js`, `bes_country_filter_alias_normalize_js`).

### Einbindung

| Loader | Pfad |
|---|---|
| Direkt | `frontend/includes/filter-helpers.php:4` → `require_once country-filter-normalize.php` |
| Indirekt | `frontend/views/renderer.php:21`, `frontend/views/map-render.php:23`, `frontend/shortcode/bes-members.php:94` laden `filter-helpers.php` |

**Aktiv eingebunden** — kein Überbleibsel.

### Verdachts-Check: Länder-Dimension für Filter

**Bestätigt.** Beispiele:

```210:242:frontend/includes/country-filter-normalize.php
function bes_field_is_country_filter(array $field): bool
{
    $fid = strtolower($field['id'] ?? '');
    // ... erkennt country/staat/land-Felder ...
}
```

```176:207:frontend/includes/country-filter-normalize.php
function bes_normalize_country_token(string $raw): ?string
{
    // „Deutschland“, „DE“, „D“ → ISO-Code
}
```

Verwendung in Filterleiste und Karte:

- `frontend/includes/filter-helpers.php:220-221` — `bes_country_filter_merge_distinct_values()`
- `frontend/views/renderer.php:460-464` — Normalisierung bei Karten-Rendering
- `frontend/views/map-render.php:540-543` — Länderfilter auf der Karte
- `frontend/shortcode/bes-members.php:101-104` — JS-Labels/Alias-Map via `wp_localize_script`

### Empfehlung

**Behalten + dokumentieren**  
- In `MODULE_OVERVIEW.md` unter `frontend/includes/` aufnehmen  
- Header-Kommentar ist bereits vorhanden und ausreichend; ggf. Verweis auf Filterleiste/Karte ergänzen  
- **Nicht** nach `includes/` verschieben — rein Frontend-Concern (Filter-UI + JS-Lokalisierung)

---

## D) Leaflet-Version

| Bibliothek | Version | Beleg |
|---|---|---|
| **Leaflet** | **1.9.4** | `L.version = "1.9.4"` in `frontend/assets/libs/leaflet/leaflet.min.js`; bestätigt durch `wp_enqueue_*`-Version in `frontend/shortcode/bes-members.php:114,122` |
| **Leaflet.markercluster** | **1.5.1** (deklariert) | `wp_enqueue_*`-Version in `frontend/shortcode/bes-members.php:137,144,152`; minifiziertes Bundle enthält **keine** `.version`-Property; Dateigröße lokal 33.852 Bytes (CDN 1.5.1: 34.287 Bytes — leichte Abweichung, vermutlich anderer Build-Zeitpunkt) |

**Datei-Timestamp:** 2025-01-15 (laut `ls -la`)  
**Kein Update empfohlen** — Baseline dokumentiert.

---

## E) Empfohlener Umsetzungsplan (Phase 2, nach Freigabe)

### Schritt 1 — Geteilte Funktionen nach `includes/` verschieben

**Entfällt.** Keine roten Treffer in A).

Optional (nicht blockierend): Latente Abhängigkeit `bes_load_json_from_path` in `bootstrap/legacy-data-paths.php` entfernen oder eigenständigen JSON-Reader in `includes/` nutzen — `bes_load_json_versioned()` wird aktuell nirgends aufgerufen.

### Schritt 2 — Admin-Block in `bootstrap/load.php` konsolidieren

Empfohlene Struktur:

```php
// is_admin() || wp_doing_cron() — bereits vorhanden für sync/, ajax-v3, calendar-handler

if (is_admin()) {
    require bootstrap/admin-page.php
    require bootstrap/admin-menu.php
    require admin/fields/fields-handler.php + includes/*
    require admin/ajax/ajax-cache.php
    require admin/ajax/ajax-debug.php
    require bootstrap/admin-assets.php   // nur UI, kein Cron
}
```

**Begründung Aufteilung:** `admin-assets.php` ist reine UI (CSS/JS) — nur `is_admin()`. Felder-Handler und AJAX-Handler registrieren `wp_ajax_*`-Hooks — ebenfalls nur Admin-Kontext, kein Cron-Bedarf.

### Schritt 3 — `sync/v3-consent-audit.php` verschieben

Wie in B) empfohlen: → `sync/consent/consent-audit.php` + Stub.

### Schritt 4 — `country-filter-normalize.php` dokumentieren

In `MODULE_OVERVIEW.md` eintragen; Datei behalten.

### Schritt 5 — Dokumentation

- `MODULE_OVERVIEW.md`: fehlende Views, Stubs, neue Lade-Reihenfolge, consent-audit-Pfad, country-filter
- `STATUS_REPORT.md`: Abschnitt „Task 1 erledigt“
- `ROADMAP.md`: prüfen und ggf. Task-Status aktualisieren

### Schritt 6 — Verifikation

- [ ] `grep`: Keine Admin-Funktionsaufrufe aus `frontend/` (sollte vorher schon grün sein)
- [ ] Frontend-Shortcode `[bes_members]` ohne PHP-Notices
- [ ] Admin-Seite: Tabs Sync, Felder, Kalender, Map
- [ ] Cron-Sync manuell triggern
- [ ] Karten-Rendering (Leaflet + Cluster)
- [ ] Länderfilter in Filterleiste/Karte (DE/AT/CH-Normalisierung)

---

## Zusammenfassung für Freigabe

| Frage | Antwort |
|---|---|
| **A) Admin im Frontend?** | Nein — 0 Symbole müssen vorher nach `includes/` |
| **B) consent-audit Pfad?** | Immer im Sync-Root; **Verschieben** nach `sync/consent/` empfohlen |
| **C) country-filter-normalize?** | Aktiv, Länderfilter — **Behalten + dokumentieren** |
| **D) Leaflet-Baseline** | Leaflet 1.9.4 \| MarkerCluster 1.5.1 |
| **Aufwand Phase 2** | Gering bis mittel — kein includes/-Blocking, hauptsächlich load.php + Stub + Doku |

**🛑 STOP — Warte auf Freigabe für Phase 2.**
