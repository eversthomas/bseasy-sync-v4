# Felder-Bereinigung: Doppelungen entfernt

## Problem
In `fields-config.json` existierten Doppelungen für Custom Fields:
- `cfraw.{id}` (roh, aus `member_cf`)
- `cf.{id}` (extracted, aus `member_cf_extracted`)

Beide Varianten bezogen sich auf dasselbe Custom Field, führten aber zu Verwirrung und möglichen Konflikten.

## Lösung

### 1. Code-Anpassung (`fields-handler.php`)
- **Reihenfolge geändert**: `cf.*` (extracted) wird zuerst extrahiert
- **Prüfung eingebaut**: `cfraw.*` wird nur erstellt, wenn kein `cf.*` existiert
- **Gleiche Logik für Contact Fields**: `contactcf.*` bevorzugt, `contactcfraw.*` nur als Fallback

### 2. Bereinigungs-Script (`cleanup-duplicate-fields.php`)
- Entfernt alle `cfraw.*` Einträge, wenn `cf.*` existiert
- Entfernt alle `contactcfraw.*` Einträge, wenn `contactcf.*` existiert
- Erstellt automatisch ein Backup vor der Bereinigung
- Migriert Konfigurationen von `cfraw` auf `cf`, falls `cf` noch keine Konfiguration hat

### 3. Ergebnis
- **28 Doppelungen entfernt** aus `fields-config.json`
- **153 Felder verbleiben** (vorher 181)
- **Keine neuen Doppelungen** werden mehr erstellt

## Warum `cf.*` bevorzugen?

1. **Bessere Formatierung**: `cf.*` nutzt `display_value`, das bereits formatiert ist
2. **Konsistenz**: `cf.*` ist die bevorzugte Variante im Frontend
3. **Weniger Redundanz**: Ein Feld, eine Konfiguration

## Verwendung

### Bereinigungs-Script ausführen:
```bash
php wp-content/plugins/bseasy-sync/admin/cleanup-duplicate-fields.php
```

### Automatische Bereinigung:
Die neue Logik in `fields-handler.php` verhindert automatisch neue Doppelungen bei zukünftigen Scans.

## Backup
Ein Backup wird automatisch erstellt: `fields-config.json.backup.YYYY-MM-DD_HHMMSS`

## Frontend-Kompatibilität
Das Frontend unterstützt weiterhin beide Varianten (`cf.*` und `cfraw.*`), aber `cf.*` wird bevorzugt verwendet.




