<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Player;
use App\Models\GameMatch;
use Psr\Log\LoggerInterface;

class AchievementService
{
    private PlayerService $playerService;
    private MatchService $matchService;
    private ?LoggerInterface $logger;

    // Achievement-Definitionen
    private const ACHIEVEMENTS = [
        'winning_streak_3' => [
            'name' => '🏆 Winning Streak (3)',
            'description' => '3 Siege in Folge',
            'icon' => '🏆'
        ],
        'winning_streak_5' => [
            'name' => '👑 Winning Streak (5)',
            'description' => '5 Siege in Folge',
            'icon' => '👑'
        ],
        'highest_win' => [
            'name' => '⚡ Höchster Sieg',
            'description' => '10+ Tore Differenz in einem Spiel',
            'icon' => '⚡'
        ],
        'bad_keeper' => [
            'name' => '💀 Bad Keeper',
            'description' => 'Meiste Gegentore in der aktuellen Saison',
            'icon' => '💀'
        ],
        'top_scorer' => [
            'name' => '⚽ Torschützenkönig',
            'description' => 'Meiste erzielte Tore in der aktuellen Saison',
            'icon' => '⚽'
        ],
        'perfect_record' => [
            'name' => '⭐ Perfekte Bilanz',
            'description' => '100% Siegquote (min. 3 Spiele)',
            'icon' => '⭐'
        ],
        'goal_machine' => [
            'name' => '🚀 Tormaschine',
            'description' => 'Durchschnittlich 5+ Tore pro Spiel (min. 3 Spiele)',
            'icon' => '🚀'
        ],
        'iron_defense' => [
            'name' => '🛡️ Eiserne Abwehr',
            'description' => 'Durchschnittlich weniger als 3 Gegentore pro Spiel (min. 3 Spiele)',
            'icon' => '🛡️'
        ],
        'unlucky' => [
            'name' => '😵 Unglücksrabe',
            'description' => '0 Siege bei 5+ Spielen',
            'icon' => '😵'
        ],
        'veteran' => [
            'name' => '🎖️ Veteran',
            'description' => '10+ absolvierte Spiele',
            'icon' => '🎖️'
        ],
        'goal_difference_king' => [
            'name' => '📈 Tordifferenz-König',
            'description' => '+20 Tordifferenz insgesamt',
            'icon' => '📈'
        ],
        'balanced' => [
            'name' => '⚖️ Ausgewogen',
            'description' => 'Gleiche Anzahl Tore und Gegentore (min. 5 Spiele)',
            'icon' => '⚖️'
        ],
    ];

    public function __construct(
        PlayerService $playerService,
        MatchService $matchService,
        ?LoggerInterface $logger = null
    ) {
        $this->playerService = $playerService;
        $this->matchService = $matchService;
        $this->logger = $logger;
    }

    /**
     * Überprüft und vergibt Achievements für einen Spieler
     */
    public function checkAchievementsForPlayer(string $playerId): array
    {
        $player = $this->playerService->getPlayerById($playerId);
        if (!$player) {
            return [];
        }

        $matches = $this->matchService->getMatchesByPlayerId($playerId);
        $allPlayers = $this->playerService->getAllPlayers();

        $newAchievements = [];

        // Prüfe alle Achievement-Typen
        $newAchievements = array_merge(
            $newAchievements,
            $this->checkWinningStreak($player, $matches),
            $this->checkHighestWin($player, $matches),
            $this->checkLeadershipAchievements($player, $allPlayers),
            $this->checkStatisticAchievements($player),
            $this->checkSpecialAchievements($player)
        );

        // Neue Achievements zum Spieler hinzufügen
        foreach ($newAchievements as $achievementId) {
            if (isset(self::ACHIEVEMENTS[$achievementId])) {
                $achievement = self::ACHIEVEMENTS[$achievementId];
                $player->addAchievement(
                    $achievementId,
                    $achievement['name'],
                    $achievement['description']
                );

                if ($this->logger) {
                    $this->logger->info("Achievement '{$achievement['name']}' für Spieler {$player->getName()} vergeben");
                }
            }
        }

        // Spieler speichern, wenn neue Achievements hinzugefügt wurden
        if (!empty($newAchievements)) {
            $this->playerService->savePlayer($player);
        }

        return $newAchievements;
    }

    /**
     * Überprüft Winning Streak Achievements
     */
    private function checkWinningStreak(Player $player, array $matches): array
    {
        $achievements = [];
        $currentStreak = 0;
        $maxStreak = 0;

        // Sortiere Matches nach Datum (älteste zuerst)
        usort($matches, function (GameMatch $a, GameMatch $b) {
            return $a->getPlayedAt()->getTimestamp() - $b->getPlayedAt()->getTimestamp();
        });

        foreach ($matches as $match) {
            $playerScore = $match->getPlayer1Id() === $player->getId() 
                ? $match->getScorePlayer1() 
                : $match->getScorePlayer2();
            $opponentScore = $match->getPlayer1Id() === $player->getId() 
                ? $match->getScorePlayer2() 
                : $match->getScorePlayer1();

            if ($playerScore > $opponentScore) {
                $currentStreak++;
                $maxStreak = max($maxStreak, $currentStreak);
            } else {
                $currentStreak = 0;
            }
        }

        if ($maxStreak >= 3 && !$this->hasAchievement($player, 'winning_streak_3')) {
            $achievements[] = 'winning_streak_3';
        }
        if ($maxStreak >= 5 && !$this->hasAchievement($player, 'winning_streak_5')) {
            $achievements[] = 'winning_streak_5';
        }

        return $achievements;
    }

    /**
     * Überprüft Highest Win Achievement
     */
    private function checkHighestWin(Player $player, array $matches): array
    {
        $achievements = [];

        if ($this->hasAchievement($player, 'highest_win')) {
            return $achievements;
        }

        foreach ($matches as $match) {
            $playerScore = $match->getPlayer1Id() === $player->getId() 
                ? $match->getScorePlayer1() 
                : $match->getScorePlayer2();
            $opponentScore = $match->getPlayer1Id() === $player->getId() 
                ? $match->getScorePlayer2() 
                : $match->getScorePlayer1();

            $goalDifference = $playerScore - $opponentScore;
            if ($goalDifference >= 10) {
                $achievements[] = 'highest_win';
                break;
            }
        }

        return $achievements;
    }

    /**
     * Überprüft Leadership Achievements (Top Scorer, Bad Keeper)
     */
    private function checkLeadershipAchievements(Player $player, array $allPlayers): array
    {
        $achievements = [];

        // Finde Spieler mit den meisten Toren und Gegentoren
        $topScorer = null;
        $worstKeeper = null;
        $maxGoals = 0;
        $maxConceded = 0;

        foreach ($allPlayers as $p) {
            $stats = $p->getStatistics();
            if ($stats['goalsScored'] > $maxGoals) {
                $maxGoals = $stats['goalsScored'];
                $topScorer = $p;
            }
            if ($stats['goalsConceded'] > $maxConceded) {
                $maxConceded = $stats['goalsConceded'];
                $worstKeeper = $p;
            }
        }

        if ($topScorer && $topScorer->getId() === $player->getId() && 
            !$this->hasAchievement($player, 'top_scorer') && $maxGoals >= 5) {
            $achievements[] = 'top_scorer';
        }

        if ($worstKeeper && $worstKeeper->getId() === $player->getId() && 
            !$this->hasAchievement($player, 'bad_keeper') && $maxConceded >= 10) {
            $achievements[] = 'bad_keeper';
        }

        return $achievements;
    }

    /**
     * Überprüft statistische Achievements
     */
    private function checkStatisticAchievements(Player $player): array
    {
        $achievements = [];
        $stats = $player->getStatistics();

        // Perfect Record
        if ($stats['matchesPlayed'] >= 3 && $stats['losses'] === 0 && $stats['draws'] === 0 && 
            !$this->hasAchievement($player, 'perfect_record')) {
            $achievements[] = 'perfect_record';
        }

        // Goal Machine
        if ($stats['matchesPlayed'] >= 3) {
            $avgGoals = $stats['goalsScored'] / $stats['matchesPlayed'];
            if ($avgGoals >= 5.0 && !$this->hasAchievement($player, 'goal_machine')) {
                $achievements[] = 'goal_machine';
            }
        }

        // Iron Defense
        if ($stats['matchesPlayed'] >= 3) {
            $avgConceded = $stats['goalsConceded'] / $stats['matchesPlayed'];
            if ($avgConceded < 3.0 && !$this->hasAchievement($player, 'iron_defense')) {
                $achievements[] = 'iron_defense';
            }
        }

        // Unlucky
        if ($stats['matchesPlayed'] >= 5 && $stats['wins'] === 0 && 
            !$this->hasAchievement($player, 'unlucky')) {
            $achievements[] = 'unlucky';
        }

        // Veteran
        if ($stats['matchesPlayed'] >= 10 && !$this->hasAchievement($player, 'veteran')) {
            $achievements[] = 'veteran';
        }

        // Goal Difference King
        if ($player->getGoalDifference() >= 20 && 
            !$this->hasAchievement($player, 'goal_difference_king')) {
            $achievements[] = 'goal_difference_king';
        }

        // Balanced
        if ($stats['matchesPlayed'] >= 5 && 
            $stats['goalsScored'] === $stats['goalsConceded'] && 
            !$this->hasAchievement($player, 'balanced')) {
            $achievements[] = 'balanced';
        }

        return $achievements;
    }

    /**
     * Überprüft spezielle Achievements
     */
    private function checkSpecialAchievements(Player $player): array
    {
        // Hier können weitere spezielle Achievements hinzugefügt werden
        return [];
    }

    /**
     * Überprüft, ob ein Spieler bereits ein bestimmtes Achievement hat
     */
    private function hasAchievement(Player $player, string $achievementId): bool
    {
        foreach ($player->getAchievements() as $achievement) {
            if ($achievement['id'] === $achievementId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Überprüft Achievements für alle Spieler nach einem Match
     */
    public function checkAchievementsAfterMatch(string $player1Id, string $player2Id): void
    {
        $this->checkAchievementsForPlayer($player1Id);
        $this->checkAchievementsForPlayer($player2Id);
    }

    /**
     * Gibt alle verfügbaren Achievement-Definitionen zurück
     */
    public function getAllAchievementDefinitions(): array
    {
        return self::ACHIEVEMENTS;
    }
} 