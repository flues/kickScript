<?php

declare(strict_types=1);

namespace App\Services;

class CoinflipService
{
    // Konstanten für Münzseiten
    public const HEADS = 'kopf';
    public const TAILS = 'zahl';
    public const VALID_OUTCOMES = [self::HEADS, self::TAILS];

    // Konstanten für Seitenzuweisung
    public const SIDE_BLUE = 'blau';
    public const SIDE_WHITE = 'weiss';

    /**
     * Führt einen Münzwurf durch
     * 
     * @return string 'kopf' oder 'zahl'
     */
    public function flip(): string
    {
        return random_int(0, 1) === 0 ? self::HEADS : self::TAILS;
    }

    /**
     * Führt einen Münzwurf mit mehreren Runden durch
     * 
     * @param int $rounds Anzahl der Würfe
     * @return array Array mit den Ergebnissen
     */
    public function multiFlip(int $rounds = 3): array
    {
        if ($rounds < 1) {
            throw new \InvalidArgumentException('Anzahl der Runden muss mindestens 1 sein.');
        }

        $results = [];
        for ($i = 0; $i < $rounds; $i++) {
            $results[] = $this->flip();
        }

        return $results;
    }

    /**
     * Bestimmt Seitenzuweisung basierend auf Münzwurf
     * 
     * @param string $player1Choice Wahl von Spieler 1 ('kopf' oder 'zahl')
     * @param string $coinResult Ergebnis des Münzwurfs
     * @return array ['player1Side' => 'blau|weiss', 'player2Side' => 'blau|weiss', 'winner' => 1|2]
     */
    public function assignSides(string $player1Choice, string $coinResult): array
    {
        if (!in_array($player1Choice, self::VALID_OUTCOMES)) {
            throw new \InvalidArgumentException("Ungültige Wahl: $player1Choice. Erlaubt: " . implode(', ', self::VALID_OUTCOMES));
        }

        if (!in_array($coinResult, self::VALID_OUTCOMES)) {
            throw new \InvalidArgumentException("Ungültiges Münzwurf-Ergebnis: $coinResult");
        }

        $player1Wins = ($player1Choice === $coinResult);
        
        return [
            'player1Side' => $player1Wins ? self::SIDE_BLUE : self::SIDE_WHITE,
            'player2Side' => $player1Wins ? self::SIDE_WHITE : self::SIDE_BLUE,
            'winner' => $player1Wins ? 1 : 2,
            'coinResult' => $coinResult,
            'player1Choice' => $player1Choice
        ];
    }

    /**
     * Vollständiger Coinflip-Prozess: Münzwurf + Seitenzuweisung
     * 
     * @param string $player1Choice Wahl von Spieler 1
     * @return array Komplettes Ergebnis mit Münzwurf und Seitenzuweisung
     */
    public function performCoinflipWithSideAssignment(string $player1Choice): array
    {
        $coinResult = $this->flip();
        $assignment = $this->assignSides($player1Choice, $coinResult);
        
        return [
            'coinflipResult' => $coinResult,
            'sideAssignment' => $assignment,
            'timestamp' => new \DateTimeImmutable()
        ];
    }

    /**
     * Erstellt eine lesbare Beschreibung des Münzwurf-Ergebnisses
     * 
     * @param array $coinflipData Daten vom performCoinflipWithSideAssignment
     * @param string $player1Name Name von Spieler 1
     * @param string $player2Name Name von Spieler 2
     * @return string Beschreibung des Ergebnisses
     */
    public function generateResultDescription(array $coinflipData, string $player1Name, string $player2Name): string
    {
        $result = $coinflipData['coinflipResult'];
        $assignment = $coinflipData['sideAssignment'];
        $choice = $assignment['player1Choice'];
        $winner = $assignment['winner'];

        $resultText = $result === self::HEADS ? 'Kopf' : 'Zahl';
        $choiceText = $choice === self::HEADS ? 'Kopf' : 'Zahl';
        $winnerName = $winner === 1 ? $player1Name : $player2Name;
        
        $player1SideText = $assignment['player1Side'] === self::SIDE_BLUE ? 'blaue' : 'weiße';
        $player2SideText = $assignment['player2Side'] === self::SIDE_BLUE ? 'blaue' : 'weiße';

        return "{$player1Name} wählte {$choiceText}. " .
               "Die Münze zeigt {$resultText}. " .
               "{$winnerName} gewinnt den Münzwurf! " .
               "{$player1Name} spielt auf der {$player1SideText} Seite, " .
               "{$player2Name} auf der {$player2SideText} Seite.";
    }

    /**
     * Validiert Münzwurf-Daten
     * 
     * @param array $data Zu validierende Daten
     * @return bool True wenn valide
     * @throws \InvalidArgumentException Bei invaliden Daten
     */
    public function validateCoinflipData(array $data): bool
    {
        $requiredKeys = ['coinflipResult', 'sideAssignment', 'timestamp'];
        
        foreach ($requiredKeys as $key) {
            if (!isset($data[$key])) {
                throw new \InvalidArgumentException("Fehlender Schlüssel in Münzwurf-Daten: $key");
            }
        }

        if (!in_array($data['coinflipResult'], self::VALID_OUTCOMES)) {
            throw new \InvalidArgumentException("Ungültiges Münzwurf-Ergebnis: " . $data['coinflipResult']);
        }

        $assignment = $data['sideAssignment'];
        $requiredAssignmentKeys = ['player1Side', 'player2Side', 'winner', 'coinResult', 'player1Choice'];
        
        foreach ($requiredAssignmentKeys as $key) {
            if (!isset($assignment[$key])) {
                throw new \InvalidArgumentException("Fehlender Schlüssel in Seitenzuweisung: $key");
            }
        }

        return true;
    }

    /**
     * Führt einen Münzwurf durch und bestimmt nur den Gewinner (ohne Seitenzuweisung)
     * 
     * @param string $player1Choice Wahl von Spieler 1
     * @return array Münzwurf-Ergebnis mit Gewinner, aber ohne Seitenzuweisung
     */
    public function performCoinflipWithWinner(string $player1Choice): array
    {
        if (!in_array($player1Choice, self::VALID_OUTCOMES)) {
            throw new \InvalidArgumentException("Ungültige Wahl: $player1Choice. Erlaubt: " . implode(', ', self::VALID_OUTCOMES));
        }

        $coinResult = $this->flip();
        $player1Wins = ($player1Choice === $coinResult);
        
        return [
            'coinflipResult' => $coinResult,
            'player1Choice' => $player1Choice,
            'winner' => $player1Wins ? 1 : 2,
            'timestamp' => new \DateTimeImmutable()
        ];
    }

    /**
     * Erstellt Seitenzuweisung basierend auf Gewinner-Wahl
     * 
     * @param int $winner Gewinner des Münzwurfs (1 oder 2)
     * @param string $winnerSideChoice Seitenwahl des Gewinners ('blau' oder 'weiss')
     * @return array Seitenzuweisung für beide Spieler
     */
    public function assignSidesByWinnerChoice(int $winner, string $winnerSideChoice): array
    {
        if (!in_array($winner, [1, 2])) {
            throw new \InvalidArgumentException("Ungültiger Gewinner: $winner. Muss 1 oder 2 sein.");
        }

        if (!in_array($winnerSideChoice, [self::SIDE_BLUE, self::SIDE_WHITE])) {
            throw new \InvalidArgumentException("Ungültige Seitenwahl: $winnerSideChoice");
        }

        $loserSide = $winnerSideChoice === self::SIDE_BLUE ? self::SIDE_WHITE : self::SIDE_BLUE;

        if ($winner === 1) {
            return [
                'player1Side' => $winnerSideChoice,
                'player2Side' => $loserSide
            ];
        } else {
            return [
                'player1Side' => $loserSide,
                'player2Side' => $winnerSideChoice
            ];
        }
    }

    /**
     * Erstellt eine Beschreibung des Münzwurf-Ergebnisses ohne Seitenzuweisung
     * 
     * @param array $coinflipData Daten vom performCoinflipWithWinner
     * @param string $player1Name Name von Spieler 1
     * @param string $player2Name Name von Spieler 2
     * @return string Beschreibung des Ergebnisses
     */
    public function generateWinnerDescription(array $coinflipData, string $player1Name, string $player2Name): string
    {
        $result = $coinflipData['coinflipResult'];
        $choice = $coinflipData['player1Choice'];
        $winner = $coinflipData['winner'];

        $resultText = $result === self::HEADS ? 'Kopf' : 'Zahl';
        $choiceText = $choice === self::HEADS ? 'Kopf' : 'Zahl';
        $winnerName = $winner === 1 ? $player1Name : $player2Name;

        return "Münze zeigt {$resultText} - {$winnerName} hat den Münzwurf gewonnen! 🎉";
    }
} 