<?php

declare(strict_types=1);

namespace App\Models;

use JsonSerializable;

class Season implements JsonSerializable
{
    private string $id;
    private string $name;
    private \DateTimeImmutable $startDate;
    private ?\DateTimeImmutable $endDate = null;
    private bool $isActive = true;
    private array $standings = [];
    private array $statistics = [
        'totalMatches' => 0,
        'totalGoals' => 0,
        'highestScore' => null,
        'longestWinStreak' => null
    ];

    /**
     * Konstruktor
     *
     * @param string $name Name der Saison
     * @param \DateTimeImmutable|null $startDate Startdatum der Saison
     */
    public function __construct(string $name, ?\DateTimeImmutable $startDate = null)
    {
        $this->id = 'season_' . uniqid();
        $this->name = $name;
        $this->startDate = $startDate ?? new \DateTimeImmutable();
    }

    /**
     * Erstellt ein Season-Objekt aus einem Array
     *
     * @param array $data Saisondaten
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $startDate = isset($data['startDate']) 
            ? new \DateTimeImmutable('@' . $data['startDate']) 
            : null;
            
        $season = new self($data['name'], $startDate);
        
        if (isset($data['id'])) {
            $season->id = $data['id'];
        }
        
        if (isset($data['endDate']) && $data['endDate'] !== null) {
            $season->endDate = new \DateTimeImmutable('@' . $data['endDate']);
        }
        
        if (isset($data['isActive'])) {
            $season->isActive = $data['isActive'];
        }
        
        if (isset($data['standings'])) {
            $season->standings = $data['standings'];
        }
        
        if (isset($data['statistics'])) {
            $season->statistics = array_merge($season->statistics, $data['statistics']);
        }
        
        return $season;
    }

    /**
     * Initialisiert die Tabelle mit den gegebenen Spielern
     *
     * @param array $players Liste der Spieler
     * @return self
     */
    public function initializeStandings(array $players): self
    {
        $this->standings = [];
        
        foreach ($players as $player) {
            $playerId = $player->getId();
            $this->standings[$playerId] = [
                'playerId' => $playerId,
                'name' => $player->getName(),
                'displayName' => $player->getDisplayName(),
                'avatar' => $player->getAvatar(),
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
        
        return $this;
    }

    /**
     * Aktualisiert die Tabelle basierend auf einem Match
     *
     * @param GameMatch $match Das zu berücksichtigende Match
     * @return self
     */
    public function updateStandings(GameMatch $match): self
    {
        $player1Id = $match->getPlayer1Id();
        $player2Id = $match->getPlayer2Id();
        
        // Prüfen, ob beide Spieler in der Tabelle vorhanden sind
        if (!isset($this->standings[$player1Id]) || !isset($this->standings[$player2Id])) {
            return $this;
        }
        
        // Spielstatistiken aktualisieren
        $this->statistics['totalMatches']++;
        $this->statistics['totalGoals'] += $match->getTotalGoals();
        
        // Höchster Sieg überprüfen
        $goalDifference = $match->getAbsoluteGoalDifference();
        if (!isset($this->statistics['highestScore']) || 
            $goalDifference > $this->statistics['highestScore']['goalDifference']) {
            $this->statistics['highestScore'] = [
                'matchId' => $match->getId(),
                'winnerId' => $match->getWinnerId(),
                'loserId' => $match->getLoserId(),
                'score' => $match->getScorePlayer1() . '-' . $match->getScorePlayer2(),
                'goalDifference' => $goalDifference,
                'date' => $match->getPlayedAt()->getTimestamp()
            ];
        }
        
        // Spieler 1 Statistiken aktualisieren
        $this->standings[$player1Id]['matches']++;
        $this->standings[$player1Id]['goalsScored'] += $match->getScorePlayer1();
        $this->standings[$player1Id]['goalsConceded'] += $match->getScorePlayer2();
        $this->standings[$player1Id]['goalDifference'] = 
            $this->standings[$player1Id]['goalsScored'] - $this->standings[$player1Id]['goalsConceded'];
        
        // Spieler 2 Statistiken aktualisieren
        $this->standings[$player2Id]['matches']++;
        $this->standings[$player2Id]['goalsScored'] += $match->getScorePlayer2();
        $this->standings[$player2Id]['goalsConceded'] += $match->getScorePlayer1();
        $this->standings[$player2Id]['goalDifference'] = 
            $this->standings[$player2Id]['goalsScored'] - $this->standings[$player2Id]['goalsConceded'];
        
        // Ausgang des Spiels auswerten
        if ($match->isDraw()) {
            // Unentschieden
            $this->standings[$player1Id]['draws']++;
            $this->standings[$player2Id]['draws']++;
            $this->standings[$player1Id]['points'] += 1;
            $this->standings[$player2Id]['points'] += 1;
        } elseif ($match->isPlayer1Winner()) {
            // Spieler 1 gewinnt
            $this->standings[$player1Id]['wins']++;
            $this->standings[$player2Id]['losses']++;
            $this->standings[$player1Id]['points'] += 3;
        } else {
            // Spieler 2 gewinnt
            $this->standings[$player2Id]['wins']++;
            $this->standings[$player1Id]['losses']++;
            $this->standings[$player2Id]['points'] += 3;
        }
        
        return $this;
    }

    /**
     * Beendet die Saison
     *
     * @param \DateTimeImmutable|null $endDate End date of the season
     * @return self
     */
    public function endSeason(?\DateTimeImmutable $endDate = null): self
    {
        $this->endDate = $endDate ?? new \DateTimeImmutable();
        $this->isActive = false;
        return $this;
    }

    /**
     * Gibt die sortierte Tabelle zurück
     *
     * @return array Die sortierte Tabelle
     */
    public function getSortedStandings(): array
    {
        $standings = array_values($this->standings);
        
        // Sortiere nach Punkten (absteigend), dann Tordifferenz (absteigend), dann geschossene Tore (absteigend)
        usort($standings, function ($a, $b) {
            // Primär nach Punkten sortieren
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }
            
            // Sekundär nach Tordifferenz sortieren
            if ($a['goalDifference'] !== $b['goalDifference']) {
                return $b['goalDifference'] <=> $a['goalDifference'];
            }
            
            // Tertiär nach geschossenen Toren sortieren
            if ($a['goalsScored'] !== $b['goalsScored']) {
                return $b['goalsScored'] <=> $a['goalsScored'];
            }
            
            // Quaternär nach weniger Spielen sortieren (bessere Effizienz)
            if ($a['matches'] !== $b['matches']) {
                return $a['matches'] <=> $b['matches'];
            }
            
            // Letztendlich alphabetisch nach Namen sortieren
            return strcmp($a['name'], $b['name']);
        });
        
        // Füge Rankingposition hinzu
        $rank = 1;
        $lastPoints = null;
        $lastGoalDiff = null;
        $lastGoalsScored = null;
        $sameRankCount = 0;
        
        foreach ($standings as &$player) {
            if ($lastPoints !== null && $lastGoalDiff !== null && $lastGoalsScored !== null) {
                if ($player['points'] === $lastPoints && 
                    $player['goalDifference'] === $lastGoalDiff && 
                    $player['goalsScored'] === $lastGoalsScored) {
                    // Gleicher Rang bei identischen Werten
                    $sameRankCount++;
                } else {
                    $rank += $sameRankCount;
                    $sameRankCount = 0;
                }
            }
            
            $player['rank'] = $rank;
            $lastPoints = $player['points'];
            $lastGoalDiff = $player['goalDifference'];
            $lastGoalsScored = $player['goalsScored'];
        }
        
        return $standings;
    }

    // Getter-Methoden

    public function getId(): string
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

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getStandings(): array
    {
        return $this->standings;
    }

    /**
     * Setzt die Tabelle der Saison
     *
     * @param array $standings Die Tabelle
     * @return self
     */
    public function setStandings(array $standings): self
    {
        $this->standings = $standings;
        return $this;
    }

    public function getStatistics(): array
    {
        return $this->statistics;
    }

    /**
     * Gibt die Spielzeit der Saison in Tagen zurück
     *
     * @return int Anzahl der Tage
     */
    public function getDurationInDays(): int
    {
        if (!$this->endDate) {
            $now = new \DateTimeImmutable();
            return (int)$now->diff($this->startDate)->days;
        }
        
        return (int)$this->endDate->diff($this->startDate)->days;
    }

    /**
     * Spezifiziert, wie das Objekt serialisiert werden soll
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'startDate' => $this->startDate->getTimestamp(),
            'endDate' => $this->endDate ? $this->endDate->getTimestamp() : null,
            'isActive' => $this->isActive,
            'standings' => $this->standings,
            'statistics' => $this->statistics
        ];
    }
} 