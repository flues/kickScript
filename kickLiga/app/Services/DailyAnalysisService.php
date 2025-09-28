<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Log\LoggerInterface;

class DailyAnalysisService
{
    private ComputationService $computation;
    private DataService $dataService;
    private GeminiService $gemini;
    private ?LoggerInterface $logger;

    public function __construct(
        ComputationService $computation,
        DataService $dataService,
        GeminiService $gemini,
        ?LoggerInterface $logger = null
    ) {
        $this->computation = $computation;
        $this->dataService = $dataService;
        $this->gemini = $gemini;
        $this->logger = $logger;
    }

    /**
     * Run the analysis if needed (max once per 24h). This runs in-process and
     * writes the summary and stamp files into the data directory.
     */
    public function runIfNeeded(): void
    {
        // Do not run if no API key configured
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

        if (is_file($stampFile) && ($now - (int) @file_get_contents($stampFile)) < 86400) {
            // Recently generated
            return;
        }

        // Acquire file lock to prevent concurrent runs
        $fp = @fopen($lockFile, 'c');
        if ($fp === false) {
            return;
        }

        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return; // another request is running the analysis
        }

        try {
            // Update stamp early to avoid thundering herd
            @file_put_contents($stampFile, (string)$now, LOCK_EX);

            $this->runOnce($dataDir);

            // update stamp to now to mark completion
            @file_put_contents($stampFile, (string)time(), LOCK_EX);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * Actual generation logic extracted from the CLI runner.
     */
    private function runOnce(string $dataDir): void
    {
        try {
            $seasonData = $this->computation->computeSeasonData();
            $playersData = array_slice($this->computation->computeAllPlayerData(), 0, 10, true);

            $topPlayers = array_slice(array_values($seasonData['rankings']), 0, 5);
            $topPlayersPayload = array_map(fn($p) => [
                'name' => $p['name'],
                'points' => $p['elo_rating'],
                'matches' => $p['matches'] ?? 0
            ], $topPlayers);

            $allMatches = $this->dataService->read('matches');
            $contextSize = (int) (getenv('AI_MATCHES_CONTEXT') ?: 20);
            $rawFullContext = array_slice(array_reverse($allMatches), 0, $contextSize);

            $playersMeta = $this->dataService->read('players_meta');

            $resolveName = function ($playerId) use ($playersMeta) {
                if (isset($playersMeta[$playerId]) && !empty($playersMeta[$playerId]['name'])) {
                    return $playersMeta[$playerId]['name'];
                }
                return null;
            };

            $fullContext = [];
            foreach ($rawFullContext as $m) {
                $copy = $m;
                $p1 = $resolveName($m['player1Id'] ?? null);
                $p2 = $resolveName($m['player2Id'] ?? null);
                if ($p1 !== null) {
                    $copy['player1Name'] = $p1;
                }
                if ($p2 !== null) {
                    $copy['player2Name'] = $p2;
                }
                if ($p1 !== null && $p2 !== null) {
                    $copy['summary'] = ($p1) . ' vs ' . ($p2) . ' - ' . ($m['scorePlayer1'] ?? '') . ':' . ($m['scorePlayer2'] ?? '');
                } else {
                    if (isset($copy['notes'])) {
                        unset($copy['notes']);
                    }
                    if (isset($copy['summary']) && preg_match('/player_\w+/', $copy['summary'])) {
                        unset($copy['summary']);
                    }
                }
                $fullContext[] = $copy;
            }

            // Build id->name map
            $idToName = [];
            if (is_array($playersMeta)) {
                foreach ($playersMeta as $p) {
                    if (isset($p['id']) && isset($p['name'])) {
                        $idToName[$p['id']] = $p['name'];
                    }
                }
            }

            $mapMatch = function($m) use ($idToName) {
                if (!is_array($m)) return false;
                $p1 = $idToName[$m['player1Id']] ?? null;
                $p2 = $idToName[$m['player2Id']] ?? null;
                if (empty($p1) || empty($p2)) {
                    return false;
                }
                $mapped = $m;
                $mapped['player1Name'] = $p1;
                $mapped['player2Name'] = $p2;
                return $mapped;
            };

            $fullContext = array_values(array_filter(array_map($mapMatch, $fullContext)));
            $recentMatches = array_slice($fullContext, 0, 5);

            $hotPlayers = [];
            foreach (array_slice($playersData, 0, 10, true) as $pid => $pdata) {
                $wins = $pdata['statistics']['wins'] ?? 0;
                $matches = $pdata['statistics']['matchesPlayed'] ?? 0;
                $streak = $wins >= 3 ? 3 : $wins;
                if ($streak >= 2) {
                    $hotPlayers[] = ['name' => $pdata['name'], 'streak' => $streak];
                }
            }

            $statsPayload = [
                'top_players' => $topPlayersPayload,
                'hot_players' => $hotPlayers,
                'recent_matches' => $recentMatches,
                'full_matches_context' => $fullContext
            ];

            try {
                $summary = $this->gemini->analyzeLeagueSummary($statsPayload);
            } catch (\Throwable $e) {
                $errFile = $dataDir . '/ai_summary_error.log';
                $entry = '[' . date('c') . '] Exception in daily-analysis: ' . $e->getMessage() . PHP_EOL;
                @file_put_contents($errFile, $entry, FILE_APPEND | LOCK_EX);
                $summary = 'AI summary unavailable (exception)';
            }

            if (!is_dir($dataDir)) {
                @mkdir($dataDir, 0755, true);
            }

            file_put_contents($dataDir . '/ai_summary.txt', $summary);

            if (empty(trim((string)$summary)) || stripos((string)$summary, 'unavailable') !== false) {
                $errFile = $dataDir . '/ai_summary_error.log';
                $entry = '[' . date('c') . '] Generated empty or unavailable summary.' . PHP_EOL;
                @file_put_contents($errFile, $entry, FILE_APPEND | LOCK_EX);
            }

            if ($this->logger) {
                $this->logger->info('DailyAnalysisService: Generated AI summary');
            }

        } catch (\Throwable $e) {
            $errFile = $dataDir . '/ai_summary_error.log';
            $entry = '[' . date('c') . '] Unhandled exception in DailyAnalysisService: ' . $e->getMessage() . PHP_EOL;
            @file_put_contents($errFile, $entry, FILE_APPEND | LOCK_EX);
            if ($this->logger) {
                $this->logger->error('DailyAnalysisService error: ' . $e->getMessage());
            }
        }
    }
}
