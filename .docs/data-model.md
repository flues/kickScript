# Data Model and Single Source of Truth Architecture

This document describes the revolutionary **Single Source of Truth (SSOT)** data architecture of the Kickerliga Management System after the full migration in 2024.

## 🏆 SSOT Principle

The system follows the **Single Source of Truth** principle: All player, season, and achievement data is exclusively computed from `matches.json`. This eliminates data inconsistencies and allows easy deletion of matches with automatic recalculation of all dependent data.

## 📁 Current Directory Structure

```
data/
├── matches.json              # 📊 SINGLE SOURCE OF TRUTH - All match data
├── players_meta.json         # 👤 Only metadata (name, avatar, nickname)
├── players_backup.json       # 💾 Backup of the old players.json (migration)
└── seasons.json              # 🏆 Only season metadata (name, period, status)
```

### Simplification Compared to Old Architecture

**Before (complex structure):**
```
data/
├── players/[player_id].json  # Redundant player data
├── matches/[year]/[month]/   # Complex directory structure
├── seasons/[year]-[month]/   # Duplicate data storage
└── system/locks/backups/     # Overhead
```

**Now (SSOT architecture):**
```
data/
├── matches.json              # Only source of truth
├── players_meta.json         # Only metadata
└── seasons.json              # Only metadata
```

## 📄 Data Models

### 1. matches.json - Single Source of Truth

**All match data in one file:**

```json
[
  {
    "id": "match_66f123abc",
    "player1Id": "player_6834ef09",
    "player2Id": "player_6834ef15",
    "scorePlayer1": 10,
    "scorePlayer2": 8,
    "playedAt": 1727123456,
    "player1Side": "blue",
    "player2Side": "white",
    "eloChange": {
      "player1": 24,
      "player2": -24
    },
    "notes": "Exciting match!",
    "coinflipData": {
      "result": "heads",
      "winner": 1,
      "sideAssignment": {
        "player1Side": "blue",
        "player2Side": "white"
      }
    }
  }
]
```

**Field Explanation:**
- `id`: Unique match ID (generated with `uniqid('match_')`)
- `player1Id`, `player2Id`: References to players in `players_meta.json`
- `scorePlayer1`, `scorePlayer2`: Goals for each player
- `playedAt`: Unix timestamp of match time
- `player1Side`, `player2Side`: Table side ("blue" or "white")
- `eloChange`: ELO changes for both players
- `notes`: Optional notes for the match
- `coinflipData`: Optional, coinflip data if used
