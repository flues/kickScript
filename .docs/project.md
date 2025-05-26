# Kickerliga Management System - Projektdokumentation (SSOT-Architektur)

Ein umfassendes webbasiertes System zur Verwaltung einer Tischfußball-Liga mit ELO-Rating und einem Achievement-System, basierend auf einer revolutionären **Single Source of Truth** Architektur.

## 🎯 SSOT-Revolution

Das System wurde 2024 vollständig auf eine **Single Source of Truth** Architektur umgestellt:
- **Eine Wahrheitsquelle**: Alle Statistiken werden aus `matches.json` berechnet
- **Automatische Konsistenz**: Unmöglich, inkonsistente Daten zu haben
- **Memory-Effizienz**: Von 128MB+ auf <10MB reduziert
- **Zukunftssicher**: Einfache Erweiterungen ohne Datenmigration

## 🚀 Funktionsübersicht

### 📊 Spielerverwaltung & Statistiken (SSOT-basiert)
- **Spielerverwaltung** mit dynamischen ELO-Wertungen (zur Laufzeit berechnet)
- **Live-Rangliste** basierend auf aktuellen ELO-Punkten aus `matches.json`
- **Umfangreiche Spielerstatistiken** (Siege, Niederlagen, Tordifferenz) - alle computed
- **Visuelle ELO-Entwicklung** über Zeit mit Chart.js (zur Laufzeit generiert)
- **Tischseiten-Tracking** (Blau vs. Weiß) für Fairness-Analysen

### ⚽ Spielerfassung & Match-Management
- Einfache Eingabe von 1-gegen-1 Spielen mit **Coinflip-Seitenwahl**
- Automatische ELO-Berechnung nach jedem Spiel (gespeichert in `matches.json`)
- Berücksichtigung der Tordifferenz in der ELO-Wertung
- Vollständige Spielhistorie für alle Matches
- **Match-Löschung** mit automatischer Neuberechnung aller Statistiken

### 🏆 Achievement-System (12 verschiedene Achievements)
Automatische Vergabe von Achievements zur Laufzeit aus `matches.json`:
- **🏆 Winning Streak (3/5)**: Siegesserien
- **👑 Höchster Sieg**: Deutliche Siege (5+ Tore Differenz)
- **⚽ Torschützenkönig**: Meiste erzielte Tore
- **💀 Bad Keeper**: Meiste Gegentore
- **⭐ Perfekte Bilanz**: 100% Siegquote
- **🚀 Tormaschine**: Ø 8+ Tore/Spiel
- **🛡️ Eiserne Abwehr**: Ø <3 Gegentore/Spiel
- **😵 Unglücksrabe**: 0 Siege bei 5+ Spielen
- **🎖️ Veteran**: 10+ absolvierte Spiele
- **📈 Tordifferenz-König**: +15 Tordifferenz insgesamt
- **⚖️ Ausgewogen**: Gleiche Anzahl Tore/Gegentore

### 🔄 Saisonverwaltung (SSOT-optimiert)
- Saisonale Metadaten-Verwaltung in `seasons.json`
- **Live-Berechnung** aller Saison-Statistiken aus `matches.json`
- Saisonübergreifende Statistiken und Vergleiche
- Automatische Saison-Tabellen ohne redundante Datenhaltung

## 💻 Technische Details

### Systemanforderungen
- PHP 7.4 oder höher
- Webserver (Apache/Nginx)
- Moderne Browser mit JavaScript-Unterstützung
- Sollte ohne spezielle Compiler o.Ä. betriebsbereit sein beim einfachen Hochladen auf den Webserver

### SSOT-Architektur
- **Backend**: Slim PHP Framework (Version 4) mit revolutionärer SSOT-Architektur
- **Frontend**: Bootstrap 5 in einem dunklen Design ähnlich Spotify oder Discord mit Gradienten
- **Datenspeicherung**: JSON-basiert mit **Single Source of Truth** Prinzip
- **Grafiken**: Chart.js für dynamische Datenvisualisierung (ELO-Verläufe zur Laufzeit)
- **Icons**: Phosphor Icons für visuelles Feedback und Badges

### ELO-System (SSOT-optimiert)
- **Startrating**: 1000 Punkte für neue Spieler (geändert von 1500)
- **K-Faktor**: 32 (Standardgewichtung für ELO-Berechnung)
- **Tordifferenz-Modifikator**: Zusätzliche Punkte für deutliche Siege
- **Live-Berechnung**: ELO-Ratings werden zur Laufzeit aus `matches.json` berechnet
- **ELO-Historie**: Vollständige Entwicklung chronologisch rekonstruiert

### SSOT-Datenspeicherung
- **`matches.json`**: 📊 **SINGLE SOURCE OF TRUTH** - Alle Spieldaten
- **`players_meta.json`**: 👤 Nur Metadaten (Name, Avatar, Nickname)
- **`seasons.json`**: 🏆 Nur Saison-Metadaten (Name, Zeitraum, Status)
- **`players_backup.json`**: 💾 Migration Backup der alten Struktur
- Geschützte Dateistruktur via .htaccess
- Logging-System für Fehler und wichtige Ereignisse

## 🏗️ SSOT-Projektstruktur

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