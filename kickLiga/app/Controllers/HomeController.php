<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DataService;
use App\Services\PlayerService;
use App\Services\MatchService;
use App\Services\SeasonService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class HomeController
{
    private Twig $view;
    private DataService $dataService;
    private PlayerService $playerService;
    private ?MatchService $matchService;
    private ?SeasonService $seasonService;

    /**
     * Controller Konstruktor mit Dependency Injection
     */
    public function __construct(
        Twig $view, 
        DataService $dataService, 
        PlayerService $playerService, 
        ?MatchService $matchService = null,
        ?SeasonService $seasonService = null
    ) {
        $this->view = $view;
        $this->dataService = $dataService;
        $this->playerService = $playerService;
        $this->matchService = $matchService;
        $this->seasonService = $seasonService;
    }

    /**
     * Home-Seite anzeigen
     */
    public function home(Request $request, Response $response): Response
    {
        // Always use non-blocking spawn for web requests to avoid long page loads.
        // For reliable daily runs, use the CLI runner (bin/daily-analysis.php) via cron/Task Scheduler.
        $this->maybeSpawnDailyAnalysis();

        // Hole die Top-Spieler und die Gesamtzahl der Spieler
        $topPlayers = $this->playerService->getTopPlayers(5);
        $allPlayers = $this->playerService->getAllPlayers();
        $playerCount = count($allPlayers);
        
        // Hole die letzten Spiele, falls der MatchService verfügbar ist
        $recentMatches = [];
        $matchCount = 0;
        if ($this->matchService !== null) {
            $recentMatchesRaw = $this->matchService->getRecentMatches(5);
            $matchCount = count($this->matchService->getAllMatches());
            
            // Erweitere die Match-Daten um Spielerinformationen für das Template
            foreach ($recentMatchesRaw as $match) {
                $player1 = $this->playerService->getPlayerById($match->getPlayer1Id());
                $player2 = $this->playerService->getPlayerById($match->getPlayer2Id());
                
                $recentMatches[] = [
                    'id' => $match->getId(),
                    'playedAt' => $match->getPlayedAt(),
                    'player1Id' => $match->getPlayer1Id(),
                    'player2Id' => $match->getPlayer2Id(),
                    'player1' => $player1,
                    'player2' => $player2,
                    'scorePlayer1' => $match->getScorePlayer1(),
                    'scorePlayer2' => $match->getScorePlayer2(),
                    'player1Side' => $match->getPlayer1Side(),
                    'player2Side' => $match->getPlayer2Side(),
                    'player1IsWinner' => $match->isPlayer1Winner(),
                    'player2IsWinner' => $match->isPlayer2Winner(),
                    'eloChange' => $match->getEloChange(),
                    'notes' => $match->getNotes()
                ];
            }
        }
        
        // Hole die aktive Saison und deren Daten (Single Source of Truth)
        $activeSeason = null;
        $seasonCount = 0;
        $seasonStandings = [];
        $seasonStatistics = null;
        
        if ($this->seasonService !== null) {
            $activeSeason = $this->seasonService->getActiveSeason();
            $seasonCount = count($this->seasonService->getAllSeasons());
            
            if ($activeSeason) {
                // Berechne Saison-Daten zur Laufzeit
                $seasonStandings = $this->seasonService->getSeasonStandings($activeSeason->getId());
                $seasonStatistics = $this->seasonService->getSeasonStatistics($activeSeason->getId());
                
                // Erstelle erweiterte Season-Daten für Template
                $activeSeason = (object) [
                    'id' => $activeSeason->getId(),
                    'name' => $activeSeason->getName(),
                    'startDate' => $activeSeason->getStartDate(),
                    'endDate' => $activeSeason->getEndDate(),
                    'isActive' => $activeSeason->isActive(),
                    'durationInDays' => $activeSeason->getDurationInDays(),
                    'effectiveEndDate' => $activeSeason->getEffectiveEndDate()
                ];
            }
        }
        
        return $this->view->render($response, 'home.twig', [
            'title' => 'Kickerliga Management System',
            'topPlayers' => $topPlayers,
            'playerCount' => $playerCount,
            'recentMatches' => $recentMatches,
            'matchCount' => $matchCount,
            'activeSeason' => $activeSeason,
            'seasonCount' => $seasonCount,
            'seasonStandings' => $seasonStandings,
            'seasonStatistics' => $seasonStatistics
            , 'aiSummary' => $this->loadAiSummary()
        ]);
    }

    private function loadAiSummary(): ?string
    {
        $file = __DIR__ . '/../../data/ai_summary.txt';
        if (file_exists($file)) {
            $content = trim(file_get_contents($file));
            return $content === '' ? null : $content;
        }
        return null;
    }

    /**
     * Lazily spawn the daily analysis script once per 24 hours when a visitor arrives.
     * Uses a timestamp file and a lockfile to avoid race conditions.
     */
    private function maybeSpawnDailyAnalysis(): void
    {
        // Do not spawn if there is no API key configured
        $apiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? null);
        if (empty($apiKey)) {
            return;
        }

        $root = dirname(__DIR__, 3);
        $dataDir = $root . '/kickLiga/data';
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0755, true);
        }

        $stampFile = $dataDir . '/ai_summary_generated_at';
        $lockFile = $dataDir . '/ai_summary_spawn.lock';
        $now = time();

        // If stamp exists and was updated within last 24h -> nothing to do
        if (is_file($stampFile) && ($now - (int) @file_get_contents($stampFile)) < 86400) {
            return;
        }

        // Acquire a non-blocking lock using a small lock file
        $fp = @fopen($lockFile, 'c');
        if ($fp === false) {
            return; // cannot lock -> skip
        }

        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            // Another request is already spawning
            fclose($fp);
            return;
        }

        try {
            // Update stamp immediately to prevent other requests starting new spawns
            @file_put_contents($stampFile, (string)$now, LOCK_EX);

            $bin = $root . '/bin/daily-analysis.php';
            if (!file_exists($bin)) {
                return;
            }

            // Platform-specific non-blocking spawn. Find a usable PHP CLI binary.
            // PHP_BINARY might point to php-fpm (FastCGI) which cannot run CLI scripts.
            $phpBinary = $this->findPhpCliBinary();
            
            $debugFile = $dataDir . '/ai_spawn_debug.log';
            $debugInfo = [
                'timestamp' => date('c'),
                'php_binary' => $phpBinary,
                'script_path' => $bin,
                'os' => PHP_OS,
                'cwd' => getcwd(),
                'path_env' => getenv('PATH') ?: getenv('Path') ?: 'not set'
            ];
            
            if (stripos(PHP_OS, 'WIN') === 0) {
                // Windows: Use more robust command that doesn't wait for process completion
                // PowerShell Start-Process is more reliable than cmd /c start
                $psCmd = sprintf(
                    'powershell.exe -WindowStyle Hidden -Command "Start-Process -NoNewWindow -FilePath %s -ArgumentList %s"',
                    escapeshellarg($phpBinary),
                    escapeshellarg($bin)
                );
                
                $debugInfo['command'] = $psCmd;
                $debugInfo['method'] = 'powershell';
                
                try {
                    // popen + pclose is non-blocking on Windows
                    @pclose(popen($psCmd, 'r'));
                    $debugInfo['status'] = 'spawned';
                } catch (\Throwable $e) {
                    $debugInfo['status'] = 'failed';
                    $debugInfo['error'] = $e->getMessage();
                }
            } else {
                // Unix-like: background the process, redirect output
                $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($bin) . ' > /dev/null 2>&1 &';
                $debugInfo['command'] = $cmd;
                $debugInfo['method'] = 'unix-background';
                
                try {
                    @exec($cmd);
                    $debugInfo['status'] = 'spawned';
                } catch (\Throwable $e) {
                    $debugInfo['status'] = 'failed';
                    $debugInfo['error'] = $e->getMessage();
                }
            }
            
            // Write detailed debug log
            try {
                $debugLine = json_encode($debugInfo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
                @file_put_contents($debugFile, $debugLine, FILE_APPEND | LOCK_EX);
            } catch (\Throwable $e) {
                // ignore logging errors
            }
        } finally {
            // Release lock
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * Testseite für DataService
     */
    public function testDataService(Request $request, Response $response): Response
    {
        // Teste das Schreiben und Lesen von Daten
        $testData = [
            'test' => true,
            'message' => 'Hello World',
            'timestamp' => time()
        ];

        $success = $this->dataService->write('test', $testData);
        $readData = $this->dataService->read('test');

        return $this->view->render($response, 'test.twig', [
            'title' => 'DataService Test',
            'writeSuccess' => $success,
            'originalData' => $testData,
            'readData' => $readData
        ]);
    }

    /**
     * Find a usable PHP CLI binary for running background scripts.
     * PHP_BINARY might point to php-fpm (FastCGI), which cannot execute CLI scripts.
     * This method implements a robust fallback mechanism.
     *
     * @return string Path to PHP CLI binary
     */
    private function findPhpCliBinary(): string
    {
        // 1. Check if PHP_BINARY is usable (not php-fpm)
        if (defined('PHP_BINARY') && !empty(PHP_BINARY)) {
            $binary = PHP_BINARY;
            
            // Skip php-fpm variants (check for any occurrence of 'fpm' in path)
            // Examples: php-fpm, php84-fpm, php8.4-fpm, phpfpm
            if (stripos($binary, 'fpm') === false) {
                // Verify it's executable
                if (is_executable($binary)) {
                    return $binary;
                }
            }
        }

        // 2. Try common PHP CLI binary names in PATH
        $candidates = [
            'php84',    // Version-specific binaries (newest first)
            'php83',
            'php82',
            'php81',
            'php80',
            'php',      // Generic php binary
        ];

        foreach ($candidates as $cmd) {
            // Use 'which' (Unix) or 'where' (Windows) to find binary in PATH
            if (stripos(PHP_OS, 'WIN') === 0) {
                // Windows: use 'where' command
                $result = @shell_exec("where {$cmd} 2>NUL");
                if (!empty($result)) {
                    $lines = explode("\n", trim($result));
                    $binary = trim($lines[0]); // Take first match
                    if (is_executable($binary)) {
                        return $binary;
                    }
                }
            } else {
                // Unix: use 'which' command
                $binary = @shell_exec("which {$cmd} 2>/dev/null");
                if (!empty($binary)) {
                    $binary = trim($binary);
                    if (is_executable($binary)) {
                        return $binary;
                    }
                }
            }
        }

        // 3. Try common installation paths (Linux)
        if (stripos(PHP_OS, 'WIN') !== 0) {
            $commonPaths = [
                '/usr/bin/php84',
                '/usr/bin/php83',
                '/usr/bin/php82',
                '/usr/bin/php81',
                '/usr/bin/php',
                '/usr/local/bin/php84',
                '/usr/local/bin/php83',
                '/usr/local/bin/php82',
                '/usr/local/bin/php',
                '/opt/plesk/php/8.4/bin/php',  // Plesk-specific paths
                '/opt/plesk/php/8.3/bin/php',
                '/opt/plesk/php/8.2/bin/php',
                '/opt/plesk/php/8.1/bin/php',
            ];

            foreach ($commonPaths as $path) {
                if (is_executable($path)) {
                    return $path;
                }
            }
        }

        // 4. Last resort: Use 'php' and hope it's in PATH
        // The spawn will fail gracefully if this doesn't work
        return 'php';
    }

} 