# Migration: bseasy-sync-main → bseasy-sync-v4

Anleitung für den Wechsel von der V3-Monolith-Version (`bseasy-sync-main`) zur modularen V4-Version auf derselben WordPress-Installation.

---

## Voraussetzungen

- Vollständiges Backup: Datenbank + `wp-content/uploads/bseasy-sync/`
- Wartungsmodus optional, aber empfohlen für Produktion
- Beide Plugins dürfen **nicht gleichzeitig aktiv** sein (gleicher Menü-Slug, gleiche Cron-Hooks)

---

## Was automatisch übernommen wird

V4 nutzt dieselben Speicherorte wie Main:

```
wp-content/uploads/bseasy-sync/
├── fields-config.json          ← Feld-Sortierung, Filter, Badges, Labels
├── v3/
│   ├── members_consent_v3.json ← synchronisierte Mitglieder
│   ├── selection_v3.json       ← V3-Feldauswahl
│   ├── field_catalog_v3.json   ← Explorer-Katalog
│   └── status_v3.json          ← Sync-Status
```

WordPress-Options (in `wp_options`) bleiben erhalten:

- `bes_api_token` (verschlüsselt)
- `bes_consent_field_id`, `bes_batch_size`, `bes_auto_continue`
- Design-Einstellungen, Karten-Optionen, Kalender-Konfiguration

V4 migriert zusätzlich automatisch alte Pfade von `uploads/easy2transfer-sync/` nach `uploads/bseasy-sync/`, falls vorhanden.

---

## Empfohlener Ablauf (Variante B — mit Sicherheits-Export)

### 1. Backup

```bash
# Beispiel: Upload-Ordner sichern
cp -a wp-content/uploads/bseasy-sync/ ~/backup-bseasy-sync-$(date +%Y%m%d)/
```

Datenbank-Backup über Hosting-Panel oder WP-CLI:

```bash
wp db export backup-$(date +%Y%m%d).sql
```

### 2. Feldkonfiguration exportieren (zusätzliche Sicherheit)

Im WordPress-Admin unter **BSEasy Sync → Felder**:

- Button **Config als Template exportieren** (falls vorhanden)
- Alternativ manuell: `fields-config.json` aus dem Upload-Ordner kopieren

### 3. Plugin wechseln

1. **bseasy-sync-main** deaktivieren (nicht deinstallieren, bis V4 verifiziert ist)
2. **bseasy-sync-v4** hochladen/aktivieren
3. Admin-Seite **BSEasy Sync** öffnen

### 4. Verifikation

| Prüfpunkt | Erwartung |
|-----------|-----------|
| API-Token | Bereits gesetzt, Sync startbar |
| Felder-Tab | Bestehende Sortierung und Filter sichtbar |
| Frontend `[bes_members]` | Mitglieder werden angezeigt |
| Sync | Testlauf mit kleinem Batch erfolgreich |

Checkliste: `docs/MANUAL_TESTS.md`

### 5. Select-Felder prüfen (wichtig)

Falls Auswahlfelder mit **sehr vielen Optionen** im Einsatz sind:

1. Ein Mitglied mit problematischem Feld identifizieren
2. Label-Werte in `members_consent_v3.json` prüfen (Text vs. numerische ID)
3. Bei Abweichung zu Main: Fix aus BACKLOG P0 abwarten, bevor Produktiv-Umschaltung

### 6. Main deinstallieren (optional)

Erst nach erfolgreicher Verifikation aller kritischen Funktionen.

---

## Rollback

Falls V4 Probleme macht:

1. V4 deaktivieren
2. Main reaktivieren
3. Daten in `uploads/bseasy-sync/` sind unverändert (sofern kein neuer Sync unter V4 gelaufen ist)

---

## Import/Export von Feldeinstellungen

V4 bietet im Felder-Tab:

| Funktion | AJAX-Action | Zweck |
|----------|-------------|-------|
| Template exportieren | `bes_export_config_template` | Aktuelle Config als Template sichern |
| Template importieren | `bes_import_template` | Config aus Template wiederherstellen |

Für Staging → Produktion reicht der Export der `fields-config.json`. Eine dedizierte Upload-Funktion im Admin ist als Backlog-Item geplant (`docs/BACKLOG.md` P1).

---

## Häufige Fragen

**Muss ich den Sync neu starten?**  
Nein — vorhandene `members_consent_v3.json` wird von V4 über das Member-Repository gelesen.

**Gehen Design-Einstellungen verloren?**  
Nein — sie liegen in `wp_options` (`bes_card_design_settings`).

**Kann ich beide Plugins parallel installiert haben?**  
Ja, als Ordner — aber nur eines darf aktiv sein.
