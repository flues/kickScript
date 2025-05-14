<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Player;
use Psr\Log\LoggerInterface;
use RuntimeException;

class MatchService
{
    private DataService $dataService;
    private PlayerService $playerService;
    private EloService $eloService;
    private ?LoggerInterface $logger;
    private const MATCHES_FILE = 'matches';

    /**
     * MatchService Konstruktor
     *
     * @param DataService $dataService DataService-Instanz
     * @param PlayerService $playerService PlayerService-Instanz
     * @param EloService $eloService EloService-Instanz
     * @param LoggerInterface|null $logger Logger-Instanz
     */
    public function __construct(
        DataService $dataService,
        PlayerService $playerService,
        EloService $eloService,
        ?LoggerInterface $logger = null
    ) {
        $this->dataService = $dataService;
        $this->playerService = $playerService;
        $this->eloService = $eloService;
        $this->logger = $logger;
    }

    /**
     * Erstellt ein neues Match und aktualisiert die Spieler
     *
     * @param string $player1Id ID des ersten Spielers
     * @param string $player2Id ID des zweiten Spielers
     * @param int $scorePlayer1 Tore des ersten Spielers
     * @param int $scorePlayer2 Tore des zweiten Spielers
     * @param ?\DateTimeImmutable $playedAt Zeitpunkt des Spiels (optional)
     * @param string|null $notes Notizen zum Spiel (optional)
     * @return GameMatch Das erstellte Match
     * @throws RuntimeException Wenn ein Spieler nicht gefunden wird oder das Speichern fehlschlägt
     */
    public function createMatch(
        string $player1Id,
        string $player2Id,
        int $scorePlayer1,
        int $scorePlayer2,
        ?\DateTimeImmutable $playedAt = null,
        ?string $notes = null
    ): GameMatch {
        // Hole die Spieler
        $player1 = $this->playerService->getPlayerById($player1Id);
        $player2 = $this->playerService->getPlayerById($player2Id);
        
        if (!$player1 || !$player2) {
            throw new RuntimeException('Ein oder beide Spieler wurden nicht gefunden.');
        }
        
        // Erstelle das Match
        $match = new GameMatch($player1Id, $player2Id, $scorePlayer1, $scorePlayer2, $playedAt, $notes);
        
        // Berechne die ELO-Änderungen
        $match = $this->eloService->processMatchRatings($match, $player1, $player2);
        
        // Aktualisiere die Spielerstatistiken
        $this->updatePlayerStatistics($player1, $player2, $match);
        
        // Speichere das Match
        $success = $this->saveMatch($match);
        
        if (!$success) {
            throw new RuntimeException('Das Match konnte nicht gespeichert werden.');
        }
        
        // Speichere die aktualisierten Spieler
        $this->playerService->savePlayer($player1);
        $this->playerService->savePlayer($player2);
        
        if ($this->logger) {
            $this->logger->info("Match erstellt: {$player1->getName()} vs {$player2->getName()} ({$scorePlayer1}:{$scorePlayer2})");
        }
        
        return $match;
    }

    /**
     * Speichert ein Match
     *
     * @param GameMatch $match Das zu speichernde Match
     * @return bool True bei Erfolg
     */
    public function saveMatch(GameMatch $match): bool
    {
        $matches = $this->getAllMatchesArray();
        
        // Match-ID extrahieren
        $matchId = $match->getId();
        
        // Match speichern/aktualisieren
        $matches[$matchId] = $match->jsonSerialize();
        
        $success = $this->dataService->write(self::MATCHES_FILE, $matches);
        
        if ($success && $this->logger) {
            $this->logger->info("Match {$matchId} gespeichert");
        }
        
        return $success;
    }

    /**
     * Holt ein Match anhand der ID
     *
     * @param string $matchId Die Match-ID
     * @return GameMatch|null Das Match-Objekt oder null, wenn nicht gefunden
     */
    public function getMatchById(string $matchId): ?GameMatch
    {
        $matches = $this->getAllMatchesArray();
        
        if (!isset($matches[$matchId])) {
            if ($this->logger) {
                $this->logger->info("Match mit ID {$matchId} nicht gefunden");
            }
            return null;
        }
        
        return GameMatch::fromArray($matches[$matchId]);
    }

    /**
     * Gibt alle Matches zurück
     *
     * @return GameMatch[] Array mit allen Matches
     */
    public function getAllMatches(): array
    {
        $matchesData = $this->getAllMatchesArray();
        $matches = [];
        
        foreach ($matchesData as $matchData) {
            $matches[] = GameMatch::fromArray($matchData);
        }
        
        return $matches;
    }

    /**
     * Gibt alle Matches eines Spielers zurück
     *
     * @param string $playerId Die Spieler-ID
     * @return GameMatch[] Array mit Matches
     */
    public function getMatchesByPlayerId(string $playerId): array
    {
        $allMatches = $this->getAllMatches();
        $playerMatches = [];
        
        foreach ($allMatches as $match) {
            if ($match->hasPlayer($playerId)) {
                $playerMatches[] = $match;
            }
        }
        
        // Sortiere nach Datum (neueste zuerst)
        usort($playerMatches, function (GameMatch $a, GameMatch $b) {
            return $b->getPlayedAt()->getTimestamp() - $a->getPlayedAt()->getTimestamp();
        });
        
        return $playerMatches;
    }

    /**
     * Gibt die letzten N Matches zurück
     *
     * @param int $limit Anzahl der zurückzugebenden Matches
     * @return GameMatch[] Array mit den letzten Matches
     */
    public function getRecentMatches(int $limit = 10): array
    {
        $matches = $this->getAllMatches();
        
        // Sortiere nach Datum (neueste zuerst)
        usort($matches, function (GameMatch $a, GameMatch $b) {
            return $b->getPlayedAt()->getTimestamp() - $a->getPlayedAt()->getTimestamp();
        });
        
        return array_slice($matches, 0, $limit);
    }

    /**
     * Löscht ein Match
     *
     * @param string $matchId Die Match-ID
     * @return bool True bei Erfolg, False wenn Match nicht gefunden
     */
    public function deleteMatch(string $matchId): bool
    {
        $matches = $this->getAllMatchesArray();
        
        if (!isset($matches[$matchId])) {
            if ($this->logger) {
                $this->logger->info("Zu löschendes Match mit ID {$matchId} nicht gefunden");
            }
            return false;
        }
        
        unset($matches[$matchId]);
        
        $success = $this->dataService->write(self::MATCHES_FILE, $matches);
        
        if ($success && $this->logger) {
            $this->logger->info("Match {$matchId} gelöscht");
        }
        
        return $success;
    }

    /**
     * Aktualisiert die Spielerstatistiken basierend auf dem Match
     *
     * @param Player $player1 Spieler 1
     * @param Player $player2 Spieler 2
     * @param GameMatch $match Das Match
     */
    private function updatePlayerStatistics(Player $player1, Player $player2, GameMatch $match): void
    {
        // Spieler 1 aktualisieren
        $player1->updateMatchStatistics(
            $match->isPlayer1Winner(),
            $match->isDraw(),
            $match->getScorePlayer1(),
            $match->getScorePlayer2()
        );
        
        // Spieler 2 aktualisieren
        $player2->updateMatchStatistics(
            $match->isPlayer2Winner(),
            $match->isDraw(),
            $match->getScorePlayer2(),
            $match->getScorePlayer1()
        );
        
        if ($this->logger) {
            $this->logger->info("Spielerstatistiken aktualisiert für: {$player1->getName()} und {$player2->getName()}");
        }
    }

    /**
     * Holt alle Matches als assoziatives Array (Hilfsmethode)
     *
     * @return array Match-Daten
     */
    private function getAllMatchesArray(): array
    {
        return $this->dataService->read(self::MATCHES_FILE) ?: [];
    }
} 