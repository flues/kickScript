<?php

declare(strict_types=1);

namespace App\Models;

use JsonSerializable;

class Player implements JsonSerializable
{
    private ?string $id = null;
    private string $name;
    private ?string $nickname = null;
    private ?string $avatar = null;
    private int $eloRating = 1000;
    private array $statistics = [
        'wins' => 0,
        'losses' => 0,
        'draws' => 0,
        'goalsScored' => 0,
        'goalsConceded' => 0,
        'tournamentsWon' => 0,
        'tournamentsParticipated' => 0,
        'matchesPlayed' => 0
    ];
    private array $achievements = [];
    private array $eloHistory = [];
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $lastMatch = null;

    /**
     * Player Konstruktor
     *
     * @param string $name Name des Spielers
     * @param string|null $nickname Spitzname (optional)
     * @param string|null $avatar Avatar-Bild (optional)
     */
    public function __construct(string $name, ?string $nickname = null, ?string $avatar = null)
    {
        $this->id = uniqid('player_');
        $this->name = $name;
        $this->nickname = $nickname;
        $this->avatar = $avatar;
        $this->createdAt = new \DateTimeImmutable();
        
        // Initialen ELO-Wert zur Historie hinzufügen
        $this->eloHistory[] = [
            'rating' => $this->eloRating,
            'timestamp' => $this->createdAt->getTimestamp(),
            'reason' => 'initial'
        ];
    }

    /**
     * Erstellt ein Player-Objekt aus einem Array
     *
     * @param array $data Spielerdaten
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $player = new self(
            $data['name'],
            $data['nickname'] ?? null,
            $data['avatar'] ?? null
        );
        
        if (isset($data['id'])) {
            $player->id = $data['id'];
        }
        
        if (isset($data['eloRating'])) {
            $player->eloRating = $data['eloRating'];
        }
        
        if (isset($data['statistics'])) {
            $player->statistics = array_merge($player->statistics, $data['statistics']);
        }
        
        if (isset($data['achievements'])) {
            $player->achievements = $data['achievements'];
        }
        
        if (isset($data['eloHistory'])) {
            $player->eloHistory = $data['eloHistory'];
        }
        
        if (isset($data['createdAt'])) {
            // Behandle verschiedene Datentypen für createdAt
            if (is_int($data['createdAt'])) {
                $player->createdAt = new \DateTimeImmutable('@' . $data['createdAt']);
            } elseif (is_string($data['createdAt'])) {
                $player->createdAt = new \DateTimeImmutable($data['createdAt']);
            } elseif (is_array($data['createdAt']) && isset($data['createdAt']['timestamp'])) {
                $player->createdAt = new \DateTimeImmutable('@' . $data['createdAt']['timestamp']);
            } else {
                // Fallback: Aktueller Zeitstempel
                $player->createdAt = new \DateTimeImmutable();
            }
        }
        
        if (isset($data['lastMatch']) && $data['lastMatch'] !== null) {
            // Behandle verschiedene Datentypen für lastMatch
            if (is_int($data['lastMatch'])) {
                $player->lastMatch = new \DateTimeImmutable('@' . $data['lastMatch']);
            } elseif (is_string($data['lastMatch'])) {
                $player->lastMatch = new \DateTimeImmutable($data['lastMatch']);
            } elseif (is_array($data['lastMatch']) && isset($data['lastMatch']['timestamp'])) {
                $player->lastMatch = new \DateTimeImmutable('@' . $data['lastMatch']['timestamp']);
            }
            // Wenn lastMatch null ist oder ungültiges Format hat, bleibt es null
        }
        
        return $player;
    }

    /**
     * Aktualisiert die Spielstatistiken nach einem Match
     *
     * @param bool $isWin War das Match ein Sieg?
     * @param bool $isDraw War das Match ein Unentschieden?
     * @param int $goalsScored Anzahl geschossener Tore
     * @param int $goalsConceded Anzahl kassierter Tore
     * @return self
     */
    public function updateMatchStatistics(bool $isWin, bool $isDraw, int $goalsScored, int $goalsConceded): self
    {
        $this->statistics['matchesPlayed']++;
        $this->statistics['goalsScored'] += $goalsScored;
        $this->statistics['goalsConceded'] += $goalsConceded;
        
        if ($isWin) {
            $this->statistics['wins']++;
        } elseif ($isDraw) {
            $this->statistics['draws']++;
        } else {
            $this->statistics['losses']++;
        }
        
        $this->lastMatch = new \DateTimeImmutable();
        
        return $this;
    }

    /**
     * Aktualisiert den ELO-Rating eines Spielers
     *
     * @param int $newRating Der neue ELO-Wert
     * @param string $reason Grund für die Änderung
     * @return self
     */
    public function updateElo(int $newRating, string $reason): self
    {
        $oldRating = $this->eloRating;
        $this->eloRating = $newRating;
        
        $this->eloHistory[] = [
            'rating' => $newRating,
            'change' => $newRating - $oldRating,
            'timestamp' => time(),
            'reason' => $reason
        ];
        
        return $this;
    }

    /**
     * Fügt ein Achievement zum Spieler hinzu
     *
     * @param string $achievementId ID des Achievements
     * @param string $achievementName Name des Achievements
     * @param string $description Beschreibung des Achievements
     * @return self
     */
    public function addAchievement(string $achievementId, string $achievementName, string $description): self
    {
        // Prüfe, ob das Achievement bereits vorhanden ist
        foreach ($this->achievements as $achievement) {
            if ($achievement['id'] === $achievementId) {
                return $this; // Achievement bereits erhalten
            }
        }
        
        $this->achievements[] = [
            'id' => $achievementId,
            'name' => $achievementName,
            'description' => $description,
            'unlockedAt' => time()
        ];
        
        return $this;
    }

    /**
     * Berechnet Win-Rate des Spielers
     *
     * @return float
     */
    public function getWinRate(): float
    {
        if ($this->statistics['matchesPlayed'] === 0) {
            return 0.0;
        }
        
        return round(($this->statistics['wins'] / $this->statistics['matchesPlayed']) * 100, 1);
    }

    /**
     * Gibt Tordifferenz des Spielers zurück
     *
     * @return int
     */
    public function getGoalDifference(): int
    {
        return $this->statistics['goalsScored'] - $this->statistics['goalsConceded'];
    }

    // Getter und Setter

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function setNickname(?string $nickname): self
    {
        $this->nickname = $nickname;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getEloRating(): int
    {
        return $this->eloRating;
    }

    public function getStatistics(): array
    {
        return $this->statistics;
    }

    public function getAchievements(): array
    {
        return $this->achievements;
    }

    public function getEloHistory(): array
    {
        return $this->eloHistory;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastMatch(): ?\DateTimeImmutable
    {
        return $this->lastMatch;
    }

    public function getDisplayName(): string
    {
        return $this->nickname ? "{$this->name} \"{$this->nickname}\"" : $this->name;
    }

    /**
     * Spezifiziert, wie das Objekt serialisiert werden soll
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'avatar' => $this->avatar,
            'eloRating' => $this->eloRating,
            'statistics' => $this->statistics,
            'achievements' => $this->achievements,
            'eloHistory' => $this->eloHistory,
            'createdAt' => $this->createdAt->getTimestamp(),
            'lastMatch' => $this->lastMatch ? $this->lastMatch->getTimestamp() : null
        ];
    }
} 