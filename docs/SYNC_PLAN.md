# Sync-Plan — Umsetzungsreihenfolge & Kontext

**Zielgruppe:** Neuer Chat / Entwickler ohne Gesprächskontext  
**Plugin:** `bseasy-sync-v4` (Version 4.0.0) — **nicht** `bseasy-sync-main`  
**Stand:** Juni 2026  
**Status:** Planung — **noch nicht umgesetzt** (außer Sync-Dauer-Anzeige, siehe unten)

---

## 1. Projektkontext (Kurz)

| Thema | Details |
|-------|---------|
| **Zweck** | EasyVerein API v2.0 → JSON in `uploads/bseasy-sync/v3/` → Frontend (Mitgliederliste, Karte, Filter) |
| **Aktives Plugin lokal** | `bseasy-sync-v4/bseasy-sync.php` |
| **Datenpfad** | `wp-content/uploads/bseasy-sync/v3/members_consent_v3.json` |
| **Nur ein Plugin aktiv** | Main und V4 teilen Upload-Pfad, dürfen nicht parallel aktiv sein |
| **Consent-Mitglieder** | ~156 (Test), Verein gesamt ~330 |
| **API Rate Limit** | 100 Requests/Minute (EasyVerein) |
| **Architektur-Regel** | Sync-Code nur über `sync/sync-service.php`; Admin/Frontend nicht direkt in `sync/` |

Weitere Docs: [README.md](README.md), [BACKLOG.md](BACKLOG.md), [MANUAL_TESTS.md](MANUAL_TESTS.md), [../MODULE_OVERVIEW.md](../MODULE_OVERVIEW.md)

---

## 2. Was bereits erledigt ist (nicht nochmal bauen)

| Feature | Status | Relevante Pfade |
|---------|--------|-----------------|
| **Sync-Dauer-Anzeige** | ✅ erledigt (Juni 2026) | `sync/v3/v3-persistence.php` (Timer), Admin-Meldung + Sync-Tab, `admin/assets/sync/sync-*.js` |
| **Sync-Lab (isoliert)** | ✅ vorhanden | `dev/sync-lab/` — wird **nicht** vom Plugin geladen |
| **Manuelle Tests dokumentiert** | ✅ | `docs/MANUAL_TESTS.md` |
| **WP-Cron für manuellen Sync** | ✅ vorhanden | Hook `bes_run_consent_v3_single` — startet Sync **on-demand** aus Admin |

---

## 3. Messwerte & Entscheidungsgrundlage (Juni 2026)

### 3.1 Produktions-Sync (V4, lokal)

| Metrik | Wert |
|--------|------|
| Mitglieder mit Consent | 156 |
| **Gesamtdauer** | **6 Min 25 Sek** |
| Anzeige Dauer | funktioniert in Admin-Erfolgsmeldung + Sync-Tab |

### 3.2 Sync-Lab (CLI, gleiche API, 5 Mitglieder)

| Strategie | Mitglieder | API-Requests | Requests/Member | Dauer |
|-----------|------------|--------------|-----------------|-------|
| **baseline** (aktueller Ablauf) | 5 | 23 | 4,6 | 8,3 s |
| **optimized** (nested Query) | 5 | 13 | 2,6 | ~6 s |

**Interpretation:**

- Pro Mitglied aktuell ~**3–4 API-Calls** (Member, CF, Contact, ggf. Contact-CF)
- Optimized Ziel ~**1 API-Call** pro Mitglied (nested Query auf Member-Detail)
- Gesamt-Requests optimized: **~43–65 % weniger** (je nach Anteil ID-Liste)

### 3.3 Erwartete Zeitersparnis (Schätzung)

| Mitglieder | Aktuell (ca.) | Mit optimized (realistisch) | Ersparnis |
|------------|---------------|----------------------------|-----------|
| **156** | 6:25 | **4:00 – 4:30** | **~2 Min** |
| **330** | 13 – 15 Min | **8 – 10 Min** | **~4 – 5 Min** |

**Nicht mit optimized gelöst:** Select-Feld-Labels (eigener Fix), Geocoding, JSON-Merge, `usleep`-Pausen.

### 3.4 Entscheidung: Select-Fix vs. Optimized-Sync

| Maßnahme | Aufwand | Nutzen | Wann |
|----------|---------|--------|------|
| **A: Select-Fix** (aus Main portieren) | 0,5–1 Tag | **Datenqualität** (Labels statt IDs) | **Immer zuerst** — unabhängig von Performance |
| **B: Optimized-Sync** (Lab → Plugin) | 3–5 Tage | **~35–40 % kürzere Sync-Zeit**, weniger API-Last | Ab ~150 Mitgliedern sinnvoll; bei 330 **klar ja** |

**Break-even B:** Lohnt sich vor allem wegen **Skalierung (330)**, **Staging-Tests**, **Rate-Limit-Robustheit** — nicht allein wegen 2 Min/Ersparnis pro Monat.

---

## 4. Empfohlene Umsetzungsreihenfolge

> **Für neuen Chat:** Phasen der Reihe nach abarbeiten. Vor jeder Phase: kurzer Stand in `docs/MANUAL_TESTS.md` / `docs/CHANGELOG.md` festhalten.

### Phase 0 — Baseline festhalten (≈ 30 Min, optional)

- [ ] Vollsync 330 Mitglieder (Staging) — Dauer notieren
- [ ] Werte in diese Datei unter „Messwerte“ ergänzen
- [ ] Offene Testlücken aus `MANUAL_TESTS.md` priorisieren

---

### Phase 1 — Select-Feld-Fix (P0, ≈ 0,5–1 Tag)

**Warum zuerst:** Hoher Nutzen, geringes Risiko, kein Sync-Labor nötig.

| Task | Quelle (Main) | Ziel (V4) |
|------|---------------|-----------|
| On-Demand-Abruf fehlender Select-Optionen | `sync/v3-helpers.php` | `sync/v3/v3-field-options.php` |
| Cache-TTL für Select-Options | `bseasy_v3_is_cf_select_options_cache_valid` | portieren |
| Einzelabruf | `bseasy_v3_fetch_cf_select_option_label()` | portieren |

**Referenz Main:** `bseasy-sync-main/sync/v3-helpers.php`  
**Test:** Mehrfach-Auswahlfeld mit vielen Optionen — Label in JSON, nicht nur ID.

**Erfolgskriterium:** Select-Felder in `members_consent_v3.json` mit lesbaren Labels.

---

### Phase 2 — Sync-Lab validieren (≈ 1 Tag, kein Plugin-Code)

**Ort:** `dev/sync-lab/` (CLI, isoliert)

```bash
cd bseasy-sync-v4/dev/sync-lab
php run.php --wp=/pfad/zu/wordpress/wp-load.php --strategy=baseline --limit=20
php run.php --wp=/pfad/zu/wordpress/wp-load.php --strategy=optimized --limit=20
php compare.php --strategy=baseline   # optional
```

**Erfolgskriterien:**

| Kriterium | Ziel |
|-----------|------|
| optimized: Requests/Member | deutlich unter baseline (~≤ 2 vs. ~4) |
| optimized: gleiche Member-Anzahl | ja |
| HTTP 429 | nicht höher als baseline |
| API-Sandbox | `php sandbox.php --experiment=all` dokumentieren |

Details: [dev/sync-lab/README.md](../dev/sync-lab/README.md)

**Ergebnis dokumentieren:** Kurz in diese Datei (Abschnitt 3) oder `docs/API_NOTES.md` anlegen.

---

### Phase 3 — Optimized-Sync ins Plugin (≈ 2–3 Tage, nur bei grünem Lab)

**Nur starten, wenn Phase 2 erfolgreich.**

| Schritt | Beschreibung |
|---------|--------------|
| 1 | Lab-Logik (nested Query) nach `sync/client/` oder `sync/strategies/` |
| 2 | Wrapper in `sync/sync-service.php` |
| 3 | **Feature-Flag** (Option oder Konstante), Default: bisheriger Sync |
| 4 | Staging: Vollsync 330, Dauer vs. Baseline |
| 5 | `docs/MANUAL_TESTS.md` + CHANGELOG aktualisieren |

**Nicht anfassen ohne Flag:** `sync/api-core-consent-v3.php` Monolith komplett ersetzen.

**Parallel sinnvoll (Phase 3b):** Sync G3 — Fehlertoleranz pro Mitglied (Batch bricht nicht ab).

---

### Phase 4 — Automatisierter Sync (WP-Cron, ≈ 1–2 Tage)

#### Ist-Zustand (bereits im Plugin)

| Mechanismus | Hook / Option | Verhalten |
|-------------|---------------|-----------|
| **Manueller Sync via Admin** | AJAX → `wp_schedule_single_event` | Ein Cron-Lauf pro Klick |
| **Auto-Fortsetzung** | `bes_v3_auto_continue` | Nach Batch automatisch nächster Durchlauf (2 s Pause) |
| **Cron-Handler** | `BES_V3_CRON_HOOK` = `bes_run_consent_v3_single` | `sync/runtime/cron-v3.php` |
| **Batch-Größe** | `bes_v3_batch_size` (50–500, Default 200) | Mehrere Durchläufe bei > Batch |

Es gibt **noch keinen** wiederkehrenden Sync (z. B. täglich 3:00 Uhr).

#### Soll-Konzept (neu umzusetzen)

| Option | Beschreibung | Empfehlung |
|--------|--------------|------------|
| **A: WP-Cron recurring** | `wp_schedule_event( time, 'daily', 'bes_v3_scheduled_sync' )` | Einfach, passt zu bestehendem Hook |
| **B: System-Cron** | Server ruft `wp cron event run` / `wp bseasy sync` auf | Zuverlässiger auf All-inkl wenn WP-Cron träge |
| **C: WP-CLI Command** | `wp bseasy-sync run` für manuell + System-Cron | Optional, nice-to-have |

#### Empfohlene Implementierung (Phase 4)

1. **Admin-Einstellungen** (Sync-Tab):
   - Checkbox „Automatischen Sync aktivieren“
   - Intervall: täglich / wöchentlich / benutzerdefiniert (Cron-Schedule)
   - Uhrzeit (Timezone WordPress)
   - Optional: E-Mail bei Fehler

2. **Technik:**
   - Neuer Hook z. B. `bes_v3_scheduled_full_sync` → ruft bestehende Sync-Pipeline auf (Offset 0, `auto_continue` an)
   - Bei Aktivierung: `wp_schedule_event`; bei Deaktivierung: `wp_clear_scheduled_hook`
   - **Lock:** Kein paralleler Sync wenn manuell + automatisch kollidieren (`bseasy_v3_is_locked`)

3. **Hosting (All-inkl):**
   - `includes/infra/hosting/hosting-compatibility.php` — Timeouts beachten
   - `bes_check_wp_cron_works()` prüfen
   - Fallback-Doku: echter System-Cron alle 5–15 Min + `wp cron event run --due-now`

4. **Sicherheit:**
   - Nur Mitglieder mit Consent (bestehende Logik)
   - Token muss gültig sein — bei Fehler loggen, nicht still fail
   - Rate-Limit: bestehende 429-Behandlung nutzen

5. **Tests:**
   - Manuell: Intervall „in 2 Minuten“ für Test
   - Staging: ein Nachtlauf
   - Prüfen: `last_sync_time`, `last_sync_duration_sec`, Log

**Erfolgskriterium:** Sync läuft ohne Admin-Klick; Dauer + Mitgliederzahl im Sync-Tab aktualisiert.

---

### Phase 5 — Produktiv & Migration (≈ 0,5–1 Tag)

- [ ] Migration Main → V4 gemäß [MIGRATION.md](MIGRATION.md)
- [ ] Offene Testlücken schließen
- [ ] Vollsync 330 auf Staging, dann Produktion

---

### Phase 6 — Mittelfristig (Backlog P2, nicht vor Phase 1–4)

- Mitglieder-Short-URLs `/m/{id}`
- Netzwerk-Badge
- Geomatching Startseite
- Siehe [BACKLOG.md](BACKLOG.md) P2

---

## 5. Übersicht: Prioritäten-Matrix

```
Phase 1  Select-Fix              ████████████  P0 — zuerst
Phase 2  Sync-Lab (--limit=20)   ████████      Validierung
Phase 3  Optimized + Flag        ██████        nur wenn Lab OK
Phase 4  Automatischer Sync      █████         WP-Cron recurring
Phase 5  Migration / 330-Test    ████          Staging → Prod
Phase 6  Features P2             ██            später
```

---

## 6. Wichtige Code-Pfade (Referenz)

| Bereich | Pfad |
|---------|------|
| Sync-Fassade | `sync/sync-service.php` |
| Sync-Engine | `sync/api-core-consent-v3.php` |
| WP-Cron Sync | `sync/runtime/cron-v3.php` |
| API-Client | `sync/client/api-core-consent-requests.php` |
| Select-Options V4 | `sync/v3/v3-field-options.php` |
| Select-Options Main (Referenz) | `../bseasy-sync-main/sync/v3-helpers.php` |
| Admin Sync UI | `admin/views/partials/sync-tab-sync.php`, `admin/assets/sync/` |
| Sync-Lab | `dev/sync-lab/` |
| Dauer-Helfer | `sync/v3/v3-persistence.php` (`bseasy_v3_sync_*_duration`) |

---

## 7. API-Recherche (Hintergrund)

| Quelle | Ort |
|--------|-----|
| OpenAPI Spec | `easyverein_api_v2_live.json` |
| Swagger UI | `easyverein-doku-api-2.html` |
| Offizielle Samples | [easyVerein-API-Samples](https://github.com/SD-Software-Design-GmbH/easyVerein-API-Samples) (nested `query`) |
| Pagination | `limit`, `page`, `showCount`, Response: `results`, `next` |
| Consent-Filter | `custom_field_name`, `custom_field_value__in` auf `GET member` |

Optional anlegen: `docs/API_NOTES.md` mit verifizierten Experimenten aus `dev/sync-lab/sandbox.php`.

---

## 8. Checkliste für neuen Chat

Beim Start eines Implementierungs-Chats:

1. **Plugin prüfen:** Nur `bseasy-sync-v4` aktiv?
2. **Phase aus dieser Datei** wählen (nicht alles parallel)
3. **Baseline:** Letzte Sync-Dauer aus Admin notieren
4. **Nach Umsetzung:** `docs/CHANGELOG.md`, ggf. `docs/MANUAL_TESTS.md` aktualisieren
5. **Kein Sync-Lab-Code** in `sync/` mergen ohne Phase-2-Erfolg
6. **Feature-Flags** für optimized Sync und Auto-Sync — Rollback ermöglichen

---

## 9. Offene Punkte / Risiken

| Risiko | Mitigation |
|--------|------------|
| WP-Cron läuft auf All-inkl unzuverlässig | System-Cron + Doku |
| Optimized liefert unvollständige CF-Daten | Lab `compare.php`, Staging-Vollsync |
| Zwei Plugins aktiv | Nur eines aktivieren |
| Token abgelaufen bei Nacht-Sync | Fehler-Log + optional Admin-Mail |
| Batch > 200 bei 330 Member | `auto_continue` muss bei Auto-Sync **an** sein |

---

*Letzte Aktualisierung: Juni 2026 — Sync-Dauer-Anzeige und Sync-Lab-Tests eingearbeitet.*
