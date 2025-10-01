# 🔧 Server-Fix: PHP-Binary-Detection für Linux

## Problem

Auf dem Linux-Server wurde `/usr/sbin/php84-fpm` als PHP-Binary erkannt - das ist **FastCGI Process Manager** und kann **keine CLI-Scripts ausführen**!

```json
{
  "php_binary": "/usr/sbin/php84-fpm",  // ❌ FALSCH!
  "command": "'/usr/sbin/php84-fpm' '.../bin/daily-analysis.php' > /dev/null 2>&1 &",
  "status": "spawned"  // Spawn "funktioniert", aber Script läuft nie
}
```

## Lösung implementiert

### ✅ Neue Methode: `findPhpCliBinary()`

Die Methode sucht intelligent nach einem **verwendbaren CLI-Binary**:

1. **Prüfe PHP_BINARY** (aber skip php-fpm Varianten)
2. **Suche in PATH** (`which php84`, `which php83`, etc.)
3. **Prüfe Standard-Pfade** (`/usr/bin/php`, `/opt/plesk/php/*/bin/php`)
4. **Fallback auf `php`**

### Unterstützte Pfade

```php
// PATH-Suche (über 'which'):
php84, php83, php82, php81, php80, php

// Direkte Pfade:
/usr/bin/php84
/usr/bin/php83
/usr/bin/php82
/usr/bin/php
/usr/local/bin/php*
/opt/plesk/php/8.4/bin/php  // Plesk-spezifisch
/opt/plesk/php/8.3/bin/php
/opt/plesk/php/8.2/bin/php
```

## 🧪 Testing auf dem Server

### Schritt 1: Debug-Script hochladen

1. **Datei erstellt:** `kickLiga/public/debug-php-binary.php`
2. **Upload auf Server** (via Git oder FTP)
3. **Im Browser öffnen:** `https://kick.flues.dev/debug-php-binary.php`

### Schritt 2: Output analysieren

**Erwartete Ausgabe (gut):**
```
🔍 PHP Binary Detection Test - Server Environment
==================================================

📊 System Info:
OS: Linux
PHP Version: 8.4.x
PHP_BINARY: /usr/sbin/php84-fpm
Is php-fpm: ⚠️ YES (BAD!)
SAPI: fpm-fcgi

🔎 Detection Result:
Detected: /usr/bin/php84          ← ✅ CLI-Binary gefunden!
Is executable: ✅ Yes
Contains 'php-fpm': ✅ No (Good)

🧪 Test execution:
Command: '/usr/bin/php84' --version
Output:
PHP 8.4.x (cli) ...              ← ✅ CLI funktioniert!

🔍 Search Results for PHP binaries:
✅ php84: /usr/bin/php84          ← Wird gefunden!
✅ php: /usr/bin/php
```

**Mögliche Problem-Ausgabe (schlecht):**
```
🔎 Detection Result:
Detected: php
Is executable: ❌ No              ← ❌ Kein Binary gefunden!

🔍 Search Results for PHP binaries:
❌ php84: not found
❌ php83: not found
❌ php: not found                 ← ❌ Nichts in PATH!
```

### Schritt 3: Homepage testen

1. **Homepage öffnen:** `https://kick.flues.dev`
2. **Debug-Log prüfen:** `kickLiga/data/ai_spawn_debug.log`

**Erwarteter Log-Eintrag (neu):**
```json
{
  "timestamp": "2025-10-01T...",
  "php_binary": "/usr/bin/php84",    ← ✅ CLI-Binary statt php-fpm!
  "script_path": ".../bin/daily-analysis.php",
  "os": "Linux",
  "command": "'/usr/bin/php84' '.../bin/daily-analysis.php' > /dev/null 2>&1 &",
  "method": "unix-background",
  "status": "spawned"
}
```

3. **AI Summary prüfen:** `kickLiga/data/ai_summary.txt` sollte nach ~5-10 Sekunden existieren/aktualisiert werden

### Schritt 4: Cleanup

```bash
# Wenn alles funktioniert, Debug-Script löschen:
rm kickLiga/public/debug-php-binary.php
```

## 🐛 Troubleshooting

### Problem: "Detected: php" / "Is executable: ❌ No"

**Ursache:** PHP CLI nicht in PATH, keine Standard-Pfade funktionieren

**Lösung 1: PHP CLI installieren**
```bash
# Debian/Ubuntu:
apt-get install php-cli

# RHEL/CentOS:
yum install php-cli

# Oder versionspezifisch:
apt-get install php8.4-cli
```

**Lösung 2: Symbolischen Link erstellen**
```bash
# Wenn PHP z.B. unter /opt/plesk/php/8.4/bin/php liegt:
ln -s /opt/plesk/php/8.4/bin/php /usr/local/bin/php
```

**Lösung 3: Hoster-spezifischen Pfad in Code hinzufügen**

Öffne `kickLiga/app/Controllers/HomeController.php` und füge in der `findPhpCliBinary()` Methode deinen Server-Pfad hinzu:

```php
// In Section "3. Try common installation paths"
$commonPaths = [
    '/dein/custom/pfad/zu/php',  // ← Füge hier deinen Pfad ein
    '/usr/bin/php84',
    // ... rest bleibt gleich
];
```

### Problem: ai_summary.txt wird nicht erstellt

**Check 1: Manuelle Ausführung testen**
```bash
# SSH auf Server:
cd /www/htdocs/w0156949/kick.flues.dev
php bin/daily-analysis.php

# Erwartete Ausgabe:
AI summary written to /www/htdocs/.../kickLiga/data/ai_summary.txt
```

**Check 2: Permissions prüfen**
```bash
# data/ Verzeichnis muss schreibbar sein:
chmod 775 kickLiga/data
chown www-data:www-data kickLiga/data  # User anpassen!
```

**Check 3: Error-Log prüfen**
```bash
tail -f kickLiga/data/ai_summary_error.log
```

### Problem: Spawn funktioniert, aber Script läuft nicht

**Ursache:** Möglicherweise fehlt ein Extension oder Composer-Package

**Check:**
```bash
# SSH auf Server:
cd /www/htdocs/w0156949/kick.flues.dev
/usr/bin/php84 bin/daily-analysis.php

# Wenn Fehler erscheinen, installiere fehlende Extensions:
php -m  # Liste aller installierten Extensions
```

## 📊 Vergleich: Vorher vs. Nachher

| Aspekt | Vorher | Nachher |
|--------|--------|---------|
| **PHP Binary** | `/usr/sbin/php84-fpm` ❌ | `/usr/bin/php84` ✅ |
| **Ausführbar** | Nein (FPM) | Ja (CLI) |
| **Script läuft** | ❌ Nein | ✅ Ja |
| **ai_summary.txt** | Nicht erstellt ❌ | Erstellt (~5s) ✅ |
| **Detection** | Blind PHP_BINARY genutzt | Intelligente Suche + Fallbacks |

## 🚀 Deployment

### Änderungen committen

```bash
git add kickLiga/app/Controllers/HomeController.php
git add kickLiga/public/debug-php-binary.php  # Optional für Testing
git commit -m "fix(spawn): Robust PHP CLI binary detection for Linux servers

- Added findPhpCliBinary() method to avoid php-fpm
- Searches PATH for php84/php83/php82/php
- Checks common paths (/usr/bin, /opt/plesk)
- Fixes ai_summary.txt generation on Linux servers"

git push origin main
```

### Auf Server deployen

```bash
# Via Git:
cd /www/htdocs/w0156949/kick.flues.dev
git pull origin main

# Via FTP:
# Lade kickLiga/app/Controllers/HomeController.php hoch
```

### Testen

1. `https://kick.flues.dev/debug-php-binary.php` öffnen
2. Prüfen ob korrektes Binary gefunden wird
3. Homepage neu laden (`https://kick.flues.dev`)
4. Nach 5-10s prüfen: `ai_spawn_debug.log` und `ai_summary.txt`

## 📝 Weitere Optimierungen

Wenn du **zuverlässige tägliche Summaries** willst, empfehle ich einen **Cronjob**:

```bash
# Crontab bearbeiten:
crontab -e

# Täglich um 6:00 Uhr ausführen:
0 6 * * * cd /www/htdocs/w0156949/kick.flues.dev && /usr/bin/php84 bin/daily-analysis.php >> /var/log/kickLiga-ai.log 2>&1
```

**Vorteile:**
- ✅ Garantierte Ausführung (unabhängig von Website-Besuchen)
- ✅ Definierte Uhrzeit (z.B. morgens vor Arbeitsbeginn)
- ✅ Logging in separates File möglich

---

**Status:** ✅ Implementiert  
**Testing:** 🧪 Benötigt Server-Test via `debug-php-binary.php`  
**Datum:** 2025-10-01
