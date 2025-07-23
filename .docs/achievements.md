# Achievement System (SSOT Architecture)

This document describes the achievement system of the Kickerliga after the **Single Source of Truth** migration, which automatically awards players for various accomplishments.

## 🏆 SSOT Principle for Achievements

The achievement system follows the **Single Source of Truth** principle:
- **All achievements are computed at runtime from `matches.json`**
- **No separate storage** of achievement data
- **Automatic consistency** - achievements are always up to date
- **Easy extension** - new achievements without data migration

## 🏅 Types of Achievements

Based on the current implementation, there are **12 different achievement types**:

| Badge | Name | Description | Condition |
|-------|------|-------------|-----------|
| 🏆 | Winning Streak (3) | Winning streak | 3 wins in a row |
| 🔥 | Winning Streak (5) | Big winning streak | 5 wins in a row |
| 👑 | Highest Victory | Clear victory | 5+ goal difference in a match |
| 💀 | Bad Keeper | Weak defense | Most goals conceded (relative) |
| ⚽ | Top Scorer | Offensive strength | Most goals scored (relative) |
| ⭐ | Perfect Record | Only wins | 100% win rate (min. 3 matches) |
| 🚀 | Goal Machine | Accuracy | Avg. 8+ goals/match (min. 3 matches) |
| 🛡️ | Iron Defense | Strong defense | Avg. <3 goals conceded/match (min. 3 matches) |
| 😵 | Unlucky | Losing streak | 0 wins in 5+ matches |
| 🎖️ | Veteran | Experience | 10+ matches played |
| 📈 | Goal Difference King | Dominance | +15 total goal difference |
| ⚖️ | Balanced | Balance | Equal number of goals/conceded (min. 5 matches) |

## ⚙️ SSOT Implementation

### ComputationService - Achievement Engine

All achievements are computed in the `ComputationService`:

```php
class ComputationService
{
    /**
     * Computes all achievements for a player from matches.json
     */
    public function computePlayerAchievements(string $playerId, array $matches): array
    {
        $achievements = [];
        if (empty($matches)) {
            return $achievements;
        }
        // Compute statistics for achievement checks
        $stats = $this->computePlayerStatistics($playerId, $matches);
        // Check all achievement types
        $achievements = array_merge(
            $achievements,
            $this->checkWinningStreakAchievements($playerId, $matches),
            $this->checkHighScoreAchievements($playerId, $matches),
            $this->checkStatisticalAchievements($playerId, $stats, $matches),
            $this->checkRelativeAchievements($playerId, $stats)
        );
        return $achievements;
    }
}
```

### Achievement Calculation at Runtime

**Winning Streak Achievements:**
```php
private function checkWinningStreakAchievements(string $playerId, array $matches): array
{
    $achievements = [];
    $currentStreak = 0;
    $maxStreak = 0;
    
    foreach ($matches as $match) {
        $isWinner = $this->isPlayerWinner($playerId, $match);
        
        if ($isWinner) {
            $currentStreak++;
            $maxStreak = max($maxStreak, $currentStreak);
        } else {
            $currentStreak = 0;
        }
    }
    
    // Winning Streak (3)
    if ($maxStreak >= 3) {
        $achievements[] = [
            'id' => 'winning_streak_3',
            'name' => '🏆 Winning Streak (3)',
            'description' => '3 wins in a row',
            'unlockedAt' => $this->findStreakUnlockTime($playerId, $matches, 3)
        ];
    }
    
    // Winning Streak (5)
    if ($maxStreak >= 5) {
        $achievements[] = [
            'id' => 'winning_streak_5',
            'name' => '🔥 Winning Streak (5)',
            'description' => '5 wins in a row',
            'unlockedAt' => $this->findStreakUnlockTime($playerId, $matches, 5)
        ];
    }
    
    return $achievements;
}
```

**Statistical Achievements:**
```php
private function checkStatisticalAchievements(string $playerId, array $stats, array $matches): array
{
    $achievements = [];
    
    // Perfekte Bilanz
    if ($stats['matchesPlayed'] >= 3 && $stats['losses'] === 0) {
        $achievements[] = [
            'id' => 'perfect_record',
            'name' => '⭐ Perfekte Bilanz',
            'description' => '100% Siegquote (min. 3 Spiele)',
            'unlockedAt' => end($matches)['playedAt']
        ];
    }
    
    // Tormaschine
    $avgGoalsScored = $stats['matchesPlayed'] > 0 ? $stats['goalsScored'] / $stats['matchesPlayed'] : 0;
    if ($stats['matchesPlayed'] >= 3 && $avgGoalsScored >= 8) {
        $achievements[] = [
            'id' => 'goal_machine',
            'name' => '🚀 Tormaschine',
            'description' => 'Ø 8+ Tore/Spiel (min. 3 Spiele)',
            'unlockedAt' => end($matches)['playedAt']
        ];
    }
    
    // Eiserne Abwehr
    $avgGoalsConceded = $stats['matchesPlayed'] > 0 ? $stats['goalsConceded'] / $stats['matchesPlayed'] : 0;
    if ($stats['matchesPlayed'] >= 3 && $avgGoalsConceded < 3) {
        $achievements[] = [
            'id' => 'iron_defense',
            'name' => '🛡️ Eiserne Abwehr',
            'description' => 'Ø <3 Gegentore/Spiel (min. 3 Spiele)',
            'unlockedAt' => end($matches)['playedAt']
        ];
    }
    
    // Weitere statistische Achievements...
    
    return $achievements;
}
```

### Relative Achievements

Für Achievements wie "Torschützenkönig" und "Bad Keeper" werden alle Spieler verglichen:

```php
private function checkRelativeAchievements(string $playerId, array $stats): array
{
    $achievements = [];
    $allPlayersData = $this->computeAllPlayerData();
    
    if (count($allPlayersData) < 2) {
        return $achievements; // Braucht mindestens 2 Spieler für Vergleiche
    }
    
    // Torschützenkönig
    $maxGoalsScored = max(array_column($allPlayersData, 'statistics.goalsScored'));
    if ($stats['goalsScored'] === $maxGoalsScored && $stats['goalsScored'] > 0) {
        $achievements[] = [
            'id' => 'top_scorer',
            'name' => '⚽ Torschützenkönig',
            'description' => 'Meiste erzielte Tore',
            'unlockedAt' => time()
        ];
    }
    
    // Bad Keeper
    $maxGoalsConceded = max(array_column($allPlayersData, 'statistics.goalsConceded'));
    if ($stats['goalsConceded'] === $maxGoalsConceded && $stats['goalsConceded'] > 0) {
        $achievements[] = [
            'id' => 'bad_keeper',
            'name' => '💀 Bad Keeper',
            'description' => 'Meiste Gegentore',
            'unlockedAt' => time()
        ];
    }
    
    return $achievements;
}
```

## 🔄 Datenfluss für Achievements

```
matches.json (SINGLE SOURCE OF TRUTH)
       ↓
ComputationService::computePlayerAchievements()
       ↓
┌─────────────────┬─────────────────┬─────────────────┬─────────────────┐
│ Winning Streaks │ High Score      │ Statistical     │ Relative        │
│ - 3er Serie     │ - 5+ Tore Diff  │ - Perfekt       │ - Torschütze    │
│ - 5er Serie     │ - Höchster Sieg │ - Tormaschine   │ - Bad Keeper    │
│                 │                 │ - Eiserne Abwehr│                 │
└─────────────────┴─────────────────┴─────────────────┴─────────────────┘
       ↓
Computed Achievements Array → PlayerService → Controller → Templates
```

## 🎨 UI-Integration

### Spielerprofile
```php
// Im PlayerController
$player = $this->playerService->getPlayerById($playerId);
$achievements = $player['achievements']; // Zur Laufzeit berechnet

// Im Template
foreach ($achievements as $achievement) {
    echo "<span class='badge achievement' title='{$achievement['description']}'>";
    echo $achievement['name'];
    echo "</span>";
}
```

### Achievement-Anzeige
- **Farbige Icons** basierend auf Achievement-Typ
- **Tooltips** mit detaillierten Beschreibungen
- **Sortierung** nach Unlock-Datum
- **Responsive Design** für mobile Geräte

## 🚀 Vorteile der SSOT-Achievement-Architektur

### 1. Automatische Konsistenz
- **Immer aktuell**: Achievements werden bei jedem Aufruf neu berechnet
- **Keine veralteten Daten**: Unmöglich, inkonsistente Achievement-Zustände zu haben
- **Automatische Korrektur**: Änderungen in `matches.json` propagieren sofort

### 2. Einfache Erweiterung
```php
// Neues Achievement hinzufügen - nur eine Methode!
private function checkNewAchievement(string $playerId, array $stats): array
{
    $achievements = [];
    
    // Neue Logik hier
    if ($stats['someCondition']) {
        $achievements[] = [
            'id' => 'new_achievement',
            'name' => '🆕 Neues Achievement',
            'description' => 'Neue Bedingung erfüllt',
            'unlockedAt' => time()
        ];
    }
    
    return $achievements;
}
```

### 3. Performance & Memory
- **Lazy Loading**: Achievements nur bei Bedarf berechnet
- **Cache-Integration**: Nutzt ComputationService-Cache
- **Memory-effizient**: Keine redundante Speicherung

### 4. Debugging & Wartung
- **Nachvollziehbar**: Alle Achievement-Logik in einer Klasse
- **Testbar**: Einfache Unit-Tests möglich
- **Transparent**: Jederzeit nachprüfbar, warum ein Achievement vergeben wurde

## 🔮 Erweiterungsmöglichkeiten

### Neue Achievement-Typen
- **Zeitbasierte Achievements**: "Früher Vogel" (Spiel vor 9 Uhr)
- **Saisonale Achievements**: "Saisonkönig" (meiste Siege in einer Saison)
- **Combo-Achievements**: "Allrounder" (mehrere andere Achievements)
- **Tischseiten-Achievements**: "Blau-Spezialist" (hohe Winrate auf blauer Seite)

### Achievement-Levels
```php
// Mehrstufige Achievements
'veteran' => [
    'level_1' => ['matches' => 10, 'name' => '🎖️ Veteran'],
    'level_2' => ['matches' => 25, 'name' => '🏅 Erfahrener Veteran'],
    'level_3' => ['matches' => 50, 'name' => '🏆 Legende']
]
```

### Seltene Achievements
- **Dynamische Schwierigkeit**: Achievements werden schwerer, je mehr Spieler sie haben
- **Zeitlich begrenzte Achievements**: Nur in bestimmten Zeiträumen verfügbar
- **Geheime Achievements**: Werden nicht angezeigt, bis sie freigeschaltet sind

---

## 📋 Zusammenfassung

Das **SSOT-Achievement-System** bietet:

✅ **Automatische Konsistenz**: Immer aktuelle Achievements  
✅ **Einfache Erweiterung**: Neue Achievements ohne Datenmigration  
✅ **Performance**: Memory-effizient durch Lazy Loading  
✅ **Wartbarkeit**: Alle Logik zentral im ComputationService  
✅ **Flexibilität**: Einfache Anpassung von Bedingungen  

**Das Achievement-System ist vollständig in die SSOT-Architektur integriert und zukunftssicher! 🎉**