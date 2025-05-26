# ELO-Rating System (SSOT-Architektur)

Dieses Dokument beschreibt die Implementierung des ELO-Ratingsystems für die Kickerliga nach der **Single Source of Truth** Umstellung.

## 🎯 SSOT-Prinzip für ELO-Ratings

Das ELO-System folgt dem **Single Source of Truth** Prinzip:
- **Alle ELO-Ratings werden zur Laufzeit aus `matches.json` berechnet**
- **Keine separate Speicherung** von ELO-Werten oder Historie
- **Automatische Konsistenz** - ELO-Ratings sind immer korrekt
- **Chronologische Berechnung** - ELO-Entwicklung wird Match für Match nachvollzogen

## 🧮 Grundprinzipien des ELO-Systems

Das ELO-System ist ein Bewertungssystem, das ursprünglich für Schach entwickelt wurde und nun für unsere Kicker-Liga angepasst wird. Die Kernprinzipien sind:

1. Jeder Spieler hat eine Bewertungszahl (Rating)
2. Die Änderung des Ratings nach einem Spiel basiert auf:
   - Dem erwarteten Ergebnis (basierend auf der Differenz der Ratings)
   - Dem tatsächlichen Ergebnis
   - Einem Gewichtungsfaktor (K-Faktor)
3. **Tordifferenz-Modifikator** für realistischere Bewertungen

## ⚙️ SSOT-Implementierung

### ComputationService - ELO-Engine

Alle ELO-Berechnungen finden im `ComputationService` statt:

```php
class ComputationService
{
    /**
     * Berechnet das aktuelle ELO-Rating eines Spielers aus matches.json
     */
    public function computeCurrentEloRating(string $playerId, array $matches): int
    {
        $currentRating = self::DEFAULT_ELO_RATING; // 1000
        
        // Chronologisch durch alle Matches gehen
        foreach ($matches as $match) {
            $eloChange = $this->getEloChangeForMatch($playerId, $match);
            $currentRating += $eloChange;
        }
        
        return $currentRating;
    }
    
    /**
     * Berechnet die komplette ELO-Historie eines Spielers
     */
    public function computeEloHistory(string $playerId, array $matches): array
    {
        $history = [];
        $currentRating = self::DEFAULT_ELO_RATING;
        
        // Startpunkt
        $history[] = [
            'rating' => $currentRating,
            'change' => 0,
            'timestamp' => $this->getPlayerCreatedAt($playerId),
            'reason' => 'initial'
        ];
        
        // Chronologisch durch alle Matches
        foreach ($matches as $match) {
            $eloChange = $this->getEloChangeForMatch($playerId, $match);
            $currentRating += $eloChange;
            
            $opponentId = $match['player1Id'] === $playerId ? $match['player2Id'] : $match['player1Id'];
            $opponentName = $this->getPlayerMeta($opponentId)['name'] ?? 'Unbekannt';
            
            $history[] = [
                'rating' => $currentRating,
                'change' => $eloChange,
                'timestamp' => $match['playedAt'],
                'reason' => "Match gegen {$opponentName}"
            ];
        }
        
        return $history;
    }
}
```

### ELO-Berechnung zur Laufzeit

**ELO-Änderung pro Match:**
```php
private function getEloChangeForMatch(string $playerId, array $match): int
{
    // Bestimme Spieler-Position und Gegner
    if ($match['player1Id'] === $playerId) {
        $playerScore = $match['scorePlayer1'];
        $opponentScore = $match['scorePlayer2'];
        $opponentId = $match['player2Id'];
    } elseif ($match['player2Id'] === $playerId) {
        $playerScore = $match['scorePlayer2'];
        $opponentScore = $match['scorePlayer1'];
        $opponentId = $match['player1Id'];
    } else {
        return 0; // Spieler nicht in diesem Match
    }
    
    // Hole gespeicherte ELO-Änderung aus Match-Daten
    if (isset($match['eloChange'])) {
        $eloChangeKey = $match['player1Id'] === $playerId ? 'player1' : 'player2';
        return $match['eloChange'][$eloChangeKey] ?? 0;
    }
    
    // Fallback: Berechne ELO-Änderung (für alte Matches ohne gespeicherte Änderung)
    return $this->calculateEloChange($playerId, $opponentId, $playerScore, $opponentScore, $match['playedAt']);
}
```

## 🔧 ELO-Berechnungslogik

### Implementierungsdetails

**Startrating:** Neue Spieler beginnen mit einem Rating von **1000 Punkten**.

**Berechnung der ELO-Änderung:**
```
Neues Rating = Altes Rating + K * (Tatsächliches Ergebnis - Erwartetes Ergebnis)
```

Wobei:
- **K-Faktor**: 32 (Standardgewichtung)
- **Tatsächliches Ergebnis**: 1 für Sieg, 0 für Niederlage
- **Erwartetes Ergebnis**: Berechnet basierend auf dem Ratingunterschied

### Berechnung des erwarteten Ergebnisses

```
Erwartetes Ergebnis = 1 / (1 + 10^((GegnerRating - SpielerRating) / 400))
```

Diese Formel gibt eine Wahrscheinlichkeit zwischen 0 und 1 zurück, die die Gewinnchance basierend auf den Ratings darstellt.

### Tordifferenz-Modifikator

Um die Tordifferenz im ELO-System zu berücksichtigen, implementieren wir einen Modifikator:

```
Modifizierter K-Faktor = K * (1 + log10(Tordifferenz) / 5)
```

Für eine Tordifferenz von 1 ist der Modifikator 1.0, für:
- Tordifferenz 5: K-Faktor * 1.14
- Tordifferenz 10: K-Faktor * 1.2

Der modifizierte K-Faktor wird nach oben auf 48 begrenzt (bei sehr hohen Tordifferenzen).

## 💻 Code-Implementierung

### EloService - Berechnungslogik

Der `EloService` enthält die reine Berechnungslogik:

```php
<?php

declare(strict_types=1);

namespace App\Services;

class EloService
{
    private const DEFAULT_K_FACTOR = 32;
    private const DEFAULT_RATING = 1000;  // Geändert von 1500 auf 1000
    private const MAX_K_FACTOR = 48;

    public function calculateNewRatings(
        int $playerRating,
        int $opponentRating,
        bool $playerWon,
        int $goalDifference
    ): int {
        $expectedOutcome = $this->calculateExpectedOutcome($playerRating, $opponentRating);
        $actualOutcome = $playerWon ? 1.0 : 0.0;
        $kFactor = $this->getModifiedKFactor($goalDifference);
        
        $ratingChange = (int)round($kFactor * ($actualOutcome - $expectedOutcome));
        return $ratingChange; // Nur die Änderung zurückgeben
    }
    
    private function calculateExpectedOutcome(int $playerRating, int $opponentRating): float
    {
        return 1.0 / (1.0 + pow(10, ($opponentRating - $playerRating) / 400.0));
    }
    
    private function getModifiedKFactor(int $goalDifference): float
    {
        if ($goalDifference <= 1) {
            return self::DEFAULT_K_FACTOR;
        }
        
        $modifier = 1.0 + (log10($goalDifference) / 5.0);
        $modifiedK = self::DEFAULT_K_FACTOR * $modifier;
        
        return min($modifiedK, self::MAX_K_FACTOR);
    }
    
    public function getDefaultRating(): int
    {
        return self::DEFAULT_RATING;
    }
}
```

### Integration im ComputationService

```php
class ComputationService
{
    public function __construct(
        private DataService $dataService,
        private EloService $eloService,  // ← EloService für Berechnungen
        private ?LoggerInterface $logger = null
    ) {}
    
    private function calculateEloChange(
        string $playerId, 
        string $opponentId, 
        int $playerScore, 
        int $opponentScore, 
        int $timestamp
    ): int {
        // Hole aktuelle Ratings zum Zeitpunkt des Matches
        $playerRating = $this->getEloRatingAtTime($playerId, $timestamp);
        $opponentRating = $this->getEloRatingAtTime($opponentId, $timestamp);
        
        $playerWon = $playerScore > $opponentScore;
        $goalDifference = abs($playerScore - $opponentScore);
        
        return $this->eloService->calculateNewRatings(
            $playerRating,
            $opponentRating,
            $playerWon,
            $goalDifference
        );
    }
}
```

## 🔄 Datenfluss für ELO-System

```
matches.json (SINGLE SOURCE OF TRUTH)
       ↓
ComputationService::computeCurrentEloRating()
       ↓
┌─────────────────┬─────────────────┬─────────────────┐
│ Chronologische  │ ELO-Berechnung  │ ELO-Historie    │
│ Match-Analyse   │ pro Match       │ Aufbau          │
│ - Sortierung    │ - EloService    │ - Zeitstempel   │
│ - Filterung     │ - Tordifferenz  │ - Änderungen    │
│ - Validierung   │ - K-Faktor      │ - Gründe        │
└─────────────────┴─────────────────┴─────────────────┘
       ↓
Computed ELO Data → PlayerService → Controller → Templates
```

## 📊 ELO-Historie (Zur Laufzeit berechnet)

Für jeden Spieler wird eine ELO-Historie zur Laufzeit generiert:

```php
// Beispiel einer berechneten ELO-Historie
[
    [
        'rating' => 1000,
        'change' => 0,
        'timestamp' => 1727000000,
        'reason' => 'initial'
    ],
    [
        'rating' => 1024,
        'change' => 24,
        'timestamp' => 1727123456,
        'reason' => 'Match gegen Anna Schmidt'
    ],
    [
        'rating' => 1008,
        'change' => -16,
        'timestamp' => 1727200000,
        'reason' => 'Match gegen Max Mustermann'
    ]
]
```

## 🎨 UI-Integration

Die ELO-Ratings werden an verschiedenen Stellen der Benutzeroberfläche angezeigt:

### 1. Spielerprofil
```php
// Controller
$player = $this->playerService->getPlayerById($playerId);
$eloHistory = $player['eloHistory']; // Zur Laufzeit berechnet

// Template - Chart.js Integration
foreach ($eloHistory as $entry) {
    $chartData[] = [
        'x' => $entry['timestamp'] * 1000, // JavaScript timestamp
        'y' => $entry['rating']
    ];
}
```

### 2. Rangliste
- Sortierung nach aktuellem ELO-Rating (zur Laufzeit berechnet)
- Live-Updates ohne separate Datenbank-Updates

### 3. Match-Anzeige
- Anzeige der ELO-Änderung nach jedem Spiel
- Historische ELO-Werte zum Zeitpunkt des Matches

### 4. Statistiken
- ELO-Entwicklung über Zeit
- Vergleiche zwischen Spielern
- Saisonale ELO-Analysen

## 🚀 Vorteile der SSOT-ELO-Architektur

### 1. Automatische Konsistenz
- **Immer korrekt**: ELO-Ratings werden bei jedem Aufruf neu berechnet
- **Keine Sync-Probleme**: Unmöglich, inkonsistente ELO-Werte zu haben
- **Automatische Korrektur**: Änderungen in `matches.json` propagieren sofort

### 2. Vollständige Nachvollziehbarkeit
- **Transparenz**: Jede ELO-Änderung ist nachvollziehbar
- **Debugging**: Einfache Fehlersuche bei ELO-Problemen
- **Audit-Trail**: Komplette Historie aus einer Quelle

### 3. Performance & Memory
- **Lazy Loading**: ELO-Daten nur bei Bedarf berechnet
- **Cache-Integration**: Nutzt ComputationService-Cache
- **Memory-effizient**: Keine redundante Speicherung

### 4. Flexibilität
- **ELO-Anpassungen**: Einfache Änderung der Berechnungslogik
- **Historische Korrekturen**: Match-Korrekturen propagieren automatisch
- **Neue Features**: Einfache Integration neuer ELO-Varianten

## 🔧 Saisonale Anpassungen

Am Ende einer Saison können ELO-Ratings angepasst werden:

```php
class ComputationService
{
    /**
     * Berechnet Saison-Start-Rating basierend auf vorherigem Rating
     */
    public function calculateSeasonStartRating(int $previousRating): int
    {
        // Regression zur Mitte: 50% des Unterschieds zu 1000
        return 1000 + (int)(($previousRating - 1000) * 0.5);
    }
}
```

Diese Berechnung:
1. Reduziert extreme Ratings
2. Gibt Spielern mit niedrigeren Ratings die Chance aufzuholen
3. Behält relative Unterschiede bei

## 🔮 Erweiterungsmöglichkeiten

### Neue ELO-Varianten
- **Zeitbasierte Gewichtung**: Neuere Matches haben mehr Einfluss
- **Saisonale K-Faktoren**: Verschiedene K-Faktoren pro Saison
- **Skill-basierte Anpassungen**: Dynamische K-Faktoren basierend auf Spielstärke

### Advanced Analytics
```php
// ELO-Volatilität berechnen
public function calculateEloVolatility(string $playerId): float
{
    $eloHistory = $this->computeEloHistory($playerId, $matches);
    $changes = array_column($eloHistory, 'change');
    return $this->calculateStandardDeviation($changes);
}

// ELO-Momentum berechnen
public function calculateEloMomentum(string $playerId, int $lastNMatches = 5): float
{
    $recentMatches = array_slice($matches, -$lastNMatches);
    $recentChanges = array_map(fn($m) => $this->getEloChangeForMatch($playerId, $m), $recentMatches);
    return array_sum($recentChanges) / count($recentChanges);
}
```

---

## 📋 Zusammenfassung

Das **SSOT-ELO-System** bietet:

✅ **Automatische Konsistenz**: Immer korrekte ELO-Ratings  
✅ **Vollständige Nachvollziehbarkeit**: Jede Änderung ist transparent  
✅ **Performance**: Memory-effizient durch Lazy Loading  
✅ **Flexibilität**: Einfache Anpassungen der Berechnungslogik  
✅ **Wartbarkeit**: Zentrale ELO-Logik im ComputationService  

**Das ELO-System ist vollständig in die SSOT-Architektur integriert und zukunftssicher! 🎉** 