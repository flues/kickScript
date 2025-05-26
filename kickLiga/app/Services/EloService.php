<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Player;
use Psr\Log\LoggerInterface;

class EloService
{
    // Konstanten für die ELO-Berechnung
    private const K_FACTOR = 32; // Standardwert für die Stärke der Anpassung
    private const GOAL_DIFFERENCE_IMPACT = 0.1; // Gewichtung der Tordifferenz
    private const EXPECTED_SCORE_SCALE = 400; // Standardskala für die erwartete Gewinnwahrscheinlichkeit
    
    private ?LoggerInterface $logger;

    /**
     * EloService Konstruktor
     *
     * @param LoggerInterface|null $logger Logger für Logging
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Berechnet die erwartete Gewinnwahrscheinlichkeit für einen Spieler
     *
     * @param int $playerRating ELO-Rating des Spielers
     * @param int $opponentRating ELO-Rating des Gegners
     * @return float Erwarteter Wert (zwischen 0 und 1)
     */
    public function calculateExpectedScore(int $playerRating, int $opponentRating): float
    {
        return 1 / (1 + pow(10, ($opponentRating - $playerRating) / self::EXPECTED_SCORE_SCALE));
    }

    /**
     * Berechnet die ELO-Änderungen für ein Match und aktualisiert die Spieler
     *
     * @param GameMatch $match Das gespielte Match
     * @param Player $player1 Spieler 1
     * @param Player $player2 Spieler 2
     * @return GameMatch Das aktualisierte Match mit den ELO-Änderungen
     */
    public function processMatchRatings(GameMatch $match, Player $player1, Player $player2): GameMatch
    {
        $player1Rating = $player1->getEloRating();
        $player2Rating = $player2->getEloRating();
        
        // Berechne die erwarteten Werte für beide Spieler
        $expectedPlayer1 = $this->calculateExpectedScore($player1Rating, $player2Rating);
        $expectedPlayer2 = $this->calculateExpectedScore($player2Rating, $player1Rating);
        
        // Bestimme die tatsächlichen Werte (1 für Sieg, 0.5 für Unentschieden, 0 für Niederlage)
        $actualPlayer1 = $match->isPlayer1Winner() ? 1 : ($match->isDraw() ? 0.5 : 0);
        $actualPlayer2 = $match->isPlayer2Winner() ? 1 : ($match->isDraw() ? 0.5 : 0);
        
        // Passe den K-Faktor basierend auf der Tordifferenz an
        $kFactor = $this->adjustKFactorByGoalDifference($match);
        
        // Berechne die ELO-Änderungen
        $player1Change = (int)round($kFactor * ($actualPlayer1 - $expectedPlayer1));
        $player2Change = (int)round($kFactor * ($actualPlayer2 - $expectedPlayer2));
        
        // Aktualisiere die Spieler
        $player1->updateElo($player1Rating + $player1Change, "Match gegen {$player2->getName()}");
        $player2->updateElo($player2Rating + $player2Change, "Match gegen {$player1->getName()}");
        
        // Aktualisiere das Match mit den ELO-Änderungen
        $match->setEloChanges($player1Change, $player2Change);
        
        if ($this->logger) {
            $this->logger->info("ELO-Änderungen berechnet: {$player1->getName()} ({$player1Change}), {$player2->getName()} ({$player2Change})");
        }
        
        return $match;
    }

    /**
     * Passt den K-Faktor basierend auf der Tordifferenz an
     *
     * @param GameMatch $match Das Match
     * @return float Der angepasste K-Faktor
     */
    private function adjustKFactorByGoalDifference(GameMatch $match): float
    {
        $goalDifference = $match->getAbsoluteGoalDifference();
        
        // Bei einem Unentschieden oder einer Tordifferenz von 1 bleibt der K-Faktor unverändert
        if ($match->isDraw() || $goalDifference <= 1) {
            return self::K_FACTOR;
        }
        
        // Je höher die Tordifferenz, desto höher der K-Faktor
        // Aber wir begrenzen den Einfluss, um extrem hohe Tordifferenzen nicht zu stark zu gewichten
        $modifier = 1 + (min($goalDifference, 10) - 1) * self::GOAL_DIFFERENCE_IMPACT;
        
        return self::K_FACTOR * $modifier;
    }

    /**
     * Berechnet die ELO-Änderungen für ein Match basierend auf aktuellen Ratings
     *
     * @param int $player1Rating Aktuelles ELO-Rating von Spieler 1
     * @param int $player2Rating Aktuelles ELO-Rating von Spieler 2
     * @param int $scorePlayer1 Tore von Spieler 1
     * @param int $scorePlayer2 Tore von Spieler 2
     * @return array Array mit ELO-Änderungen ['player1' => int, 'player2' => int]
     */
    public function calculateEloChanges(
        int $player1Rating,
        int $player2Rating,
        int $scorePlayer1,
        int $scorePlayer2
    ): array {
        // Berechne die erwarteten Werte für beide Spieler
        $expectedPlayer1 = $this->calculateExpectedScore($player1Rating, $player2Rating);
        $expectedPlayer2 = $this->calculateExpectedScore($player2Rating, $player1Rating);
        
        // Bestimme die tatsächlichen Werte (1 für Sieg, 0.5 für Unentschieden, 0 für Niederlage)
        $actualPlayer1 = $scorePlayer1 > $scorePlayer2 ? 1 : ($scorePlayer1 === $scorePlayer2 ? 0.5 : 0);
        $actualPlayer2 = $scorePlayer2 > $scorePlayer1 ? 1 : ($scorePlayer1 === $scorePlayer2 ? 0.5 : 0);
        
        // Passe den K-Faktor basierend auf der Tordifferenz an
        $goalDifference = abs($scorePlayer1 - $scorePlayer2);
        $kFactor = $this->adjustKFactorByGoalDifferenceStatic($goalDifference, $scorePlayer1 === $scorePlayer2);
        
        // Berechne die ELO-Änderungen
        $player1Change = (int)round($kFactor * ($actualPlayer1 - $expectedPlayer1));
        $player2Change = (int)round($kFactor * ($actualPlayer2 - $expectedPlayer2));
        
        if ($this->logger) {
            $this->logger->info("ELO-Änderungen berechnet: Spieler 1 ({$player1Change}), Spieler 2 ({$player2Change})");
        }
        
        return [
            'player1' => $player1Change,
            'player2' => $player2Change
        ];
    }

    /**
     * Statische Version der K-Faktor-Anpassung basierend auf der Tordifferenz
     *
     * @param int $goalDifference Die absolute Tordifferenz
     * @param bool $isDraw Ob das Spiel unentschieden war
     * @return float Der angepasste K-Faktor
     */
    private function adjustKFactorByGoalDifferenceStatic(int $goalDifference, bool $isDraw): float
    {
        // Bei einem Unentschieden oder einer Tordifferenz von 1 bleibt der K-Faktor unverändert
        if ($isDraw || $goalDifference <= 1) {
            return self::K_FACTOR;
        }
        
        // Je höher die Tordifferenz, desto höher der K-Faktor
        // Aber wir begrenzen den Einfluss, um extrem hohe Tordifferenzen nicht zu stark zu gewichten
        $modifier = 1 + (min($goalDifference, 10) - 1) * self::GOAL_DIFFERENCE_IMPACT;
        
        return self::K_FACTOR * $modifier;
    }
} 