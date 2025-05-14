<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Player;
use Psr\Log\LoggerInterface;
use RuntimeException;

class PlayerService
{
    private DataService $dataService;
    private ?LoggerInterface $logger;
    private const PLAYERS_FILE = 'players';

    /**
     * PlayerService Konstruktor
     *
     * @param DataService $dataService DataService-Instanz
     * @param LoggerInterface|null $logger Logger-Instanz
     */
    public function __construct(DataService $dataService, ?LoggerInterface $logger = null)
    {
        $this->dataService = $dataService;
        $this->logger = $logger;
    }

    /**
     * Speichert einen Spieler
     *
     * @param Player $player Spieler-Objekt
     * @return bool True bei Erfolg
     * @throws RuntimeException Wenn das Speichern fehlschlägt
     */
    public function savePlayer(Player $player): bool
    {
        $players = $this->getAllPlayersArray();
        
        // Spieler-ID extrahieren
        $playerId = $player->getId();
        if (!$playerId) {
            throw new RuntimeException('Spieler hat keine ID');
        }
        
        // Spieler speichern/aktualisieren
        $players[$playerId] = $player->jsonSerialize();
        
        $success = $this->dataService->write(self::PLAYERS_FILE, $players);
        
        if ($success && $this->logger) {
            $this->logger->info("Spieler {$player->getName()} (ID: {$playerId}) gespeichert");
        }
        
        return $success;
    }

    /**
     * Holt einen Spieler anhand der ID
     *
     * @param string $playerId Die Spieler-ID
     * @return Player|null Das Spielerobjekt oder null, wenn nicht gefunden
     */
    public function getPlayerById(string $playerId): ?Player
    {
        $players = $this->getAllPlayersArray();
        
        if (!isset($players[$playerId])) {
            if ($this->logger) {
                $this->logger->info("Spieler mit ID {$playerId} nicht gefunden");
            }
            return null;
        }
        
        return Player::fromArray($players[$playerId]);
    }

    /**
     * Gibt alle Spieler zurück
     *
     * @return Player[] Array mit allen Spielern
     */
    public function getAllPlayers(): array
    {
        $playersData = $this->getAllPlayersArray();
        $players = [];
        
        foreach ($playersData as $playerData) {
            $players[] = Player::fromArray($playerData);
        }
        
        return $players;
    }

    /**
     * Löscht einen Spieler
     *
     * @param string $playerId Die Spieler-ID
     * @return bool True bei Erfolg, False wenn Spieler nicht gefunden
     */
    public function deletePlayer(string $playerId): bool
    {
        $players = $this->getAllPlayersArray();
        
        if (!isset($players[$playerId])) {
            if ($this->logger) {
                $this->logger->info("Zu löschender Spieler mit ID {$playerId} nicht gefunden");
            }
            return false;
        }
        
        $playerName = $players[$playerId]['name'];
        unset($players[$playerId]);
        
        $success = $this->dataService->write(self::PLAYERS_FILE, $players);
        
        if ($success && $this->logger) {
            $this->logger->info("Spieler {$playerName} (ID: {$playerId}) gelöscht");
        }
        
        return $success;
    }

    /**
     * Sucht nach Spielern, die den Suchbegriff enthalten
     *
     * @param string $searchTerm Der Suchbegriff
     * @return array Array mit gefundenen Spielern
     */
    public function searchPlayers(string $searchTerm): array
    {
        $searchTerm = strtolower(trim($searchTerm));
        if (empty($searchTerm)) {
            return $this->getAllPlayers();
        }
        
        $players = $this->getAllPlayers();
        $results = [];
        
        foreach ($players as $player) {
            if (
                str_contains(strtolower($player->getName()), $searchTerm) ||
                ($player->getNickname() && str_contains(strtolower($player->getNickname()), $searchTerm))
            ) {
                $results[] = $player;
            }
        }
        
        return $results;
    }

    /**
     * Sortiert die Spieler nach ELO-Rating absteigend
     *
     * @param array $players Array mit Spieler-Objekten
     * @return array Sortiertes Array mit Spieler-Objekten
     */
    public function sortPlayersByElo(array $players): array
    {
        usort($players, function (Player $a, Player $b) {
            return $b->getEloRating() - $a->getEloRating();
        });
        
        return $players;
    }

    /**
     * Holt die Top-N Spieler nach ELO-Rating
     *
     * @param int $limit Anzahl der Top-Spieler
     * @return array Array mit Top-Spielern
     */
    public function getTopPlayers(int $limit = 10): array
    {
        $players = $this->getAllPlayers();
        $players = $this->sortPlayersByElo($players);
        
        return array_slice($players, 0, $limit);
    }

    /**
     * Holt alle Spieler als assoziatives Array (Hilfsmethode)
     *
     * @return array Spielerdaten
     */
    private function getAllPlayersArray(): array
    {
        return $this->dataService->read(self::PLAYERS_FILE) ?: [];
    }
} 