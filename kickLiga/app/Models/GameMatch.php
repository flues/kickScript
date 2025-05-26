<?php

declare(strict_types=1);

namespace App\Models;

use JsonSerializable;

class GameMatch implements JsonSerializable
{
    // Konstanten für gültige Seitenwerte
    public const SIDE_BLUE = 'blau';
    public const SIDE_WHITE = 'weiss';
    public const VALID_SIDES = [self::SIDE_BLUE, self::SIDE_WHITE];

    private string $id;
    private string $player1Id;
    private string $player2Id;
    private int $scorePlayer1;
    private int $scorePlayer2;
    private ?\DateTimeImmutable $playedAt;
    private array $eloChange = [
        'player1' => 0,
        'player2' => 0
    ];
    private ?string $notes = null;
    private string $player1Side = self::SIDE_BLUE;  // Default: Spieler 1 auf blauer Seite
    private string $player2Side = self::SIDE_WHITE; // Default: Spieler 2 auf weißer Seite
    private ?array $coinflipData = null; // Münzwurf-Daten falls verwendet

    public function __construct(
        string $player1Id,
        string $player2Id,
        int $scorePlayer1,
        int $scorePlayer2,
        ?\DateTimeImmutable $playedAt = null,
        ?string $notes = null,
        string $player1Side = self::SIDE_BLUE,
        string $player2Side = self::SIDE_WHITE,
        ?array $coinflipData = null
    ) {
        $this->id = uniqid('match_');
        $this->player1Id = $player1Id;
        $this->player2Id = $player2Id;
        $this->scorePlayer1 = $scorePlayer1;
        $this->scorePlayer2 = $scorePlayer2;
        $this->playedAt = $playedAt ?? new \DateTimeImmutable();
        $this->notes = $notes;
        $this->setPlayer1Side($player1Side);
        $this->setPlayer2Side($player2Side);
        $this->coinflipData = $coinflipData;
    }

    public static function fromArray(array $data): self
    {
        $playedAt = isset($data['playedAt']) && $data['playedAt'] !== null
            ? new \DateTimeImmutable('@' . $data['playedAt'])
            : null;
        
        $match = new self(
            $data['player1Id'],
            $data['player2Id'],
            $data['scorePlayer1'],
            $data['scorePlayer2'],
            $playedAt,
            $data['notes'] ?? null,
            $data['player1Side'] ?? self::SIDE_BLUE,
            $data['player2Side'] ?? self::SIDE_WHITE,
            $data['coinflipData'] ?? null
        );
        
        if (isset($data['id'])) {
            $match->id = $data['id'];
        }
        
        if (isset($data['eloChange'])) {
            $match->eloChange = $data['eloChange'];
        }
        
        return $match;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPlayer1Id(): string
    {
        return $this->player1Id;
    }

    public function getPlayer2Id(): string
    {
        return $this->player2Id;
    }

    public function getScorePlayer1(): int
    {
        return $this->scorePlayer1;
    }

    public function getScorePlayer2(): int
    {
        return $this->scorePlayer2;
    }

    public function getPlayedAt(): ?\DateTimeImmutable
    {
        return $this->playedAt;
    }

    public function getEloChange(): array
    {
        return $this->eloChange;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getPlayer1Side(): string
    {
        return $this->player1Side;
    }

    public function setPlayer1Side(string $side): self
    {
        if (!in_array($side, self::VALID_SIDES)) {
            throw new \InvalidArgumentException("Ungültige Seite: $side. Erlaubt: " . implode(', ', self::VALID_SIDES));
        }
        $this->player1Side = $side;
        return $this;
    }

    public function getPlayer2Side(): string
    {
        return $this->player2Side;
    }

    public function setPlayer2Side(string $side): self
    {
        if (!in_array($side, self::VALID_SIDES)) {
            throw new \InvalidArgumentException("Ungültige Seite: $side. Erlaubt: " . implode(', ', self::VALID_SIDES));
        }
        $this->player2Side = $side;
        return $this;
    }

    public function getPlayerSide(string $playerId): ?string
    {
        if ($this->player1Id === $playerId) {
            return $this->player1Side;
        } elseif ($this->player2Id === $playerId) {
            return $this->player2Side;
        }
        return null;
    }

    public function getOpponentSide(string $playerId): ?string
    {
        if ($this->player1Id === $playerId) {
            return $this->player2Side;
        } elseif ($this->player2Id === $playerId) {
            return $this->player1Side;
        }
        return null;
    }

    public function hasValidSideAssignment(): bool
    {
        return $this->player1Side !== $this->player2Side;
    }

    public function setSides(string $player1Side, string $player2Side): self
    {
        $this->setPlayer1Side($player1Side);
        $this->setPlayer2Side($player2Side);
        
        if (!$this->hasValidSideAssignment()) {
            throw new \InvalidArgumentException("Beide Spieler können nicht auf derselben Seite spielen.");
        }
        
        return $this;
    }

    public function setEloChanges(int $player1EloChange, int $player2EloChange): self
    {
        $this->eloChange['player1'] = $player1EloChange;
        $this->eloChange['player2'] = $player2EloChange;
        return $this;
    }

    public function isPlayer1Winner(): bool
    {
        return $this->scorePlayer1 > $this->scorePlayer2;
    }

    public function isPlayer2Winner(): bool
    {
        return $this->scorePlayer2 > $this->scorePlayer1;
    }

    public function isDraw(): bool
    {
        return $this->scorePlayer1 === $this->scorePlayer2;
    }

    public function getWinnerId(): ?string
    {
        if ($this->isPlayer1Winner()) {
            return $this->player1Id;
        } elseif ($this->isPlayer2Winner()) {
            return $this->player2Id;
        }
        return null;
    }

    public function getLoserId(): ?string
    {
        if ($this->isPlayer1Winner()) {
            return $this->player2Id;
        } elseif ($this->isPlayer2Winner()) {
            return $this->player1Id;
        }
        return null;
    }

    public function getWinningSide(): ?string
    {
        $winnerId = $this->getWinnerId();
        return $winnerId ? $this->getPlayerSide($winnerId) : null;
    }

    public function getLosingSide(): ?string
    {
        $loserId = $this->getLoserId();
        return $loserId ? $this->getPlayerSide($loserId) : null;
    }

    public function hasPlayer(string $playerId): bool
    {
        return $this->player1Id === $playerId || $this->player2Id === $playerId;
    }

    public function getGoalDifference(): int
    {
        return $this->scorePlayer1 - $this->scorePlayer2;
    }

    public function getAbsoluteGoalDifference(): int
    {
        return abs($this->getGoalDifference());
    }

    public function getTotalGoals(): int
    {
        return $this->scorePlayer1 + $this->scorePlayer2;
    }

    public function getPointsForPlayer(string $playerId): int
    {
        if ($this->isDraw()) {
            return 1;
        }
        if ($this->getWinnerId() === $playerId) {
            return 3;
        }
        return 0;
    }

    /**
     * Gibt die Coinflip-Daten zurück
     */
    public function getCoinflipData(): ?array
    {
        return $this->coinflipData;
    }

    /**
     * Setzt die Coinflip-Daten
     */
    public function setCoinflipData(?array $coinflipData): self
    {
        $this->coinflipData = $coinflipData;
        return $this;
    }

    /**
     * Überprüft, ob die Seitenzuweisung durch einen Münzwurf erfolgte
     */
    public function hasCoinflipData(): bool
    {
        return $this->coinflipData !== null;
    }

    /**
     * Gibt das Ergebnis des Münzwurfs zurück
     */
    public function getCoinflipResult(): ?string
    {
        return $this->coinflipData['coinflipResult'] ?? null;
    }

    /**
     * Gibt die Wahl von Spieler 1 beim Münzwurf zurück
     */
    public function getPlayer1CoinChoice(): ?string
    {
        return $this->coinflipData['sideAssignment']['player1Choice'] ?? null;
    }

    /**
     * Gibt zurück, wer den Münzwurf gewonnen hat (1 oder 2)
     */
    public function getCoinflipWinner(): ?int
    {
        return $this->coinflipData['sideAssignment']['winner'] ?? null;
    }

    /**
     * Erstellt eine lesbare Beschreibung des Münzwurfs
     */
    public function getCoinflipDescription(): ?string
    {
        if (!$this->hasCoinflipData()) {
            return null;
        }

        $result = $this->getCoinflipResult();
        $choice = $this->getPlayer1CoinChoice();
        $winner = $this->getCoinflipWinner();

        $resultText = $result === 'kopf' ? 'Kopf' : 'Zahl';
        $choiceText = $choice === 'kopf' ? 'Kopf' : 'Zahl';
        $winnerText = $winner === 1 ? 'Spieler 1' : 'Spieler 2';

        return "Spieler 1 wählte {$choiceText}. Die Münze zeigt {$resultText}. {$winnerText} gewinnt den Münzwurf!";
    }
    
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'player1Id' => $this->player1Id,
            'player2Id' => $this->player2Id,
            'scorePlayer1' => $this->scorePlayer1,
            'scorePlayer2' => $this->scorePlayer2,
            'playedAt' => $this->playedAt ? $this->playedAt->getTimestamp() : null,
            'eloChange' => $this->eloChange,
            'notes' => $this->notes,
            'player1Side' => $this->player1Side,
            'player2Side' => $this->player2Side,
            'coinflipData' => $this->coinflipData,
        ];
    }
} 