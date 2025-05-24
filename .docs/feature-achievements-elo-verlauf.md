# Feature: Achievements und ELO-Verlauf auf Spielerseiten

## Übersicht

Dieses Dokument beschreibt die Implementierung der Achievements-Anzeige und des ELO-Verlaufs auf den Spielerseiten der Kickerliga-Anwendung.

## Implementierte Features

### 1. Achievement-System

Das Achievement-System wurde vollständig implementiert und umfasst:

#### Verfügbare Achievements

| Achievement | Icon | Beschreibung | Bedingung |
|-------------|------|--------------|-----------|
| Winning Streak (3) | 🏆 | 3 Siege in Folge | Mindestens 3 aufeinanderfolgende Siege |
| Winning Streak (5) | 👑 | 5 Siege in Folge | Mindestens 5 aufeinanderfolgende Siege |
| Höchster Sieg | ⚡ | 10+ Tore Differenz | Mindestens 10 Tore Differenz in einem Spiel |
| Bad Keeper | 💀 | Meiste Gegentore | Spieler mit den meisten Gegentoren (min. 10) |
| Torschützenkönig | ⚽ | Meiste erzielte Tore | Spieler mit den meisten Toren (min. 5) |
| Perfekte Bilanz | ⭐ | 100% Siegquote | Nur Siege bei mindestens 3 Spielen |
| Tormaschine | 🚀 | Treffsicherheit | Durchschnittlich 5+ Tore pro Spiel (min. 3 Spiele) |
| Eiserne Abwehr | 🛡️ | Starke Defensive | Durchschnittlich <3 Gegentore pro Spiel (min. 3 Spiele) |
| Unglücksrabe | 😵 | Pechsträhne | 0 Siege bei 5+ Spielen |
| Veteran | 🎖️ | Erfahrung | 10+ absolvierte Spiele |
| Tordifferenz-König | 📈 | Dominanz | +20 Tordifferenz insgesamt |
| Ausgewogen | ⚖️ | Balance | Gleiche Anzahl Tore/Gegentore (min. 5 Spiele) |

#### Technische Implementierung

**AchievementService (`app/Services/AchievementService.php`)**
- Überprüft automatisch alle Achievement-Bedingungen
- Wird nach jedem Spiel für beide Spieler aufgerufen
- Verhindert doppelte Vergabe von Achievements
- Loggt Achievement-Vergaben

**Integration in PlayerController**
- Achievements werden bei jedem Seitenaufruf aktualisiert
- Neue Achievements werden automatisch gespeichert

**Integration in MatchController**
- Nach jedem neuen Spiel werden Achievements für beide Spieler überprüft

### 2. ELO-Verlaufs-Diagramm

#### Funktionalität
- Zeigt die ELO-Entwicklung eines Spielers über die Zeit
- Verwendet Chart.js für interaktive Darstellung
- Responsive Design für verschiedene Bildschirmgrößen
- Dunkles Theme passend zum Anwendungsdesign

#### Technische Implementierung

**Datenaufbereitung im PlayerController**
```php
// Bereite ELO-Historie für Chart.js vor
$eloHistory = $player->getEloHistory();
$eloChartData = [];

foreach ($eloHistory as $entry) {
    $eloChartData[] = [
        'x' => $entry['timestamp'] * 1000, // JavaScript braucht Millisekunden
        'y' => $entry['rating']
    ];
}
```

**Frontend-Darstellung**
- Daten werden über JSON in das Template übertragen
- Chart.js rendert ein Liniendiagramm mit Zeitachse
- Fallback-Anzeige wenn nicht genügend Daten vorhanden

### 3. Verbesserte Spielerseiten-UI

#### Achievement-Sektion
- Visuelle Darstellung mit Icons und Farben
- Datum der Freischaltung wird angezeigt
- Zähler für die Anzahl der Achievements
- Motivierende Nachrichten für Spieler ohne Achievements

#### ELO-Verlaufs-Sektion
- Interaktives Diagramm mit Hover-Effekten
- Zeitbasierte X-Achse mit deutschen Datumsformaten
- Responsive Höhe und Breite
- Fallback-Nachricht bei unzureichenden Daten

## Dateistruktur

### Neue Dateien
```
kickLiga/app/Services/AchievementService.php    # Achievement-Logik
.docs/feature-achievements-elo-verlauf.md       # Diese Dokumentation
```

### Geänderte Dateien
```
kickLiga/app/Controllers/PlayerController.php   # Achievement-Integration
kickLiga/app/Controllers/MatchController.php    # Achievement-Aufruf nach Spielen
kickLiga/app/Config/ContainerConfig.php         # Dependency Injection
kickLiga/templates/players/view.twig            # UI-Verbesserungen
```

## Konfiguration

### Dependency Injection

Der AchievementService wurde in die Container-Konfiguration integriert:

```php
// AchievementService
AchievementService::class => function (Container $container) {
    return new AchievementService(
        $container->get(PlayerService::class),
        $container->get(MatchService::class),
        $container->get(LoggerInterface::class)
    );
},
```

### Controller-Integration

Beide relevanten Controller wurden erweitert:

**PlayerController**: Erhält AchievementService für Achievement-Updates bei Seitenaufrufen
**MatchController**: Erhält AchievementService für Achievement-Checks nach neuen Spielen

## Verwendung

### Automatische Achievement-Vergabe

Achievements werden automatisch vergeben:
1. **Bei Seitenaufruf**: Wenn ein Spielerprofil aufgerufen wird
2. **Nach Spielen**: Wenn ein neues Spiel erfasst wird

### ELO-Verlauf anzeigen

Der ELO-Verlauf wird automatisch angezeigt, wenn:
- Mindestens 2 ELO-Einträge in der Historie vorhanden sind
- Chart.js korrekt geladen wurde

## Erweiterungsmöglichkeiten

### Neue Achievements hinzufügen

1. Achievement in `AchievementService::ACHIEVEMENTS` definieren
2. Prüflogik in entsprechender `check*`-Methode implementieren
3. Optional: Neue Prüfkategorie erstellen

### UI-Verbesserungen

- Achievement-Animationen bei Freischaltung
- Detailansicht für Achievement-Fortschritt
- ELO-Verlauf mit zusätzlichen Metriken
- Achievement-Leaderboard

## Technische Details

### Performance-Überlegungen

- Achievement-Checks werden nur bei Bedarf ausgeführt
- ELO-Historie wird effizient im Player-Model gespeichert
- JavaScript-Daten werden über JSON-Script-Tag übertragen (Linter-freundlich)

### Fehlerbehandlung

- Graceful Degradation wenn Services nicht verfügbar sind
- Fallback-Anzeigen bei fehlenden Daten
- Logging von Achievement-Vergaben für Debugging

### Browser-Kompatibilität

- Chart.js unterstützt moderne Browser
- Responsive Design für mobile Geräte
- Dunkles Theme konsistent mit Anwendungsdesign

## Fazit

Das implementierte Feature erweitert die Spielerseiten um zwei wichtige Funktionen:

1. **Achievements**: Motivieren Spieler durch Belohnungen für besondere Leistungen
2. **ELO-Verlauf**: Visualisiert die Entwicklung der Spielstärke über Zeit

Beide Features sind vollständig in das bestehende System integriert und folgen den etablierten Architekturprinzipien der Anwendung. 