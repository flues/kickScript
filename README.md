
# Kickerliga Management System

## Table of Contents (English)
- [Project Overview](#project-overview)
- [Features](#features)
- [Quick Start](#quick-start)
- [Project Documentation](#project-documentation)
- [Main Functions](#main-functions)
- [Design-ux](#design-ux)
- [Technical Details](#technical-details)
- [Development Status](#development-status)
- [Contributing](#contributing)
- [License](#license)
- [Links](#links)

# 🏓 Project Overview

A comprehensive web-based system for managing a table football league, featuring ELO rating, achievements, season management, and advanced table side tracking.

**Made with ❤️ for offices with foosball tables 🏓**

![Kickerliga Management System](/kickLiga/public/assets/img/preview.png)

## Features

- Full ELO rating system with goal difference modifier
- Achievement system with [12 different rewards](.docs/achievements.md)
- Extensive statistics and Chart.js visualizations
- Table side tracking [(Blue vs. White)](.docs/feature-table-side-tracking.md) for fairness analysis
- Season management with archiving functions
- Responsive dark-theme UI with Bootstrap 5
- Modern video background design
- Performance optimized with PSR-4 autoloading

## Quick Start

### System Requirements
- PHP 7.4 or higher
- Web server (Apache/Nginx)
- Modern browsers with JavaScript support

### Quickstart Installation
```powershell
# Clone repository
git clone [repository-url] kickerliga
cd kickLiga

# Install dependencies
composer install

# Start local server
php -S localhost:1337 -t public
# Open local app
http://localhost:1337

# Deploy: Configure web server (PHP/htaccess)
# Upload complete app including vendor folder to web server
# Set DocumentRoot to 'public' folder
```

**👉 Detailed installation guide: [installation.md](.docs/installation.md)**

## Project Documentation

### 🏗️ Architecture & Basics
- **[project.md](.docs/project.md)** - Main project documentation with feature overview
- **[architecture.md](.docs/architecture.md)** - Slim Framework 4 architecture and design patterns
- **[data-model.md](.docs/data-model.md)** - JSON-based data storage concept
- **[installation.md](.docs/installation.md)** - Step-by-step installation guide

### ⚡ Core Features
- **[elo-system.md](.docs/elo-system.md)** - ELO rating algorithm with goal difference calculation
- **[achievements.md](.docs/achievements.md)** - 12 different achievement types and reward logic
- **[feature-single-source-of-truth.md](.docs/feature-single-source-of-truth.md)** - Single Source of Truth architecture (**FULLY IMPLEMENTED**)

  *Revolutionary data architecture: All player, season, and achievement data are exclusively calculated from `matches.json`. Eliminates data inconsistencies and enables easy deletion of matches with automatic recalculation of all dependent data.*

### 🔥 Advanced Features
- **[feature-table-side-tracking.md](.docs/feature-table-side-tracking.md)** - Complete table side tracking (**FULLY IMPLEMENTED**)

  *Comprehensive system for recording and analyzing table side selection (Blue vs. White) with statistics, visualizations, and fairness analysis for all migrated matches.*

- **[feature-achievements-elo-history.md](.docs/feature-achievements-elo-history.md)** - Achievement system with ELO history charts

  *Interactive player profiles with automatic achievement assignment and Chart.js-based ELO development history.*

- **[feature-coinflip-side-selection.md](.docs/feature-coinflip-side-selection.md)** - Coinflip system for fair side selection (**FULLY IMPLEMENTED**)

  *Interactive coinflip interface with animations for fair table side selection. Fully integrated into the match recording system with automatic side assignment.*

## Main Functions

### 👥 Player Management
- Complete CRUD operations for players
- Detailed player profiles with statistics
- ELO rating history with interactive charts
- Achievement display with unlock dates

### ⚽ Match Recording & Matching
- Intuitive match recording with side selection
- Automatic ELO calculation after each game
- Table side tracking (Blue/White) for fairness analysis
- Comprehensive match history with filters

### 📊 Statistics & Analytics
- ELO system: Dynamic rating calculation with goal difference bonus
- Side statistics: Win rate analysis per table side
- Achievement tracking: 12 different reward categories
- Visualizations: Chart.js-based diagrams and trends

### 🏆 Season Management
- Season change with rating adjustments
- Historical data archiving
- Cross-season statistics
- Leaderboard functions

## Design-ux

### 🌙 Dark Theme
- Fully responsive Bootstrap 5 dark theme
- Gradient-based UI with modern glance effects
- Phosphor icons for professional look

### 🎥 Video Background
- Immersive video backgrounds for better UX
- Transparent UI elements with backdrop filter
- Performance-optimized display

### 📱 Responsive Design
- Mobile-first approach
- Touch-optimized operation
- Flexible grid layouts

## Technical Details

### Framework & Dependencies
- **Backend**: Slim Framework 4.x with PSR-4 autoloading
- **Templating**: Twig template engine
- **Frontend**: Bootstrap 5, Chart.js, Phosphor Icons
- **Database**: JSON-based data storage with file locking

### Architecture Highlights
- **[Single Source of Truth](.docs/feature-single-source-of-truth.md)**: Revolutionary data architecture eliminates inconsistencies
- Memory optimization: Drastically reduced memory usage (128MB+ → <10MB)
- Cache system: Intelligent invalidation for performance without data loss
- SSOT principle: All statistics are calculated at runtime from `matches.json`

### Code Quality
- PSR-12 Extended Coding Style Standards
- Dependency Injection Container (PHP-DI)
- Comprehensive error handling and logging
- XSS protection via Twig escaping

### Project Structure
```
kickLiga/
├── app/                    # PHP Application Logic
│   ├── Controllers/        # Request Handler
│   ├── Models/            # Data Models  
│   ├── Services/          # Business Logic
│   └── Config/            # Configuration
├── public/                # Web Root
│   ├── assets/           # CSS, JS, Images
│   └── index.php         # Application Entry Point
├── templates/             # Twig Templates
├── data/                 # JSON Data Storage
└── .docs/               # Feature Documentation
```

## Development Status

### ✅ Completed Features
- Core system: Player, match, and ELO management
- [Achievement system](.docs/achievements.md): 12 different achievements
- [Table side tracking](.docs/feature-table-side-tracking.md): Fully implemented and migrated
- [Single Source of Truth](.docs/feature-single-source-of-truth.md): Revolutionary data architecture
- Responsive UI: Dark theme with video backgrounds
- Season management: With archiving and leaderboards

### 🔄 In Development
- Advanced statistics dashboards
- Automatic backup mechanisms
- API endpoints for external integration

### 📝 Planned Features
- Tournament bracket system
- Email notifications
- Advanced analytics dashboard

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push the branch (`git push origin feature/amazing-feature`)
5. Open a pull request

## License

This project is licensed under the MIT License. See the `LICENSE` file for details.

## Links

- **Project documentation**: [.docs/](.docs/)
- **Live demo**: [https://kick.flues.dev](https://kick.flues.dev)
- **Issue tracker**: [GitHub Issues]
- **Changelog**: [.docs/todo.md](.docs/todo.md)

---

## Gemini AI Integration (Daily Summary)

This project can produce a short daily AI summary of the league using Gemini. The integration expects an API key to be available at runtime as an environment variable.

Where to place `.env`
- Runtime (server): place your `.env` in `kickLiga/.env`. The application (and the scheduled runner) will prefer `kickLiga/.env` first when loading environment variables.
- Development (optional): you may also keep a repo-root `.env` for local development convenience, but server runtime prioritizes `kickLiga/.env`.

1. Create a `.env` file in `kickLiga/` (copy from `.env.example`) and add your Gemini API key:

```
GEMINI_API_KEY=your_real_api_key_here
GEMINI_MODEL=gemini-2.5-flash
APP_ENV=production
```

2. Do not commit `.env` — it is already listed in `.gitignore`.

3. A CLI runner is provided at `bin/daily-analysis.php`. It computes season and player stats and calls Gemini to produce a 1-2 sentence summary. The summary is written to `kickLiga/data/ai_summary.txt` and shown at the top of the dashboard.

4. Scheduling options

- Recommended: configure a system scheduler (Cron on Linux, Task Scheduler on Windows) to run `bin/daily-analysis.php` daily.

  Example (cron, daily at 06:00):

  ```cron
  0 6 * * * /usr/bin/php /path/to/repo/bin/daily-analysis.php >> /path/to/repo/kickLiga/logs/daily-analysis.log 2>&1
  ```

  Example (Windows Task Scheduler via PowerShell):

  ```powershell
  $Action = New-ScheduledTaskAction -Execute 'php' -Argument 'W:\kickScript\bin\daily-analysis.php'
  $Trigger = New-ScheduledTaskTrigger -Daily -At 6:00AM
  Register-ScheduledTask -TaskName "KickLigaDailyAnalysis" -Action $Action -Trigger $Trigger -User 'SYSTEM'
  ```

- Alternative: If you cannot configure a scheduler on the host, the app includes a "lazy trigger" that will run the analysis once per 24 hours when the first visitor arrives. This is implemented in `HomeController::maybeSpawnDailyAnalysis()` and uses a timestamp file (`kickLiga/data/ai_summary_generated_at`) and a lockfile to avoid race conditions. It requires `GEMINI_API_KEY` to be present in `kickLiga/.env`.

Notes:
- `vlucas/phpdotenv` is included in the project and `ContainerConfig` will load `.env` from `kickLiga/` first, then fall back to the repository root if present. A lightweight fallback parser is also present for environments where phpdotenv cannot be used.
- The `GeminiService` uses a minimal cURL wrapper. If you prefer, switch to the official Google GenAI SDK for PHP for more robust auth and features.
- The runner expects `vendor/` to be installed (run `composer install`) and `GEMINI_API_KEY` to be set in the environment or `kickLiga/.env`.

Security tip: treat API keys as secrets. Do not commit `.env` to version control. Consider using a platform secret manager in production.

---
# 🏓 Kickerliga Management System

Ein umfassendes webbasiertes System zur Verwaltung einer Tischfußball-Liga mit ELO-Rating, Achievements, Saisonverwaltung und fortschrittlichem Tischseiten-Tracking.

**❤️ Gemacht mit Herz für Büros mit Tischkicker 🏓**

![Kickerliga Management System](/kickLiga/public/assets/img/preview.png)

## 🌟 Features im Überblick

- **🔥 Vollständiges ELO-Rating-System** mit Tordifferenz-Modifikator
- **🏆 Achievement-System** mit [12 verschiedenen Belohnungen](.docs/achievements.md)  
- **📊 Umfassende Statistiken** und Chart.js-Visualisierungen
- **⚖️ Tischseiten-Tracking** [(Blau vs. Weiß)](.docs/feature-table-side-tracking.md) für Fairness-Analysen
- **🎯 Saisonverwaltung** mit Archivierungsfunktionen
- **📱 Responsive Dark-Theme UI** mit Bootstrap 5
- **🎥 Modernes Video-Background-Design**
- **⚡ Performance-optimiert** mit PSR-4 Autoloading

## 🚀 Quick Start

### Systemvoraussetzungen
- PHP 7.4 oder höher
- Webserver (Apache/Nginx) 
- Moderne Browser mit JavaScript-Unterstützung

### Quickstart Installation
```bash
# Repository klonen
git clone [repository-url] kickerliga
cd kickLiga

# Dependencies installieren
composer install

# Lokalen Server starten
php -S localhost:1337 -t public
# Lokale App aufrufen
http://localhost:1337

# Deploy: Webserver passend konfigurieren (PHP/htaccess)
# Komplette App inkl. vendor-Ordner auf den Webserver hochladen
# DocumentRoot auf 'public' Ordner zeigen lassen

```

**👉 Detaillierte Installationsanleitung: [installation.md](.docs/installation.md)**

## 📚 Projektdokumentation

### 🏗️ Architektur & Grundlagen
- **[project.md](.docs/project.md)** - Hauptprojektdokumentation mit Funktionsübersicht
- **[architecture.md](.docs/architecture.md)** - Slim Framework 4 Architektur und Design Patterns
- **[data-model.md](.docs/data-model.md)** - JSON-basiertes Datenspeicherungskonzept
- **[installation.md](.docs/installation.md)** - Schritt-für-Schritt Installationsanleitung

### ⚡ Core Features
- **[elo-system.md](.docs/elo-system.md)** - ELO-Rating-Algorithmus mit Tordifferenz-Berechnung
- **[achievements.md](.docs/achievements.md)** - 12 verschiedene Achievement-Typen und Belohnungslogik
- **[feature-single-source-of-truth.md](.docs/feature-single-source-of-truth.md)** - Single Source of Truth Architektur (✅ **VOLLSTÄNDIG IMPLEMENTIERT**)
  
  *Revolutionäre Datenarchitektur: Alle Spieler-, Saison- und Achievement-Daten werden ausschließlich aus `matches.json` berechnet. Eliminiert Dateninkonsistenzen und ermöglicht einfaches Löschen von Matches mit automatischer Neuberechnung aller abhängigen Daten.*

### 🔥 Erweiterte Features
- **[feature-table-side-tracking.md](.docs/feature-table-side-tracking.md)** - Vollständiges Tischseiten-Tracking (✅ **KOMPLETT IMPLEMENTIERT**)
  
  *Umfassendes System zur Erfassung und Analyse der Tischseitenwahl (Blau vs. Weiß) mit Statistiken, Visualisierungen und Fairness-Analysen für alle 8 migrierten Matches.*

- **[feature-achievements-elo-history.md](.docs/feature-achievements-elo-history.md)** - Achievement-System mit ELO-Verlaufs-Diagrammen
  
  *Interaktive Spielerprofile mit automatischer Achievement-Vergabe und Chart.js-basierten ELO-Entwicklungsverläufen.*

- **[feature-coinflip-side-selection.md](.docs/feature-coinflip-side-selection.md)** - Münzwurf-System für faire Seitenwahl (✅ **KOMPLETT IMPLEMENTIERT**)
  
  *Interaktives Münzwurf-Interface mit Animationen für faire Tischseitenwahl. Vollständig integriert in das Match-Erfassungssystem mit automatischer Seitenzuweisung.*


## 🎮 Hauptfunktionen

### 👥 Spielerverwaltung
- Vollständige CRUD-Operationen für Spieler
- Detaillierte Spielerprofile mit Statistiken
- ELO-Rating-Historie mit interaktiven Charts
- Achievement-Anzeige mit Freischaltungsdaten

### ⚽ Spielerfassung & Matching
- Intuitive Spielerfassung mit Seitenwahl
- Automatische ELO-Berechnung nach jedem Spiel
- Tischseiten-Tracking (Blau/Weiß) für Fairness-Analysen
- Umfassende Spielhistorie mit Filtern

### 📊 Statistiken & Analytics
- **ELO-System**: Dynamische Rating-Berechnung mit Tordifferenz-Bonus
- **Seitenstatistiken**: Win-Rate-Analysen pro Tischseite
- **Achievement-Tracking**: 12 verschiedene Belohnungskategorien
- **Visualisierungen**: Chart.js-basierte Diagramme und Trends

### 🏆 Saisonverwaltung
- Saisonwechsel mit Rating-Anpassungen
- Historische Datenarchivierung
- Saisonübergreifende Statistiken
- Leaderboard-Funktionen

## 🎨 Design & UX

### 🌙 Dark Theme
- Vollständig responsives Bootstrap 5 Dark Theme
- Gradient-basierte UI mit modernen Glance-Effekten
- Phosphor Icons für professionelle Optik

### 🎥 Video Background
- Immersive Video-Hintergründe für bessere UX
- Transparente UI-Elemente mit Backdrop-Filter
- Performance-optimierte Darstellung

### 📱 Responsive Design
- Mobile-First Ansatz
- Touch-optimierte Bedienung
- Flexible Grid-Layouts

## 🛠️ Technische Details

### Framework & Dependencies
- **Backend**: Slim Framework 4.x mit PSR-4 Autoloading
- **Templating**: Twig Template Engine
- **Frontend**: Bootstrap 5, Chart.js, Phosphor Icons
- **Datenbank**: JSON-basierte Datenspeicherung mit File-Locking

### Architektur-Highlights
- **[Single Source of Truth](.docs/feature-single-source-of-truth.md)**: Revolutionäre Datenarchitektur eliminiert Inkonsistenzen
- **Memory-Optimierung**: Drastisch reduzierter Speicherverbrauch (128MB+ → <10MB)
- **Cache-System**: Intelligente Invalidierung für Performance ohne Datenverlust
- **SSOT-Prinzip**: Alle Statistiken werden zur Laufzeit aus `matches.json` berechnet

### Code-Qualität
- PSR-12 Extended Coding Style Standards
- Dependency Injection Container (PHP-DI)
- Umfassende Fehlerbehandlung und Logging
- XSS-Schutz durch Twig-Escaping

### Projektstruktur
```
kickLiga/
├── app/                    # PHP Application Logic
│   ├── Controllers/        # Request Handler
│   ├── Models/            # Data Models  
│   ├── Services/          # Business Logic
│   └── Config/            # Configuration
├── public/                # Web Root
│   ├── assets/           # CSS, JS, Images
│   └── index.php         # Application Entry Point
├── templates/             # Twig Templates
├── data/                 # JSON Data Storage
└── .docs/               # Feature Documentation
```

## 🚀 Development Status

### ✅ Abgeschlossene Features
- **Core-System**: Spieler-, Match- und ELO-Verwaltung
- **[Achievement-System](.docs/achievements.md)**: [12 verschiedene Achievements](.docs/feature-achievements-elo-history.md)
- **[Tischseiten-Tracking](.docs/feature-table-side-tracking.md)**: Vollständig implementiert und migriert
- **[Single Source of Truth](.docs/feature-single-source-of-truth.md)**: Revolutionäre Datenarchitektur
- **Responsive UI**: Dark Theme mit Video-Backgrounds
- **Saisonverwaltung**: Mit Archivierung und Leaderboards

### 🔄 In Entwicklung
- Erweiterte Statistik-Dashboards
- Automatische Backup-Mechanismen
- API-Endpunkte für externe Integration

### 📝 Geplante Features
- Tournament-Bracket-System
- E-Mail-Benachrichtigungen
- Advanced Analytics Dashboard

## 🤝 Contributing

1. Fork des Repositories erstellen
2. Feature-Branch erstellen (`git checkout -b feature/amazing-feature`)
3. Changes committen (`git commit -m 'Add amazing feature'`)
4. Branch pushen (`git push origin feature/amazing-feature`)
5. Pull Request öffnen

## 📄 Lizenz

Dieses Projekt steht unter der MIT-Lizenz. Siehe `LICENSE` Datei für Details.

## 🔗 Links

- **Projektdokumentation**: [.docs/](.docs/)
- **Live-Demo**: [https://kick.flues.dev](https://kick.flues.dev)
- **Issue-Tracker**: [GitHub Issues]
- **Changelog**: [.docs/todo.md](.docs/todo.md)

---

**💡 Tipp**: Für eine schnelle Einrichtung am besten mit der [installation.md](.docs/installation.md) starten, oder die [Featuredokumentation](.docs/) für tiefere Einblicke in spezifische Funktionen erkunden.

*Entwickelt mit ❤️ für Büros mit Tischkicker*