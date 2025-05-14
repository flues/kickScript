# Datenmodell und JSON-Dateistruktur

Dieses Dokument beschreibt die Datenstrukturen und das JSON-basierte Speichersystem des Kickerliga Management Systems.

## Übersicht

Das System verwendet eine dateibasierte JSON-Speicherung anstelle einer relationalen Datenbank. Alle Daten werden in JSON-Dateien gespeichert, die in entsprechenden Verzeichnissen im `data/`-Ordner organisiert sind.

## Verzeichnisstruktur

```
data/
├── players/                   # Spielerdaten
│   ├── [player_id].json      # Einzelne Spielerdateien
│   └── metadata.json         # Zusätzliche Spielerinformationen
├── matches/                   # Spieldaten
│   ├── [year]/               # Nach Jahr organisiert
│   │   ├── [month]/          # Nach Monat organisiert
│   │   │   └── [day].json    # Tägliche Spieldaten
│   │   └── all_matches.json      # Index aller Spiele (optional, für Performancegründe)
│   └── all_matches.json      # Index aller Spiele (optional, für Performancegründe)
├── seasons/                  # Saisonale Daten
│   ├── [year]-[month].json   # Archivierte Saisondaten
│   └── current_season.json   # Aktuelle Saisoninformationen
└── system/                   # Systemdaten
    ├── config.json           # Systemkonfiguration
    ├── locks/                # Datei-Locking-Verzeichnis
    └── backups/              # Automatische Backups
```

## Datenmodelle

### Spieler (Player)

Jeder Spieler wird in einer separaten JSON-Datei gespeichert:

```json
{
  "id": "player123",
  "username": "maxmustermann",
  "name": "Max Mustermann",
  "created_at": "2023-07-15T10:30:00",
  "elo_rating": 1542,
  "elo_history": [
    {
      "date": "2023-07-15T10:30:00",
      "rating": 1500,
      "change": 0
    },
    {
      "date": "2023-07-16T14:20:00",
      "rating": 1532,
      "change": 32,
      "match_id": "match123",
      "opponent_id": "player456"
    }
  ],
  "stats": {
    "total_matches": 10,
    "wins": 6,
    "losses": 4,
    "goals_scored": 65,
    "goals_conceded": 45,
    "goal_difference": 20,
    "current_streak": 2,
    "best_streak": 3,
    "win_ratio": 0.6
  },
  "achievements": [
    {
      "id": "winning_streak",
      "earned_date": "2023-07-18T16:45:00",
      "level": 1,
      "match_id": "match456"
    }
  ],
  "last_active": "2023-07-20T09:15:00"
}
```

Zusätzlich wird eine `metadata.json`-Datei geführt, die Basisdaten aller Spieler enthält:

```json
{
  "players": [
    {
      "id": "player123",
      "username": "maxmustermann",
      "name": "Max Mustermann",
      "elo_rating": 1542
    },
    {
      "id": "player456",
      "username": "annaexample",
      "name": "Anna Example",
      "elo_rating": 1490
    }
  ],
  "last_updated": "2023-07-20T09:15:00"
}
```

### Spiel (Match)

Spiele werden nach Datum in täglichen JSON-Dateien gespeichert:

```json
{
  "matches": [
    {
      "id": "match123",
      "date": "2023-07-16T14:20:00",
      "player1_id": "player123",
      "player2_id": "player456",
      "player1_score": 10,
      "player2_score": 6,
      "player1_elo_before": 1500,
      "player2_elo_before": 1520,
      "player1_elo_after": 1532,
      "player2_elo_after": 1488,
      "player1_elo_change": 32,
      "player2_elo_change": -32
    },
    {
      "id": "match124",
      "date": "2023-07-16T15:40:00",
      "player1_id": "player123",
      "player2_id": "player789",
      "player1_score": 8,
      "player2_score": 10,
      "player1_elo_before": 1532,
      "player2_elo_before": 1480,
      "player1_elo_after": 1510,
      "player2_elo_after": 1502,
      "player1_elo_change": -22,
      "player2_elo_change": 22
    }
  ]
}
```

### Saison (Season)

Saisonale Daten werden monatlich archiviert:

```json
{
  "season_id": "2023-07",
  "start_date": "2023-07-01T00:00:00",
  "end_date": "2023-07-31T23:59:59",
  "rankings": [
    {
      "position": 1,
      "player_id": "player123",
      "username": "maxmustermann",
      "name": "Max Mustermann",
      "elo_rating": 1542,
      "matches": 10,
      "wins": 6,
      "losses": 4
    },
    {
      "position": 2,
      "player_id": "player789",
      "username": "lukasbeispiel",
      "name": "Lukas Beispiel",
      "elo_rating": 1530,
      "matches": 8,
      "wins": 5,
      "losses": 3
    }
  ],
  "matches_played": 28,
  "stats": {
    "total_goals": 325,
    "average_goals_per_match": 11.6,
    "highest_score": {
      "match_id": "match789",
      "player_id": "player123",
      "opponent_id": "player456",
      "score": "10-0",
      "date": "2023-07-18T16:45:00"
    }
  }
}
```

## Datenzugriff und -verwaltung

### DataService

Der zentrale `DataService` ist für das Lesen und Schreiben von Daten verantwortlich:

```php
<?php

declare(strict_types=1);

namespace App\Services;

class DataService
{
    private string $dataPath;
    private array $locks = [];
    
    public function __construct(string $dataPath)
    {
        $this->dataPath = $dataPath;
    }
    
    public function readJsonFile(string $filePath): array
    {
        $fullPath = $this->dataPath . '/' . $filePath;
        
        if (!file_exists($fullPath)) {
            return [];
        }
        
        $content = file_get_contents($fullPath);
        return json_decode($content, true) ?? [];
    }
    
    public function writeJsonFile(string $filePath, array $data): bool
    {
        $fullPath = $this->dataPath . '/' . $filePath;
        $directory = dirname($fullPath);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($fullPath, $json) !== false;
    }
    
    public function lockFile(string $filePath): bool
    {
        $lockPath = $this->dataPath . '/system/locks/' . md5($filePath) . '.lock';
        $lockFile = fopen($lockPath, 'w+');
        
        if ($lockFile === false) {
            return false;
        }
        
        if (!flock($lockFile, LOCK_EX | LOCK_NB)) {
            fclose($lockFile);
            return false;
        }
        
        $this->locks[$filePath] = $lockFile;
        return true;
    }
    
    public function unlockFile(string $filePath): void
    {
        if (isset($this->locks[$filePath])) {
            flock($this->locks[$filePath], LOCK_UN);
            fclose($this->locks[$filePath]);
            unset($this->locks[$filePath]);
        }
    }
    
    // Weitere Methoden für spezifische Datenzugriffe...
}
```

### File-Locking

Um Race Conditions zu vermeiden, wird ein File-Locking-Mechanismus eingesetzt:

1. Vor dem Schreiben wird eine Lock-Datei erstellt und exklusiv gesperrt
2. Nach dem Schreiben wird die Sperre aufgehoben
3. Wenn eine Datei bereits gesperrt ist, wird gewartet oder eine Ausnahme ausgelöst

## Backup-Strategie

Das System implementiert automatische Backups:

1. Vor jeder Änderung wird eine temporäre Kopie der zu ändernden Datei erstellt
2. Bei erfolgreicher Änderung wird die temporäre Datei gelöscht
3. Bei Fehlern kann die ursprüngliche Datei wiederhergestellt werden
4. Zusätzlich werden tägliche, wöchentliche und monatliche Backups des gesamten `data/`-Verzeichnisses erstellt

## Performance-Optimierungen

Für häufig abgerufene Daten werden Index-Dateien gepflegt:

1. `players/metadata.json`: Liste aller Spieler mit Basis-Informationen
2. `matches/all_matches.json`: Optional, für schnellen Zugriff auf die Spielhistorie

Diese Index-Dateien werden automatisch aktualisiert, wenn die zugehörigen Daten geändert werden.

## Datenkonsistenz

Um die Konsistenz zu gewährleisten, werden folgende Strategien eingesetzt:

1. **Transaktionale Schreibvorgänge**: Änderungen werden erst in temporäre Dateien geschrieben und dann atomar umbenannt
2. **Referenzielle Integrität**: Überprüfung von Referenzen (z.B. Spieler-IDs) beim Schreiben
3. **Datenvalidierung**: Schemaprüfung vor dem Speichern
4. **Logging**: Protokollierung aller Änderungen für Nachverfolgung und Fehlerbehebung

## Datenmigration

Für zukünftige Änderungen am Datenmodell werden Migrations-Skripte bereitgestellt, die alte Daten in das neue Format konvertieren. 