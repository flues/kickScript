<?php
// Standalone test script to simulate a visitor triggering the lazy spawn.
// Reads kickLiga/.env, sets environment variables, and runs the spawn logic.

// Load .env from kickLiga if present
$envFile = __DIR__ . '/../kickLiga/.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $k = trim($parts[0]);
            $v = trim($parts[1]);
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}

$apiKey = getenv('GEMINI_API_KEY') ?: null;
if (empty($apiKey)) {
    echo "No GEMINI_API_KEY set in kickLiga/.env — aborting spawn.\n";
    exit(0);
}

$root = realpath(__DIR__ . '/..');
$dataDir = $root . '/kickLiga/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

$stampFile = $dataDir . '/ai_summary_generated_at';
$lockFile = $dataDir . '/ai_summary_spawn.lock';
$now = time();

if (is_file($stampFile) && ($now - (int) @file_get_contents($stampFile)) < 86400) {
    echo "Stamp file is recent — no spawn needed.\n";
    exit(0);
}

$fp = @fopen($lockFile, 'c');
if ($fp === false) {
    echo "Unable to open lock file, aborting.\n";
    exit(1);
}

if (!flock($fp, LOCK_EX | LOCK_NB)) {
    echo "Another process is spawning — abort.\n";
    fclose($fp);
    exit(0);
}

try {
    @file_put_contents($stampFile, (string)$now, LOCK_EX);
    $bin = $root . '/bin/daily-analysis.php';
    if (!file_exists($bin)) {
        echo "Runner not found: $bin\n";
        exit(1);
    }

    if (stripos(PHP_OS, 'WIN') === 0) {
        $cmd = 'cmd /c start /B php ' . escapeshellarg($bin);
        pclose(popen($cmd, 'r'));
        echo "Spawned runner (Windows)\n";
    } else {
        $cmd = 'php ' . escapeshellarg($bin) . ' > /dev/null 2>&1 &';
        exec($cmd);
        echo "Spawned runner (Unix-like)\n";
    }
} finally {
    flock($fp, LOCK_UN);
    fclose($fp);
}

echo "Stamp written: " . date('c', $now) . "\n";
