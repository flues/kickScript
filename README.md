# 🏓 Kickerliga Management System

Ein umfassendes webbasiertes System zur Verwaltung einer Tischfußball-Liga mit ELO-Rating, Achievements, Saisonverwaltung und fortschrittlichem Tischseiten-Tracking.

![Kickerliga Management System](assets/img/preview.png)

## 🌟 Features im Überblick

- **🔥 Vollständiges ELO-Rating-System** mit Tordifferenz-Modifikator
- **🏆 Achievement-System** mit 12 verschiedenen Belohnungen  
- **📊 Umfassende Statistiken** und Chart.js-Visualisierungen
- **⚖️ Tischseiten-Tracking** (Blau vs. Weiß) für Fairness-Analysen
- **🎯 Saisonverwaltung** mit Archivierungsfunktionen
- **📱 Responsive Dark-Theme UI** mit Bootstrap 5
- **🎥 Modernes Video-Background-Design**
- **⚡ Performance-optimiert** mit PSR-4 Autoloading

## 🚀 Quick Start

### Systemvoraussetzungen
- PHP 7.4 oder höher
- Webserver (Apache/Nginx) 
- Moderne Browser mit JavaScript-Unterstützung

### Installation
```bash
# Repository klonen
git clone [repository-url] kickerliga
cd kickerliga

# Dependencies installieren
composer install

# Webserver-Verzeichnis konfigurieren
# DocumentRoot auf 'public' Ordner zeigen lassen
```

**👉 Detaillierte Installationsanleitung: [installation.md](.docs/installation.md)**

## 📚 Projektdokumentation

### 🏗️ Architektur & Grundlagen
- **[project.md](.docs/project.md)** - Hauptprojektdokumentation mit Funktionsübersicht
- **[architektur.md](.docs/architektur.md)** - Slim Framework 4 Architektur und Design Patterns
- **[datenmodell.md](.docs/datenmodell.md)** - JSON-basiertes Datenspeicherungskonzept
- **[installation.md](.docs/installation.md)** - Schritt-für-Schritt Installationsanleitung

### ⚡ Core Features
- **[elo-system.md](.docs/elo-system.md)** - ELO-Rating-Algorithmus mit Tordifferenz-Berechnung
- **[achievements.md](.docs/achievements.md)** - 12 verschiedene Achievement-Typen und Belohnungslogik

### 🔥 Erweiterte Features
- **[feature-tischseiten-tracking.md](.docs/feature-tischseiten-tracking.md)** - Vollständiges Tischseiten-Tracking (✅ **KOMPLETT IMPLEMENTIERT**)
  
  *Umfassendes System zur Erfassung und Analyse der Tischseitenwahl (Blau vs. Weiß) mit Statistiken, Visualisierungen und Fairness-Analysen für alle 8 migrierten Matches.*

- **[feature-achievements-elo-verlauf.md](.docs/feature-achievements-elo-verlauf.md)** - Achievement-System mit ELO-Verlaufs-Diagrammen
  
  *Interaktive Spielerprofile mit automatischer Achievement-Vergabe und Chart.js-basierten ELO-Entwicklungsverläufen.*

### 📋 Projektmanagement
- **[todo.md](.docs/todo.md)** - Aktueller Entwicklungsstatus und Arbeitsplan

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
- **Achievement-System**: 12 verschiedene Achievements
- **Tischseiten-Tracking**: Vollständig implementiert und migriert
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
- **Live-Demo**: [Coming Soon]
- **Issue-Tracker**: [GitHub Issues]
- **Changelog**: [.docs/todo.md](.docs/todo.md)

---

**💡 Tipp**: Beginnen Sie mit der [installation.md](.docs/installation.md) für eine schnelle Einrichtung, oder erkunden Sie die [Featuredokumentation](.docs/) für tiefere Einblicke in spezifische Funktionen.

*Entwickelt mit ❤️ für die Kicker-Community*