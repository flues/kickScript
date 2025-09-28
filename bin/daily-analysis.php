<?php

declare(strict_types=1);

// Minimal bootstrap for CLI daily analysis
require __DIR__ . '/../kickLiga/vendor/autoload.php';

use App\Config\ContainerConfig;
use App\Services\ComputationService;
use App\Services\DataService;
use App\Services\GeminiService;

$container = ContainerConfig::createContainer();
/** @var ComputationService $computation */
$computation = $container->get(ComputationService::class);
/** @var DataService $dataService */
$dataService = $container->get(DataService::class);
/** @var GeminiService $gemini */
$gemini = $container->get(GeminiService::class);

// Build a compact stats payload
$seasonData = $computation->computeSeasonData();
$playersData = array_slice($computation->computeAllPlayerData(), 0, 10, true);

// Top players by ELO (first 5)
$topPlayers = array_slice(array_values($seasonData['rankings']), 0, 5);
// Include matches played so the AI can distinguish between high ELO due to play vs. default 1000
$topPlayersPayload = array_map(fn($p) => [
    'name' => $p['name'],
    'points' => $p['elo_rating'],
    'matches' => $p['matches'] ?? 0
], $topPlayers);

// Recent matches: read a larger context of recent matches (default 20), but keep the
// 'recent_matches' array focused on the latest 5 for direct commentary. Make the
// context size configurable via AI_MATCHES_CONTEXT env var.
$allMatches = $dataService->read('matches');
$contextSize = (int) (getenv('AI_MATCHES_CONTEXT') ?: 20);
// Keep fullContext as an array of the most recent $contextSize matches (chronological newest first)
$rawFullContext = array_slice(array_reverse($allMatches), 0, $contextSize);

// Load player meta for name resolution
$playersMeta = $dataService->read('players_meta');

// Helper to resolve player id to name
$resolveName = function ($playerId) use ($playersMeta) {
    if (isset($playersMeta[$playerId]) && !empty($playersMeta[$playerId]['name'])) {
        return $playersMeta[$playerId]['name'];
    }
    return null;
};

// Enrich matches by replacing ids with names where possible. If a match involves
// unknown/unresolvable players, we will omit the textual note for that match so
// the AI doesn't mention raw IDs.
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
    // prepare a human-friendly summary if both names available
    if ($p1 !== null && $p2 !== null) {
        $copy['summary'] = ($p1) . ' vs ' . ($p2) . ' - ' . ($m['scorePlayer1'] ?? '') . ':' . ($m['scorePlayer2'] ?? '');
    } else {
        // remove any notes that contain raw player ids to avoid leaking ids to the model
        if (isset($copy['notes'])) {
            unset($copy['notes']);
        }
        // also avoid adding summary with ids
        if (isset($copy['summary']) && preg_match('/player_\w+/', $copy['summary'])) {
            unset($copy['summary']);
        }
    }
    $fullContext[] = $copy;
}

$recentMatches = array_slice($fullContext, 0, 5);

// Resolve player IDs to display names using players_meta (if available)
$playersMeta = $dataService->read('players_meta');
$idToName = [];
if (is_array($playersMeta)) {
    foreach ($playersMeta as $p) {
        if (isset($p['id']) && isset($p['name'])) {
            $idToName[$p['id']] = $p['name'];
        }
    }
}

// Helper to map ids to names; returns false if no names available for both players
$mapMatch = function($m) use ($idToName) {
    if (!is_array($m)) return false;
    $p1 = $idToName[$m['player1Id']] ?? null;
    $p2 = $idToName[$m['player2Id']] ?? null;
    if (empty($p1) || empty($p2)) {
        return false; // skip matches with missing display names to avoid raw ids in prompts
    }

    $mapped = $m;
    $mapped['player1Name'] = $p1;
    $mapped['player2Name'] = $p2;
    return $mapped;
};

// Map and filter fullContext/recentMatches
$fullContext = array_values(array_filter(array_map($mapMatch, $fullContext)));
$recentMatches = array_values(array_filter(array_map($mapMatch, $recentMatches)));

// Hot players: compute simple winning streaks from playersData
$hotPlayers = [];
foreach ($playersData as $pid => $pdata) {
    $wins = $pdata['statistics']['wins'] ?? 0;
    $matches = $pdata['statistics']['matchesPlayed'] ?? 0;
    $streak = $wins >= 3 ? 3 : $wins; // naive
    if ($streak >= 2) {
        $hotPlayers[] = ['name' => $pdata['name'], 'streak' => $streak];
    }
}
$hotPlayers = array_slice($hotPlayers, 0, 5);

$statsPayload = [
    'top_players' => $topPlayersPayload,
    'hot_players' => $hotPlayers,
    'recent_matches' => $recentMatches,
    // provide the larger context for the model to reference if needed
    'full_matches_context' => $fullContext
];

try {
    $summary = $gemini->analyzeLeagueSummary($statsPayload);
} catch (\Throwable $e) {
    $errFile = __DIR__ . '/../kickLiga/data/ai_summary_error.log';
    $entry = '[' . date('c') . '] Exception in daily-analysis: ' . $e->getMessage() . PHP_EOL;
    @file_put_contents($errFile, $entry, FILE_APPEND | LOCK_EX);
    $summary = 'AI summary unavailable (exception)';
}

// Ensure data directory exists
$dataDir = __DIR__ . '/../kickLiga/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

file_put_contents($dataDir . '/ai_summary.txt', $summary);

// If the summary looks suspicious (empty or placeholder), write a short debug line
if (empty(trim((string)$summary)) || stripos((string)$summary, 'unavailable') !== false) {
    $errFile = __DIR__ . '/../kickLiga/data/ai_summary_error.log';
    $entry = '[' . date('c') . '] Generated empty or unavailable summary.' . PHP_EOL;
    @file_put_contents($errFile, $entry, FILE_APPEND | LOCK_EX);
}

echo "AI summary written to {$dataDir}/ai_summary.txt\n";
