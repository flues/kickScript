<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Player;
use Psr\Log\LoggerInterface;
use RuntimeException;

class MatchService
{
    private const MATCHES_FILE = 'matches';

    private DataService $dataService;
    private PlayerService $playerService;
    private EloService $eloService;
    private ?LoggerInterface $logger;

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
     * @param string $player1Side Seite des ersten Spielers (blau/weiss)
     * @param string $player2Side Seite des zweiten Spielers (blau/weiss)
     * @return GameMatch Das erstellte Match
     * @throws RuntimeException Wenn ein Spieler nicht gefunden wird oder das Speichern fehlschlägt
     */
    public function createMatch(
        string $player1Id,
        string $player2Id,
        int $scorePlayer1,
        int $scorePlayer2,
        ?\DateTimeImmutable $playedAt = null,
        ?string $notes = null,
        string $player1Side = GameMatch::SIDE_BLUE,
        string $player2Side = GameMatch::SIDE_WHITE
    ): GameMatch {
        // Validiere die Seitenwahl
        $this->validateSides($player1Side, $player2Side);
        
        // Hole die Spieler
        $player1 = $this->playerService->getPlayerById($player1Id);
        $player2 = $this->playerService->getPlayerById($player2Id);
        
        if (!$player1 || !$player2) {
            throw new RuntimeException('Ein oder beide Spieler wurden nicht gefunden.');
        }
        
        // Erstelle das Match mit Seitenwahl
        $match = new GameMatch($player1Id, $player2Id, $scorePlayer1, $scorePlayer2, $playedAt, $notes, $player1Side, $player2Side);
        
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
            $this->logger->info("Match erstellt: {$player1->getName()} vs {$player2->getName()} ({$scorePlayer1}:{$scorePlayer2}) - Seiten: {$player1Side} vs {$player2Side}");
        }
        
        return $match;
    }

    /**
     * Validiert die Seitenwahl
     *
     * @param string $player1Side Seite des ersten Spielers
     * @param string $player2Side Seite des zweiten Spielers
     * @throws RuntimeException Bei ungültiger Seitenwahl
     */
    public function validateSides(string $player1Side, string $player2Side): void
    {
        // Prüfe, ob die Seiten gültig sind
        if (!in_array($player1Side, GameMatch::VALID_SIDES)) {
            throw new RuntimeException("Ungültige Seite für Spieler 1: {$player1Side}. Erlaubt: " . implode(', ', GameMatch::VALID_SIDES));
        }
        
        if (!in_array($player2Side, GameMatch::VALID_SIDES)) {
            throw new RuntimeException("Ungültige Seite für Spieler 2: {$player2Side}. Erlaubt: " . implode(', ', GameMatch::VALID_SIDES));
        }
        
        // Prüfe, ob beide Spieler verschiedene Seiten haben
        if ($player1Side === $player2Side) {
            throw new RuntimeException("Beide Spieler können nicht auf derselben Seite spielen. Spieler 1: {$player1Side}, Spieler 2: {$player2Side}");
        }
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
     * Gibt alle Matches eines Spielers auf einer bestimmten Seite zurück
     *
     * @param string $playerId Die Spieler-ID
     * @param string $side Die Seite (blau/weiss)
     * @return GameMatch[] Array mit Matches auf der angegebenen Seite
     */
    public function getMatchesByPlayerIdAndSide(string $playerId, string $side): array
    {
        $allMatches = $this->getMatchesByPlayerId($playerId);
        $sideMatches = [];
        
        foreach ($allMatches as $match) {
            if ($match->getPlayerSide($playerId) === $side) {
                $sideMatches[] = $match;
            }
        }
        
        return $sideMatches;
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
     * Erstellt automatisch Seitendaten für bestehende Matches ohne Seitenwahl
     *
     * @param bool $randomAssignment Zufällige Zuweisung vs. Standard (Spieler1=blau, Spieler2=weiß)
     * @return int Anzahl der aktualisierten Matches
     */
    public function migrateLegacyMatches(bool $randomAssignment = false): int
    {
        $matches = $this->getAllMatchesArray();
        $updatedCount = 0;
        
        foreach ($matches as $matchId => $matchData) {
            // Prüfe, ob Seitendaten fehlen
            if (!isset($matchData['player1Side']) || !isset($matchData['player2Side'])) {
                if ($randomAssignment) {
                    // Zufällige Zuweisung
                    $player1Side = rand(0, 1) ? GameMatch::SIDE_BLUE : GameMatch::SIDE_WHITE;
                    $player2Side = $player1Side === GameMatch::SIDE_BLUE ? GameMatch::SIDE_WHITE : GameMatch::SIDE_BLUE;
                } else {
                    // Standard-Zuweisung
                    $player1Side = GameMatch::SIDE_BLUE;
                    $player2Side = GameMatch::SIDE_WHITE;
                }
                
                $matches[$matchId]['player1Side'] = $player1Side;
                $matches[$matchId]['player2Side'] = $player2Side;
                $updatedCount++;
            }
        }
        
        if ($updatedCount > 0) {
            $this->dataService->write(self::MATCHES_FILE, $matches);
            
            if ($this->logger) {
                $this->logger->info("Legacy-Migration: {$updatedCount} Matches mit Seitendaten ergänzt");
            }
        }
        
        return $updatedCount;
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
     * Gibt alle Matches als Array zurück (interne Verwendung)
     *
     * @return array Array mit Match-Daten
     */
    private function getAllMatchesArray(): array
    {
        return $this->dataService->read(self::MATCHES_FILE) ?: [];
    }
} 