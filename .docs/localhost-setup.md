# Localhost Setup - Kickerliga starten

## Voraussetzungen

- **PHP 7.4 oder höher** (installiert und im PATH verfügbar)
- **Composer** (für Dependency Management)
- **Git** (optional, für Updates)

## Schnellstart mit PHP Built-in Server

### 1. Terminal/Eingabeaufforderung öffnen

Navigiere zum kickLiga-Verzeichnis:
```bash
cd /d:/kickScript/kickLiga
```

### 2. Dependencies installieren (wenn noch nicht geschehen)

```bash
composer install
```

### 3. Server starten

```bash
php -S localhost:8000 -t public
```

### 4. Im Browser öffnen

Öffne deinen Browser und gehe zu:
```
http://localhost:8000
```
