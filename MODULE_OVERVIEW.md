# BS Easy Sync V4 — Modulübersicht

Dieses Dokument listet alle Module des Plugins mit ihren Dateien und Funktionen.

**Architekturregeln (V4):**
- `includes/` → immer geladen; keine Abhängigkeit zu anderen Modulen
- `sync/` → nur in Admin/Cron; Einstiegspunkt von außen: `sync/sync-service.php`
- `admin/` → nur in Admin/Cron
- `frontend/` → immer geladen
- Mitgliederdaten: ausschließlich über `includes/data/member-repository.php`
- Design-Einstellungen: ausschließlich über `includes/design/design-settings.php`

**Weitere Dokumentation:**
- `README.md` (Setup/Shortcodes, High-Level Überblick)
- `dev/ROADMAP.md` (interne Roadmap, nicht produktionsrelevant)

---

## Inhaltsverzeichnis

1. [bootstrap/](#1-bootstrap)
2. [includes/](#2-includes)
3. [sync/](#3-sync)
4. [admin/](#4-admin)
5. [frontend/](#5-frontend)

---

## 1. `bootstrap/`

Plugin-Initialisierung, Ladereihenfolge, Lifecycle.

### `bootstrap/load.php`
Zentrale Ladereihenfolge aller Module. Einzige Datei, die direkt aus `bseasy-sync.php` geladen wird.

**Lade-Reihenfolge (V4):**
1. **Immer:** `includes/` (Konstanten, Repository, Design, Infrastruktur), `bootstrap/debug-log.php`, `bootstrap/legacy-data-paths.php`
2. **`is_admin() || wp_doing_cron()`:** `sync/sync-service.php`, `sync/runtime/cron-v3.php`, `admin/ajax/ajax-v3.php`, `admin/calendar-handler.php`
3. **Immer:** `frontend/` (Stubs → Views/Shortcode/AJAX)
4. **`is_admin()` only:** `bootstrap/admin-page.php`, `bootstrap/admin-menu.php`, `admin/fields/*`, `admin/ajax/ajax-cache.php`, `admin/ajax/ajax-debug.php`, `bootstrap/admin-assets.php`
5. **Immer:** `bootstrap/plugin-lifecycle.php`

### `bootstrap/debug-log.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_write_debug_log(string $msg)` | Schreibt Nachricht in `debug.log` mit Timestamp |
| `bes_wp_error_handler(...)` | PHP-Fehler-Handler (registriert via `set_error_handler`) |
| `bes_wp_shutdown_handler()` | PHP-Shutdown-Handler für fatale Fehler |
| `bes_bootstrap_register_admin_error_handlers()` | Registriert Error- und Shutdown-Handler im Admin-Kontext |

### `bootstrap/admin-page.php`
Implementiert das PRG-Pattern (Post/Redirect/Get): POST-Verarbeitung via `admin_init`-Hook, Ergebnisse werden per `set_transient` über den Redirect hinaus weitergegeben.

| Funktion | Beschreibung |
|----------|-------------|
| `bes_admin_handle_post(): void` | Verarbeitet POST-Formulare (Token, Consent-Feld, Batch-Größe), schreibt Ergebnis per `add_settings_error()` + Transient, leitet dann per `wp_safe_redirect()` weiter |
| `bes_admin_page(): void` | Rendert die Admin-Hauptseite; restauriert Settings-Errors aus Transient nach PRG-Redirect |

### `bootstrap/admin-menu.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_bootstrap_register_admin_menu()` | Registriert den Menüeintrag im WP-Admin *(Hook: `admin_menu`)* |

### `bootstrap/admin-assets.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_bootstrap_admin_enqueue_scripts()` | Bindet Admin-CSS/JS ein *(Hook: `admin_enqueue_scripts`)* |

### `bootstrap/plugin-lifecycle.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_plugin_activate()` | Aktivierung: erstellt Verzeichnisse, setzt Default-Optionen |
| `bes_plugin_deactivate()` | Deaktivierung: leert Caches |
| `bes_migrate_to_versioned_dirs()` | Migriert V2-Dateipfade zu versionierten Verzeichnissen |
| `bes_plugin_uninstall()` | Deinstallation: entfernt alle Plugin-Daten |

### `bootstrap/legacy-data-paths.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_get_data_dir()` | Gibt V2-Datenverzeichnis zurück (Fallback für alte Pfade) |
| `bes_load_json_versioned(string $file)` | Lädt JSON aus V2-Verzeichnis (Abwärtskompatibilität) |

---

## 2. `includes/`

Infrastruktur und gemeinsam genutzte Ressourcen. Keine Abhängigkeit zu `sync/`, `admin/` oder `frontend/`.

### `includes/constants/constants.php` *(geladen via Stub `includes/constants.php`)*
Definiert alle globalen Konstanten: `BES_VERSION`, `BES_DIR`, `BES_URL`, `BES_DATA`, `BES_IMG`, `BES_TEXT_DOMAIN`.

### `includes/constants-v3.php`
Definiert V3-Konstanten:
- Verzeichnisse: `BES_DATA_V3`, `BES_DATA_V3_URL`
- Dateinamen: `BES_V3_MEMBERS_FILE`, `BES_V3_MEMBERS_PART_PREFIX`, `BES_V3_STATUS_FILE`, `BES_V3_FIELD_CATALOG`, `BES_V3_SELECTION`, `BES_V3_HISTORY_FILE`, `BES_V3_LOG_FILE`, `BES_V3_DEBUG_LOG_FILE`
- Einstellungen: `BES_V3_BATCH_SIZE_DEFAULT/MIN/MAX`, `BES_V3_EXPLORER_SAMPLE_DEFAULT/MIN/MAX`
- Hooks: `BES_V3_CRON_HOOK`, `BES_V3_EXPLORER_CRON_HOOK`
- Optionen: `BES_V3_OPTION_PREFIX`, `BES_V3_REQUIRED_FIELDS`, `BES_V3_PII_PATTERNS`

---

### `includes/data/member-repository.php` *(neu in V4)*
Einzige erlaubte Schnittstelle zum Lesen von `members_consent_v3.json`. Kein `sync/`-Code in `frontend/` oder `admin/`.

| Funktion | Beschreibung |
|----------|-------------|
| `bes_members_get_all(): array` | Gibt alle synchronisierten Mitglieder zurück (data-Array) |
| `bes_members_get_count(): int` | Anzahl der Mitglieder |
| `bes_members_get_file_mtime(): int` | Unix-Timestamp der letzten Dateiänderung (für Cache-Keys) |
| `bes_members_file_exists(): bool` | Prüft ob members.json vorhanden und nicht leer ist |
| `bes_members_get_file_path(): string` | Gibt absoluten Pfad zur members.json zurück |

---

### `includes/design/design-settings.php` *(neu in V4, verschoben aus `admin/`)*
Design-Einstellungen liegen hier, da sowohl Admin (Speichern) als auch Frontend (CSS-Generierung) sie benötigen.

| Funktion | Beschreibung |
|----------|-------------|
| `bes_get_default_design_settings(): array` | Liefert Standard-Farbwerte für Cards |
| `bes_get_design_settings(): array` | Lädt gespeicherte Design-Einstellungen (mit Defaults gemergt) |
| `bes_save_design_settings(array $settings): bool` | Speichert und validiert Design-Einstellungen, leert Cache |
| `bes_validate_color(string $color): bool` | Validiert Hex-, RGB-, RGBA- und CSS-Farbwerte |
| `bes_generate_design_css(bool $with_style_tags): string` | Generiert Inline-CSS aus gespeicherten Farbwerten |

**Wichtige Keys in `bes_card_design_settings`:**
- Card: `card_bg`, `card_border`, `card_text`, `card_link`, `card_stripe`
- Badge: `badge_bg`, `badge_text`
- Button: `button_bg`, `button_bg_hover`, `button_text`
- Toggle: `image_shadow` (bool)

---

### `includes/infra/security/token-crypto.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_encrypt_token(string $token): string` | Verschlüsselt API-Token mit AES-256-CBC + HMAC-SHA256 |
| `bes_decrypt_token(string $encrypted): string` | Entschlüsselt gespeicherten API-Token |

### `includes/infra/security/safe-io.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_safe_json_decode(string $json, bool $assoc): mixed` | JSON-Decode mit Fehlerbehandlung |
| `bes_safe_file_get_contents(string $path, string $allowed_dir): ?string` | Liest Datei mit Path-Traversal-Schutz |
| `bes_check_rate_limit(string $endpoint, int $max, int $window): bool` | Rate-Limiting für AJAX-Endpunkte via Transients |

### `includes/infra/cache/cache-utils.php` *(geladen via Stub `includes/cache-utils.php`)*
| Funktion | Beschreibung |
|----------|-------------|
| `bes_clear_render_cache(): void` | Löscht alle Render-Cache-Transients (`bes_members_render_*`, `bes_members_map_*`) |
| `bes_generate_cache_key(array $files): string` | Generiert Cache-Key aus Dateipfaden + Timestamps |
| `bes_get_cached(string $key): mixed` | Liest Wert aus Transient-Cache |
| `bes_set_cached(string $key, mixed $value, int $ttl): void` | Schreibt Wert in Transient-Cache |
| `bes_get_cache_stats(): array` | Gibt Cache-Statistiken zurück (Anzahl, Größe) |

### `includes/infra/hosting/hosting-compatibility.php` *(geladen via Stub `includes/hosting-compatibility.php`)*
| Funktion | Beschreibung |
|----------|-------------|
| `bes_detect_hosting_provider(): string` | Erkennt Hosting-Anbieter (WP Engine, Kinsta, etc.) |
| `bes_get_hosting_limits(): array` | Gibt Ressourcenlimits des Hosts zurück |
| `bes_get_safe_timeout(): int` | Gibt sicheren Timeout-Wert zurück |
| `bes_get_safe_batch_size(): int` | Gibt sichere Batch-Größe für diesen Host zurück |
| `bes_safe_set_time_limit(int $seconds): void` | Erhöht PHP-Ausführungslimit sicher |
| `bes_safe_increase_memory(string $target): void` | Erhöht PHP-Memory-Limit sicher |
| `bes_convert_to_bytes(string $val): int` | Wandelt PHP-ini-Format (z.B. `256M`) in Bytes um |
| `bes_bytes_to_string(int $bytes): string` | Wandelt Bytes in lesbare Größe um |
| `bes_ensure_writable_directory(string $dir): bool` | Stellt sicher, dass Verzeichnis schreibbar ist |
| `bes_ensure_writable_file(string $file): bool` | Stellt sicher, dass Datei schreibbar ist |
| `bes_safe_file_put_contents(string $file, string $data): bool` | Schreibt Datei mit Retry-Logik |
| `bes_check_wp_cron_works(): bool` | Prüft ob WP-Cron funktioniert |
| `bes_get_hosting_recommendations(): array` | Gibt Optimierungsempfehlungen für diesen Host zurück |

### `includes/infra/logging/debug-log.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_debug_log(string $msg, string $level, string $context): void` | Schreibt in WP-Debug-Log |

### `includes/infra/filters/filters.php` *(geladen via Stub `includes/filters.php`)*
Developer-API: Wrapper um WordPress-Filter und -Actions.

| Funktion | Hook | Beschreibung |
|----------|------|-------------|
| `bes_filter_cache_duration(int $seconds): int` | `bes_cache_duration` | Cache-Dauer überschreiben |
| `bes_filter_batch_size(int $size): int` | `bes_batch_size` | Batch-Größe überschreiben |
| `bes_filter_api_timeout(int $seconds): int` | `bes_api_timeout` | API-Timeout überschreiben |
| `bes_filter_allowed_html(array $tags): array` | `bes_allowed_html` | Erlaubtes HTML überschreiben |
| `bes_filter_map_markers(array $markers): array` | `bes_map_markers` | Karten-Marker filtern |
| `bes_filter_members_data(array $members): array` | `bes_members_data` | Mitgliederdaten vor Ausgabe filtern |
| `bes_do_after_sync(array $result): void` | `bes_after_sync` | Action nach Sync-Abschluss |
| `bes_do_before_sync(): void` | `bes_before_sync` | Action vor Sync-Start |

### `includes/infra/errors/error-handler-class.php`
| Klasse | Beschreibung |
|--------|-------------|
| `BES_Error_Handler` | Zentrale Fehlerbehandlungs-Klasse |

---

## 3. `sync/`

EasyVerein REST API v2.0 Integration, Datensynchronisation, Explorer. Wird nur in Admin/Cron geladen. Einstiegspunkt von außen: `sync/sync-service.php`.

---

### `sync/sync-service.php` *(neu in V4 — öffentliche API-Fassade)*
Einzige Datei, die von außerhalb von `sync/` eingebunden werden darf. Lädt alle internen Sync-Dateien und stellt stabile Wrapper-Funktionen bereit.

| Funktion | Beschreibung |
|----------|-------------|
| `bes_sync_run(int $offset, int $limit): array` | Sync-Durchlauf starten |
| `bes_sync_get_status(): array` | Aktuellen Status aus `status_v3.json` lesen |
| `bes_sync_cancel(): void` | Sync abbrechen (Status → `cancelled`, Cron-Hook entfernen) |
| `bes_sync_reset(): void` | Sync zurücksetzen (Statusdatei und Part-Optionen löschen) |
| `bes_sync_get_selection(): array` | Aktuelle Feldauswahl laden |
| `bes_sync_save_selection(array $selection): bool` | Feldauswahl speichern |
| `bes_sync_merge_parts(): array` | Part-Dateien zu finaler `members_consent_v3.json` zusammenführen |
| `bes_sync_get_catalog(): ?array` | Feldkatalog aus `field_catalog_v3.json` laden |
| `bes_explorer_schedule(int $sample_size, bool $fresh): bool` | Explorer asynchron via WP-Cron planen |
| `bes_explorer_cancel(): void` | Explorer abbrechen |
| `bes_audit_schedule(): bool` | Consent-Audit asynchron via WP-Cron planen |
| `bes_audit_get_status(): array` | Audit-Status und -Ergebnis laden |

---

### `sync/api-core-consent-v3.php` — Haupt-Sync-Engine
| Funktion | Beschreibung |
|----------|-------------|
| `bseasy_v3_run_sync(int $offset, int $limit): array` | Hauptfunktion: Mitglieder holen, extrahieren, speichern |
| `bseasy_v3_load_selection(): array` | Gespeicherte Feldauswahl laden |
| `bseasy_v3_save_selection(array $selection): bool` | Feldauswahl speichern |
| `bseasy_v3_fetch_member_ids(): array` | Alle Mitglieder-IDs von der API holen |
| `bseasy_v3_process_member_batch(array $ids, array $selection): array` | Batch von Mitgliedern abrufen und verarbeiten |
| `bseasy_v3_get_path_value(array $data, string $path): mixed` | Verschachtelten Wert per Punkt-Pfad abrufen |
| `bseasy_v3_extract_selected_fields(array $member, array $selection): array` | Ausgewählte Felder aus Mitglied-Record extrahieren |
| `bseasy_v3_merge_parts(): array` | Part-Dateien zu finaler JSON zusammenführen |

### `sync/api-explorer-v3.php` — Feld-Entdeckung
| Funktion | Beschreibung |
|----------|-------------|
| `bseasy_v3_run_explorer(int $sample_size, bool $fresh): void` | Feldstruktur der API erkunden, `field_catalog_v3.json` schreiben |
| `bseasy_v3_explorer_is_cancelled(): bool` | Prüft ob Explorer abgebrochen wurde |
| `bseasy_v3_fetch_sample_members(int $size): array` | Stichprobe von Mitgliedern für Analyse holen |
| `bseasy_v3_analyze_member_fields(array $members): array` | Member-Felder analysieren |
| `bseasy_v3_analyze_contact_fields(array $members): array` | Contact-Felder analysieren |
| `bseasy_v3_analyze_custom_fields(array $members): array` | Custom-Felder analysieren |
| `bseasy_v3_flatten_keys_with_values(array $data, string $prefix): array` | Verschachtelte Struktur zu flachen Schlüssel-Wert-Paaren |

### `sync/v3-consent-audit.php` *(Compatibility-Stub)*
Delegiert an `sync/consent/consent-audit.php`.

### `sync/consent/consent-audit.php` — Consent-Datenprüfung
| Funktion | Beschreibung |
|----------|-------------|
| `bseasy_v3_audit_consent(): array` | Vergleicht lokale Daten mit API-Consent-Status (Serverfilter A/B/C, Differenzliste) |
| `bseasy_v3_audit_fetch_ids(): array` | Holt Mitglieder-IDs für Audit-Vergleich |
| `bseasy_v3_audit_local_consent_check(): array` | Prüft Übereinstimmung lokal/API pro Mitglied |

Wird von `sync/sync-service.php` geladen; Cron-Hook `bes_run_audit_consent_v3` in `sync/runtime/cron-v3.php`.

---

### Sync-Root-Stubs *(Abwärtskompatibilität)*

Dateien im `sync/`-Root, die nur auf die eigentliche Implementierung weiterleiten:

| Stub | Ziel |
|------|------|
| `sync/cron-v3.php` | `sync/runtime/cron-v3.php` |
| `sync/v3-consent-audit.php` | `sync/consent/consent-audit.php` |
| `sync/api-core-consent-member-fetch.php` | `sync/client/api-core-consent-member-fetch.php` |
| `sync/api-core-consent-requests.php` | `sync/client/api-core-consent-requests.php` |
| `sync/api-core-consent-token.php` | `sync/client/api-core-consent-token.php` |

---

### `sync/client/api-core-consent-requests.php` — HTTP-Kommunikation
| Funktion | Beschreibung |
|----------|-------------|
| `bes_consent_api_get(string $endpoint, string $token): array` | GET-Request an EasyVerein-API |
| `bes_consent_api_safe_get(string $endpoint, string $token): array` | GET mit automatischem Retry |
| `bes_consent_api_safe_get_try_query(string $endpoint, string $token): array` | Retry mit alternativem Query-Parameter |
| `bes_consent_api_get_custom_field_name_by_id(int $id, string $token): string` | Custom-Feld-Name per ID abrufen |
| `bes_consent_api_get_custom_field_meta_by_id(int $id, string $token): array` | Custom-Feld-Metadaten per ID abrufen |
| `bes_consent_api_fetch_all_list(string $endpoint, string $token): array` | Alle paginierten Ergebnisse eines Endpunkts abrufen |
| `bes_consent_api_fetch_all_list_with_meta(string $endpoint, string $token): array` | Paginierte Ergebnisse mit Metadaten abrufen |

### `sync/client/api-core-consent-token.php` — Token-Verwaltung
| Funktion | Beschreibung |
|----------|-------------|
| `bes_consent_api_refresh_token(string $new_token): void` | Token rotieren und verschlüsselt speichern |

### `sync/client/api-core-consent-member-fetch.php` — Mitglieder-IDs
| Funktion | Beschreibung |
|----------|-------------|
| `bes_consent_api_fetch_member_ids(string $token, int $consent_field_id): array` | Alle Mitglieder-IDs mit gesetztem Consent-Feld holen |

---

### `sync/consent/consent-bootstrap.php`
Stellt sicher, dass `BES_DATA` und zugehörige Upload-Pfade definiert sind (z. B. bei WP-Cron ohne vollständigen Plugin-Bootstrap). Wird von `sync/api-core-consent.php` als erstes Consent-Modul geladen.

### `sync/consent/consent-config.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_get_consent_field_id(): int` | Gibt konfigurierte Consent-Feld-ID zurück |

### `sync/consent/consent-extraction.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_consent_extract_custom_fields_with_options(array $member, string $token): array` | Extrahiert Custom-Felder mit aufgelösten Select-Optionen |
| `bes_consent_extract_flat_contact(array $contact): array` | Flacht verschachtelte Contact-Struktur ab |
| `bes_consent_norm_list(mixed $data): array` | Normalisiert API-Listen-Response zu Array |

### `sync/consent/consent-select-options.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_consent_resolve_select_options_batch(array $field_ids, string $token): array` | Löst Select-Optionen für mehrere Felder auf (batched) |
| `bes_consent_get_cached_option(string $key): ?string` | Gecachte Select-Option abrufen |
| `bes_consent_cache_option(string $key, string $label): void` | Select-Option cachen |
| `bes_consent_clear_option_cache(): void` | Optionen-Cache leeren |
| `bes_consent_resolve_select_option(int $field_id, string $value, string $token): string` | Einzelne Select-Option auflösen |

### `sync/consent/consent-geocoding.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_geocode_contact_fallback(array $contact): ?array` | Geocodiert Kontaktadresse (Fallback) |
| `bes_geocode_nominatim(string $address): ?array` | Geocodierung via OpenStreetMap Nominatim |

### `sync/consent/consent-logging-debug.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_consent_log(string $msg, string $level): void` | Schreibt in `consent.log` |
| `bes_debug_custom_field(array $field): void` | Gibt Custom-Feld-Debug-Info aus |
| `bes_debug_snapshot_custom_fields(array $fields): void` | Erstellt Debug-Snapshot aller Custom-Felder |
| `bes_debug_api_request(string $url, array $response): void` | Loggt API-Request-Details |

### `sync/consent/consent-health-json.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_consent_health_check(): array` | Führt Gesundheitsprüfung der Sync-Daten durch |
| `bes_consent_write_health_check(array $result): void` | Speichert Health-Check-Ergebnis |
| `bes_consent_analyze_existing_json(): array` | Analysiert vorhandene members.json auf Konsistenz |

### `sync/consent/consent-debug-member.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_consent_debug_single_member(int $member_id, string $token): void` | Debuggt Sync-Prozess für einzelnes Mitglied |

---

### `sync/v3/v3-persistence.php` — Datei-I/O & Locking
| Funktion | Beschreibung |
|----------|-------------|
| `bseasy_v3_update_status(int $progress, int $total, string $msg, string $state, array $extra): void` | Schreibt Fortschritt in `status_v3.json` |
| `bseasy_v3_save_history(array $status): void` | Speichert Sync-Verlauf (letzte 10 Einträge) |
| `bseasy_v3_safe_write_json(string $file, mixed $data): bool` | Atomares Schreiben via Temp-Datei + Rename |
| `bseasy_v3_read_json(string $file): ?array` | Liest JSON-Datei mit Fehlerbehandlung |
| `bseasy_v3_mask_pii(string $value): string` | Maskiert E-Mail, Telefon, Adresse in Logs |
| `bseasy_v3_mask_pii_array(array $data, array $keys): array` | Rekursives PII-Masking für Arrays |
| `bseasy_v3_set_lock(string $key, int $timeout): bool` | Setzt Transient-basierten Lock |
| `bseasy_v3_release_lock(string $key): void` | Entfernt Lock |
| `bseasy_v3_is_locked(string $key): bool` | Prüft ob Lock aktiv ist |
| `bseasy_v3_setup_directories(): void` | Erstellt Datenverzeichnis mit `index.php` + `.htaccess` |

### `sync/v3/v3-logging.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bseasy_v3_log(string $msg, string $level): void` | Schreibt in `sync-v3.log` |
| `bseasy_v3_debug_log(string $msg): void` | Schreibt in `debug-v3.log` (nur wenn `BES_DEBUG_MODE=true`) |

### `sync/v3/v3-field-options.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bseasy_v3_option_id_from_url(string $url): ?int` | Extrahiert Options-ID aus API-URL |
| `bseasy_v3_get_cached_option_label(int $field_id, string $value): ?string` | Gecachtes Options-Label abrufen |
| `bseasy_v3_set_cached_option_label(int $field_id, string $value, string $label): void` | Options-Label cachen |
| `bseasy_v3_fetch_custom_field_option_label(int $field_id, string $value, string $token): string` | Options-Label von API holen |
| `bseasy_v3_get_cf_select_options_map(int $field_id, string $token): array` | Alle Options eines Select-Felds holen |
| `bseasy_v3_resolve_selected_options_labels(array $values, int $field_id, string $token): array` | Mehrere ausgewählte Options auflösen |
| `bseasy_v3_resolve_selected_options(array $raw, int $field_id, string $token): array` | Options-Array auflösen |
| `bseasy_v3_get_cf_effective_value(array $field, string $token): mixed` | Effektiven Feldwert mit Options-Label zurückgeben |

### `sync/runtime/cron-v3.php` — WP-Cron-Hooks
Registriert die WP-Cron-Actions:
- `bes_run_consent_v3_single` → startet `bseasy_v3_run_sync()`
- `bes_run_explorer_v3` → startet `bseasy_v3_run_explorer()`
- `bes_run_audit_consent_v3` → startet `bseasy_v3_audit_consent()`

---

## 4. `admin/`

Admin-Interface, Feldkonfiguration, AJAX-Handler für Admin-Operationen.

### `admin/ajax/ajax-v3.php` — Sync-AJAX-Handler

Registriert alle `wp_ajax_bes_v3_*`-Actions. Bindet ausschließlich `sync/sync-service.php` ein.

| AJAX-Action | Beschreibung |
|-------------|-------------|
| `wp_ajax_bes_v3_run_explorer` | Explorer starten (async via WP-Cron) |
| `wp_ajax_bes_v3_start_sync` | Sync starten (async via WP-Cron) |
| `wp_ajax_bes_v3_status` | Sync-/Explorer-Status abrufen |
| `wp_ajax_bes_v3_stop_sync` | Sync stoppen |
| `wp_ajax_bes_v3_stop_explorer` | Explorer stoppen |
| `wp_ajax_bes_v3_reset_sync` | Sync zurücksetzen |
| `wp_ajax_bes_v3_merge_parts` | Part-Dateien zusammenführen |
| `wp_ajax_bes_v3_load_catalog` | Feldkatalog laden |
| `wp_ajax_bes_v3_load_selection` | Feldauswahl laden |
| `wp_ajax_bes_v3_save_selection` | Feldauswahl speichern |
| `wp_ajax_bes_v3_reset_selection` | Feldauswahl auf Pflichtfelder zurücksetzen |
| `wp_ajax_bes_v3_audit_consent` | Consent-Audit starten (async) |
| `wp_ajax_bes_v3_audit_status` | Audit-Status abrufen |

### `admin/ajax/ajax-cache.php`
| AJAX-Action | Beschreibung |
|-------------|-------------|
| `wp_ajax_bes_clear_cache` | Alle Plugin-Caches leeren |
| `wp_ajax_bes_cache_stats` | Cache-Statistiken abrufen |

### `admin/ajax/ajax-debug.php`
| AJAX-Action | Beschreibung |
|-------------|-------------|
| `wp_ajax_bes_debug_log` | Inhalt der Debug-Log-Datei abrufen |

---

### `admin/fields/fields-handler.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_load_json(string $filename): ?array` | Lädt JSON aus Datenverzeichnis |
| `bes_load_json_from_path(string $path): ?array` | Lädt JSON von absolutem Pfad mit Validierung |
| `bes_save_json(string $filename, mixed $data): bool` | Speichert JSON im Datenverzeichnis |
| `bes_load_fields_config(): array` | Lädt `fields-config.json` (Feldanzeigeconfig) |
| `bes_norm_id(string $id): string` | Normalisiert Feld-IDs |
| `bes_extract_all_fields(): array` | Scannt API und extrahiert verfügbare Felder |
| `bes_extract_fields_from_v3(): array` | Extrahiert Felder aus V3-Mitglied-Records |
| `bes_merge_fields(array $scanned, array $config): array` | Mergt auto-erkannte Felder mit Nutzerkonfiguration |

### `admin/fields/includes/field-label-generator.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_generate_field_label(string $field_id, array $field_data): string` | Erzeugt benutzerfreundliches Label für ein Feld |
| `bes_get_known_field_mappings(): array` | Gibt bekannte Feld-ID → Label Mappings zurück |
| `bes_extract_label_from_example(array $field): string` | Extrahiert Label-Hinweis aus Feldbeispiel |
| `bes_generate_label_from_id(string $id): string` | Wandelt Feld-ID in lesbares Label um |
| `bes_camelcase_to_label(string $str): string` | Konvertiert CamelCase zu lesbarem Text |
| `bes_auto_generate_labels(array $fields): array` | Generiert Labels für alle Felder automatisch |

### `admin/fields/includes/fields-template.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_load_fields_template(): array` | Lädt Standard-Template-Konfiguration |
| `bes_fields_config_exists(): bool` | Prüft ob Nutzerkonfiguration vorhanden ist |
| `bes_fields_template_exists(): bool` | Prüft ob Template-Datei vorhanden ist |
| `bes_init_fields_config_from_template(): bool` | Initialisiert Konfiguration aus Template |
| `bes_export_config_as_template(): bool` | Exportiert aktuelle Konfiguration als Template |
| `bes_merge_template_with_fields(array $fields): array` | Mergt Template mit auto-erkannten Feldern |

### `admin/fields/includes/design-settings.php` *(Compatibility-Stub)*
Delegiert an `includes/design/design-settings.php`. Nur für Abwärtskompatibilität.

### Feld-Badges (Backend-Konfiguration)
Badges sind ein Feld-Flag in `fields-config.json` und werden im Feld-UI als Checkbox gepflegt:
- Checkbox: „Als Badge anzeigen“
- Speicherung: via `wp_ajax_bes_save_fields` (in `admin/fields/fields-handler.php`)
- Rendering: Frontend sammelt Badge-Feld-IDs und erzeugt daraus Badge-Pills

### `admin/calendar-handler.php`
Verarbeitet Kalender-Konfigurationen. *(Hook: `admin_post_bes_save_calendars`)*

### `admin/views/ui-main.php`
Admin-Hauptlayout: Tab-Navigation (Felder, Kalender, Map, Sync), lädt die Tab-Views per Include. Wird von `bes_admin_page()` eingebunden. Gibt `settings_errors('bes_settings')` nach PRG-Redirect aus.

### `admin/views/ui-felder.php`
Tab „Felder“: Feldverwaltung-UI (Sidebar per JS, Drag & Drop, Filter-/Badge-Konfiguration). Kommuniziert mit `wp_ajax_bes_get_fields` / `wp_ajax_bes_save_fields`.

### `admin/views/ui-kalender.php`
Tab „Kalender“: Verwaltung mehrerer iCal-Kalender (Name, ICS-URL, Event-Limit). Formular-Action: `admin_post_bes_save_calendars` via `admin/calendar-handler.php`.

### `admin/views/ui-map.php`
Tab „Map“: Map-Einstellungen (Center, Zoom, Marker-Stil). Speichert Optionen per POST mit Nonce `bes_map_settings`.

### `admin/views/ui-sync.php` — Sync-Tab Layout
Dünnes Layout-Skelett; enthält die gemeinsamen JavaScript-Handler für Explorer, Feldauswahl und Sync.
Bindet via `require_once` alle vier Partials ein (s.u.).

### `admin/views/partials/sync-data-loader.php`
PHP-Datenvorbereitung für den Sync-Tab (kein HTML). Stellt alle PHP-Variablen bereit, die von den HTML-Partials benötigt werden:
`$cache_stats`, `$explorer_last_run`, `$explorer_field_count`, `$explorer_running`, `$catalog_file`, `$catalog_exists`, `$last_sync_time_v3`, `$members_with_consent_v3`, `$explorer_status`

### `admin/views/partials/sync-tab-cache-card.php`
HTML: Cache-Status-Karte (bereits vorhanden).

### `admin/views/partials/sync-onboarding-hint.php`
HTML: Onboarding-Hinweis (bereits vorhanden).

### `admin/views/partials/sync-tab-explorer.php`
HTML: V3 Explorer + Feldauswahl-Karte (`.bes-sync-grid`).

### `admin/views/partials/sync-tab-sync.php`
HTML: V3 Sync-Karte (Start, Stop, Reset, Merge, Consent Audit).

---

## 5. `frontend/`

Öffentliche Ausgabe: Shortcode, Rendering, AJAX-Filterung.

### Frontend-Root-Stubs *(Abwärtskompatibilität)*

| Stub | Ziel |
|------|------|
| `frontend/renderer.php` | `frontend/views/renderer.php` |
| `frontend/map-render.php` | `frontend/views/map-render.php` |
| `frontend/calendar-render.php` | `frontend/views/calendar-render.php` |
| `frontend/shortcode.php` | `frontend/shortcode/bes-members.php` |
| `frontend/ajax-endpoints.php` | `frontend/ajax/ajax-endpoints.php` |
| `frontend/filter-helpers.php` | `frontend/includes/filter-helpers.php` |

Diese Stubs werden von `bootstrap/load.php` geladen; die Implementierung liegt in den Unterverzeichnissen.

---

### `frontend/shortcode/bes-members.php`
Registriert `[bes_members]`-Shortcode.

| Attribut | Werte | Beschreibung |
|----------|-------|-------------|
| `view` | `kachel`, `map`, `toggle` | Anzeigemodus |

Bindet bei Bedarf Leaflet.js (1.9.4), MarkerCluster (1.5.1) und Frontend-Assets ein.

### `frontend/assets/`
- `frontend/assets/frontend.css` — Styles (inkl. neues Card-Design, Badge-Pills, Design-Token-Overrides)
- `frontend/assets/frontend.js` — Frontend-Interaktionen (Filter, Toggle/Accordion, Infinite Scroll/Load More)
- `frontend/assets/map.js` — Leaflet-Interaktionen
- `frontend/assets/calendar.js` — Kalender-Interaktionen

### `frontend/views/renderer.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_render_members(array $atts): string` | Rendert Mitgliederkarten als HTML (mit Transient-Cache) |

**Badge-System (Frontend):**
- Badge-Feld-IDs aus `fields-config.json` (Flag `badge=true`)
- Ausgabe als „Pills“ im Card-Body (`.theme-pill`)
- Badge-Felder werden im normalen Feld-Loop ausgeschlossen (keine doppelte Ausgabe)

### `frontend/views/map-render.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_render_members_map(array $atts): string` | Rendert interaktive Leaflet.js-Karte mit Mitglieder-Markern |

### `frontend/views/calendar-render.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_render_calendar_shortcode(array $atts): string` | Rendert Kalenderansicht aus iCal-URLs |
| `bes_parse_ics(string $ics_url): array` | Parst iCalendar-Format |
| `bes_parse_datetime(string $dt): int` | Wandelt iCal-Datetime in Unix-Timestamp um |

### `frontend/ajax/ajax-endpoints.php`
| AJAX-Action | Beschreibung |
|-------------|-------------|
| `wp_ajax_bes_filter_members` | Mitglieder filtern (eingeloggte Nutzer) |
| `wp_ajax_nopriv_bes_filter_members` | Mitglieder filtern (Gäste, Rate-Limiting: 120/min) |

| Funktion | Beschreibung |
|----------|-------------|
| `bes_ajax_filter_members(): void` | Suche + Filterung via `bes_members_get_all()`, Whitelist-Validierung, Pagination |

### `frontend/includes/design-bridge.php`
| Funktion | Beschreibung |
|----------|-------------|
| `bes_frontend_get_design_settings(): array` | Gibt Design-Einstellungen für Frontend zurück |
| `bes_frontend_generate_design_css(bool $with_style_tags): string` | Generiert Design-CSS für Frontend-Ausgabe |

### `frontend/includes/filter-helpers.php`
Lädt `country-filter-normalize.php`. Hilfsfunktionen für die einheitliche Filterleiste (Kacheln & Karte).

| Funktion | Beschreibung |
|----------|-------------|
| `bes_get_default_filter_order(): array` | Gibt Standard-Reihenfolge der Filter-Felder zurück |
| `bes_prepare_filter_fields(array $config): array` | Bereitet Felder für Filterleiste auf |
| `bes_clean_filter_value(string $value): string` | Bereinigt und sanitisiert Filterwerte |
| `bes_collect_filter_values(array $members, array $fields): array` | Sammelt verfügbare Filterwerte aus Mitgliederdaten |
| `bes_render_filterbar(array $fields, array $values): string` | Rendert HTML-Filterleiste |

### `frontend/includes/country-filter-normalize.php`
Länderwert-Normalisierung für die Filterleiste und Karte: Rohwerte aus EasyVerein (z. B. „Deutschland“, „DE“, „D“) werden auf **ISO-3166-1-alpha-2** (DE/AT/CH …) abgebildet; deutsche Anzeigenamen für Dropdowns; Erkennung von Land-Filterfeldern anhand von Feld-ID/Label.

| Funktion | Beschreibung |
|----------|-------------|
| `bes_country_iso_german_labels(): array` | ISO-Code → deutscher Anzeigename |
| `bes_country_alias_to_iso_map(): array` | Alias (kleingeschrieben) → ISO |
| `bes_country_filter_labels_js(): array` | Labels für `wp_localize_script` (Shortcode) |
| `bes_country_filter_alias_normalize_js(): array` | Alias-Map für Frontend-JS |
| `bes_normalize_country_token(string $raw): ?string` | Einzelwert auf ISO normalisieren |
| `bes_field_is_country_filter(array $field): bool` | Erkennt Land-/Staat-Filterfelder |
| `bes_country_filter_option_label(string $option_value): string` | Anzeigename für Filteroption |
| `bes_country_filter_merge_distinct_values(array $raw_values): array` | Deduplizierte, sortierte Filteroptionen |

Eingebunden via `filter-helpers.php`; genutzt in `renderer.php`, `map-render.php`, `shortcode/bes-members.php`.

### `frontend/includes/geo-utils.php`
Geo-/Radius-Helfer für Umkreissuche und Distanzberechnung (nutzt `bes_members_get_all()` als Datenquelle).

### `frontend/includes/schema.php`
Schema.org-Ausgabe/Hilfsfunktionen für strukturierte Daten im Frontend.

### `frontend/includes/og-meta.php`
OpenGraph/Sharing-Meta (falls im Projekt aktiviert/ausgegeben).

---

## Datenpfad (Übersicht)

```
EasyVerein API
    ↓  sync/client/api-core-consent-requests.php
    ↓  sync/api-core-consent-v3.php  (bseasy_v3_run_sync)
    ↓
members_consent_v3.json  ←→  sync/v3/v3-persistence.php
    ↓
includes/data/member-repository.php  (bes_members_get_all)
    ↓
frontend/views/renderer.php          (bes_render_members)
frontend/views/map-render.php        (bes_render_members_map)
frontend/ajax/ajax-endpoints.php     (bes_ajax_filter_members)
    ↓
Transient-Cache  →  [bes_members]-Shortcode  →  Browser
```

## Datenspeicherung

| Speicherort | Inhalt |
|-------------|--------|
| `wp_options` | API-Token (verschlüsselt), Sync-Einstellungen, Design-Farben, Sync-Status-Optionen |
| Transients | Render-Cache (Kacheln, Karte), Select-Options-Cache |
| `uploads/bseasy-sync/v3/members_consent_v3.json` | Synchronisierte Mitgliederdaten |
| `uploads/bseasy-sync/v3/field_catalog_v3.json` | Feldmetadaten aus API (Explorer) |
| `uploads/bseasy-sync/v3/selection_v3.json` | Vom Nutzer gewählte Felder |
| `uploads/bseasy-sync/v3/status_v3.json` | Sync-Fortschritt (Offset, Total, State) |
| `uploads/bseasy-sync/fields-config.json` | Feldanzeigeconfig (Reihenfolge, Filter, Labels) |
| `uploads/bseasy-sync/v3/sync-v3.log` | Sync-Protokoll |
| `uploads/bseasy-sync/v3/debug-v3.log` | Debug-Protokoll (nur bei `BES_DEBUG_MODE=true`) |
| `uploads/bseasy-sync/v3/consent.log` | Consent-Extraktionsprotokoll |
