# Kickerliga Management System - Project Documentation (SSOT Architecture)

A comprehensive web-based system for managing a table football league with ELO rating and an achievement system, based on a revolutionary **Single Source of Truth** architecture.

## 🏆 SSOT Revolution

The system was fully migrated to a **Single Source of Truth** architecture in 2024:
- **One source of truth**: All statistics are computed from `matches.json`
- **Automatic consistency**: Impossible to have inconsistent data
- **Memory efficiency**: Reduced from 128MB+ to <10MB
- **Future-proof**: Easy extensions without data migration

## 🚀 Feature Overview

### 📊 Player Management & Statistics (SSOT-based)
- **Player management** with dynamic ELO ratings (computed at runtime)
- **Live leaderboard** based on current ELO points from `matches.json`
- **Comprehensive player statistics** (wins, losses, goal difference) - all computed
- **Visual ELO development** over time with Chart.js (generated at runtime)
- **Table side tracking** (Blue vs. White) for fairness analysis

### ⚽ Match Recording & Management
- Simple entry of 1-vs-1 matches with **coinflip side selection**
- Automatic ELO calculation after each match (stored in `matches.json`)
- Goal difference considered in ELO rating
- Complete match history for all matches
- **Match deletion** with automatic recalculation of all statistics

### 🏆 Achievement System (12 different achievements)
Automatic awarding of achievements at runtime from `matches.json`:
- **🏆 Winning Streak (3/5)**: Winning streaks
- **👑 Highest Victory**: Clear victories (5+ goal difference)
- **⚽ Top Scorer**: Most goals scored
- **💀 Bad Keeper**: Most goals conceded
- **⭐ Perfect Record**: 100% win rate
- **🚀 Goal Machine**: Avg. 8+ goals/match
- **🛡️ Iron Defense**: Avg. <3 goals conceded/match
- **😵 Unlucky**: 0 wins in 5+ matches
- **🎖️ Veteran**: 10+ matches played
- **📈 Goal Difference King**: +15 total goal difference
- **⚖️ Balanced**: Equal number of goals/conceded

### 🔄 Season Management (SSOT-optimized)
- Seasonal metadata management in `seasons.json`
- **Live calculation** of all season statistics from `matches.json`
- Cross-season statistics and comparisons
- Automatic season tables without redundant data storage

## 💻 Technical Details

### System Requirements
- PHP 7.4 or higher
- Web server (Apache/Nginx)
- Modern browsers with JavaScript support
- Should run without special compilers etc. by simply uploading to the web server

### SSOT Architecture
- **Backend**: Slim PHP Framework (Version 4) with revolutionary SSOT architecture
- **Frontend**: Bootstrap 5 in a dark design similar to Spotify or Discord with gradients
- **Data storage**: JSON-based with **Single Source of Truth** principle
- **Graphics**: Chart.js for dynamic data visualization (ELO history at runtime)
- **Icons**: Phosphor Icons for visual feedback and badges

### ELO System (SSOT-optimized)
- **Starting rating**: 1000 points for new players (changed from 1500)
- **K-factor**: 32 (standard weighting for ELO calculation)
- **Goal difference modifier**: Extra points for clear victories
- **Live calculation**: ELO ratings are computed at runtime from `matches.json`
- **ELO history**: Full development chronologically reconstructed

### SSOT Data Storage
- **`matches.json`**: 📊 **SINGLE SOURCE OF TRUTH** - All match data
- **`players_meta.json`**: 👤 Only metadata (name, avatar, nickname)
- **`seasons.json`**: 🏆 Only season metadata (name, period, status)
- **`players_backup.json`**: 💾 Migration backup of the old structure
- Protected file structure via .htaccess
- Logging system for errors and important events

## 🏗️ SSOT Project Structure

Das Projekt folgt dem MVC-Muster mit revolutionärer SSOT-Architektur:

```
kickScript/
├── 📁 kickLiga/                    # Hauptanwendung
│   ├── 📁 app/                     # PHP Application Logic
│   │   ├── 📁 Controllers/         # HTTP Request Handler
│   │   │   ├── HomeController.php              # Dashboard
│   │   │   ├── PlayerController.php            # Spielerverwaltung
│   │   │   ├── MatchController.php             # Match-Management
│   │   │   └── SeasonController.php            # Saisonverwaltung
│   │   ├── 📁 Models/              # Data Models (nur Repräsentation)
│   │   │   ├── Player.php                      # Spieler-Objekte
│   │   │   ├── GameMatch.php                   # Match-Objekte
│   │   │   └── Season.php                      # Saison-Objekte
│   │   ├── 📁 Services/            # Business Logic (SSOT-Core)
│   │   │   ├── 🎯 ComputationService.php       # SSOT CORE ENGINE
│   │   │   ├── PlayerService.php               # Metadaten + Delegation
│   │   │   ├── MatchService.php                # Match-Erstellung
│   │   │   ├── SeasonService.php               # Saison-Management
│   │   │   ├── EloService.php                  # ELO-Berechnungslogik
│   │   │   └── DataService.php                 # Datei-I/O
│   │   └── 📁 Config/              # Konfiguration & DI Container
│   │       ├── dependencies.php                # Service-Container
│   │       ├── routes.php                      # Slim-Routes
│   │       └── middleware.php                  # Middleware-Stack
│   ├── 📁 public/                  # Web Root
│   │   ├── 📄 index.php            # Application Entry Point
│   │   └── 📁 assets/              # Frontend Assets
│   │       ├── css/                            # Dark Theme Styles
│   │       ├── js/                             # JavaScript-Module
│   │       └── img/                            # Bilder & Video-Backgrounds
│   ├── 📁 templates/               # Twig Templates
│   │   ├── layout/                             # Basis-Templates
│   │   ├── pages/                              # Seiten-Templates
│   │   └── components/                         # Wiederverwendbare Komponenten
│   ├── 📁 data/                    # 🎯 SSOT Data Storage
│   │   ├── 📊 matches.json         # SINGLE SOURCE OF TRUTH
│   │   ├── 👤 players_meta.json    # Nur Metadaten
│   │   ├── 🏆 seasons.json         # Nur Metadaten
│   │   └── 💾 players_backup.json  # Migration Backup
│   └── 📁 vendor/                  # Composer Dependencies
├── 📁 .docs/                       # Projektdokumentation
│   ├── feature-single-source-of-truth.md      # SSOT-Dokumentation
│   ├── architektur.md                         # Architektur-Details
│   ├── datenmodell.md                         # SSOT-Datenmodell
│   ├── achievements.md                        # Achievement-System
│   ├── elo-system.md                          # ELO-Berechnungen
│   └── project-structure.md                   # Projektstruktur
├── 📄 README.md                    # Hauptdokumentation
└── 📄 composer.json                # Dependency Management
```

## 🔧 SSOT-Service-Architektur

### ComputationService - Herzstück der SSOT-Architektur
```php
class ComputationService
{
    // 🎯 Zentrale SSOT-Funktionen
    public function computeAllPlayerData(): array          // Alle Spielerdaten
    public function computePlayerData(string $playerId): array  // Einzelspieler
    public function computeCurrentEloRating(string $playerId, array $matches): int
    public function computePlayerAchievements(string $playerId, array $matches): array
    public function calculateStandings(array $matches): array
    
    // 🔄 Cache-Management für Performance
    public function invalidateCache(): void
    public function invalidatePlayerMetaCache(): void
}
```

### Saubere Service-Hierarchie
- **PlayerService**: Metadaten-Verwaltung + Delegation an ComputationService
- **MatchService**: Match-Erstellung + Cache-Invalidierung
- **SeasonService**: Saison-Management + Delegation an ComputationService
- **DataService**: Einziger direkter Dateizugriff (Atomic Operations)

## 🚀 SSOT-Vorteile

### 1. Datenintegrität
- **Unmöglich inkonsistente Daten** zu haben
- **Automatische Synchronisation** - keine manuellen Sync-Operationen
- **Verlässliche Statistiken** - alle basieren auf derselben Quelle

### 2. Performance & Memory
- **Memory-Effizienz**: Von 128MB+ auf <10MB reduziert
- **Cache-System**: Verhindert redundante Berechnungen
- **Lazy Loading**: Daten werden nur bei Bedarf berechnet

### 3. Wartbarkeit & Erweiterbarkeit
- **Einfachheit**: Nur eine Datenquelle für alle Berechnungen
- **Debugging**: Probleme sind leichter zu lokalisieren
- **Neue Features**: Einfach in ComputationService hinzufügbar

### 4. Flexibilität
- **Match-Löschung**: Sicher möglich mit automatischer Neuberechnung
- **Datenkorrektur**: Änderungen in `matches.json` propagieren automatisch
- **Migration**: Einfache Datenstruktur-Änderungen

## 🎨 Frontend-Features

### Dark Theme Design
- **Bootstrap 5**: Modernes Dark Theme ähnlich Discord/Spotify
- **Gradient-Effekte**: Professionelle Optik mit CSS3
- **Video-Backgrounds**: Immersive Benutzeroberfläche
- **Responsive Design**: Mobile-First Ansatz

### Interaktive Komponenten
- **Chart.js Integration**: ELO-Verlaufs-Diagramme zur Laufzeit generiert
- **Coinflip-Animation**: CSS3 + JavaScript für Seitenwahl
- **Achievement-Badges**: Dynamische Anzeige mit Tooltips
- **Live-Updates**: Automatische Aktualisierung ohne Page-Reload

## 🔐 Sicherheit & Qualität

### Code-Qualität
- **PSR-12**: Extended Coding Style Standards
- **Type Safety**: `declare(strict_types=1)` in allen Dateien
- **Dependency Injection**: Saubere Service-Abhängigkeiten ohne Zyklen
- **Error Handling**: Umfassende Exception-Behandlung

### Datensicherheit
- **Atomic Operations**: Verhindert korrupte Dateien
- **Input Validation**: Server-seitige Validierung aller Eingaben
- **XSS-Schutz**: Twig Auto-Escaping
- **File Security**: Data-Verzeichnis außerhalb Web-Root

## 🔮 Zukunftssicherheit

### Einfache Erweiterungen
- **Neue Statistiken**: Einfach in ComputationService hinzufügen
- **Neue Achievements**: Automatische Berechnung aus bestehenden Matches
- **API-Endpunkte**: RESTful API-Erweiterung möglich
- **Analytics**: Erweiterte Analysen auf Basis von `matches.json`

### Skalierbarkeit
- **Performance**: Optimierte Algorithmen für große Datenmengen
- **Storage**: Minimaler Speicherbedarf durch SSOT
- **Maintenance**: Einfache Wartung durch reduzierte Komplexität

---

## 📋 Zusammenfassung

Das **Kickerliga Management System** mit SSOT-Architektur bietet:

✅ **Revolutionäre Architektur**: Single Source of Truth eliminiert Inkonsistenzen  
✅ **Performance**: Memory-Verbrauch von 128MB+ auf <10MB reduziert  
✅ **12 Achievement-Typen**: Automatisch berechnet zur Laufzeit  
✅ **Tischseiten-Tracking**: Vollständig implementiert und migriert  
✅ **Coinflip-System**: Faire Seitenwahl mit Animationen  
✅ **ELO-System**: Zur Laufzeit berechnet mit vollständiger Historie  
✅ **Dark Theme**: Modernes, responsives Design  
✅ **Zukunftssicher**: Einfache Erweiterungen ohne Datenmigration  

**Das System ist produktionsbereit und revolutioniert die Datenhaltung! 🎉**