<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Season;
use App\Models\GameMatch;
use App\Models\Player;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SeasonService
{
    private DataService $dataService;
    private PlayerService $playerService;
    private ?LoggerInterface $logger;
    private const SEASONS_FILE = 'seasons';

    /**
     * SeasonService Konstruktor
     *
     * @param DataService $dataService DataService-Instanz
     * @param PlayerService $playerService PlayerService-Instanz
     * @param LoggerInterface|null $logger Logger-Instanz
     */
    public function __construct(
        DataService $dataService,
        PlayerService $playerService,
        ?LoggerInterface $logger = null
    ) {
        $this->dataService = $dataService;
        $this->playerService = $playerService;
        $this->logger = $logger;
    }

    /**
     * Speichert eine Saison
     *
     * @param Season $season Saison-Objekt
     * @return bool True bei Erfolg
     * @throws RuntimeException Wenn das Speichern fehlschlägt
     */
    public function saveSeason(Season $season): bool
    {
        $seasons = $this->getAllSeasonsArray();
        
        // Saison-ID extrahieren
        $seasonId = $season->getId();
        if (!$seasonId) {
            throw new RuntimeException('Saison hat keine ID');
        }
        
        // Saison speichern/aktualisieren
        $seasons[$seasonId] = $season->jsonSerialize();
        
        $success = $this->dataService->write(self::SEASONS_FILE, $seasons);
        
        if ($success && $this->logger) {
            $this->logger->info("Saison {$season->getName()} (ID: {$seasonId}) gespeichert");
        }
        
        return $success;
    }

    /**
     * Holt eine Saison anhand der ID
     *
     * @param string $seasonId Die Saison-ID
     * @return Season|null Das Saison-Objekt oder null, wenn nicht gefunden
     */
    public function getSeasonById(string $seasonId): ?Season
    {
        $seasons = $this->getAllSeasonsArray();
        
        if (!isset($seasons[$seasonId])) {
            if ($this->logger) {
                $this->logger->info("Saison mit ID {$seasonId} nicht gefunden");
            }
            return null;
        }
        
        return Season::fromArray($seasons[$seasonId]);
    }

    /**
     * Gibt alle Saisons zurück
     *
     * @return Season[] Array mit allen Saisons
     */
    public function getAllSeasons(): array
    {
        $seasonsData = $this->getAllSeasonsArray();
        $seasons = [];
        
        foreach ($seasonsData as $seasonData) {
            $seasons[] = Season::fromArray($seasonData);
        }
        
        return $seasons;
    }

    /**
     * Löscht eine Saison
     *
     * @param string $seasonId Die Saison-ID
     * @return bool True bei Erfolg, False wenn Saison nicht gefunden
     */
    public function deleteSeason(string $seasonId): bool
    {
        $seasons = $this->getAllSeasonsArray();
        
        if (!isset($seasons[$seasonId])) {
            if ($this->logger) {
                $this->logger->info("Zu löschende Saison mit ID {$seasonId} nicht gefunden");
            }
            return false;
        }
        
        $seasonName = $seasons[$seasonId]['name'];
        unset($seasons[$seasonId]);
        
        $success = $this->dataService->write(self::SEASONS_FILE, $seasons);
        
        if ($success && $this->logger) {
            $this->logger->info("Saison {$seasonName} (ID: {$seasonId}) gelöscht");
        }
        
        return $success;
    }

    /**
     * Erstellt eine neue Saison mit initialisierter Tabelle
     *
     * @param string $name Name der Saison
     * @param \DateTimeImmutable|null $startDate Startdatum der Saison
     * @return Season Die erstellte Saison
     */
    public function createSeason(string $name, ?\DateTimeImmutable $startDate = null): Season
    {
        // Wenn kein Startdatum angegeben, verwende den ersten Tag des aktuellen Monats
        if ($startDate === null) {
            $startDate = new \DateTimeImmutable('first day of this month');
        } else {
            // Wenn Startdatum angegeben, setze es auf den ersten Tag des angegebenen Monats
            $startDate = new \DateTimeImmutable($startDate->format('Y-m-01'));
        }
        
        $season = new Season($name, $startDate);
        
        // Initialisiere die Tabelle mit allen aktiven Spielern
        $allPlayers = $this->playerService->getAllPlayers();
        $season->initializeStandings($allPlayers);
        
        $this->saveSeason($season);
        
        if ($this->logger) {
            $this->logger->info("Neue Saison '{$name}' erstellt, Beginn: {$startDate->format('Y-m-d')}");
        }
        
        return $season;
    }

    /**
     * Aktualisiert die Saison mit einem neuen Match
     *
     * @param GameMatch $match Das zu berücksichtigende Match
     * @param string|null $seasonId Die ID der Saison, oder null für die aktive Saison
     * @return bool True bei Erfolg
     */
    public function updateSeasonWithMatch(GameMatch $match, ?string $seasonId = null): bool
    {
        // Wenn keine Saison-ID angegeben wurde, verwende die aktive Saison
        if ($seasonId === null) {
            $activeSeason = $this->getActiveSeason();
            if (!$activeSeason) {
                if ($this->logger) {
                    $this->logger->warning("Keine aktive Saison gefunden für Match {$match->getId()}");
                }
                return false;
            }
            $seasonId = $activeSeason->getId();
        }
        
        $season = $this->getSeasonById($seasonId);
        if (!$season) {
            if ($this->logger) {
                $this->logger->warning("Saison mit ID {$seasonId} nicht gefunden für Match {$match->getId()}");
            }
            return false;
        }
        
        // Prüfe, ob das Match innerhalb des Saisonzeitraums liegt
        $matchDate = $match->getPlayedAt();
        $seasonStart = $season->getStartDate();
        $seasonEnd = $season->getEndDate() ?? new \DateTimeImmutable('last day of this month 23:59:59');
        
        // Match nur hinzufügen, wenn es im Saisonzeitraum liegt
        if ($matchDate >= $seasonStart && $matchDate <= $seasonEnd) {
            // Aktualisiere die Tabelle mit dem Match
            $season->updateStandings($match);
            return $this->saveSeason($season);
        } else {
            if ($this->logger) {
                $this->logger->info("Match {$match->getId()} liegt nicht im Zeitraum der Saison {$season->getId()}");
            }
            return false;
        }
    }

    /**
     * Gibt die aktive Saison zurück
     *
     * @return Season|null Die aktive Saison oder null, wenn keine aktiv ist
     */
    public function getActiveSeason(): ?Season
    {
        $seasons = $this->getAllSeasons();
        
        // Filtere aktive Saisons
        $activeSeasons = array_values(array_filter($seasons, function (Season $season) {
            return $season->isActive();
        }));
        
        // Wenn keine aktive Saison vorhanden ist, gib null zurück
        if (empty($activeSeasons)) {
            return null;
        }
        
        // Wenn mehrere aktive Saisons vorhanden sind, nimm die neueste
        usort($activeSeasons, function (Season $a, Season $b) {
            return $b->getStartDate()->getTimestamp() - $a->getStartDate()->getTimestamp();
        });
        
        return $activeSeasons[0];
    }

    /**
     * Beendet eine Saison
     *
     * @param string $seasonId Die Saison-ID
     * @param \DateTimeImmutable|null $endDate Enddatum der Saison
     * @return bool True bei Erfolg
     */
    public function endSeason(string $seasonId, ?\DateTimeImmutable $endDate = null): bool
    {
        $season = $this->getSeasonById($seasonId);
        if (!$season) {
            if ($this->logger) {
                $this->logger->warning("Zu beendende Saison mit ID {$seasonId} nicht gefunden");
            }
            return false;
        }
        
        // Wenn kein Enddatum angegeben, verwende den letzten Tag des Monats
        if ($endDate === null) {
            // Verwende den letzten Tag des Monats des Startdatums
            $startDate = $season->getStartDate();
            $endDate = new \DateTimeImmutable($startDate->format('Y-m-t 23:59:59'));
        } else {
            // Wenn Enddatum angegeben, setze es auf den letzten Tag des angegebenen Monats
            $endDate = new \DateTimeImmutable($endDate->format('Y-m-t 23:59:59'));
        }
        
        // Speichere den finalen ELO-Wert jedes Spielers in den Season-Standings
        $allPlayers = $this->playerService->getAllPlayers();
        $standings = $season->getStandings();
        
        foreach ($allPlayers as $player) {
            $playerId = $player->getId();
            if (isset($standings[$playerId])) {
                // Speichere den aktuellen ELO-Wert als Abschluss-ELO in den Season-Standings
                $standings[$playerId]['finalElo'] = $player->getEloRating();
                
                if ($this->logger) {
                    $this->logger->info("Abschluss-ELO von Spieler {$playerId} ({$player->getDisplayName()}) gespeichert: {$player->getEloRating()}");
                }
            }
        }
        
        // Aktualisiere die Standings in der Season
        $season->setStandings($standings);
        
        // Beende die Saison
        $season->endSeason($endDate);
        
        // Alle Spieler-ELO zurücksetzen
        $defaultElo = 1000; // Standardwert für ELO-Rating
        
        foreach ($allPlayers as $player) {
            $player->setEloRating($defaultElo);
            $this->playerService->savePlayer($player);
            
            if ($this->logger) {
                $this->logger->info("ELO-Rating von Spieler {$player->getId()} ({$player->getDisplayName()}) zurückgesetzt auf {$defaultElo}");
            }
        }
        
        if ($this->logger) {
            $this->logger->info("Saison {$seasonId} beendet und ELO-Ratings zurückgesetzt");
        }
        
        return $this->saveSeason($season);
    }

    /**
     * Initialisiert die Tabelle einer bestehenden Saison mit allen Matches im Saisonzeitraum
     *
     * @param string $seasonId Die Saison-ID
     * @param array $matches Liste aller Matches
     * @return bool True bei Erfolg
     */
    public function rebuildSeasonStandings(string $seasonId, array $matches): bool
    {
        $season = $this->getSeasonById($seasonId);
        if (!$season) {
            if ($this->logger) {
                $this->logger->warning("Saison mit ID {$seasonId} nicht gefunden für Neuberechnung");
            }
            return false;
        }
        
        // Initialisiere die Tabelle neu mit allen Spielern
        $allPlayers = $this->playerService->getAllPlayers();
        $season->initializeStandings($allPlayers);
        
        // Saisonzeitraum bestimmen
        $seasonStart = $season->getStartDate();
        $seasonEnd = $season->getEndDate() ?? new \DateTimeImmutable('last day of this month 23:59:59');
        
        // Füge nur Matches hinzu, die im Saisonzeitraum liegen
        foreach ($matches as $match) {
            if ($match instanceof GameMatch) {
                $matchDate = $match->getPlayedAt();
                if ($matchDate >= $seasonStart && $matchDate <= $seasonEnd) {
                    $season->updateStandings($match);
                }
            }
        }
        
        return $this->saveSeason($season);
    }

    /**
     * Gibt alle Saisondaten als Array zurück
     *
     * @return array Assoziatives Array mit allen Saisondaten
     */
    private function getAllSeasonsArray(): array
    {
        try {
            return $this->dataService->read(self::SEASONS_FILE);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Fehler beim Lesen der Saisondaten: " . $e->getMessage());
            }
            return [];
        }
    }
} 