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
    private ?\App\Services\DailyAnalysisService $dailyAnalysisService = null;

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
        // DailyAnalysisService is optional; fetch from container if available
        try {
            // Delay loading to avoid adding a hard DI dependency; the ContainerConfig
            // will inject HomeController via a factory that has access to the container.
            // If the container provides DailyAnalysisService, set it here.
            $container = \DI\Bridge\Pimple\container() ?? null;
        } catch (\Throwable $e) {
            $container = null;
        }
        // Note: we'll rely on ContainerConfig to set the service via setter if needed.
    }

    /**
     * Home-Seite anzeigen
     */
    public function home(Request $request, Response $response): Response
    {
        // Run the daily analysis in-process ONLY for CLI invocations (e.g., manual runs).
        // For web requests (including the built-in php dev server), use the non-blocking
        // spawn fallback to avoid long blocking page loads on localhost.
        if ($this->dailyAnalysisService !== null) {
            try {
                $this->dailyAnalysisService->runIfNeeded();
            } catch (\Throwable $e) {
                // Don't let analysis failures break the homepage; they are logged by the service
            }
        } else {
            // Fallback: attempt a non-blocking spawn (existing logic)
            $this->maybeSpawnDailyAnalysis();
        }

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

            // Platform-specific non-blocking spawn. Use the same PHP binary that
            // runs the current process (PHP_BINARY) to avoid relying on 'php' in PATH.
            $phpBinary = defined('PHP_BINARY') ? PHP_BINARY : 'php';
            if (stripos(PHP_OS, 'WIN') === 0) {
                // Windows: start requires a title argument; provide empty title "".
                // Use cmd /c start "" /B <php> <script>
                $cmd = 'cmd /c start "" /B ' . escapeshellarg($phpBinary) . ' ' . escapeshellarg($bin);
            } else {
                // Unix-like: background the process, redirect output
                $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($bin) . ' > /dev/null 2>&1 &';
            }

            // Write a short debug line so we can inspect the exact command and env on the live server.
            try {
                $debugFile = $dataDir . '/ai_spawn_debug.log';
                $debug = '[' . date('c') . '] Spawn command: ' . $cmd . "\n";
                $debug .= 'PHP_BINARY: ' . (defined('PHP_BINARY') ? PHP_BINARY : 'undefined') . "\n";
                $debug .= 'CWD: ' . getcwd() . "\n";
                $debug .= 'PATH: ' . (getenv('PATH') ?: getenv('Path') ?: '') . "\n";
                @file_put_contents($debugFile, $debug, FILE_APPEND | LOCK_EX);
            } catch (\Throwable $e) {
                // ignore
            }

            // Execute spawn (non-blocking). Use appropriate call for platform.
            if (stripos(PHP_OS, 'WIN') === 0) {
                @pclose(popen($cmd, 'r'));
            } else {
                @exec($cmd);
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
     * Optional setter for the DailyAnalysisService so the container factory can inject it.
     */
    public function setDailyAnalysisService(\App\Services\DailyAnalysisService $service): void
    {
        $this->dailyAnalysisService = $service;
    }
} 