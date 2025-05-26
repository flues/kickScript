<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * ComputationService - Single Source of Truth für alle berechneten Daten
 * 
 * Alle Spieler-, Saison- und Achievement-Daten werden ausschließlich 
 * aus matches.json berechnet, um Dateninkonsistenzen zu vermeiden.
 * 
 * OPTIMIERT für Memory-Effizienz und Performance
 */
class ComputationService
{
    private DataService $dataService;
    private EloService $eloService;
    private ?LoggerInterface $logger;
    private const MATCHES_FILE = 'matches';
    private const PLAYERS_META_FILE = 'players_meta';
    
    // Cache für bereits geladene Daten (Memory-Optimierung)
    private ?array $cachedMatches = null;
    private ?array $cachedPlayersMeta = null;

    public function __construct(
        DataService $dataService,
        EloService $eloService,
        ?LoggerInterface $logger = null
    ) {
        $this->dataService = $dataService;
        $this->eloService = $eloService;
        $this->logger = $logger;
    }

    /**
     * Berechnet alle Spielerdaten basierend auf matches.json
     * OPTIMIERT: Lädt Matches nur einmal und verarbeitet sie effizient
     */
    public function computeAllPlayerData(): array
    {
        $matches = $this->getAllMatches();
        if (empty($matches)) {
            return [];
        }
        
        $playerIds = $this->extractPlayerIds($matches);
        $playersData = [];

        // Gruppiere Matches nach Spielern für effiziente Verarbeitung
        $playerMatches = $this->groupMatchesByPlayer($matches);

        foreach ($playerIds as $playerId) {
            $playerSpecificMatches = $playerMatches[$playerId] ?? [];
            $playersData[$playerId] = $this->computePlayerDataFromMatches($playerId, $playerSpecificMatches);
        }

        return $playersData;
    }

    /**
     * Berechnet alle Daten für einen spezifischen Spieler
     * OPTIMIERT: Verwendet bereits gefilterte Matches wenn verfügbar
     */
    public function computePlayerData(string $playerId): array
    {
        $matches = $this->getMatchesForPlayer($playerId);
        return $this->computePlayerDataFromMatches($playerId, $matches);
    }

    /**
     * Interne Methode zur Berechnung der Spielerdaten aus gegebenen Matches
     * OPTIMIERT: Keine redundanten Datenbankzugriffe
     */
    private function computePlayerDataFromMatches(string $playerId, array $matches): array
    {
        $playerMeta = $this->getPlayerMeta($playerId);

        if (empty($matches)) {
            return $this->getEmptyPlayerData($playerId, $playerMeta);
        }

        // Sortiere Matches chronologisch (nur einmal)
        usort($matches, fn($a, $b) => $a['playedAt'] <=> $b['playedAt']);

        return [
            'id' => $playerId,
            'name' => $playerMeta['name'] ?? 'Unbekannter Spieler',
            'nickname' => $playerMeta['nickname'] ?? null,
            'avatar' => $playerMeta['avatar'] ?? null,
            'eloRating' => $this->computeCurrentEloRating($playerId, $matches),
            'statistics' => $this->computePlayerStatistics($playerId, $matches),
            'achievements' => $this->computePlayerAchievements($playerId, $matches),
            'eloHistory' => $this->computeEloHistory($playerId, $matches),
            'createdAt' => $playerMeta['createdAt'] ?? $matches[0]['playedAt'],
            'lastMatch' => end($matches)['playedAt']
        ];
    }

    /**
     * Gruppiert Matches nach Spielern für effiziente Verarbeitung
     * OPTIMIERT: Reduziert die Anzahl der Array-Durchläufe
     */
    private function groupMatchesByPlayer(array $matches): array
    {
        $playerMatches = [];
        
        foreach ($matches as $match) {
            $player1Id = $match['player1Id'];
            $player2Id = $match['player2Id'];
            
            if (!isset($playerMatches[$player1Id])) {
                $playerMatches[$player1Id] = [];
            }
            if (!isset($playerMatches[$player2Id])) {
                $playerMatches[$player2Id] = [];
            }
            
            $playerMatches[$player1Id][] = $match;
            $playerMatches[$player2Id][] = $match;
        }
        
        return $playerMatches;
    }

    /**
     * Berechnet die aktuelle ELO-Rating für einen Spieler
     * OPTIMIERT: Verwendet bereits sortierte Matches
     */
    public function computeCurrentEloRating(string $playerId, array $matches = null): int
    {
        if ($matches === null) {
            $matches = $this->getMatchesForPlayer($playerId);
        }

        if (empty($matches)) {
            return 1000; // Standard-ELO
        }

        // Matches sollten bereits sortiert sein, aber sicherheitshalber prüfen
        if (!$this->isArraySorted($matches, 'playedAt')) {
            usort($matches, fn($a, $b) => $a['playedAt'] <=> $b['playedAt']);
        }

        $currentElo = 1000;
        foreach ($matches as $match) {
            $eloChange = $this->calculateEloChangeForMatch($match, $playerId);
            $currentElo += $eloChange;
        }

        return $currentElo;
    }

    /**
     * Berechnet die ELO-Historie für einen Spieler
     * OPTIMIERT: Verwendet bereits sortierte Matches
     */
    public function computeEloHistory(string $playerId, array $matches = null): array
    {
        if ($matches === null) {
            $matches = $this->getMatchesForPlayer($playerId);
        }

        if (empty($matches)) {
            return [];
        }

        // Matches sollten bereits sortiert sein
        if (!$this->isArraySorted($matches, 'playedAt')) {
            usort($matches, fn($a, $b) => $a['playedAt'] <=> $b['playedAt']);
        }

        $eloHistory = [
            [
                'rating' => 1000,
                'timestamp' => $matches[0]['playedAt'],
                'reason' => 'initial'
            ]
        ];

        $currentElo = 1000;
        foreach ($matches as $match) {
            $eloChange = $this->calculateEloChangeForMatch($match, $playerId);
            $currentElo += $eloChange;
            $opponentId = $this->getOpponentId($match, $playerId);
            $opponentName = $this->getPlayerMeta($opponentId)['name'] ?? 'Unbekannt';

            $eloHistory[] = [
                'rating' => $currentElo,
                'change' => $eloChange,
                'timestamp' => $match['playedAt'],
                'reason' => "Match gegen {$opponentName}"
            ];
        }

        return $eloHistory;
    }

    /**
     * Berechnet Spielerstatistiken
     * OPTIMIERT: Einzelner Durchlauf durch die Matches
     */
    public function computePlayerStatistics(string $playerId, array $matches = null): array
    {
        if ($matches === null) {
            $matches = $this->getMatchesForPlayer($playerId);
        }

        $stats = [
            'wins' => 0,
            'losses' => 0,
            'draws' => 0,
            'goalsScored' => 0,
            'goalsConceded' => 0,
            'tournamentsWon' => 0,
            'tournamentsParticipated' => 0,
            'matchesPlayed' => count($matches)
        ];

        foreach ($matches as $match) {
            $isPlayer1 = $match['player1Id'] === $playerId;
            $playerScore = $isPlayer1 ? $match['scorePlayer1'] : $match['scorePlayer2'];
            $opponentScore = $isPlayer1 ? $match['scorePlayer2'] : $match['scorePlayer1'];

            $stats['goalsScored'] += $playerScore;
            $stats['goalsConceded'] += $opponentScore;

            if ($playerScore > $opponentScore) {
                $stats['wins']++;
            } elseif ($playerScore < $opponentScore) {
                $stats['losses']++;
            } else {
                $stats['draws']++;
            }
        }

        return $stats;
    }

    /**
     * Berechnet Achievements für einen Spieler
     * OPTIMIERT: Verwendet bereits berechnete Statistiken
     */
    public function computePlayerAchievements(string $playerId, array $matches = null): array
    {
        if ($matches === null) {
            $matches = $this->getMatchesForPlayer($playerId);
        }

        if (empty($matches)) {
            return [];
        }

        $stats = $this->computePlayerStatistics($playerId, $matches);
        $achievements = [];

        // Sortiere Matches chronologisch für Streak-Berechnungen (falls nicht bereits sortiert)
        if (!$this->isArraySorted($matches, 'playedAt')) {
            usort($matches, fn($a, $b) => $a['playedAt'] <=> $b['playedAt']);
        }

        // Goal Machine Achievement
        if ($stats['matchesPlayed'] >= 3) {
            $avgGoals = $stats['goalsScored'] / $stats['matchesPlayed'];
            if ($avgGoals >= 5.0) {
                $achievements[] = [
                    'id' => 'goal_machine',
                    'name' => '🚀 Tormaschine',
                    'description' => 'Durchschnittlich 5+ Tore pro Spiel (min. 3 Spiele)',
                    'unlockedAt' => $this->findAchievementUnlockTime($matches, 'goal_machine', $playerId)
                ];
            }
        }

        // Winning Streak Achievements
        $winningStreak = $this->calculateWinningStreak($matches, $playerId);
        if ($winningStreak >= 3) {
            $achievements[] = [
                'id' => 'winning_streak_3',
                'name' => '🏆 Winning Streak (3)',
                'description' => '3 Siege in Folge',
                'unlockedAt' => $this->findStreakUnlockTime($matches, $playerId, 3)
            ];
        }

        // Top Scorer Achievement (saisonbasiert) - OPTIMIERT: Lazy Loading
        if ($this->isTopScorer($playerId)) {
            $achievements[] = [
                'id' => 'top_scorer',
                'name' => '⚽ Torschützenkönig',
                'description' => 'Meiste erzielte Tore in der aktuellen Saison',
                'unlockedAt' => $this->findTopScorerUnlockTime($playerId)
            ];
        }

        // Bad Keeper Achievement (saisonbasiert) - OPTIMIERT: Lazy Loading
        if ($this->isBadKeeper($playerId)) {
            $achievements[] = [
                'id' => 'bad_keeper',
                'name' => '💀 Bad Keeper',
                'description' => 'Meiste Gegentore in der aktuellen Saison',
                'unlockedAt' => $this->findBadKeeperUnlockTime($playerId)
            ];
        }

        // Goal Difference King
        $goalDifference = $stats['goalsScored'] - $stats['goalsConceded'];
        if ($goalDifference >= 20) {
            $achievements[] = [
                'id' => 'goal_difference_king',
                'name' => '📈 Tordifferenz-König',
                'description' => '+20 Tordifferenz insgesamt',
                'unlockedAt' => $this->findGoalDifferenceUnlockTime($matches, $playerId, 20)
            ];
        }

        return $achievements;
    }

    /**
     * Berechnet Saisondaten
     * OPTIMIERT: Verwendet bereits berechnete Spielerdaten
     */
    public function computeSeasonData(): array
    {
        $matches = $this->getAllMatches();
        $playersData = $this->computeAllPlayerData();

        // Sortiere Spieler nach ELO-Rating
        uasort($playersData, fn($a, $b) => $b['eloRating'] <=> $a['eloRating']);

        $rankings = [];
        $position = 1;
        foreach ($playersData as $playerData) {
            $rankings[] = [
                'position' => $position++,
                'player_id' => $playerData['id'],
                'name' => $playerData['name'],
                'elo_rating' => $playerData['eloRating'],
                'matches' => $playerData['statistics']['matchesPlayed'],
                'wins' => $playerData['statistics']['wins'],
                'losses' => $playerData['statistics']['losses']
            ];
        }

        return [
            'season_id' => date('Y-m'),
            'start_date' => $this->getSeasonStartDate(),
            'end_date' => null, // Aktuelle Saison
            'rankings' => $rankings,
            'matches_played' => count($matches),
            'stats' => $this->computeSeasonStats($matches)
        ];
    }

    /**
     * Hilfsmethoden - OPTIMIERT mit Caching
     */
    private function getAllMatches(): array
    {
        if ($this->cachedMatches === null) {
            $this->cachedMatches = $this->dataService->read(self::MATCHES_FILE);
        }
        return $this->cachedMatches;
    }

    private function getMatchesForPlayer(string $playerId): array
    {
        $allMatches = $this->getAllMatches();
        return array_filter($allMatches, function ($match) use ($playerId) {
            return $match['player1Id'] === $playerId || $match['player2Id'] === $playerId;
        });
    }

    private function extractPlayerIds(array $matches): array
    {
        $playerIds = [];
        foreach ($matches as $match) {
            $playerIds[] = $match['player1Id'];
            $playerIds[] = $match['player2Id'];
        }
        return array_unique($playerIds);
    }

    private function getPlayerMeta(string $playerId): array
    {
        if ($this->cachedPlayersMeta === null) {
            $this->cachedPlayersMeta = $this->dataService->read(self::PLAYERS_META_FILE);
        }
        return $this->cachedPlayersMeta[$playerId] ?? [];
    }

    private function getEmptyPlayerData(string $playerId, array $playerMeta): array
    {
        return [
            'id' => $playerId,
            'name' => $playerMeta['name'] ?? 'Unbekannter Spieler',
            'nickname' => $playerMeta['nickname'] ?? null,
            'avatar' => $playerMeta['avatar'] ?? null,
            'eloRating' => 1000,
            'statistics' => [
                'wins' => 0,
                'losses' => 0,
                'draws' => 0,
                'goalsScored' => 0,
                'goalsConceded' => 0,
                'tournamentsWon' => 0,
                'tournamentsParticipated' => 0,
                'matchesPlayed' => 0
            ],
            'achievements' => [],
            'eloHistory' => [],
            'createdAt' => $playerMeta['createdAt'] ?? time(),
            'lastMatch' => null
        ];
    }

    private function calculateEloChangeForMatch(array $match, string $playerId): int
    {
        // Verwende die bestehende ELO-Berechnung aus dem Match
        if (isset($match['eloChange'])) {
            if ($match['player1Id'] === $playerId) {
                return $match['eloChange']['player1'];
            } elseif ($match['player2Id'] === $playerId) {
                return $match['eloChange']['player2'];
            }
        }

        // Fallback: Berechne ELO-Änderung neu
        $isPlayer1 = $match['player1Id'] === $playerId;
        $playerScore = $isPlayer1 ? $match['scorePlayer1'] : $match['scorePlayer2'];
        $opponentScore = $isPlayer1 ? $match['scorePlayer2'] : $match['scorePlayer1'];

        // Vereinfachte ELO-Berechnung (sollte durch EloService ersetzt werden)
        $kFactor = 32;
        $expectedScore = 0.5; // Vereinfacht
        $actualScore = $playerScore > $opponentScore ? 1 : ($playerScore < $opponentScore ? 0 : 0.5);

        return (int) round($kFactor * ($actualScore - $expectedScore));
    }

    private function getOpponentId(array $match, string $playerId): string
    {
        return $match['player1Id'] === $playerId ? $match['player2Id'] : $match['player1Id'];
    }

    private function calculateWinningStreak(array $matches, string $playerId): int
    {
        $streak = 0;
        $maxStreak = 0;

        foreach (array_reverse($matches) as $match) {
            $isPlayer1 = $match['player1Id'] === $playerId;
            $playerScore = $isPlayer1 ? $match['scorePlayer1'] : $match['scorePlayer2'];
            $opponentScore = $isPlayer1 ? $match['scorePlayer2'] : $match['scorePlayer1'];

            if ($playerScore > $opponentScore) {
                $streak++;
                $maxStreak = max($maxStreak, $streak);
            } else {
                break; // Streak unterbrochen
            }
        }

        return $maxStreak;
    }

    /**
     * OPTIMIERT: Direkte Berechnung ohne Rekursion
     */
    private function isTopScorer(string $playerId): bool
    {
        $matches = $this->getAllMatches();
        if (empty($matches)) {
            return false;
        }

        $playerGoals = [];
        
        // Berechne Tore für alle Spieler direkt aus Matches
        foreach ($matches as $match) {
            $player1Id = $match['player1Id'];
            $player2Id = $match['player2Id'];
            
            if (!isset($playerGoals[$player1Id])) {
                $playerGoals[$player1Id] = 0;
            }
            if (!isset($playerGoals[$player2Id])) {
                $playerGoals[$player2Id] = 0;
            }
            
            $playerGoals[$player1Id] += $match['scorePlayer1'];
            $playerGoals[$player2Id] += $match['scorePlayer2'];
        }

        $maxGoals = max($playerGoals);
        return isset($playerGoals[$playerId]) && $playerGoals[$playerId] === $maxGoals;
    }

    /**
     * OPTIMIERT: Direkte Berechnung ohne Rekursion
     */
    private function isBadKeeper(string $playerId): bool
    {
        $matches = $this->getAllMatches();
        if (empty($matches)) {
            return false;
        }

        $playerConceded = [];
        
        // Berechne Gegentore für alle Spieler direkt aus Matches
        foreach ($matches as $match) {
            $player1Id = $match['player1Id'];
            $player2Id = $match['player2Id'];
            
            if (!isset($playerConceded[$player1Id])) {
                $playerConceded[$player1Id] = 0;
            }
            if (!isset($playerConceded[$player2Id])) {
                $playerConceded[$player2Id] = 0;
            }
            
            $playerConceded[$player1Id] += $match['scorePlayer2']; // Player1 kassiert Player2's Tore
            $playerConceded[$player2Id] += $match['scorePlayer1']; // Player2 kassiert Player1's Tore
        }

        $maxConceded = max($playerConceded);
        return isset($playerConceded[$playerId]) && $playerConceded[$playerId] === $maxConceded;
    }

    /**
     * Prüft, ob ein Array nach einem bestimmten Feld sortiert ist
     */
    private function isArraySorted(array $array, string $field): bool
    {
        $count = count($array);
        for ($i = 1; $i < $count; $i++) {
            if ($array[$i-1][$field] > $array[$i][$field]) {
                return false;
            }
        }
        return true;
    }

    private function findAchievementUnlockTime(array $matches, string $achievementId, string $playerId): int
    {
        // Vereinfachte Implementierung - nimmt das letzte Match als Unlock-Zeit
        return end($matches)['playedAt'];
    }

    private function findStreakUnlockTime(array $matches, string $playerId, int $streakLength): int
    {
        // Vereinfachte Implementierung
        return end($matches)['playedAt'];
    }

    private function findTopScorerUnlockTime(string $playerId): int
    {
        $matches = $this->getMatchesForPlayer($playerId);
        return empty($matches) ? time() : end($matches)['playedAt'];
    }

    private function findBadKeeperUnlockTime(string $playerId): int
    {
        $matches = $this->getMatchesForPlayer($playerId);
        return empty($matches) ? time() : end($matches)['playedAt'];
    }

    private function findGoalDifferenceUnlockTime(array $matches, string $playerId, int $targetDifference): int
    {
        // Vereinfachte Implementierung
        return end($matches)['playedAt'];
    }

    private function getSeasonStartDate(): string
    {
        $matches = $this->getAllMatches();
        if (empty($matches)) {
            return date('Y-m-01T00:00:00');
        }

        $earliestMatch = min(array_column($matches, 'playedAt'));
        return date('Y-m-01T00:00:00', $earliestMatch);
    }

    private function computeSeasonStats(array $matches): array
    {
        $totalGoals = 0;
        $highestScore = ['score' => 0, 'match_id' => null];

        foreach ($matches as $match) {
            $totalGoals += $match['scorePlayer1'] + $match['scorePlayer2'];
            $maxScore = max($match['scorePlayer1'], $match['scorePlayer2']);
            
            if ($maxScore > $highestScore['score']) {
                $highestScore = [
                    'score' => $maxScore,
                    'match_id' => $match['id'],
                    'player_id' => $match['scorePlayer1'] > $match['scorePlayer2'] ? $match['player1Id'] : $match['player2Id'],
                    'opponent_id' => $match['scorePlayer1'] > $match['scorePlayer2'] ? $match['player2Id'] : $match['player1Id'],
                    'score_string' => $match['scorePlayer1'] . '-' . $match['scorePlayer2'],
                    'date' => date('Y-m-d\TH:i:s', $match['playedAt'])
                ];
            }
        }

        return [
            'total_goals' => $totalGoals,
            'average_goals_per_match' => count($matches) > 0 ? round($totalGoals / count($matches), 1) : 0,
            'highest_score' => $highestScore
        ];
    }

    /**
     * Invalidiert alle berechneten Daten (für Cache-Implementierung)
     */
    public function invalidateCache(): void
    {
        $this->cachedMatches = null;
        $this->cachedPlayersMeta = null;
        
        if ($this->logger) {
            $this->logger->info('Cache invalidiert - alle Daten werden neu berechnet');
        }
    }

    /**
     * Vollständige Neuberechnung aller Daten
     */
    public function recomputeAll(): array
    {
        $this->invalidateCache();
        
        return [
            'players' => $this->computeAllPlayerData(),
            'season' => $this->computeSeasonData()
        ];
    }

    /**
     * Berechnet die Tabelle aus gegebenen Matches
     * 
     * @param array $matches Array von GameMatch-Objekten oder Match-Arrays
     * @return array Sortierte Tabelle
     */
    public function calculateStandings(array $matches): array
    {
        if (empty($matches)) {
            return [];
        }

        $standings = [];
        
        // Initialisiere Standings für alle Spieler
        foreach ($matches as $match) {
            $matchArray = is_array($match) ? $match : $match->jsonSerialize();
            
            $player1Id = $matchArray['player1Id'];
            $player2Id = $matchArray['player2Id'];
            
            if (!isset($standings[$player1Id])) {
                $playerMeta = $this->getPlayerMeta($player1Id);
                $standings[$player1Id] = [
                    'playerId' => $player1Id,
                    'name' => $playerMeta['name'] ?? 'Unbekannt',
                    'displayName' => $playerMeta['nickname'] ?? $playerMeta['name'] ?? 'Unbekannt',
                    'avatar' => $playerMeta['avatar'] ?? null,
                    'matches' => 0,
                    'wins' => 0,
                    'draws' => 0,
                    'losses' => 0,
                    'goalsScored' => 0,
                    'goalsConceded' => 0,
                    'goalDifference' => 0,
                    'points' => 0
                ];
            }
            
            if (!isset($standings[$player2Id])) {
                $playerMeta = $this->getPlayerMeta($player2Id);
                $standings[$player2Id] = [
                    'playerId' => $player2Id,
                    'name' => $playerMeta['name'] ?? 'Unbekannt',
                    'displayName' => $playerMeta['nickname'] ?? $playerMeta['name'] ?? 'Unbekannt',
                    'avatar' => $playerMeta['avatar'] ?? null,
                    'matches' => 0,
                    'wins' => 0,
                    'draws' => 0,
                    'losses' => 0,
                    'goalsScored' => 0,
                    'goalsConceded' => 0,
                    'goalDifference' => 0,
                    'points' => 0
                ];
            }
        }
        
        // Verarbeite alle Matches
        foreach ($matches as $match) {
            $matchArray = is_array($match) ? $match : $match->jsonSerialize();
            
            $player1Id = $matchArray['player1Id'];
            $player2Id = $matchArray['player2Id'];
            $score1 = $matchArray['scorePlayer1'];
            $score2 = $matchArray['scorePlayer2'];
            
            // Aktualisiere Spielstatistiken
            $standings[$player1Id]['matches']++;
            $standings[$player2Id]['matches']++;
            
            $standings[$player1Id]['goalsScored'] += $score1;
            $standings[$player1Id]['goalsConceded'] += $score2;
            $standings[$player2Id]['goalsScored'] += $score2;
            $standings[$player2Id]['goalsConceded'] += $score1;
            
            // Berechne Tordifferenz
            $standings[$player1Id]['goalDifference'] = 
                $standings[$player1Id]['goalsScored'] - $standings[$player1Id]['goalsConceded'];
            $standings[$player2Id]['goalDifference'] = 
                $standings[$player2Id]['goalsScored'] - $standings[$player2Id]['goalsConceded'];
            
            // Bestimme Gewinner und vergebe Punkte
            if ($score1 > $score2) {
                // Spieler 1 gewinnt
                $standings[$player1Id]['wins']++;
                $standings[$player2Id]['losses']++;
                $standings[$player1Id]['points'] += 3;
            } elseif ($score2 > $score1) {
                // Spieler 2 gewinnt
                $standings[$player2Id]['wins']++;
                $standings[$player1Id]['losses']++;
                $standings[$player2Id]['points'] += 3;
            } else {
                // Unentschieden
                $standings[$player1Id]['draws']++;
                $standings[$player2Id]['draws']++;
                $standings[$player1Id]['points'] += 1;
                $standings[$player2Id]['points'] += 1;
            }
        }
        
        // Sortiere Tabelle
        $sortedStandings = array_values($standings);
        usort($sortedStandings, function ($a, $b) {
            // Primär nach Punkten
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }
            
            // Sekundär nach Tordifferenz
            if ($a['goalDifference'] !== $b['goalDifference']) {
                return $b['goalDifference'] <=> $a['goalDifference'];
            }
            
            // Tertiär nach geschossenen Toren
            if ($a['goalsScored'] !== $b['goalsScored']) {
                return $b['goalsScored'] <=> $a['goalsScored'];
            }
            
            // Quaternär nach weniger Spielen (bessere Effizienz)
            if ($a['matches'] !== $b['matches']) {
                return $a['matches'] <=> $b['matches'];
            }
            
            // Alphabetisch nach Namen
            return strcmp($a['name'], $b['name']);
        });
        
        // Füge Rangposition hinzu
        foreach ($sortedStandings as $index => &$player) {
            $player['rank'] = $index + 1;
        }
        
        return $sortedStandings;
    }

    /**
     * Berechnet Saisonstatistiken aus gegebenen Matches
     * 
     * @param array $matches Array von GameMatch-Objekten oder Match-Arrays
     * @return array Saisonstatistiken
     */
    public function calculateSeasonStatistics(array $matches): array
    {
        if (empty($matches)) {
            return [
                'totalMatches' => 0,
                'totalGoals' => 0,
                'highestScore' => null,
                'longestWinStreak' => null
            ];
        }

        $totalMatches = count($matches);
        $totalGoals = 0;
        $highestScore = null;
        $maxGoalDifference = 0;
        
        foreach ($matches as $match) {
            $matchArray = is_array($match) ? $match : $match->jsonSerialize();
            
            $score1 = $matchArray['scorePlayer1'];
            $score2 = $matchArray['scorePlayer2'];
            $totalGoals += $score1 + $score2;
            
            // Höchster Sieg
            $goalDifference = abs($score1 - $score2);
            if ($goalDifference > $maxGoalDifference) {
                $maxGoalDifference = $goalDifference;
                $winnerId = $score1 > $score2 ? $matchArray['player1Id'] : $matchArray['player2Id'];
                $loserId = $score1 > $score2 ? $matchArray['player2Id'] : $matchArray['player1Id'];
                
                $highestScore = [
                    'matchId' => $matchArray['id'],
                    'winnerId' => $winnerId,
                    'loserId' => $loserId,
                    'score' => $score1 . '-' . $score2,
                    'goalDifference' => $goalDifference,
                    'date' => $matchArray['playedAt']
                ];
            }
        }
        
        // Längste Siegesserie berechnen (vereinfacht)
        $longestWinStreak = $this->calculateLongestWinStreakFromMatches($matches);
        
        return [
            'totalMatches' => $totalMatches,
            'totalGoals' => $totalGoals,
            'highestScore' => $highestScore,
            'longestWinStreak' => $longestWinStreak
        ];
    }

    /**
     * Berechnet die längste Siegesserie aus Matches
     */
    private function calculateLongestWinStreakFromMatches(array $matches): ?array
    {
        if (empty($matches)) {
            return null;
        }

        $playerStreaks = [];
        $maxStreak = 0;
        $longestStreakPlayer = null;
        
        // Sortiere Matches chronologisch
        $sortedMatches = $matches;
        usort($sortedMatches, function($a, $b) {
            $aArray = is_array($a) ? $a : $a->jsonSerialize();
            $bArray = is_array($b) ? $b : $b->jsonSerialize();
            return $aArray['playedAt'] <=> $bArray['playedAt'];
        });
        
        foreach ($sortedMatches as $match) {
            $matchArray = is_array($match) ? $match : $match->jsonSerialize();
            
            $player1Id = $matchArray['player1Id'];
            $player2Id = $matchArray['player2Id'];
            $score1 = $matchArray['scorePlayer1'];
            $score2 = $matchArray['scorePlayer2'];
            
            if (!isset($playerStreaks[$player1Id])) {
                $playerStreaks[$player1Id] = 0;
            }
            if (!isset($playerStreaks[$player2Id])) {
                $playerStreaks[$player2Id] = 0;
            }
            
            if ($score1 > $score2) {
                // Spieler 1 gewinnt
                $playerStreaks[$player1Id]++;
                $playerStreaks[$player2Id] = 0;
                
                if ($playerStreaks[$player1Id] > $maxStreak) {
                    $maxStreak = $playerStreaks[$player1Id];
                    $longestStreakPlayer = $player1Id;
                }
            } elseif ($score2 > $score1) {
                // Spieler 2 gewinnt
                $playerStreaks[$player2Id]++;
                $playerStreaks[$player1Id] = 0;
                
                if ($playerStreaks[$player2Id] > $maxStreak) {
                    $maxStreak = $playerStreaks[$player2Id];
                    $longestStreakPlayer = $player2Id;
                }
            } else {
                // Unentschieden - Serien werden unterbrochen
                $playerStreaks[$player1Id] = 0;
                $playerStreaks[$player2Id] = 0;
            }
        }
        
        if ($maxStreak > 0 && $longestStreakPlayer) {
            $playerMeta = $this->getPlayerMeta($longestStreakPlayer);
            return [
                'playerId' => $longestStreakPlayer,
                'playerName' => $playerMeta['name'] ?? 'Unbekannt',
                'streak' => $maxStreak
            ];
        }
        
        return null;
    }
} 