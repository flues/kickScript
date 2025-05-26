<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Season;
use App\Models\GameMatch;
use App\Models\Player;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * SeasonService - Single Source of Truth
 * 
 * Verwaltet nur Saison-Metadaten (Name, Zeitraum, Status).
 * Alle Statistiken und Tabellen werden zur Laufzeit aus matches.json berechnet.
 */
class SeasonService
{
    private DataService $dataService;
    private ComputationService $computationService;
    private ?LoggerInterface $logger;
    private const SEASONS_FILE = 'seasons';

    /**
     * SeasonService Konstruktor
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
     * Invalidiert den Cache für alle abhängigen Services
     * Wird nach Änderungen an Matches aufgerufen
     */
    public function invalidateCache(): void
    {
        $this->computationService->invalidateCache();
        
        if ($this->logger) {
            $this->logger->info('SeasonService: Cache invalidiert nach Match-Änderung');
        }
    }

    /**
     * Speichert eine Saison (nur Metadaten)
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
        
        // Saison speichern/aktualisieren (nur Metadaten)
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
     * Erstellt eine neue Saison
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
        $this->saveSeason($season);
        
        if ($this->logger) {
            $this->logger->info("Neue Saison '{$name}' erstellt, Beginn: {$startDate->format('Y-m-d')}");
        }
        
        return $season;
    }

    /**
     * Gibt die aktive Saison zurück
     *
     * @return Season|null Die aktive Saison oder null
     */
    public function getActiveSeason(): ?Season
    {
        $seasons = $this->getAllSeasons();
        
        foreach ($seasons as $season) {
            if ($season->isActive()) {
                return $season;
            }
        }
        
        if ($this->logger) {
            $this->logger->info('Keine aktive Saison gefunden');
        }
        
        return null;
    }

    /**
     * Beendet eine Saison
     *
     * @param string $seasonId Die Saison-ID
     * @param \DateTimeImmutable|null $endDate Das Enddatum
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
        
        $season->endSeason($endDate);
        $success = $this->saveSeason($season);
        
        if ($success && $this->logger) {
            $this->logger->info("Saison {$season->getName()} beendet");
        }
        
        return $success;
    }

    // === COMPUTED PROPERTIES (Single Source of Truth) ===

    /**
     * Berechnet die Tabelle für eine Saison zur Laufzeit
     *
     * @param string $seasonId Die Saison-ID
     * @return array Die sortierte Tabelle
     */
    public function getSeasonStandings(string $seasonId): array
    {
        $season = $this->getSeasonById($seasonId);
        if (!$season) {
            return [];
        }

        // Alle Matches der Saison holen
        $seasonMatches = $this->getSeasonMatches($season);
        
        // Tabelle aus Matches berechnen
        return $this->computationService->calculateStandings($seasonMatches);
    }

    /**
     * Berechnet die Statistiken für eine Saison zur Laufzeit
     *
     * @param string $seasonId Die Saison-ID
     * @return array Die Saisonstatistiken
     */
    public function getSeasonStatistics(string $seasonId): array
    {
        $season = $this->getSeasonById($seasonId);
        if (!$season) {
            return [
                'totalMatches' => 0,
                'totalGoals' => 0,
                'highestScore' => null,
                'longestWinStreak' => null
            ];
        }

        // Alle Matches der Saison holen
        $seasonMatches = $this->getSeasonMatches($season);
        
        // Statistiken aus Matches berechnen
        return $this->computationService->calculateSeasonStatistics($seasonMatches);
    }

    /**
     * Holt alle Matches einer Saison
     *
     * @param Season $season Die Saison
     * @return GameMatch[] Array mit Matches der Saison
     */
    public function getSeasonMatches(Season $season): array
    {
        $allMatches = $this->dataService->read('matches') ?? [];
        $seasonMatches = [];

        foreach ($allMatches as $matchData) {
            $match = GameMatch::fromArray($matchData);
            
            // Prüfe, ob Match in Saisonzeitraum fällt
            if ($season->isMatchInSeason($match->getPlayedAt())) {
                $seasonMatches[] = $match;
            }
        }

        return $seasonMatches;
    }

    /**
     * Holt alle Saisons als Array
     *
     * @return array Array mit Saisondaten
     */
    private function getAllSeasonsArray(): array
    {
        return $this->dataService->read(self::SEASONS_FILE) ?? [];
    }
} 