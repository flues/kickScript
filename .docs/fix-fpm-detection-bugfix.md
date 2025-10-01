# 🐛 Bugfix: FPM-Detection verbessert

## Problem (2. Iteration)

Der erste Fix hatte einen Bug in der FPM-Detection:

```php
// VORHER (FALSCH):
if (stripos($binary, 'php-fpm') === false && stripos($binary, 'phpfpm') === false) {
```

**Bug:** Erkennt `/usr/sbin/php84-fpm` NICHT als FPM-Variante!

Der Check suchte nach `php-fpm` (mit Bindestrich am Anfang), aber auf dem Server ist es `php84-fpm` (mit Versionsnummer davor).

### Server-Output zeigt das Problem:

```
PHP_BINARY: /usr/sbin/php84-fpm
Is php-fpm: ✅ No  ← FALSCH! Es IST php-fpm!

Detected: /usr/sbin/php84-fpm  ← FALSCH detektiert!

Search Results:
✅ php84: /usr/bin/php84  ← Das richtige Binary ist verfügbar!
```

## Lösung

**Vereinfachter Check:** Suche nach `fpm` (ohne Präfix):

```php
// NACHHER (KORREKT):
if (stripos($binary, 'fpm') === false) {
```

### Warum das funktioniert:

**Erkannte FPM-Varianten:**
- ✅ `/usr/sbin/php84-fpm` → enthält `fpm` → SKIP ✓
- ✅ `/usr/bin/php-fpm` → enthält `fpm` → SKIP ✓
- ✅ `/usr/sbin/phpfpm` → enthält `fpm` → SKIP ✓
- ✅ `/usr/bin/php8.4-fpm` → enthält `fpm` → SKIP ✓

**CLI-Binaries (bleiben verwendbar):**
- ✅ `/usr/bin/php84` → enthält NICHT `fpm` → USE ✓
- ✅ `/usr/bin/php` → enthält NICHT `fpm` → USE ✓
- ✅ `/usr/local/bin/php` → enthält NICHT `fpm` → USE ✓

## Geänderte Dateien

| Datei | Änderung |
|-------|----------|
| `kickLiga/app/Controllers/HomeController.php` | `stripos($binary, 'php-fpm')` → `stripos($binary, 'fpm')` |
| `kickLiga/public/debug-php-binary.php` | Gleiche Änderung (3 Stellen) |
| `test-php-binary-detection.php` | Gleiche Änderung (2 Stellen) |

## Testing

### Lokal getestet ✅

```bash
php test-fpm-detection.php

# Output:
✅ /usr/sbin/php84-fpm → SKIP (correctly detected!)
✅ /usr/bin/php84 → USE (correctly detected!)
```

### Server-Test (nach Deployment)

**Erwartete neue Ausgabe von `debug-php-binary.php`:**

```
📊 System Info:
PHP_BINARY: /usr/sbin/php84-fpm
Is php-fpm: ⚠️ YES (BAD!)  ← Jetzt korrekt!

🔎 Detection Result:
Detected: /usr/bin/php84  ← Jetzt korrekt!
Is executable: ✅ Yes
Contains 'fpm': ✅ No (Good)

🧪 Test execution:
Output:
PHP 8.4.12 (cli) ...  ← CLI funktioniert!
```

## Deployment

```bash
# Committen
git add kickLiga/app/Controllers/HomeController.php
git add kickLiga/public/debug-php-binary.php
git add test-php-binary-detection.php
git add test-fpm-detection.php

git commit -m "fix(spawn): Improved FPM detection to catch php84-fpm variants

- Changed check from 'php-fpm' to 'fpm' (catches all variants)
- Now correctly detects: php84-fpm, php8.4-fpm, phpfpm
- Falls back to /usr/bin/php84 on server
- Added test-fpm-detection.php for validation"

git push origin main
```

## Nach Deployment auf Server

1. **Git Pull:**
   ```bash
   cd /www/htdocs/w0156949/kick.flues.dev
   git pull origin main
   ```

2. **Debug-Script aufrufen:**
   ```
   https://kick.flues.dev/debug-php-binary.php
   ```

3. **Erwartete Änderungen im Log:**
   - "Is php-fpm: ⚠️ YES (BAD!)" statt "✅ No"
   - "Detected: /usr/bin/php84" statt "/usr/sbin/php84-fpm"
   - "Output: PHP 8.4.12 (cli)" statt "Fatal Error opcache.file_cache_only"

4. **Homepage testen:**
   - `https://kick.flues.dev` neu laden
   - Nach 5-10s: `ai_summary.txt` sollte existieren
   - `ai_spawn_debug.log` sollte zeigen: `"php_binary": "/usr/bin/php84"`

5. **Cleanup:**
   ```bash
   rm kickLiga/public/debug-php-binary.php
   ```

## Erwartetes Ergebnis

| Check | Vorher | Nachher |
|-------|--------|---------|
| **FPM erkannt** | ❌ Nein (`php-fpm` Check zu spezifisch) | ✅ Ja (`fpm` Check generisch) |
| **Detected Binary** | `/usr/sbin/php84-fpm` ❌ | `/usr/bin/php84` ✅ |
| **CLI funktioniert** | ❌ Nein (opcache error) | ✅ Ja |
| **ai_summary.txt** | ❌ Nicht erstellt | ✅ Erstellt (~5s) |

---

**Status:** ✅ Implementiert & lokal getestet  
**Nächster Schritt:** Server-Deployment & Testing  
**Datum:** 2025-10-01
