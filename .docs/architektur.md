# Architektur des Kickerliga Management Systems

Diese Dokumentation beschreibt die grundlegende Architektur des Kickerliga Management Systems, basierend auf dem Slim PHP Framework.

## Framework-Auswahl: Slim PHP

Für das Projekt haben wir uns für das Slim Framework (Version 4) entschieden aus folgenden Gründen:

1. **Leichtgewichtig**: Slim ist eine schlanke Lösung ohne unnötigen Overhead
2. **Flexibilität**: Einfache Integration von Drittanbieter-Komponenten
3. **Modernes PHP**: Unterstützung für moderne PHP-Konzepte und PSR-Standards
4. **RESTful Routing**: Einfache Definition von API-Routen
5. **Middleware-Support**: Gute Unterstützung für HTTP-Middleware
6. **Aktive Community**: Gute Dokumentation und Community-Support
7. **Einfache Lernkurve**: Schneller Einstieg und Produktivität

## Architekturübersicht

Das System folgt dem MVC-Pattern (Model-View-Controller) mit folgender Struktur:

### Controller

- Verarbeiten Benutzeranfragen
- Rufen die benötigten Services auf
- Geben Daten an Views oder als JSON zurück
- Ein Controller pro Hauptfunktion:
  - `PlayerController.php`: Spielerverwaltung
  - `MatchController.php`: Spielerfassung/-verwaltung
  - `StatsController.php`: Statistikgenerierung
  - `AchievementController.php`: Achievement-System

### Models

- Repräsentieren die Datenstruktur
- Verantwortlich für Datenspeicherung/-abruf (JSON-Files)
- Hauptmodelle:
  - `Player.php`: Spielerdaten inkl. ELO-Rating
  - `Match.php`: Spielergebnisse
  - `Achievement.php`: Badge-Definitionen und -Zuteilungen
  - `Season.php`: Saisonverwaltung

### Services

- Beinhalten die Geschäftslogik
- Trennung von Datenzugriff und Anwendungslogik
- Kernservices:
  - `EloService.php`: ELO-Berechnungen
  - `MatchService.php`: Spiellogik und -regeln
  - `AchievementService.php`: Erkennung von Badge-Qualifikationen
  - `StatsService.php`: Statistische Analysen
  - `DataService.php`: Datenzugriff und -manipulation

### Middleware

- Verarbeitet Anfragen vor/nach Controllern
- Implementiert:
  - `CorsMiddleware.php`: Cross-Origin Resource Sharing
  - `JsonBodyParserMiddleware.php`: JSON-Verarbeitung
  - `ErrorHandlerMiddleware.php`: Fehlerbehandlung
  - `LoggerMiddleware.php`: Anfragenprotokollierung

### Views (Templates)

- Twig als Template-Engine
- Trennung von Logik und Präsentation
- Responsive Design mit Bootstrap 5
- Dunkles Theme mit modernen Designelementen

## Datenfluss

1. **Anfrage**: Benutzer interagiert mit der Anwendung über Browser
2. **Routing**: Slim leitet Anfrage an entsprechenden Controller weiter
3. **Controller**: Verarbeitet Anfrage und ruft Services auf
4. **Service**: Führt Geschäftslogik aus
5. **Model**: Liest/schreibt Daten in JSON-Dateien
6. **Response**: Controller sendet Antwort als HTML (via Twig) oder JSON zurück

## Datenspeicherung

Da laut Anforderungen keine Datenbank erforderlich ist, verwenden wir ein JSON-basiertes Dateisystem:

- Jeder Spieler wird als separate JSON-Datei gespeichert
- Spiele werden in zeitbasierten Dateien organisiert
- File-Locking-Mechanismus zur Verhinderung von Race Conditions
- Automatische Datensicherung und -archivierung

## Frontend-Architektur

- **Bootstrap 5**: Grundlegendes UI-Framework
- **Custom SCSS**: Dunkles Theme mit Gradienten
- **Chart.js**: Datenvisualisierung für ELO-Verläufe und Statistiken
- **Phosphor Icons**: Moderne Icon-Bibliothek
- **Vanilla JS**: Modulare JavaScript-Organisation
- **Fetch API**: Asynchrone Datenkommunikation mit dem Backend

## Sicherheitskonzept

- Schutz von Datenverzeichnissen über `.htaccess`
- Eingabevalidierung auf Client- und Serverseite
- CSRF-Schutz für Formulare
- XSS-Schutz durch Twig-Escaping
- Content Security Policy implementiert 