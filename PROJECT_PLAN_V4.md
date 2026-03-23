# BS Easy Sync v4 – Masterplan für Modularisierung und kontrollierte Modernisierung

**Projektname:** BS Easy Sync v4  
**Arbeitsverzeichnis lokal:** `bseasy-sync-v4`  
**Referenzsystem lokal:** `bseasy-sync-main`  
**Ziel:** Keine vorschnelle Neuerstellung des Plugins, sondern eine kontrollierte, testbare Modularisierung als stabile Grundlage für spätere Verbesserungen, insbesondere im Sync-Bereich.

---

# 1. Ausgangslage

Das bestehende Plugin funktioniert grundsätzlich produktiv, enthält bereits viel Domänenwissen und soll **nicht als Full Rewrite** neu erstellt werden. Gleichzeitig ist der Code organisch gewachsen. Dadurch sind Verantwortlichkeiten teilweise vermischt, einzelne Dateien zu groß oder zu zentral, und spätere Weiterentwicklungen werden unnötig riskant.

Besonders relevant ist der Sync-Bereich. Es gibt die berechtigte Annahme, dass der aktuelle Sync nicht optimal geschnitten ist und wegen der verschachtelten EasyVerein-REST-Struktur viele Schleifen und Requests benötigt. Diese mögliche Schwäche soll jedoch **nicht sofort durch tiefen fachlichen Umbau** angegangen werden, sondern erst dann, wenn die Gesamtstruktur modularer und sicherer bearbeitbar ist.

Die bestehenden Sync-Dateien heißen bereits `v3`. Die neue Architektur- und Modernisierungslinie wird deshalb als **v4** gedacht.

---

# 2. Strategische Grundentscheidung

Dieses Projekt ist **kein Rewrite**, sondern eine **kontrollierte v4-Modernisierungslinie** mit folgenden Prinzipien:

## 2.1 Kein Full Rewrite
Das bestehende Plugin bleibt in seiner Fachlogik zunächst Referenzsystem. Bewährte Logiken, Sonderfälle und produktiv erprobte Workarounds sollen nicht leichtfertig entfernt werden.

## 2.2 Zuerst Struktur, dann Verhalten
In den ersten Phasen werden Verantwortlichkeiten getrennt, Dateien sinnvoll neu geschnitten und Kopplungen reduziert. Das fachliche Verhalten soll dabei zunächst möglichst unverändert bleiben.

## 2.3 Modularisierung vor Optimierung
Insbesondere der Sync soll später gezielt modernisiert werden. Vorher muss die Struktur so verbessert werden, dass einzelne Bereiche isolierter, risikoärmer und testbarer bearbeitet werden können.

## 2.4 Referenzsystem schützen
Das Originalsystem bleibt außerhalb dieser v4-Linie als Vergleichs- und Rückfallbasis bestehen. v4 ist die Arbeitskopie für den kontrollierten Umbau.

## 2.5 Testbare Stabilität statt bloßer Aufräumarbeiten
Die Modularisierung gilt nur dann als erfolgreich, wenn das Plugin danach weiterhin stabil funktioniert und spätere Änderungen lokaler und ungefährlicher möglich werden.

---

# 3. Zielbild von v4

Das Ziel von v4 ist **nicht**, den Code einfach nur auf mehr Dateien zu verteilen. Ziel ist eine Struktur mit klaren Verantwortlichkeiten, geringerem Seiteneffekt-Risiko und besserer Änderbarkeit.

Die gewünschte v4-Zielarchitektur orientiert sich an den folgenden Verantwortungsbereichen.

---

## 3.1 Bootstrap / Plugin-Kern

Dieser Bereich soll nur noch die technische Initialisierung des Plugins enthalten.

### Verantwortung
- Plugin-Start
- Laden zentraler Module
- Konstanten
- zentrale Hook-Registrierung
- Aktivierung / Deaktivierung
- grundlegende Initialisierung

### Nicht Ziel
- keine umfangreiche Fachlogik
- keine komplexe Sync-Logik
- keine direkte Render- oder UI-Logik
- keine überladenen Include-Ketten ohne erkennbare Zuständigkeit

---

## 3.2 Sync-Domäne

Dieser Bereich soll künftig in sich klarer strukturiert sein. Der Sync ist später der wichtigste Modernisierungskandidat, soll aber zunächst vor allem **strukturell entkoppelt** werden.

### Verantwortung
- EasyVerein-API-Zugriff
- Auth / Token
- Request-Strategie
- Consent-Logik
- Member-Fetch
- Contact-Details-Fetch
- Batch-Steuerung
- Normalisierung / Aufbereitung
- Status / Locking / History
- ggf. Medien-/Bild-Handling
- Persistenz und Sync-Zwischenstände

### Ziel
Die Sync-Domäne soll später so geschnitten sein, dass man ihre Teile getrennt analysieren und modernisieren kann, ohne ständig Frontend, Admin oder Bootstrap mitbewegen zu müssen.

---

## 3.3 Admin-Domäne

Der Admin-Bereich soll als klarer eigener Modulbereich erkennbar sein.

### Verantwortung
- Admin-Menüs
- Einstellungsseiten
- Sync-UI
- Feld-Mapping-UI
- Kalender-/weitere Admin-Screens
- Form-Handler
- Admin-AJAX

### Ziel
Administrative Oberflächen und ihre Handler sollen nicht quer in Sync- oder Frontend-Dateien verteilt sein.

---

## 3.4 Frontend-Domäne

Frontend-Ausgabe, Filter, Kartenlogik und zugehörige AJAX-Endpunkte sollen klar gruppiert werden.

### Verantwortung
- Shortcode
- Renderer
- Listenansicht
- Kartenansicht
- Filterlogik
- Frontend-AJAX
- Assets (JS / CSS)

### Ziel
Frontend-Anpassungen sollen später möglichst ohne Eingriffe in den Sync-Kern möglich sein.

---

## 3.5 Infrastruktur / Helpers

Gemeinsame Hilfslogiken sollen sichtbar zentralisiert werden.

### Verantwortung
- Logging
- Cache
- Upload-/Dateioperationen
- Utilities / Helpers
- Sanitizing / Security-Helfer
- zentrale Konfigurationshilfen

### Ziel
Wiederverwendbare technische Hilfen sollen nicht mehrfach verteilt oder implizit in Fachdateien versteckt sein.

---

# 4. Zentrale Annahmen und Leitplanken für Cursor

## 4.1 Cursor soll zunächst nicht „umgestalten um des Umgestaltens willen“
Wenn eine bestehende produktive Logik nicht vollständig verstanden ist, darf sie nicht leichtfertig „vereinfacht“ oder durch vermeintlich schönere Muster ersetzt werden.

## 4.2 Vor jeder Strukturentscheidung ist die reale Codebasis maßgeblich
Das oben beschriebene Zielbild ist ein strategisches Wunschbild. Es muss mit der tatsächlichen Struktur der vorhandenen Dateien abgeglichen werden. Wenn die reale Codebasis eine abweichende, sinnvollere Zwischenstruktur nahelegt, soll Cursor diese benennen.

## 4.3 Keine tiefen Funktionsänderungen in der Analysephase
In der ersten Phase soll Cursor analysieren, abgleichen, Risiken benennen und eine realistische Reihenfolge empfehlen. Es soll noch kein breiter Umbau erfolgen.

## 4.4 v4 bedeutet nicht zwingend sofortige Dateiumbenennung
Die neue Linie heißt architektonisch v4. Das bedeutet nicht, dass zu Beginn sofort alle `v3`-Dateinamen umbenannt werden müssen. Dateiumbenennungen sollen nur dort erfolgen, wo sie fachlich und strukturell wirklich sinnvoll sind.

## 4.5 Die Produktlogik ist wertvoll
Das bestehende Plugin enthält produktiv erprobte Lösungen. Cursor soll nicht so tun, als sei die bisherige Struktur „falsch“, sondern sie zunächst als gewachsenen Bestand analysieren.

---

# 5. Arbeitsmodus für dieses Projekt

Dieses Projekt wird in **zwei Ebenen** durchgeführt:

## Ebene A – Analyse und Plan
- Bestandsaufnahme
- Abgleich mit Zielbild
- Benennung kritischer Kopplungen
- Vorschlag für v4-Zielstruktur
- Vorschlag für sinnvolle Umsetzungsreihenfolge

## Ebene B – Umsetzung in Phasen
- schrittweiser Strukturumbau
- jede Phase mit begrenztem Scope
- zwischen den Phasen Tests und Rückmeldung
- fachliche Optimierungen erst nach erfolgreicher Modularisierung

---

# 6. Hauptauftrag an Cursor in der aktuellen Phase

## Wichtige Anweisung
Führe **jetzt noch keine großen strukturellen Massenänderungen** durch.

Stattdessen analysiere zuerst die aktuelle Codebasis dieses Plugins und gleiche sie mit dem oben beschriebenen Zielbild ab.

Ich möchte zunächst eine belastbare Rückmeldung, bevor ich mit der eigentlichen Umsetzung beginne.

---

# 7. Analyseauftrag an Cursor

Bitte analysiere die vorhandene Plugin-Struktur konkret und beantworte die folgenden Fragen auf Basis der **tatsächlichen Dateien und ihrer aktuellen Verantwortlichkeiten**.

---

## A. Bestandsaufnahme

### A1
Welche aktuellen Hauptmodule oder Verantwortungsbereiche erkennst du bereits in der bestehenden Codebasis?

### A2
Welche Dateien oder Datei-Gruppen bilden heute faktisch:
- Bootstrap
- Sync
- Admin
- Frontend
- Infrastruktur / Utilities

### A3
Welche Dateien erscheinen heute als besonders zentral oder überladen?

---

## B. Architekturprobleme

### B1
Wo sind Verantwortlichkeiten aktuell erkennbar vermischt?

### B2
Wo bestehen starke Kopplungen zwischen:
- Bootstrap und Fachlogik
- Sync und Admin
- Sync und Frontend
- Rendering und Datenlogik
- API-Zugriff und UI-/Statuslogik

### B3
Welche Bereiche wirken besonders riskant für eine reine Strukturmigration, selbst wenn fachlich nichts geändert wird?

---

## C. Abgleich mit dem Zielbild

### C1
Welche Teile des oben beschriebenen Zielbilds passen bereits gut zur bestehenden Struktur?

### C2
Welche Teile des Zielbilds müssten an die reale Codebasis angepasst werden?

### C3
Welche konkrete v4-Zielstruktur würdest du **für diese konkrete Codebasis** empfehlen?

Bitte nicht nur abstrakt antworten, sondern möglichst mit:
- Ordnern
- Modulblöcken
- Zuständigkeiten
- sinnvollen Grenzen zwischen den Bereichen

---

## D. Reihenfolge der Modularisierung

### D1
Welche Bereiche lassen sich voraussichtlich relativ risikoarm zuerst modularisieren?

### D2
Welche Bereiche sollten vorerst möglichst wenig bewegt werden?

### D3
Welche Reihenfolge würdest du für die Modularisierung empfehlen, wenn fachliches Verhalten zunächst möglichst unverändert bleiben soll?

### D4
Welche Dateien oder Datei-Gruppen würdest du in einer ersten Strukturphase bewusst **nicht** anfassen?

---

## E. Besondere Betrachtung des Sync-Bereichs

Der Sync ist mittelfristig der wichtigste Modernisierungskandidat. In der aktuellen Phase soll er aber zunächst **strukturell** und nicht fachlich betrachtet werden.

### E1
Welche Hauptbestandteile des Sync-Bereichs erkennst du aktuell?

### E2
Welche Verantwortlichkeiten innerhalb des Syncs sind heute vermutlich vermischt?

### E3
Welche sinnvolle interne Modulstruktur würdest du für den Sync-Bereich als v4-Zielbild empfehlen, ohne schon die Fachlogik umzubauen?

Zum Beispiel in Richtung:
- API-Client
- Token/Auth
- Fetcher
- Consent
- Batch-Runner
- Normalizer
- Persistence / Status
- Media / Images
- History / Locking

Bitte nur dann so oder ähnlich aufteilen, wenn es wirklich zur bestehenden Codebasis passt.

### E4
Welche Teile des aktuellen Syncs würdest du für die erste Modularisierungswelle **noch nicht** neu denken, sondern nur sauberer einsortieren?

---

## F. Risiken und No-Go-Bereiche

### F1
Wo siehst du die größten Risiken, wenn man zu früh zu viel umbaut?

### F2
Welche aktuellen Logiken wirken so produktiv-kritisch, dass man sie zunächst möglichst unverändert belassen sollte?

### F3
Welche Bereiche darf man nicht zusammen mit anderen Baustellen vermischen, um die Fehleranalyse beherrschbar zu halten?

---

# 8. Form der gewünschten Cursor-Antwort

Bitte antworte strukturiert und konkret.

Ich möchte keine allgemeine Theorie zur Softwarearchitektur, sondern eine **konkrete Analyse dieses Plugins**.

Die Antwort soll möglichst enthalten:
- betroffene Dateien / Datei-Gruppen
- konkrete Problemstellen
- konkrete Risiken
- konkrete Vorschläge für eine realistische v4-Zielstruktur
- konkrete Empfehlung für die erste Umsetzungsreihenfolge

Wenn sinnvoll, gib bitte zusätzlich eine **empfohlene Ziel-Ordnerstruktur** an, aber noch ohne automatischen Massenumbau.

---

# 9. Vorläufiger Phasenrahmen für v4

Dieser Rahmen ist noch vorläufig und soll durch die Cursor-Analyse überprüft und ggf. angepasst werden.

---

## Phase 0 – Analyse und Abgleich
Ziel:
- reale Struktur verstehen
- kritische Kopplungen benennen
- Zielbild mit Bestand abgleichen
- konkrete Modularisierungsreihenfolge festlegen

**In dieser Phase keine großen Umbauten.**

---

## Phase 1 – Bootstrap entlasten
Ziel:
- Einstiegspunkt verschlanken
- Initialisierung klarer schneiden
- Modul-Laden zentralisieren
- fachliches Verhalten möglichst unverändert

---

## Phase 2 – Admin-Domäne sauber gruppieren
Ziel:
- Admin-Screens, Menüs, Handler, Admin-AJAX klarer zusammenführen
- Zuständigkeiten im Admin-Bereich erkennbar machen
- keine fachlichen Neuheiten

---

## Phase 3 – Frontend-Domäne sauber gruppieren
Ziel:
- Shortcode, Renderer, Filter, Frontend-AJAX und Assets logisch gruppieren
- spätere Frontend-Anpassungen entkoppeln
- Verhalten zunächst möglichst unverändert

---

## Phase 4 – Sync strukturell neu schneiden
Ziel:
- Sync-interne Verantwortlichkeiten sauberer trennen
- noch keine große fachliche Optimierung der Request-Strategie
- Ziel: bessere Bearbeitbarkeit und geringere Seiteneffekte

---

## Phase 5 – Infrastruktur konsolidieren
Ziel:
- Logging, Cache, Dateioperationen, technische Helpers zentralisieren
- technische Wiederverwendung verbessern
- implizite Hilfslogik sichtbarer machen

---

## Phase 6 – Stabilisierung und Vergleichstest
Ziel:
- Verhalten gegen Referenzsystem prüfen
- Sync, Admin, Frontend gegentesten
- offene Übergangsstellen dokumentieren
- v4 als modularisierte stabile Basis abschließen

---

## Phase 7 – Erst danach gezielte fachliche Modernisierung
Erst wenn die Modularisierung stabil abgeschlossen ist, sollen fachliche Verbesserungen begonnen werden, z. B.:
- Optimierung der Sync-Request-Strategie
- Entkopplung teurer Teilprozesse
- bessere interne Sync-Architektur
- spätere Frontend-Erweiterungen wie Radiussuche

---

# 10. Testbares Zielbild: Wann ist v4 modularisierungsseitig „stabil fertig“?

Die Modularisierung von v4 gilt als erfolgreich abgeschlossen, wenn die folgenden Kriterien erfüllt sind.

## 10.1 Funktionsstabilität
- bestehender Sync läuft weiterhin
- bestehende Admin-Seiten funktionieren weiterhin
- Frontend-Shortcodes funktionieren weiterhin
- Listen- und Kartenansichten funktionieren weiterhin
- bestehende Filterlogik funktioniert weiterhin, soweit sie nicht bewusst in einer späteren Phase geändert wird

## 10.2 Strukturklarheit
- Bootstrap enthält keine übermäßige Fachlogik mehr
- Admin, Frontend und Sync sind als getrennte Verantwortungsbereiche erkennbar
- Infrastruktur-Logiken sind nicht mehr unnötig verstreut
- zentrale Seiteneffekte und Übergänge sind dokumentierbar

## 10.3 Bearbeitbarkeit
- Änderungen im Frontend erfordern nicht automatisch Eingriffe in den Sync-Kern
- Änderungen im Sync erzwingen nicht automatisch Umbauten im Admin
- einzelne Bereiche lassen sich isolierter durch Cursor analysieren und weiterentwickeln

## 10.4 Dokumentation
- es existiert eine beschriebene v4-Zielstruktur
- bekannte Übergangsstellen und technische Altlasten sind dokumentiert
- offene spätere Modernisierungskandidaten sind benannt

## 10.5 Verlässliche Basis für Folgeprojekte
- v4 ist nach Abschluss der Modularisierung eine stabile Basis für spätere fachliche Verbesserungen
- insbesondere der Sync kann danach gezielter modernisiert werden, ohne dass vorherige Strukturprobleme ständig dazwischenfunken

---

# 11. Was nach dieser Analysephase passieren soll

Nach der Analyse und Rückmeldung von Cursor wird der Plan präzisiert.

Dann folgen:
1. finalisierte Zielstruktur
2. finaler Phasenplan
3. testbare Kriterien je Phase
4. einzelne Umsetzungs-Prompts für Cursor pro Phase

---

# 12. Abschließende Arbeitsanweisung an Cursor für jetzt

Bitte beginne **nicht** sofort mit Umbenennungen, Verschiebungen oder großflächigem Refactoring.

Arbeite in dieser ersten Runde ausschließlich so:

1. aktuelle Dateien und Verantwortlichkeiten analysieren  
2. mit dem v4-Zielbild abgleichen  
3. Risiken und Kopplungen benennen  
4. realistische Zielstruktur empfehlen  
5. sinnvolle Modularisierungsreihenfolge vorschlagen  

Ich möchte auf Basis deiner Analyse zunächst strategisch weiterarbeiten, bevor die eigentliche Umsetzung beginnt.