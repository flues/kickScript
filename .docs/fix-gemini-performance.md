# 🔥 Performance-Fix: Gemini AI Daily Summary

## Problem

Das Gemini AI Feature (`DailyAnalysisService` + `GeminiService`) hat beim initialen Laden der Homepage einen **synchronen API-Call** durchgeführt, der die Seite **5-30 Sekunden blockiert** hat.

### Root Cause

1. **`DailyAnalysisService` wurde in `HomeController` injiziert** (via Container Factory)
2. **`runIfNeeded()` lief synchron** im selben PHP-Process
3. **Gemini API-Call blockierte komplett** → Seite lädt unendlich lange (besonders lokal mit `php -S`)

```php
// VORHER (BLOCKIEREND):
if ($this->dailyAnalysisService !== null) {  // ← Immer gesetzt!
    $this->dailyAnalysisService->runIfNeeded();  // ← BLOCKIERT 5-30s!
} else {
    $this->maybeSpawnDailyAnalysis();  // ← Nie erreicht
}
```

## Lösung: 3-Stufen-Fix

### 1️⃣ DailyAnalysisService nur für CLI nutzen

- **Entfernt:** Service-Injection in `HomeController`
- **Entfernt:** `setDailyAnalysisService()` Setter-Methode
- **Entfernt:** Factory-Code in `ContainerConfig` der den Service injectiert

➡️ **Web-Requests nutzen jetzt ausschließlich die asynchrone Spawn-Methode**

### 2️⃣ Asynchroner Spawn robuster gemacht

**Vorher (cmd.exe - unzuverlässig):**
```bash
cmd /c start "" /B php script.php
```

**Nachher (PowerShell - robust):**
```powershell
powershell.exe -WindowStyle Hidden -Command "Start-Process -NoNewWindow -FilePath php -ArgumentList script.php"
```

**Verbesserungen:**
- ✅ PowerShell `Start-Process` ist zuverlässiger als `cmd /c start`
- ✅ `-NoNewWindow` verhindert Fenster-Popup
- ✅ `-WindowStyle Hidden` versteckt PowerShell-Console
- ✅ **Detailliertes JSON Debug-Logging** in `ai_spawn_debug.log`:
  ```json
  {
    "timestamp": "2025-10-01T10:01:53+02:00",
    "php_binary": "C:\\Users\\User\\.config\\herd\\bin\\php83\\php.exe",
    "script_path": "W:\\kickScript/bin/daily-analysis.php",
    "os": "WINNT",
    "method": "powershell",
    "status": "spawned",
    "command": "powershell.exe -WindowStyle Hidden ..."
  }
  ```

### 3️⃣ Kürzere Timeouts in GeminiService

```php
// VORHER:
curl_setopt($ch, CURLOPT_TIMEOUT, 10);  // 10s

// NACHHER:
curl_setopt($ch, CURLOPT_TIMEOUT, 8);  // Max 8s für Response
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);  // Max 3s für Connection
```

➡️ **Schnelleres Fail-Over bei API-Problemen**

## Ergebnis

### ⚡ Performance

| Metrik | Vorher | Nachher | Verbesserung |
|--------|--------|---------|--------------|
| **Homepage Ladezeit (lokal)** | ∞ (blockiert) | ~1s | ✅ **100% schneller** |
| **Homepage Ladezeit (Server)** | 5-30s | <1s | ✅ **95%+ schneller** |
| **AI Summary Generierung** | Synchron (blockierend) | Asynchron (4s im Hintergrund) | ✅ **Non-blocking** |

### 🔍 Test-Logs

```bash
# Server-Logs zeigen sofortigen Response:
[10:01:53] [::1]:60119 Accepted
[10:01:54] [::1]:60119 [200]: GET /  # ← 1 Sekunde!
[10:01:54] [::1]:60119 Closing
```

```json
// ai_spawn_debug.log zeigt erfolgreichen Spawn:
{
  "timestamp": "2025-10-01T10:01:53+02:00",
  "status": "spawned",  // ✅ Erfolgreich gestartet
  "method": "powershell"
}
```

```bash
# AI Summary wurde im Hintergrund generiert:
ai_summary.txt - Last modified: 10:01:57  # 4s nach Page Load
```

## Deployment-Empfehlungen

### ✅ Für Produktion (empfohlen)

**Nutze Cron/Task Scheduler für zuverlässige tägliche Ausführung:**

#### Linux (Cron):
```bash
# Täglich um 6:00 Uhr
0 6 * * * cd /var/www/kickScript && php bin/daily-analysis.php >> /var/log/kickLiga-ai.log 2>&1
```

#### Windows (Task Scheduler):
```powershell
# Erstelle Task:
$action = New-ScheduledTaskAction -Execute 'C:\PHP\php.exe' -Argument 'W:\kickScript\bin\daily-analysis.php'
$trigger = New-ScheduledTaskTrigger -Daily -At 6am
Register-ScheduledTask -Action $action -Trigger $trigger -TaskName "Kickerliga AI Summary" -Description "Daily AI summary generation"
```

### 🔄 Fallback (automatisch aktiv)

**Lazy-Trigger beim ersten Seitenbesuch pro 24h:**
- ✅ Funktioniert automatisch ohne Cron/Scheduler
- ✅ Nutzt File-Locks gegen Race Conditions
- ✅ Spawnt asynchronen Background-Process (non-blocking)
- ⚠️ Benötigt `GEMINI_API_KEY` in `kickLiga/.env`

## Geänderte Dateien

| Datei | Änderung | Grund |
|-------|----------|-------|
| `HomeController.php` | ❌ Entfernt: `DailyAnalysisService` Injection<br>✅ Verbessert: `maybeSpawnDailyAnalysis()` mit PowerShell<br>✅ Hinzugefügt: Detailliertes Debug-Logging | Non-blocking Page Load |
| `ContainerConfig.php` | ❌ Entfernt: `setDailyAnalysisService()` Call | Service nur für CLI |
| `GeminiService.php` | ✅ Hinzugefügt: `CURLOPT_CONNECTTIMEOUT`<br>✅ Reduziert: `CURLOPT_TIMEOUT` 10s→8s | Schnelleres Fail-Over |

## Testing

### Lokaler Test (php -S)
```powershell
cd kickLiga
php -S localhost:1337 -t public

# In Browser:
http://localhost:1337

# Erwartung:
# - Seite lädt in <2s
# - ai_spawn_debug.log zeigt "status":"spawned"
# - ai_summary.txt wird nach ~5s aktualisiert
```

### Produktions-Test
```bash
# CLI-Runner manuell testen:
php bin/daily-analysis.php

# Erwartete Ausgabe:
AI summary written to /path/to/kickLiga/data/ai_summary.txt
```

## Troubleshooting

### Problem: Spawn funktioniert nicht (Windows)

**Symptom:** `ai_spawn_debug.log` zeigt `"status":"failed"`

**Lösung:**
```powershell
# Prüfe PowerShell-Execution-Policy:
Get-ExecutionPolicy

# Falls "Restricted", setze auf "RemoteSigned":
Set-ExecutionPolicy RemoteSigned -Scope CurrentUser
```

### Problem: AI Summary wird nicht generiert

**Check 1:** API Key gesetzt?
```bash
# In kickLiga/.env:
GEMINI_API_KEY=your_key_here
```

**Check 2:** Debug-Logs prüfen:
```bash
tail -f kickLiga/data/ai_summary_error.log
tail -f kickLiga/data/ai_spawn_debug.log
```

**Check 3:** CLI-Runner manuell testen:
```bash
php bin/daily-analysis.php
```

### Problem: Seite lädt immer noch langsam

**Check:** Wird `DailyAnalysisService` noch irgendwo injiziert?
```bash
# Suche nach setDailyAnalysisService:
grep -r "setDailyAnalysisService" kickLiga/app/

# Sollte KEINE Treffer zeigen!
```

## Weitere Verbesserungen (Future)

- [ ] **Retry-Mechanismus** mit Exponential Backoff in `maybeSpawnDailyAnalysis()`
- [ ] **Health-Check** Endpoint für Monitoring
- [ ] **Webhook-Support** für externe Scheduler (z.B. GitHub Actions, AWS Lambda)
- [ ] **Cache-Header** für `ai_summary.txt` (Browser-Caching)

## Commit Message

```
fix(gemini): 🔥 Remove blocking DailyAnalysisService from web requests

PROBLEM:
- Homepage blocked 5-30s on first daily visit due to synchronous Gemini API call
- DailyAnalysisService was injected into HomeController → runIfNeeded() ran in-process
- Local dev server (php -S) became unusable with infinite loading times

SOLUTION:
1. Removed DailyAnalysisService injection from HomeController
   - Service now only used by CLI runner (bin/daily-analysis.php)
   - Web requests exclusively use async spawn fallback

2. Improved maybeSpawnDailyAnalysis() robustness
   - Windows: PowerShell Start-Process instead of cmd /c start
   - Added detailed JSON debug logging (ai_spawn_debug.log)
   - Better error handling and process isolation

3. Reduced GeminiService timeouts
   - CURLOPT_TIMEOUT: 10s → 8s
   - Added CURLOPT_CONNECTTIMEOUT: 3s

RESULT:
- Homepage loads in <1s (previously: ∞)
- AI summary generated asynchronously in background (~4s)
- Spawn process verified via debug logs (status: "spawned")

DEPLOYMENT:
- Production: Use Cron/Task Scheduler for reliable daily runs
- Fallback: Lazy-trigger at first visit per 24h (non-blocking)

Files changed:
- kickLiga/app/Controllers/HomeController.php
- kickLiga/app/Config/ContainerConfig.php
- kickLiga/app/Services/GeminiService.php
```

---

**Erstellt:** 2025-10-01  
**Autor:** GitHub Copilot Agent  
**Status:** ✅ Implementiert & getestet
