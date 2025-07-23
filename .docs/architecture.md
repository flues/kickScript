# Architecture of the Kickerliga Management System

This documentation describes the revolutionary **Single Source of Truth (SSOT)** architecture of the Kickerliga Management System, based on Slim PHP Framework after the full migration in 2024.

## Framework Choice: Slim PHP

The project uses Slim Framework (version 4) for these reasons:

1. Lightweight: Slim is a minimal solution without unnecessary overhead
2. Flexibility: Easy integration of third-party components
3. Modern PHP: Supports modern PHP concepts and PSR standards
4. RESTful Routing: Simple API route definitions
5. Middleware Support: Robust HTTP middleware support
6. Active Community: Good documentation and support
7. Easy Learning Curve: Fast onboarding and productivity

## 🏗️ SSOT Architecture Overview

The system follows a revolutionary **Single Source of Truth** architecture with the MVC pattern:

```
┌───────────────────────────────────────────────────────────────┐
│                        PRESENTATION LAYER                    │
├───────────────────────────────────────────────────────────────┤
│  Controllers (HTTP Request Handling)                         │
│  ├── HomeController      ├── PlayerController                │
│  ├── MatchController     ├── SeasonController                │
│  └── AchievementController                                   │
├───────────────────────────────────────────────────────────────┤
│                        BUSINESS LOGIC LAYER                  │
├───────────────────────────────────────────────────────────────┤
│  Services (Domain Logic)                                     │
│  ├── ComputationService  ← 🏆 SSOT CORE ENGINE                │
│  ├── PlayerService      ← Uses ComputationService            │
│  ├── SeasonService      ← Uses ComputationService            │
│  ├── MatchService       ← Writes to matches.json              │
│  ├── EloService         ← ELO calculations                   │
│  └── DataService        ← File I/O operations                │
├───────────────────────────────────────────────────────────────┤
│                        DATA ACCESS LAYER                     │
├───────────────────────────────────────────────────────────────┤
│  Models (Data Representation)                                │
│  ├── Player              ← For object representation         │
│  ├── GameMatch           ← For object representation         │
│  └── Season              ← Only metadata, no statistics      │
├───────────────────────────────────────────────────────────────┤
│                        STORAGE LAYER                         │
├───────────────────────────────────────────────────────────────┤
│  📊 matches.json         ← SINGLE SOURCE OF TRUTH             │
│  👤 players_meta.json    ← Only metadata                      │
│  🏆 seasons.json         ← Only metadata                      │
└───────────────────────────────────────────────────────────────┘
```

## 🏆 SSOT Core Principles

### 1. Single Source of Truth
- `matches.json` is the only source of truth for all statistics
- All ELO ratings, achievements, and statistics are computed at runtime
- Eliminates data inconsistencies completely

### 2. Computed Data Architecture
- No redundant data storage
- All derived data is computed on-demand
- Memory-efficient implementation with smart caching

### 3. Dependency Injection without Cycles
- Clean service dependencies
- `ComputationService` as the central calculation service
- No circular dependencies between services

## 🔧 Service Architecture (Refactored)

### ComputationService - SSOT Core Engine

**Central Role**: Calculates all data from `matches.json`

```php
class ComputationService
{
    // ...implementation...
}
```
