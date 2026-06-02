# Dokumentation — BSEasy Sync V4

Übersicht aller Projekt-Dokumente.

---

## Für Betrieb & Migration

| Dokument | Inhalt |
|----------|--------|
| [../README.md](../README.md) | Installation, Shortcodes, Schnellstart |
| [MIGRATION.md](MIGRATION.md) | Wechsel von bseasy-sync-main → V4 |
| [MANUAL_TESTS.md](MANUAL_TESTS.md) | Manuelle Test-Checkliste (Stand Juni 2026) |

---

## Für Entwicklung

| Dokument | Inhalt |
|----------|--------|
| [../MODULE_OVERVIEW.md](../MODULE_OVERVIEW.md) | Architektur, Module, Funktionen, Coding-Regeln |
| [BACKLOG.md](BACKLOG.md) | Priorisierte Aufgabenliste (P0–P3) |
| [**SYNC_PLAN.md**](SYNC_PLAN.md) | **Sync-Umsetzungsplan** (Reihenfolge, Messwerte, Auto-Sync, Kontext für neuen Chat) |
| [CHANGELOG.md](CHANGELOG.md) | Versionshistorie |
| [../dev/ROADMAP.md](../dev/ROADMAP.md) | Detaillierte technische Roadmap (A–J) |

---

## Dokumentations-Hierarchie

```
README.md              ← Einstieg
docs/
├── README.md          ← Diese Übersicht
├── MANUAL_TESTS.md    ← Was wurde getestet
├── MIGRATION.md       ← Main → V4
├── BACKLOG.md         ← Was als Nächstes (priorisiert)
├── SYNC_PLAN.md       ← Sync: Reihenfolge, Lab, Auto-Sync, Messwerte
└── CHANGELOG.md       ← Was sich geändert hat
MODULE_OVERVIEW.md     ← Architektur-Referenz
dev/ROADMAP.md         ← Technische Detailplanung
```

**Prioritäten:** `docs/SYNC_PLAN.md` ist der **operative Sync-Fahrplan** (inkl. Auto-Sync). `docs/BACKLOG.md` listet alle Tasks. `dev/ROADMAP.md` beschreibt technische Maßnahmen im Detail.
