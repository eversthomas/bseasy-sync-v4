# Field Intelligence Dashboard

## Übersicht

Das Field Intelligence Dashboard ist ein zusätzliches Modal-Tool, das Analysen, Vorschläge und Empfehlungen für die Feldverwaltung liefert. Es ändert **keine** bestehende Funktionalität, sondern bietet zusätzliche Hilfe und Dokumentation.

## Features

### 📊 Übersicht
- Gesamtstatistiken über alle Felder
- Verteilung nach Typ (member, contact, cf, etc.)
- Verteilung nach Bereich (above, below, unused)
- Status-Übersicht (konfiguriert, unkonfiguriert, ignoriert)

### 🔍 Vorschläge
- **Label-Vorschläge**: Intelligente Vorschläge für Felder ohne Label
  - Basierend auf Feld-ID-Patterns
  - Basierend auf Beispielwert-Analyse (E-Mail, URL, Telefon, PLZ)
  - Confidence-Werte (hoch/mittel/niedrig)
- **Kategorisierungs-Vorschläge**: Vorschläge für Feld-Kategorien
- **Aktivierungs-Vorschläge**: Felder die aktiviert werden könnten

### 🎯 Empfehlungen
- Felder mit Beispielwerten, die noch unused sind
- Felder in Verwendung ohne Label
- Empfehlungen basierend auf Feld-Analyse

### 🔗 Duplikate
- Findet Felder mit ähnlichen Beispielwerten
- Hilft bei der Identifikation von Duplikaten

### 📦 Gruppierungen
- Vorschläge für Felder die zusammengehören könnten
- Erkennt ähnliche Feld-IDs (z.B. "Internet 1", "Internet 2")

### 📚 Hilfe
- Dokumentation über Field Intelligence
- Erklärung der Label-Erkennung
- Best Practices für Field Management

## Technische Details

### Dateien
- `admin/fields/includes/field-intelligence.php` - PHP-Backend-Logik
- `admin/assets/field-intelligence.js` - JavaScript für Modal
- `admin/assets/field-intelligence.css` - Styling für Modal

### AJAX-Endpoint
- `bes_get_field_intelligence` - Liefert Analysen und Vorschläge

### Integration
- Button in `ui-felder.php` Header-Bereich
- Modal wird per JavaScript eingefügt
- Keine Änderungen an bestehender Funktionalität

## Label-Erkennung

### Was funktioniert gut:
- ✅ E-Mail-Adressen (Pattern: `user@domain.com`)
- ✅ URLs/Websites (Pattern: `https://...` oder `www.`)
- ✅ Telefonnummern (Pattern: Zahlen, Leerzeichen, +, -, ())
- ✅ PLZ (Pattern: 5-stellige Zahl)
- ✅ Datum (Pattern: `YYYY-MM-DD`)

### Was nicht zuverlässig funktioniert:
- ❌ Straßennamen vs. Nachnamen (beide sind Text)
- ❌ Städtenamen vs. Nachnamen (beide sind Text)
- ❌ Freitext-Felder (benötigen manuelle Labels)

### Strategie:
Die Erkennung kombiniert mehrere Signale:
1. **Feld-ID** (höchste Priorität)
2. **Pattern-Erkennung** (für eindeutige Fälle)
3. **Kontext-Analyse** (wenn möglich)
4. **Häufigkeitsanalyse** (wenn viele Beispiele vorhanden)

**Wichtig**: Vorschläge sind als Hilfe gedacht, nicht als vollautomatische Lösung. Der User behält die Kontrolle.

## Verwendung

1. Im Backend zur Feldverwaltung navigieren
2. Button "🧠 Field Intelligence" klicken
3. Modal öffnet sich mit verschiedenen Tabs
4. Analysen und Vorschläge durchsehen
5. Bei Bedarf im Haupt-Interface umsetzen

## Erweiterungen

Das System ist erweiterbar:
- Neue Analyse-Funktionen können hinzugefügt werden
- Weitere Tabs können ergänzt werden
- Vorschlags-Algorithmen können verbessert werden




