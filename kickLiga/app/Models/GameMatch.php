<?php

declare(strict_types=1);

namespace App\Models;

use JsonSerializable;

class GameMatch implements JsonSerializable
{
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

    public function __construct(
        string $player1Id,
        string $player2Id,
        int $scorePlayer1,
        int $scorePlayer2,
        ?\DateTimeImmutable $playedAt = null,
        ?string $notes = null
    ) {
        $this->id = uniqid('match_');
        $this->player1Id = $player1Id;
        $this->player2Id = $player2Id;
        $this->scorePlayer1 = $scorePlayer1;
        $this->scorePlayer2 = $scorePlayer2;
        $this->playedAt = $playedAt ?? new \DateTimeImmutable();
        $this->notes = $notes;
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
            $data['notes'] ?? null
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
        ];
    }
} 