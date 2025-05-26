<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Player;
use App\Models\GameMatch;
use Psr\Log\LoggerInterface;
use RuntimeException;

class PlayerService
{
    private DataService $dataService;
    private ComputationService $computationService;
    private ?LoggerInterface $logger;
    private const PLAYERS_META_FILE = 'players_meta';

    /**
     * PlayerService Konstruktor - Refactored für Single Source of Truth
     *
     * @param DataService $dataService DataService-Instanz
     * @param ComputationService $computationService ComputationService-Instanz
     * @param LoggerInterface|null $logger Logger-Instanz
     */
    public function __construct(
        DataService $dataService, 
        ComputationService $computationService,
        ?LoggerInterface $logger = null
    ) {
        $this->dataService = $dataService;
        $this->computationService = $computationService;
        $this->logger = $logger;
    }

    /**
     * Speichert Spieler-Metadaten (Name, Avatar, etc.)
     * Statistiken und ELO werden aus matches.json berechnet
     *
     * @param Player $player Spieler-Objekt
     * @return bool True bei Erfolg
     * @throws RuntimeException Wenn das Speichern fehlschlägt
     */
    public function savePlayer(Player $player): bool
    {
        $playersMeta = $this->getAllPlayersMetaArray();
        
        // Spieler-ID extrahieren
        $playerId = $player->getId();
        if (!$playerId) {
            throw new RuntimeException('Spieler hat keine ID');
        }
        
        // Nur Metadaten speichern
        $playersMeta[$playerId] = [
            'id' => $playerId,
            'name' => $player->getName(),
            'nickname' => $player->getNickname(),
            'avatar' => $player->getAvatar(),
            'createdAt' => $player->getCreatedAt()
        ];
        
        $success = $this->dataService->write(self::PLAYERS_META_FILE, $playersMeta);
        
        if ($success && $this->logger) {
            $this->logger->info("Spieler-Metadaten {$player->getName()} (ID: {$playerId}) gespeichert");
        }
        
        return $success;
    }

    /**
     * Holt einen Spieler anhand der ID - berechnet aus matches.json
     *
     * @param string $playerId Die Spieler-ID
     * @return Player|null Das Spielerobjekt oder null, wenn nicht gefunden
     */
    public function getPlayerById(string $playerId): ?Player
    {
        $playerData = $this->computationService->computePlayerData($playerId);
        
        if (empty($playerData) || !isset($playerData['id'])) {
            if ($this->logger) {
                $this->logger->info("Spieler mit ID {$playerId} nicht gefunden");
            }
            return null;
        }
        
        return Player::fromArray($playerData);
    }

    /**
     * Gibt alle Spieler zurück - berechnet aus matches.json
     *
     * @return Player[] Array mit allen Spielern
     */
    public function getAllPlayers(): array
    {
        $playersDataArrays = $this->computationService->computeAllPlayerData(); // Dies sollte bereits keyed by ID sein
        $playersMap = [];
        
        foreach ($playersDataArrays as $playerId => $playerDataArray) {
            if (is_array($playerDataArray) && isset($playerDataArray['id'])) {
                $playersMap[$playerId] = Player::fromArray($playerDataArray);
            } elseif ($this->logger) {
                $this->logger->warning("Ungültige Spielerdaten für ID {$playerId} von ComputationService erhalten.");
            }
        }
        
        return $playersMap;
    }

    /**
     * Löscht Spieler-Metadaten
     * WARNUNG: Matches des Spielers bleiben bestehen!
     *
     * @param string $playerId Die Spieler-ID
     * @return bool True bei Erfolg, False wenn Spieler nicht gefunden
     */
    public function deletePlayer(string $playerId): bool
    {
        $playersMeta = $this->getAllPlayersMetaArray();
        
        if (!isset($playersMeta[$playerId])) {
            if ($this->logger) {
                $this->logger->info("Zu löschender Spieler mit ID {$playerId} nicht gefunden");
            }
            return false;
        }
        
        $playerName = $playersMeta[$playerId]['name'];
        unset($playersMeta[$playerId]);
        
        $success = $this->dataService->write(self::PLAYERS_META_FILE, $playersMeta);
        
        if ($success && $this->logger) {
            $this->logger->info("Spieler-Metadaten {$playerName} (ID: {$playerId}) gelöscht");
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
     * Invalidiert den Cache im ComputationService
     * Wird nach Änderungen an Matches aufgerufen (Single Source of Truth)
     */
    public function invalidateCache(): void
    {
        $this->computationService->invalidateCache();
        
        if ($this->logger) {
            $this->logger->info('PlayerService: Cache invalidiert nach Match-Änderung');
        }
    }

    /**
     * Berechnet Seitenstatistiken für einen Spieler
     *
     * @param string $playerId Die Spieler-ID
     * @param GameMatch[] $matches Array mit Matches des Spielers
     * @return array Seitenstatistiken
     */
    public function calculateSideStatistics(string $playerId, array $matches): array
    {
        $sideStats = [
            GameMatch::SIDE_BLUE => [
                'matchesPlayed' => 0,
                'wins' => 0,
                'losses' => 0,
                'draws' => 0,
                'winRate' => 0.0,
                'goalsScored' => 0,
                'goalsConceded' => 0,
                'avgGoalsScored' => 0.0,
                'avgGoalsConceded' => 0.0
            ],
            GameMatch::SIDE_WHITE => [
                'matchesPlayed' => 0,
                'wins' => 0,
                'losses' => 0,
                'draws' => 0,
                'winRate' => 0.0,
                'goalsScored' => 0,
                'goalsConceded' => 0,
                'avgGoalsScored' => 0.0,
                'avgGoalsConceded' => 0.0
            ]
        ];

        foreach ($matches as $match) {
            $side = $match->getPlayerSide($playerId);
            if (!$side) {
                continue; // Spieler nicht in diesem Match
            }

            $sideStats[$side]['matchesPlayed']++;

            // Ermittle Tore für und gegen diesen Spieler
            if ($match->getPlayer1Id() === $playerId) {
                $goalsFor = $match->getScorePlayer1();
                $goalsAgainst = $match->getScorePlayer2();
                $isWinner = $match->isPlayer1Winner();
            } else {
                $goalsFor = $match->getScorePlayer2();
                $goalsAgainst = $match->getScorePlayer1();
                $isWinner = $match->isPlayer2Winner();
            }

            $sideStats[$side]['goalsScored'] += $goalsFor;
            $sideStats[$side]['goalsConceded'] += $goalsAgainst;

            if ($match->isDraw()) {
                $sideStats[$side]['draws']++;
            } elseif ($isWinner) {
                $sideStats[$side]['wins']++;
            } else {
                $sideStats[$side]['losses']++;
            }
        }

        // Berechne Durchschnittswerte und Win-Rate
        foreach ([GameMatch::SIDE_BLUE, GameMatch::SIDE_WHITE] as $side) {
            $played = $sideStats[$side]['matchesPlayed'];
            
            if ($played > 0) {
                $sideStats[$side]['winRate'] = round(($sideStats[$side]['wins'] / $played) * 100, 1);
                $sideStats[$side]['avgGoalsScored'] = round($sideStats[$side]['goalsScored'] / $played, 1);
                $sideStats[$side]['avgGoalsConceded'] = round($sideStats[$side]['goalsConceded'] / $played, 1);
            }
        }

        return $sideStats;
    }

    /**
     * Ermittelt die bevorzugte Seite eines Spielers
     *
     * @param array $sideStats Seitenstatistiken
     * @return array Bevorzugte Seite und Vorteil in Prozentpunkten
     */
    public function getPreferredSide(array $sideStats): array
    {
        $blueWinRate = $sideStats[GameMatch::SIDE_BLUE]['winRate'];
        $whiteWinRate = $sideStats[GameMatch::SIDE_WHITE]['winRate'];
        
        if ($blueWinRate > $whiteWinRate) {
            return [
                'side' => GameMatch::SIDE_BLUE,
                'advantage' => round($blueWinRate - $whiteWinRate, 1),
                'winRate' => $blueWinRate
            ];
        } elseif ($whiteWinRate > $blueWinRate) {
            return [
                'side' => GameMatch::SIDE_WHITE,
                'advantage' => round($whiteWinRate - $blueWinRate, 1),
                'winRate' => $whiteWinRate
            ];
        } else {
            return [
                'side' => null,
                'advantage' => 0.0,
                'winRate' => $blueWinRate
            ];
        }
    }

    /**
     * Erstellt Daten für ein Seitenvergleich-Chart
     *
     * @param array $sideStats Seitenstatistiken
     * @return array Chart-Daten
     */
    public function prepareSideComparisonChartData(array $sideStats): array
    {
        return [
            'labels' => ['Blaue Seite', 'Weiße Seite'],
            'datasets' => [
                [
                    'label' => 'Siege',
                    'data' => [
                        $sideStats[GameMatch::SIDE_BLUE]['wins'],
                        $sideStats[GameMatch::SIDE_WHITE]['wins']
                    ],
                    'backgroundColor' => ['rgba(13, 110, 253, 0.6)', 'rgba(108, 117, 125, 0.6)'],
                    'borderColor' => ['#0d6efd', '#6c757d'],
                    'borderWidth' => 2
                ],
                [
                    'label' => 'Niederlagen',
                    'data' => [
                        $sideStats[GameMatch::SIDE_BLUE]['losses'],
                        $sideStats[GameMatch::SIDE_WHITE]['losses']
                    ],
                    'backgroundColor' => ['rgba(220, 53, 69, 0.6)', 'rgba(220, 53, 69, 0.4)'],
                    'borderColor' => ['#dc3545', '#dc3545'],
                    'borderWidth' => 2
                ]
            ]
        ];
    }

    /**
     * Berechnet globale Seitenstatistiken aller Spieler
     *
     * @param GameMatch[] $allMatches Alle Matches
     * @return array Globale Seitenstatistiken
     */
    public function calculateGlobalSideStatistics(array $allMatches): array
    {
        $globalStats = [
            GameMatch::SIDE_BLUE => [
                'wins' => 0,
                'losses' => 0,
                'draws' => 0,
                'totalMatches' => 0,
                'winRate' => 0.0
            ],
            GameMatch::SIDE_WHITE => [
                'wins' => 0,
                'losses' => 0,
                'draws' => 0,
                'totalMatches' => 0,
                'winRate' => 0.0
            ]
        ];

        foreach ($allMatches as $match) {
            $winningSide = $match->getWinningSide();
            $losingSide = $match->getLosingSide();

            if ($match->isDraw()) {
                $globalStats[GameMatch::SIDE_BLUE]['draws']++;
                $globalStats[GameMatch::SIDE_WHITE]['draws']++;
            } else {
                $globalStats[$winningSide]['wins']++;
                $globalStats[$losingSide]['losses']++;
            }

            $globalStats[GameMatch::SIDE_BLUE]['totalMatches']++;
            $globalStats[GameMatch::SIDE_WHITE]['totalMatches']++;
        }

        // Berechne Win-Rates
        foreach ([GameMatch::SIDE_BLUE, GameMatch::SIDE_WHITE] as $side) {
            $total = $globalStats[$side]['totalMatches'];
            if ($total > 0) {
                $globalStats[$side]['winRate'] = round(($globalStats[$side]['wins'] / $total) * 100, 1);
            }
        }

        return $globalStats;
    }

    /**
     * Holt alle Spieler-Metadaten als assoziatives Array (Hilfsmethode)
     *
     * @return array Spieler-Metadaten
     */
    private function getAllPlayersMetaArray(): array
    {
        return $this->dataService->read(self::PLAYERS_META_FILE) ?: [];
    }
} 