# Installation und Einrichtung

Diese Anleitung beschreibt die Installation und Konfiguration des Kickerliga Management Systems.

## Systemvoraussetzungen

- PHP 7.4 oder höher
- Composer (für Abhängigkeitsmanagement)
- Webserver (Apache oder Nginx)
- mod_rewrite (für Apache) oder entsprechende URL-Rewriting-Funktionalität (für Nginx)
- Grundlegende Zugriffsrechte für Dateien und Verzeichnisse

## Installation

### 1. Code herunterladen

Klone das Repository oder lade die Dateien auf deinen Webserver hoch:

```bash
git clone https://github.com/username/kickerliga.git
cd kickerliga
```

### 2. Abhängigkeiten installieren

Verwende Composer, um die erforderlichen PHP-Pakete zu installieren:

```bash
composer install
```

Hauptsächliche Abhängigkeiten:
- Slim Framework 4.x
- Twig Template Engine
- PHP-DI (Dependency Injection Container)
- Monolog (für Logging)

### 3. Verzeichnisberechtigungen

Stelle sicher, dass die folgenden Verzeichnisse beschreibbar sind:

```bash
chmod -R 775 data/
chmod -R 775 logs/
```

### 4. Webserver-Konfiguration

#### Apache

Aktiviere mod_rewrite und erstelle oder bearbeite die `.htaccess`-Datei im öffentlichen Verzeichnis:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]

# Verzeichnisschutz für data/
<IfModule mod_rewrite.c>
    RewriteRule ^data/ - [F,L]
</IfModule>
```

#### Nginx

Konfiguriere die Nginx-Server-Block-Datei:

```nginx
server {
    listen 80;
    server_name kickerliga.example.com;
    root /pfad/zu/kickerliga/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php7.4-fpm.sock;  # Anpassen an PHP-Version
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Zugriff auf data/ Verzeichnis verbieten
    location ~ ^/data/ {
        deny all;
        return 403;
    }
}
```

### 5. Konfigurationsdatei

Erstelle eine Konfigurationsdatei `config/settings.php` basierend auf der Vorlage:

```bash
cp config/settings.example.php config/settings.php
```

Bearbeite die Einstellungen nach Bedarf:

```php
<?php

return [
    'app' => [
        'name' => 'Kickerliga Management System',
        'environment' => 'development', // Für Produktion auf 'production' setzen
        'displayErrorDetails' => true,  // Für Produktion auf false setzen
    ],
    'logger' => [
        'name' => 'kickerliga',
        'path' => __DIR__ . '/../logs/app.log',
        'level' => \Monolog\Logger::DEBUG, // Für Produktion auf ERROR setzen
    ],
    'data' => [
        'path' => __DIR__ . '/../data',
    ],
];
```

### 6. Datenstruktur initialisieren

Führe das Setup-Skript aus, um die erforderliche Verzeichnisstruktur für die Datenspeicherung zu erstellen:

```bash
php bin/setup.php
```

Dieses Skript erstellt folgende Verzeichnisse:
- `data/players/` - Für Spielerdaten
- `data/matches/` - Für Spieldaten
- `data/tournaments/` - Für Turnierdaten
- `data/seasons/` - Für saisonale Daten

### 7. Frontend-Assets

Die Frontend-Assets (CSS, JavaScript) sind bereits im Repository enthalten und müssen nicht separat gebaut werden. Sollten Änderungen nötig sein:

```bash
# Nur wenn du SCSS-Dateien bearbeiten möchtest
npm install
npm run build
```

## Überprüfung der Installation

Nach der Installation kannst du prüfen, ob das System ordnungsgemäß funktioniert:

1. Öffne einen Webbrowser und navigiere zur URL deines Servers (z.B. http://localhost/kickerliga oder http://kickerliga.example.com).
2. Die Startseite des Kickerliga Management Systems sollte angezeigt werden.
3. Überprüfe im Debug-Modus die Logdateien unter `logs/app.log` auf etwaige Fehler.

## Erste Schritte

Nach erfolgreicher Installation kannst du mit der Nutzung des Systems beginnen:

1. **Spieler hinzufügen**: Erstelle zunächst einige Spieler im System
2. **Spiele erfassen**: Gib ein paar Spielergebnisse ein
3. **Statistiken ansehen**: Überprüfe, ob die Rangliste und Statistiken korrekt angezeigt werden
4. **Turnier erstellen**: Teste die Turnierfunktionalität

## Bekannte Probleme und Lösungen

### Problem: Leere Seite oder 500-Fehler

- Überprüfe, ob die PHP-Fehlerprotokolle aktiviert sind
- Stelle sicher, dass mod_rewrite (Apache) oder URL-Rewriting (Nginx) korrekt konfiguriert ist
- Prüfe die Dateiberechtigungen für Verzeichnisse `data/` und `logs/`

### Problem: Datenspeicherung funktioniert nicht

- Stelle sicher, dass PHP Schreibzugriff auf das Verzeichnis `data/` hat
- Überprüfe, ob die Datei-Locking-Funktionen in PHP aktiviert sind

### Problem: Darstellungsprobleme im Frontend

- Leere den Browser-Cache
- Stelle sicher, dass alle Assets (JS, CSS) korrekt geladen werden (prüfe die Netzwerkanfragen in den Browser-Entwicklertools)

## Sicherheitshinweise

- Das System verwendet keine Benutzerverwaltung oder Authentifizierung. Wenn dies erforderlich ist, solltest du entsprechende Maßnahmen ergreifen (z.B. Basic Auth auf Webserver-Ebene).
- Stelle sicher, dass das `data/`-Verzeichnis vor direktem Zugriff über das Web geschützt ist.
- In einer Produktionsumgebung solltest du `displayErrorDetails` auf `false` setzen und das Log-Level auf `ERROR` anheben.

## Aktualisierung

Um das System auf eine neuere Version zu aktualisieren:

1. Sichere deine Daten (`data/` Verzeichnis)
2. Aktualisiere den Code (via Git oder durch manuelles Ersetzen der Dateien)
3. Führe `composer update` aus, um Abhängigkeiten zu aktualisieren
4. Überprüfe, ob neue Konfigurationsoptionen hinzugefügt wurden

## Support

Bei Problemen oder Fragen:
- Prüfe die Dokumentation im `.docs/` Verzeichnis
- Überprüfe die Issue-Tracker im GitHub-Repository
- Erstelle ein neues Issue für ungelöste Probleme 