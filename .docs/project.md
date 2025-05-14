# Kickerliga Management System - Projektdokumentation

Ein umfassendes webbasiertes System zur Verwaltung einer Tischfußball-Liga mit ELO-Rating und einem Achievements-System.

## 🚀 Funktionsübersicht

### 📊 Spielerverwaltung & Statistiken
- **Spielerverwaltung** mit individuellen ELO-Wertungen
- **Dynamische Rangliste** basierend auf aktuellen ELO-Punkten
- **Umfangreiche Spielerstatistiken** (Siege, Niederlagen, Tordifferenz)
- **Visuelle Darstellung** der ELO-Entwicklung über Zeit

### ⚽ Spielerfassung
- Einfache Eingabe von 1-gegen-1 Spielen
- Automatische ELO-Berechnung nach jedem Spiel
- Berücksichtigung der Tordifferenz in der Wertung
- Vollständige Spielhistorie für alle Matches

### 🥇 Achievement-System
Automatische Vergabe von Achievements (Badges) für besondere Leistungen gemäß der in der README aufgeführten Tabelle.

### 🔄 Saisonverwaltung
- Automatische monatliche Archivierung der Ergebnisse
- Countdown-Timer zum nächsten Reset
- Saisonübergreifende Statistiken und Vergleiche

## 💻 Technische Details

### Systemanforderungen
- PHP 7.4 oder höher
- Webserver (Apache/Nginx)
- Moderne Browser mit JavaScript-Unterstützung
- Sollte ohne spezielle Compiler o.Ä. betriebsbereit sein beim einfachen Hochladen auf den Webserver

### Architektur
- **Frontend**: Bootstrap 5 in einem dunklen Design ähnlich Spotify oder Discord mit Gradienten
- **Backend**: Slim PHP Framework (Version 4) für RESTful API und Routing
- **Datenspeicherung**: JSON-Dateien mit File-Locking
- **Grafiken**: Chart.js für dynamische Datenvisualisierung
- **Icons**: Phosphor Icons für visuelles Feedback und Badges

### ELO-System
- **Startrating**: 1500 Punkte für neue Spieler
- **K-Faktor**: 32 (Standardgewichtung für ELO-Berechnung)
- **Tordifferenz-Modifikator**: Zusätzliche Punkte für deutliche Siege

### Datenspeicherung
- JSON-basierte Datenhaltung in `/data/`
- Automatische Archivierung alter Daten
- Geschützte Dateistruktur via .htaccess
- Logging-System für Fehler und wichtige Ereignisse

## Projektstruktur

Das Projekt wird nach dem MVC-Muster mit dem Slim Framework organisiert:

```
kickScript/
├── app/                  # Hauptanwendungscode
│   ├── Controllers/      # Controller-Klassen
│   ├── Models/           # Datenmodelle
│   ├── Services/         # Dienste (z.B. ELO-Berechnung)
│   ├── Middleware/       # Slim-Middleware
│   ├── routes.php        # Routendefinitionen
│   └── dependencies.php  # Abhängigkeitsdefinitionen
├── public/               # Öffentlich zugängliche Dateien
│   ├── index.php         # Einstiegspunkt
│   ├── assets/           # Frontend-Assets
│   │   ├── css/          # Stylesheets
│   │   ├── js/           # JavaScript-Dateien
│   │   └── img/          # Bilder und Icons
├── data/                 # Datenspeicherung (nicht öffentlich)
│   ├── players/          # Spielerdaten
│   ├── matches/          # Spieldaten
│   └── seasons/          # Saisondaten
├── templates/            # Twig-Templates
├── .docs/                # Projektdokumentation
├── vendor/               # Composer-Abhängigkeiten
├── composer.json         # Composer-Konfiguration
└── .htaccess             # Webserver-Konfiguration
``` 