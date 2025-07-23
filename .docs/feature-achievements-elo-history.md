# Feature: Achievements and ELO History on Player Pages

## Overview

This document describes the implementation of achievement display and ELO history on player pages in the Kickerliga application.

## Implemented Features

### 1. Achievement System

The achievement system is fully implemented and includes:

#### Available Achievements

| Achievement | Icon | Description | Condition |
|-------------|------|-------------|-----------|
| Winning Streak (3) | 🏆 | 3 wins in a row | At least 3 consecutive wins |
| Winning Streak (5) | 👑 | 5 wins in a row | At least 5 consecutive wins |
| Highest Victory | ⚡ | 10+ goal difference | At least 10 goal difference in a match |
| Bad Keeper | 💀 | Most goals conceded | Player with most conceded goals (min. 10) |
| Top Scorer | ⚽ | Most goals scored | Player with most goals (min. 5) |
| Perfect Record | ⭐ | 100% win rate | Only wins with at least 3 matches |
| Goal Machine | 🚀 | Accuracy | Average 5+ goals per match (min. 3 matches) |
| Iron Defense | 🛡️ | Strong defense | Average <3 goals conceded per match (min. 3 matches) |
| Unlucky | 😵 | Losing streak | 0 wins in 5+ matches |
| Veteran | 🎖️ | Experience | 10+ matches played |
| Goal Difference King | 📈 | Dominance | +20 total goal difference |
| Balanced | ⚖️ | Balance | Equal number of goals/conceded (min. 5 matches) |

#### Technical Implementation

**AchievementService (`app/Services/AchievementService.php`)**
- Automatically checks all achievement conditions
- Called after every match for both players
- Prevents duplicate achievement assignment
- Logs achievement assignments

**Integration in PlayerController**
- Achievements are updated on every page load
- New achievements are automatically saved

**Integration in MatchController**
- After every new match, achievements for both players are checked

### 2. ELO History Chart

#### Functionality
- Shows a player's ELO development over time
- Uses Chart.js for interactive display
- Responsive design for various screen sizes
- Dark theme matching the app design

#### Technical Implementation

**Data preparation in PlayerController**
```php
// Prepare ELO history for Chart.js
$eloHistory = $player->getEloHistory();
$eloChartData = [];
foreach ($eloHistory as $entry) {
    $eloChartData[] = [
        'x' => $entry['timestamp'] * 1000, // JavaScript needs milliseconds
        'y' => $entry['rating']
    ];
}
```

**Frontend display**
- Data is transferred to the template via JSON
- Chart.js renders a line chart with a time axis
- Fallback display if not enough data is available

### 3. Improved Player Page UI

#### Achievement Section
- Visual display with icons and colors
- Unlock date is shown
- Counter for number of achievements
- Motivational messages for players without achievements
