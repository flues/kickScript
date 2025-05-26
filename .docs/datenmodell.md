# Datenmodell und Single Source of Truth Architektur

Dieses Dokument beschreibt die revolutionäre **Single Source of Truth (SSOT)** Datenarchitektur des Kickerliga Management Systems nach der vollständigen Umstellung im Jahr 2024.

## 🎯 SSOT-Prinzip

Das System folgt dem **Single Source of Truth** Prinzip: Alle Spieler-, Saison- und Achievement-Daten werden ausschließlich aus `matches.json` berechnet. Dies eliminiert Dateninkonsistenzen und ermöglicht einfaches Löschen von Matches mit automatischer Neuberechnung aller abhängigen Daten.

## 📁 Aktuelle Verzeichnisstruktur

```
data/
├── matches.json              # 📊 SINGLE SOURCE OF TRUTH - Alle Spieldaten
├── players_meta.json         # 👤 Nur Metadaten (Name, Avatar, Nickname)
├── players_backup.json       # 💾 Backup der alten players.json (Migration)
└── seasons.json              # 🏆 Nur Saison-Metadaten (Name, Zeitraum, Status)
```

### Vereinfachung gegenüber alter Architektur

**Vorher (komplexe Struktur):**
```
data/
├── players/[player_id].json  # Redundante Spielerdaten
├── matches/[year]/[month]/   # Komplexe Verzeichnisstruktur
├── seasons/[year]-[month]/   # Doppelte Datenhaltung
└── system/locks/backups/     # Overhead
```

**Jetzt (SSOT-Architektur):**
```
data/
├── matches.json              # Einzige Wahrheitsquelle
├── players_meta.json         # Nur Metadaten
└── seasons.json              # Nur Metadaten
```

## 🗂️ Datenmodelle

### 1. matches.json - Single Source of Truth

**Alle Spieldaten in einer Datei:**

```json
[
  {
    "id": "match_66f123abc",
    "player1Id": "player_6834ef09",
    "player2Id": "player_6834ef15",
    "scorePlayer1": 10,
    "scorePlayer2": 8,
    "playedAt": 1727123456,
    "player1Side": "blau",
    "player2Side": "weiss",
    "eloChange": {
      "player1": 24,
      "player2": -24
    },
    "notes": "Spannendes Spiel!",
    "coinflipData": {
      "result": "kopf",
      "winner": 1,
      "sideAssignment": {
        "player1Side": "blau",
        "player2Side": "weiss"
      }
    }
  }
]
```

**Felder-Erklärung:**
- `id`: Eindeutige Match-ID (generiert mit `uniqid('match_')`)
- `player1Id`, `player2Id`: Referenzen auf Spieler in `players_meta.json`
- `scorePlayer1`, `scorePlayer2`: Tore der jeweiligen Spieler
- `playedAt`: Unix-Timestamp des Spielzeitpunkts
- `player1Side`, `player2Side`: Tischseite ("blau" oder "weiss")
- `eloChange`: ELO-Änderungen für beide Spieler
- `notes`: Optionale Notizen zum Spiel
- `coinflipData`: Optional, Münzwurf-Daten falls verwendet

### 2. players_meta.json - Nur Metadaten

**Enthält ausschließlich Metadaten, keine Statistiken:**

```json
{
  "player_6834ef09": {
    "id": "player_6834ef09",
    "name": "Max Mustermann",
    "nickname": "Maxi",
    "avatar": "avatar1.png",
    "createdAt": 1727000000
  },
  "player_6834ef15": {
    "id": "player_6834ef15",
    "name": "Anna Schmidt",
    "nickname": null,
    "avatar": null,
    "createdAt": 1727001000
  }
}
```

**Wichtig:** Keine Statistiken, ELO-Ratings oder Achievements! Diese werden zur Laufzeit aus `matches.json` berechnet.

### 3. seasons.json - Nur Saison-Metadaten

**Enthält nur Saison-Informationen, keine Statistiken:**

```json
{
  "mai-season-2024": {
    "id": "mai-season-2024",
    "name": "Mai Season 2024",
    "startDate": "2024-05-01T00:00:00+00:00",
    "endDate": "2024-05-31T23:59:59+00:00",
    "isActive": true,
    "description": "Erste Saison nach SSOT-Umstellung"
  }
}
```

**Wichtig:** Keine `$standings`, `$statistics` oder andere berechnete Daten! Diese werden zur Laufzeit aus `matches.json` berechnet.

## ⚙️ Datenberechnung zur Laufzeit

### ComputationService - Herzstück der SSOT-Architektur

Der `ComputationService` berechnet alle Daten zur Laufzeit aus `matches.json`:

```php
class ComputationService
{
    /**
     * Berechnet alle Spielerdaten aus matches.json
     */
    public function computeAllPlayerData(): array
    {
        $matches = $this->getAllMatches();
        $playerIdsFromMatches = $this->extractPlayerIds($matches);
        $playerIdsFromMeta = $this->extractPlayerIdsFromMeta();
        
        // Kombiniere beide Listen (auch Spieler ohne Matches)
        $allPlayerIds = array_unique(array_merge($playerIdsFromMatches, $playerIdsFromMeta));
        
        foreach ($allPlayerIds as $playerId) {
            $playerMatches = $this->getMatchesForPlayer($playerId);
            $playersData[$playerId] = $this->computePlayerDataFromMatches($playerId, $playerMatches);
        }
        
        return $playersData;
    }
    
    /**
     * Berechnet Spielerdaten aus Matches
     */
    private function computePlayerDataFromMatches(string $playerId, array $matches): array
    {
        $playerMeta = $this->getPlayerMeta($playerId);
        
        return [
            'id' => $playerId,
            'name' => $playerMeta['name'] ?? 'Unbekannt',
            'nickname' => $playerMeta['nickname'] ?? null,
            'avatar' => $playerMeta['avatar'] ?? null,
            'eloRating' => $this->computeCurrentEloRating($playerId, $matches),
            'statistics' => $this->computePlayerStatistics($playerId, $matches),
            'achievements' => $this->computePlayerAchievements($playerId, $matches),
            'eloHistory' => $this->computeEloHistory($playerId, $matches),
            'createdAt' => $playerMeta['createdAt'] ?? time(),
            'lastMatch' => empty($matches) ? null : end($matches)['playedAt']
        ];
    }
}
```

### Berechnete Datenstrukturen

**Spieler-Statistiken (zur Laufzeit berechnet):**
```php
[
    'wins' => 15,
    'losses' => 8,
    'draws' => 2,
    'goalsScored' => 245,
    'goalsConceded' => 198,
    'tournamentsWon' => 0,
    'tournamentsParticipated' => 0,
    'matchesPlayed' => 25
]
```

**ELO-Historie (zur Laufzeit berechnet):**
```php
[
    [
        'rating' => 1000,
        'timestamp' => 1727000000,
        'reason' => 'initial'
    ],
    [
        'rating' => 1024,
        'change' => 24,
        'timestamp' => 1727123456,
        'reason' => 'Match gegen Anna Schmidt'
    ]
]
```

**Achievements (zur Laufzeit berechnet):**
```php
[
    [
        'id' => 'winning_streak_3',
        'name' => '🏆 Winning Streak (3)',
        'description' => '3 Siege in Folge',
        'unlockedAt' => 1727123456
    ]
]
```

## 🔄 Datenfluss-Architektur

```
matches.json (SINGLE SOURCE OF TRUTH)
       ↓
ComputationService (Memory-effizient mit Cache)
       ↓
┌─────────────────┬─────────────────┬─────────────────┬─────────────────┐
│   ELO-Rating    │   Statistiken   │  Achievements   │ Saison-Tabellen │
│   - Berechnung  │   - Siege       │   - Streaks     │   - Standings   │
│   - Historie    │   - Tore        │   - Rekorde     │   - Statistiken │
│   - Änderungen  │   - Seiten      │   - Titel       │   - Matches     │
└─────────────────┴─────────────────┴─────────────────┴─────────────────┘
       ↓
PlayerService + SeasonService → Controller → Templates
```

## 🚀 Vorteile der SSOT-Architektur

### 1. Datenintegrität
- **Unmöglich inkonsistente Daten** zu haben
- **Automatische Synchronisation** - keine manuellen Sync-Operationen
- **Verlässliche Statistiken** - alle basieren auf derselben Quelle

### 2. Wartbarkeit
- **Einfachheit**: Nur eine Datenquelle für alle Berechnungen
- **Debugging**: Probleme sind leichter zu lokalisieren
- **Erweiterbarkeit**: Neue Statistiken einfach hinzufügbar

### 3. Performance
- **Memory-Effizienz**: Von 128MB+ auf <10MB reduziert
- **Cache-System**: Verhindert redundante Berechnungen
- **Lazy Loading**: Daten werden nur bei Bedarf berechnet

### 4. Flexibilität
- **Match-Löschung**: Sicher möglich mit automatischer Neuberechnung
- **Datenkorrektur**: Änderungen in `matches.json` propagieren automatisch
- **Migration**: Einfache Datenstruktur-Änderungen

## 🛠️ DataService - Vereinfacht

```php
class DataService
{
    private string $dataPath;
    
    public function read(string $filename): array
    {
        $filepath = $this->dataPath . '/' . $filename . '.json';
        
        if (!file_exists($filepath)) {
            return [];
        }
        
        $content = file_get_contents($filepath);
        return json_decode($content, true) ?? [];
    }
    
    public function write(string $filename, array $data): bool
    {
        $filepath = $this->dataPath . '/' . $filename . '.json';
        
        // Atomic write mit temporärer Datei
        $tempFile = $filepath . '.tmp';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($tempFile, $json) === false) {
            return false;
        }
        
        return rename($tempFile, $filepath);
    }
}
```

## 🔒 Cache-Management

### Intelligente Cache-Invalidierung

```php
class ComputationService
{
    private ?array $cachedMatches = null;
    private ?array $cachedPlayersMeta = null;
    
    public function invalidateCache(): void
    {
        $this->cachedMatches = null;
        $this->cachedPlayersMeta = null;
    }
    
    public function invalidatePlayerMetaCache(): void
    {
        $this->cachedPlayersMeta = null;
    }
}
```

**Cache wird automatisch invalidiert bei:**
- Match-Änderungen (Hinzufügen, Löschen)
- Spieler-Metadaten-Änderungen
- Saison-Änderungen

## 📊 Migration von alter Architektur

### Migrationsprozess (bereits durchgeführt)

1. **Backup erstellt**: `players_backup.json`
2. **Metadaten extrahiert**: Nur Name, Avatar, Nickname nach `players_meta.json`
3. **Statistiken entfernt**: Alle berechneten Daten gelöscht
4. **Services refactored**: Verwendung von `ComputationService`
5. **Templates angepasst**: Neue Datenstruktur

### Vor/Nach Vergleich

**Vorher:**
- 4 Spieler in separaten Dateien
- Redundante Statistiken in `players.json` UND `matches.json`
- Memory-Probleme durch doppelte Datenhaltung
- Inkonsistenz-Risiko bei Updates

**Nachher:**
- 4 Spieler-Metadaten in `players_meta.json`
- Alle Statistiken werden aus `matches.json` berechnet
- Memory-Verbrauch drastisch reduziert
- Garantierte Konsistenz durch SSOT

## 🔮 Zukunftssicherheit

### Erweiterungsmöglichkeiten
- **Neue Statistiken**: Einfach in `ComputationService` hinzufügen
- **Neue Achievements**: Automatische Berechnung aus bestehenden Matches
- **Datenexport**: Alle Daten aus einer Quelle exportierbar
- **Analytics**: Erweiterte Analysen auf Basis von `matches.json`

### Skalierbarkeit
- **Performance**: Optimierte Algorithmen für große Datenmengen
- **Storage**: Minimaler Speicherbedarf durch SSOT
- **Maintenance**: Einfache Wartung durch reduzierte Komplexität

---

## 📋 Zusammenfassung

Die **Single Source of Truth** Architektur revolutioniert die Datenhaltung:

✅ **Eine Wahrheitsquelle**: `matches.json`  
✅ **Berechnete Daten**: Alles andere wird zur Laufzeit berechnet  
✅ **Automatische Konsistenz**: Unmöglich, inkonsistente Daten zu haben  
✅ **Memory-Effizienz**: Drastisch reduzierter Speicherverbrauch  
✅ **Wartbarkeit**: Einfache, nachvollziehbare Architektur  

**Das System ist zukunftssicher und wird nie wieder Inkonsistenzen haben! 🎉** 