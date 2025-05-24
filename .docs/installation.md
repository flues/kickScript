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