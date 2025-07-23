
# Feature: Single Source of Truth (SSOT)

## 🎯 Objective

Implementation of a "Single Source of Truth" concept, where all player, season, and achievement data is exclusively calculated from `matches.json`. This eliminates data inconsistencies and enables easy deletion of matches with automatic recalculation of all dependent data.

## 🔍 Problem Analysis

### Original Issues ✅ FULLY RESOLVED
- **Redundant Data Storage**: Player data was stored in both `players.json` and `matches.json`
- **Season Class Design Flaw**: Stored its own `$standings` and `$statistics` even though SSOT should have been implemented
- **Inconsistency Risk**: ELO ratings, statistics, and achievements could diverge between files
- **Memory Issues**: Duplicate data storage led to "Allowed memory size exhausted" errors
- **Complex Updates**: Changes to matches required manual updates in multiple files
- **Synchronization Problems**: Cache was not invalidated after match deletions
- **Template Issues**: "Unknown" for player names due to non-working service calls

### Target State ✅ ACHIEVED
- **One Source of Truth**: `matches.json` as the only persistent data source
- **Computed Data**: All other data is calculated at runtime from matches
- **Automatic Consistency**: Deleting/adding matches automatically triggers recalculation
- **Simplified Architecture**: Fewer files, fewer synchronization problems
- **Memory Efficiency**: Drastically reduced memory usage

## 🏗️ Solution Architecture

### New Data Structure ✅ IMPLEMENTED

```
data/
├── matches.json          # 📊 SINGLE SOURCE OF TRUTH - All match data
├── players_meta.json     # 👤 Only metadata (name, avatar, nickname)
├── players_backup.json   # 💾 Backup of the old players.json
└── seasons.json          # 🏆 Only season metadata (name, period, status)
```

### Data Flow ✅ OPTIMIZED


matches.json (SINGLE SOURCE OF TRUTH)
       ↓
ComputationService (memory-efficient with cache)
       ↓
```
┌─────────────────┬─────────────────┬─────────────────┬─────────────────┐
│   ELO Rating    │   Statistics    │  Achievements   │ Season Tables   │
│   - Calculation │   - Wins        │   - Streaks     │   - Standings   │
│   - History     │   - Goals       │   - Records     │   - Statistics  │
│   - Changes     │   - Sides       │   - Titles      │   - Matches     │
└─────────────────┴─────────────────┴─────────────────┴─────────────────┘
       ↓
PlayerService + SeasonService → Controller → Templates
```

## 🏁 Conclusion

The Single Source of Truth approach ensures that all statistics, ratings, and achievements are always consistent and up-to-date. It simplifies the architecture, reduces memory usage, and makes the system more robust and maintainable. By relying on one central data file, errors due to data duplication and manual synchronization are eliminated, making future development and data management much easier.

