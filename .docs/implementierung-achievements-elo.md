# Implementierungsanleitung: Achievements und ELO-Verlauf

## Übersicht

Diese Anleitung beschreibt die Schritte zur Implementierung des Achievement-Systems und der ELO-Verlaufsanzeige auf den Spielerseiten.

## Implementierte Komponenten

### 1. AchievementService
- **Datei**: `kickLiga/app/Services/AchievementService.php`
- **Zweck**: Überprüfung und Vergabe von Achievements basierend auf Spielerstatistiken
- **Features**: 12 verschiedene Achievement-Typen mit automatischer Erkennung

### 2. Controller-Erweiterungen
- **PlayerController**: Integration des AchievementService für Achievement-Updates
- **MatchController**: Automatische Achievement-Prüfung nach neuen Spielen

### 3. UI-Verbesserungen
- **Template**: `kickLiga/templates/players/view.twig`
- **ELO-Diagramm**: Interaktive Chart.js-Visualisierung
- **Achievement-Anzeige**: Verbesserte visuelle Darstellung mit Icons

### 4. Dependency Injection
- **Datei**: `kickLiga/app/Config/ContainerConfig.php`
- **Änderungen**: AchievementService-Registrierung und Controller-Updates

## Funktionsweise

### Achievement-System
1. **Automatische Prüfung**: Bei jedem Spielerprofil-Aufruf und nach neuen Spielen
2. **Verschiedene Kategorien**:
   - Winning Streaks (3 und 5 Siege)
   - Statistische Achievements (Torschützenkönig, Bad Keeper, etc.)
   - Spezielle Leistungen (Perfekte Bilanz, Veteran, etc.)
3. **Doppelvergabe-Schutz**: Achievements werden nur einmal vergeben

### ELO-Verlauf
1. **Datenaufbereitung**: ELO-Historie wird für Chart.js formatiert
2. **Visualisierung**: Zeitbasiertes Liniendiagramm mit responsivem Design
3. **Fallback**: Informative Nachricht bei unzureichenden Daten

## Installation/Deployment

### Voraussetzungen
- Bestehende Kickerliga-Installation
- Chart.js bereits im Frontend verfügbar
- PHP 7.4+ mit allen bestehenden Dependencies

### Deployment-Schritte
1. Neue Dateien hochladen:
   - `kickLiga/app/Services/AchievementService.php`
   
2. Bestehende Dateien aktualisieren:
   - `kickLiga/app/Controllers/PlayerController.php`
   - `kickLiga/app/Controllers/MatchController.php`
   - `kickLiga/app/Config/ContainerConfig.php`
   - `kickLiga/templates/players/view.twig`

3. Keine Datenbank-Änderungen erforderlich (JSON-basierte Speicherung)

### Testen
1. Spielerprofil aufrufen → Achievement-Sektion sollte sichtbar sein
2. Neues Spiel erfassen → Achievements sollten automatisch geprüft werden
3. ELO-Verlauf sollte bei Spielern mit mehreren Spielen angezeigt werden

## Konfiguration

### Achievement-Anpassungen
Neue Achievements können in `AchievementService::ACHIEVEMENTS` hinzugefügt werden:

```php
'new_achievement' => [
    'name' => '🎯 Neues Achievement',
    'description' => 'Beschreibung der Bedingung',
    'icon' => '🎯'
],
```

### UI-Anpassungen
- Achievement-Styling in `templates/players/view.twig`
- Chart.js-Konfiguration im JavaScript-Bereich
- Responsive Breakpoints für verschiedene Bildschirmgrößen

## Monitoring

### Logging
- Achievement-Vergaben werden automatisch geloggt
- Fehler bei der Achievement-Prüfung werden erfasst
- ELO-Berechnungen sind bereits geloggt

### Performance
- Achievement-Checks sind optimiert für häufige Aufrufe
- ELO-Daten werden effizient aus bestehender Player-Historie gelesen
- Keine zusätzlichen Datenbankabfragen erforderlich

## Wartung

### Regelmäßige Aufgaben
- Keine speziellen Wartungsaufgaben erforderlich
- Achievements werden automatisch bei Spieleraktivität aktualisiert
- ELO-Historie wird automatisch bei neuen Spielen erweitert

### Troubleshooting
1. **Achievements werden nicht angezeigt**: Prüfe AchievementService-Registrierung in ContainerConfig
2. **ELO-Diagramm lädt nicht**: Prüfe Chart.js-Verfügbarkeit und JavaScript-Konsole
3. **Performance-Probleme**: Prüfe Anzahl der Achievement-Checks pro Seitenaufruf

## Erweiterungen

### Geplante Features
- Achievement-Benachrichtigungen
- Detaillierte Achievement-Fortschrittsanzeige
- Achievement-Leaderboard
- Erweiterte ELO-Statistiken

### Anpassungsmöglichkeiten
- Neue Achievement-Kategorien
- Anpassbare Achievement-Schwellenwerte
- Zusätzliche Chart-Typen für ELO-Analyse
- Export-Funktionen für Statistiken 