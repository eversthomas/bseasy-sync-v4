# BS Easy Sync V4

WordPress-Plugin zum Synchronisieren von EasyVerein-Mitgliedsdaten nach WordPress und zur Ausgabe als Berater-/Mitglieder-Cards im Frontend (inkl. Filter, Karte und Toggle-Ansicht).

## Voraussetzungen

- WordPress (aktuelle LTS empfohlen)
- PHP (8.x empfohlen)
- EasyVerein-Account inkl. API-Zugang / Token
- Schreibrechte für `wp-content/uploads/` (Plugin speichert Sync-Daten in `uploads/bseasy-sync/`)

## Installation

1. Plugin-Ordner `bseasy-sync-v4/` nach `wp-content/plugins/` kopieren.
2. Im WordPress-Backend unter **Plugins** aktivieren.
3. Plugin-Adminseite öffnen und den EasyVerein API-Key/Token hinterlegen.
4. Sync/Explorer ausführen (je nach Setup), danach stehen Daten im Frontend zur Verfügung.

## Features (Kurzüberblick)

- **Sync**: Synchronisiert Mitgliederdaten aus EasyVerein in lokale JSON-Dateien (inkl. Status/Logs).
- **Feldverwaltung**: Auswahl/Sortierung der Felder (Bereiche „above/below“), Filter-Optionen, Badge-Flag pro Feld.
- **Card-Design**: Zentrale Design-Settings (Farben) werden als CSS Custom Properties ins Frontend gespiegelt.
- **Umkreissuche**: Geo-/Radius-Logik (je nach aktivierter Frontend-Funktionalität/Filter) über die Frontend-AJAX-Schicht.
- **Shortcodes**: Ausgabe als Kachelansicht, Karte oder Toggle-Ansicht.

## Shortcode-Referenz

Ausgabe der Mitgliederliste:

- `[bes_members view="kachel"]`
- `[bes_members view="map"]`
- `[bes_members view="toggle"]`

## Entwickler-Dokumentation

Für die Modul-/Dateiübersicht und Architekturhinweise siehe `MODULE_OVERVIEW.md`.

