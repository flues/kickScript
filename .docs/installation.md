# 🚀 Installation & Setup (English)

This guide describes the quick installation of the Kickerliga Management System for local development and production use.

## 📑 Table of Contents (English)
- [System Requirements](#system-requirements)
- [Quickstart (Local Development)](#quickstart-local-development)
- [Production Setup](#production-setup)
- [Advanced Configuration](#advanced-configuration)
- [Verify Installation](#verify-installation)
- [Troubleshooting](#troubleshooting)
- [Security Notes](#security-notes)
- [Update Instructions](#update-instructions)
- [Support](#support)

## 📋 System Requirements

- **PHP 7.4 or higher** with CLI access
- **Composer** (for dependency management)
- **Web server** with PHP support (Apache/Nginx) or PHP built-in server
- **mod_rewrite** (for Apache) or URL rewriting functionality
- **Modern browsers** with JavaScript support

## ⚡ Quickstart (Local Development)

### 1. Clone the repository
```powershell
git clone [repository-url] kickerliga
cd kickerliga
```

### 2. Install dependencies
```powershell
composer install
```

### 3. Set directory permissions
```powershell
# Windows (PowerShell as Administrator)
icacls data /grant Everyone:F /T
icacls logs /grant Everyone:F /T
# Linux/macOS
chmod -R 775 data/
chmod -R 775 logs/
```

### 4. Start local server
```powershell
# PHP built-in server (easiest method)
php -S localhost:1337 -t public

# Alternative: With specific IP
php -S 192.168.1.100:1337 -t public
```

### 5. Open in browser
```
http://localhost:1337
```

✅ **That's it!** The system is ready to use.

## 🌐 Production Setup

### Web Server Configuration

#### Apache
Create a `.htaccess` file in the `public/` directory:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]

# Protect sensitive directories
<IfModule mod_rewrite.c>
    RewriteRule ^data/ - [F,L]
    RewriteRule ^logs/ - [F,L]
    RewriteRule ^\.docs/ - [F,L]
</IfModule>
```

#### Nginx
```nginx
server {
    listen 80;
    server_name kickerliga.example.com;
    root /var/www/kickerliga/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to data directories
    location ~ ^/(data|logs|\.docs)/ {
        deny all;
        return 403;
    }
}
```

### Virtual Host Setup
```powershell
# Document Root should point to the 'public' directory
DocumentRoot /var/www/kickerliga/public
```

## 🔧 Advanced Configuration

### Environment Settings (Optional)
Create a `.env` file in the project root:
```env
APP_ENV=development
APP_DEBUG=true
APP_NAME="Kickerliga Management"
LOG_LEVEL=debug
```

### Log Configuration
Logs are automatically saved in `logs/app.log`. For production:
```powershell
# Set up log rotation (Linux)
sudo nano /etc/logrotate.d/kickerliga
```

## ✅ Verify Installation

### Function Test
1. **Load homepage**: Dashboard should be visible
2. **Add player**: Test player management
3. **Record match**: Create a test match
4. **Check statistics**: ELO ratings should update

### Debug Information
- **Check logs**: `tail -f logs/app.log`
- **PHP errors**: Browser developer tools → Console
- **Permissions**: `ls -la data/` should show write access

## 🛠️ Troubleshooting

### Common Issues

#### 🚫 500 Internal Server Error
```powershell
# Check permissions
ls -la data/ logs/

# Check PHP error log
tail -f /var/log/php_errors.log

# Test web server configuration
php -S localhost:1337 -t public  # If this works, it's a web server issue
```

#### 📁 "Class not found" error
```powershell
# Regenerate autoloader
composer dump-autoload

# Reinstall dependencies
rm -rf vendor/ composer.lock
composer install
```

#### 💾 Data storage not working
```powershell
# Check and fix directory permissions
sudo chown -R www-data:www-data data/ logs/
chmod -R 775 data/ logs/
```

#### 🎨 Frontend issues
- Clear browser cache (Ctrl+F5)
- Check Network tab in browser developer tools
- Check asset paths in `templates/layout.twig`

### System Information
```powershell
# Check PHP version and modules
php -v
php -m | findstr "json curl mbstring"

# Composer version
composer --version

# Available storage
dir  # Disk space (Windows)
df -h  # Disk space (Linux)
free -h  # RAM (Linux)
```

## 🔐 Security Notes

### Production Environment
- **Disable error messages**: `APP_DEBUG=false` in `.env`
- **Protect sensitive directories**: Restrict web access to `data/`, `logs/`, `.docs/`
- **Use HTTPS**: Set up SSL certificate
- **Set up backups**: Automatic backup of the `data/` directory

### Access Control (Optional)
```apache
# Basic Auth for the entire application (.htaccess)
AuthType Basic
AuthName "Kickerliga Access"
AuthUserFile /path/to/.htpasswd
Require valid-user
```

## 🔄 Update Instructions

```powershell
# Update code
git pull origin main

# Update dependencies
composer update

# Clear cache (if present)
del cache\* tmp\*
rm -rf cache/* tmp/*  # Linux

# Check permissions
icacls data /grant Everyone:F /T
icacls logs /grant Everyone:F /T
chmod -R 775 data/ logs/  # Linux
```

## 📞 Support

If you have problems:
1. **Check logs**: `logs/app.log`
2. **Documentation**: More details in `.docs/`
3. **GitHub Issues**: [Repository Issues]
4. **Debug mode**: `APP_DEBUG=true` for detailed error messages

---

**💡 Tip**: For first setup, the quickstart with PHP built-in server is sufficient. For production, a full-featured web server is recommended.

---
# 🚀 Installation und Setup

Diese Anleitung beschreibt die schnelle Installation des Kickerliga Management Systems für lokale Entwicklung und Produktiveinsatz.

## 📋 Systemvoraussetzungen

- **PHP 7.4 oder höher** mit CLI-Zugang
- **Composer** (für Dependency Management)
- **Webserver** mit PHP-Unterstützung (Apache/Nginx) oder PHP Built-in Server
- **mod_rewrite** (bei Apache) oder URL-Rewriting-Funktionalität
- **Moderne Browser** mit JavaScript-Unterstützung

## ⚡ Schnellstart (Lokale Entwicklung)

### 1. Repository klonen
```bash
git clone [repository-url] kickerliga
cd kickerliga
```

### 2. Dependencies installieren
```bash
composer install
```

### 3. Verzeichnisberechtigungen setzen
```bash
# Linux/macOS
chmod -R 775 data/
chmod -R 775 logs/

# Windows (PowerShell als Administrator)
icacls data /grant Everyone:F /T
icacls logs /grant Everyone:F /T
```

### 4. Lokalen Server starten
```bash
# PHP Built-in Server (einfachste Methode)
php -S localhost:1337 -t public

# Alternative: Mit spezifischer IP
php -S 192.168.1.100:1337 -t public
```

### 5. Im Browser öffnen
```
http://localhost:1337
```

✅ **Das war's!** Das System ist einsatzbereit.

## 🌐 Produktions-Setup

### Webserver-Konfiguration

#### Apache
Erstelle eine `.htaccess` im `public/` Verzeichnis:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]

# Datenschutz für sensible Verzeichnisse
<IfModule mod_rewrite.c>
    RewriteRule ^data/ - [F,L]
    RewriteRule ^logs/ - [F,L]
    RewriteRule ^\.docs/ - [F,L]
</IfModule>
```

#### Nginx
```nginx
server {
    listen 80;
    server_name kickerliga.example.com;
    root /var/www/kickerliga/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Zugriff auf Datenverzeichnisse verbieten
    location ~ ^/(data|logs|\.docs)/ {
        deny all;
        return 403;
    }
}
```

### Virtual Host Setup
```bash
# Document Root sollte auf das 'public' Verzeichnis zeigen
DocumentRoot /var/www/kickerliga/public
```

## 🔧 Erweiterte Konfiguration

### Umgebungseinstellungen (Optional)
Erstelle eine `.env` Datei im Projektroot:
```env
APP_ENV=development
APP_DEBUG=true
APP_NAME="Kickerliga Management"
LOG_LEVEL=debug
```

### Log-Konfiguration
Logs werden automatisch in `logs/app.log` gespeichert. Für Produktion:
```bash
# Log-Rotation einrichten (Linux)
sudo nano /etc/logrotate.d/kickerliga
```

## ✅ Installation überprüfen

### Funktionstest
1. **Startseite laden**: Dashboard sollte sichtbar sein
2. **Spieler hinzufügen**: Teste die Spielerverwaltung
3. **Spiel erfassen**: Erstelle ein Testmatch
4. **Statistiken prüfen**: ELO-Ratings sollten aktualisiert werden

### Debug-Informationen
- **Logs prüfen**: `tail -f logs/app.log`
- **PHP-Fehler**: Browser-Entwicklertools → Console
- **Berechtigungen**: `ls -la data/` sollte Schreibzugriff zeigen

## 🛠️ Troubleshooting

### Häufige Probleme

#### 🚫 500 Internal Server Error
```bash
# Berechtigungen prüfen
ls -la data/ logs/

# PHP-Fehlerlog prüfen
tail -f /var/log/php_errors.log

# Webserver-Konfiguration testen
php -S localhost:1337 -t public  # Wenn das funktioniert, ist es ein Webserver-Problem
```

#### 📁 "Class not found" Fehler
```bash
# Autoloader neu generieren
composer dump-autoload

# Dependencies neu installieren
rm -rf vendor/ composer.lock
composer install
```

#### 💾 Datenspeicherung funktioniert nicht
```bash
# Verzeichnisberechtigungen prüfen und korrigieren
sudo chown -R www-data:www-data data/ logs/
chmod -R 775 data/ logs/
```

#### 🎨 Frontend-Probleme
- Browser-Cache leeren (Strg+F5)
- Browser-Entwicklertools → Network-Tab prüfen
- Asset-Pfade in `templates/layout.twig` überprüfen

### System-Informationen
```bash
# PHP-Version und Module prüfen
php -v
php -m | grep -E "(json|curl|mbstring)"

# Composer-Version
composer --version

# Verfügbare Speicher
df -h  # Festplattenspeicher
free -h  # RAM (Linux)
```

## 🔐 Sicherheitshinweise

### Produktionsumgebung
- **Fehlermeldungen deaktivieren**: `APP_DEBUG=false` in `.env`
- **Sensible Verzeichnisse schützen**: `data/`, `logs/`, `.docs/` vor Web-Zugriff sperren
- **HTTPS verwenden**: SSL-Zertifikat einrichten
- **Backups einrichten**: Automatische Sicherung des `data/` Verzeichnises

### Zugriffskontrolle (Optional)
```apache
# Basic Auth für gesamte Anwendung (.htaccess)
AuthType Basic
AuthName "Kickerliga Access"
AuthUserFile /path/to/.htpasswd
Require valid-user
```

## 🔄 Updates durchführen

```bash
# Code aktualisieren
git pull origin main

# Dependencies aktualisieren
composer update

# Cache leeren (falls vorhanden)
rm -rf cache/* tmp/*

# Berechtigungen prüfen
chmod -R 775 data/ logs/
```

## 📞 Support

Bei Problemen:
1. **Logs prüfen**: `logs/app.log`
2. **Dokumentation**: Weitere Details in `.docs/`
3. **GitHub Issues**: [Repository-Issues]
4. **Debug-Modus**: `APP_DEBUG=true` für detaillierte Fehlermeldungen

---

**💡 Tipp**: Für die erste Einrichtung reicht der Schnellstart mit PHP Built-in Server. Für Produktiveinsatz empfiehlt sich ein vollwertiger Webserver.