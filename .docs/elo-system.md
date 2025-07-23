# ELO Rating System (SSOT Architecture)

This document describes the implementation of the ELO rating system for the Kickerliga after the **Single Source of Truth** migration.

## 🏆 SSOT Principle for ELO Ratings

The ELO system follows the **Single Source of Truth** principle:
- **All ELO ratings are computed at runtime from `matches.json`**
- **No separate storage** of ELO values or history
- **Automatic consistency** - ELO ratings are always correct
- **Chronological calculation** - ELO development is tracked match by match

## 🤖 ELO System Principles

The ELO system is a rating system originally developed for chess, now adapted for our foosball league. Core principles:

1. Each player has a rating number
2. The rating change after a match is based on:
   - The expected result (based on rating difference)
   - The actual result
   - A weighting factor (K-factor)
3. **Goal difference modifier** for more realistic ratings

## ⚙️ SSOT Implementation

### ComputationService - ELO Engine

All ELO calculations are performed in the `ComputationService`:

```php
class ComputationService
{
    /**
     * Computes the current ELO rating of a player from matches.json
     */
    public function computeCurrentEloRating(string $playerId, array $matches): int
    {
        $currentRating = self::DEFAULT_ELO_RATING; // 1000
        
        // Chronologically process all matches
        foreach ($matches as $match) {
            $eloChange = $this->getEloChangeForMatch($playerId, $match);
            $currentRating += $eloChange;
        }
        
        return $currentRating;
    }
    
    /**
     * Computes the full ELO history of a player
     */
    public function computeEloHistory(string $playerId, array $matches): array
    {
        $history = [];
        $currentRating = self::DEFAULT_ELO_RATING;
        
        // Start point
        $history[] = [
            'rating' => $currentRating,
            'change' => 0,
            'timestamp' => $this->getPlayerCreatedAt($playerId),
            'reason' => 'initial'
        ];
        
        // Chronologically process all matches
        foreach ($matches as $match) {
            $eloChange = $this->getEloChangeForMatch($playerId, $match);
            $currentRating += $eloChange;
            
            $opponentId = $match['player1Id'] === $playerId ? $match['player2Id'] : $match['player1Id'];
            $opponentName = $this->getPlayerMeta($opponentId)['name'] ?? 'Unknown';
            
            $history[] = [
                'rating' => $currentRating,
                'change' => $eloChange,
                'timestamp' => $match['playedAt'],
                'reason' => "Match vs. {$opponentName}"
            ];
        }
        
        return $history;
    }
}
```

### ELO Calculation at Runtime

**ELO Change per Match:**
```php
private function getEloChangeForMatch(string $playerId, array $match): int
{
    // Determine player position and opponent
    if ($match['player1Id'] === $playerId) {
        $playerScore = $match['scorePlayer1'];
        $opponentScore = $match['scorePlayer2'];
        $opponentId = $match['player2Id'];
    } elseif ($match['player2Id'] === $playerId) {
        $playerScore = $match['scorePlayer2'];
        $opponentScore = $match['scorePlayer1'];
        $opponentId = $match['player1Id'];
    } else {
        return 0; // Player not in this match
    }
    
    // Get stored ELO change from match data
    if (isset($match['eloChange'])) {
        $eloChangeKey = $match['player1Id'] === $playerId ? 'player1' : 'player2';
        return $match['eloChange'][$eloChangeKey] ?? 0;
    }
    
    // Fallback: Calculate ELO change (for old matches without stored change)
    return $this->calculateEloChange($playerId, $opponentId, $playerScore, $opponentScore, $match['playedAt']);
}
```

## 🔧 ELO Calculation Logic

### Implementation Details

**Starting Rating:** New players begin with a rating of **1000 points**.

**Calculation of ELO Change:**
```
New Rating = Old Rating + K * (Actual Result - Expected Result)
```

Where:
- **K-Factor**: 32 (Standard weighting)
- **Actual Result**: 1 for win, 0 for loss
- **Expected Result**: Calculated based on the rating difference

### Calculation of Expected Result

```
Expected Result = 1 / (1 + 10^((OpponentRating - PlayerRating) / 400))
```

This formula returns a probability between 0 and 1, representing the chance of winning based on the ratings.

### Goal Difference Modifier

To account for the goal difference in the ELO system, we implement a modifier:

```
Modified K-Factor = K * (1 + log10(GoalDifference) / 5)
```

For a goal difference of 1, the modifier is 1.0, for:
- Goal difference 5: K-Factor * 1.14
- Goal difference 10: K-Factor * 1.2

The modified K-Factor is capped at 48 (for very high goal differences).

## 💻 Code Implementation

### EloService - Calculation Logic

The `EloService` contains the pure calculation logic:

```php
<?php

declare(strict_types=1);

namespace App\Services;

class EloService
{
    private const DEFAULT_K_FACTOR = 32;
    private const DEFAULT_RATING = 1000;  // Changed from 1500 to 1000
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
        return $ratingChange; // Only return the change
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

### Integration in ComputationService

```php
class ComputationService
{
    public function __construct(
        private DataService $dataService,
        private EloService $eloService,  // ← EloService for calculations
        private ?LoggerInterface $logger = null
    ) {}
    
    private function calculateEloChange(
        string $playerId, 
        string $opponentId, 
        int $playerScore, 
        int $opponentScore, 
        int $timestamp
    ): int {
        // Get current ratings at the time of the match
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

## 🔄 Data Flow for ELO System

```
matches.json (SINGLE SOURCE OF TRUTH)
       ↓
ComputationService::computeCurrentEloRating()
       ↓
┌─────────────────┬─────────────────┬─────────────────┐
│ Chronological   │ ELO Calculation │ ELO History     │
│ Match Analysis  │ per Match      │ Construction    │
│ - Sorting       │ - EloService   │ - Timestamp     │
│ - Filtering      │ - Goal Difference│ - Changes      │
│ - Validation    │ - K-Factor     │ - Reasons       │
└─────────────────┴─────────────────┴─────────────────┘
       ↓
Computed ELO Data → PlayerService → Controller → Templates
```

## 📊 ELO History (Calculated at Runtime)

For each player, an ELO history is generated at runtime:

```php
// Example of a calculated ELO history
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
        'reason' => 'Match vs. Anna Schmidt'
    ],
    [
        'rating' => 1008,
        'change' => -16,
        'timestamp' => 1727200000,
        'reason' => 'Match vs. Max Mustermann'
    ]
]
```

## 🎨 UI Integration

The ELO ratings are displayed in various places in the user interface:

### 1. Player Profile
```php
// Controller
$player = $this->playerService->getPlayerById($playerId);
$eloHistory = $player['eloHistory']; // Calculated at runtime

// Template - Chart.js Integration
foreach ($eloHistory as $entry) {
    $chartData[] = [
        'x' => $entry['timestamp'] * 1000, // JavaScript timestamp
        'y' => $entry['rating']
    ];
}
```

### 2. Leaderboard
- Sorted by current ELO rating (calculated at runtime)
- Live updates without separate database updates

### 3. Match Display
- Display of ELO change after each game
- Historical ELO values at the time of the match

### 4. Statistics
- ELO development over time
- Comparisons between players
- Seasonal ELO analyses

## 🚀 Advantages of the SSOT ELO Architecture

### 1. Automatic Consistency
- **Always correct**: ELO ratings are recalculated with every call
- **No sync issues**: Impossible to have inconsistent ELO values
- **Automatic correction**: Changes in `matches.json` propagate immediately

### 2. Complete Traceability
- **Transparency**: Every ELO change is traceable
- **Debugging**: Easy troubleshooting of ELO issues
- **Audit trail**: Complete history from a single source

### 3. Performance & Memory
- **Lazy loading**: ELO data calculated only on demand
- **Cache integration**: Uses ComputationService cache
- **Memory-efficient**: No redundant storage

### 4. Flexibility
- **ELO adjustments**: Easy modification of calculation logic
- **Historical corrections**: Match corrections propagate automatically
- **New features**: Easy integration of new ELO variants

## 🔧 Seasonal Adjustments

At the end of a season, ELO ratings can be adjusted:

```php
class ComputationService
{
    /**
     * Calculates season start rating based on previous rating
     */
    public function calculateSeasonStartRating(int $previousRating): int
    {
        // Regression to the mean: 50% of the difference to 1000
        return 1000 + (int)(($previousRating - 1000) * 0.5);
    }
}
```

This calculation:
1. Reduces extreme ratings
2. Gives players with lower ratings a chance to catch up
3. Maintains relative differences

## 🔮 Extension Possibilities

### New ELO Variants
- **Time-based weighting**: Newer matches have more impact
- **Seasonal K-factors**: Different K-factors per season
- **Skill-based adjustments**: Dynamic K-factors based on skill level

### Advanced Analytics
```php
// Calculate ELO volatility
public function calculateEloVolatility(string $playerId): float
{
    $eloHistory = $this->computeEloHistory($playerId, $matches);
    $changes = array_column($eloHistory, 'change');
    return $this->calculateStandardDeviation($changes);
}

// Calculate ELO momentum
public function calculateEloMomentum(string $playerId, int $lastNMatches = 5): float
{
    $recentMatches = array_slice($matches, -$lastNMatches);
    $recentChanges = array_map(fn($m) => $this->getEloChangeForMatch($playerId, $m), $recentMatches);
    return array_sum($recentChanges) / count($recentChanges);
}
```

---

## 📋 Summary

The **SSOT ELO System** offers:

✅ **Automatic consistency**: Always correct ELO ratings  
✅ **Complete traceability**: Every change is transparent  
✅ **Performance**: Memory-efficient through lazy loading  
✅ **Flexibility**: Easy adjustments of calculation logic  
✅ **Maintainability**: Central ELO logic in ComputationService  

**The ELO system is fully integrated into the SSOT architecture and is future-proof! 🎉**