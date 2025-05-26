<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Season;
use App\Services\SeasonService;
use App\Services\MatchService;
use App\Services\PlayerService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

class SeasonController
{
    private Twig $view;
    private SeasonService $seasonService;
    private MatchService $matchService;
    private PlayerService $playerService;

    /**
     * Controller Konstruktor mit Dependency Injection
     */
    public function __construct(
        Twig $view,
        SeasonService $seasonService,
        MatchService $matchService,
        PlayerService $playerService
    ) {
        $this->view = $view;
        $this->seasonService = $seasonService;
        $this->matchService = $matchService;
        $this->playerService = $playerService;
    }

    /**
     * Liste aller Saisons
     */
    public function listSeasons(Request $request, Response $response): Response
    {
        $seasons = $this->seasonService->getAllSeasons();
        
        // Sortiere Saisons nach Startdatum (neueste zuerst)
        usort($seasons, function ($a, $b) {
            return $b->getStartDate()->getTimestamp() - $a->getStartDate()->getTimestamp();
        });

        $activeSeason = $this->seasonService->getActiveSeason();

        return $this->view->render($response, 'seasons/list.twig', [
            'title' => 'Saisons',
            'seasons' => $seasons,
            'activeSeason' => $activeSeason,
            'seasonCount' => count($seasons)
        ]);
    }

    /**
     * Saison-Details anzeigen
     */
    public function viewSeason(Request $request, Response $response, array $args): Response
    {
        $seasonId = $args['id'] ?? '';
        $season = $this->seasonService->getSeasonById($seasonId);
        
        if (!$season) {
            return $this->view->render($response->withStatus(404), 'error.twig', [
                'title' => 'Saison nicht gefunden',
                'message' => 'Die gesuchte Saison konnte nicht gefunden werden.'
            ]);
        }

        // Sortierte Tabelle abrufen (Single Source of Truth)
        $standings = $this->seasonService->getSeasonStandings($seasonId);
        
        // Saison-Statistiken abrufen (Single Source of Truth)
        $seasonStatistics = $this->seasonService->getSeasonStatistics($seasonId);
        
        // Saison-Matches abrufen
        $seasonMatches = $this->seasonService->getSeasonMatches($season);
        
        // Sortiere Matches nach Datum (neueste zuerst)
        usort($seasonMatches, function ($a, $b) {
            return $b->getPlayedAt()->getTimestamp() - $a->getPlayedAt()->getTimestamp();
        });
        
        // Begrenze auf die letzten 10 Spiele
        $recentMatches = array_slice($seasonMatches, 0, 10);
        
        // Erweitere Match-Daten um Spielerinformationen
        $enrichedMatches = [];
        foreach ($recentMatches as $match) {
            $player1 = $this->playerService->getPlayerById($match->getPlayer1Id());
            $player2 = $this->playerService->getPlayerById($match->getPlayer2Id());
            
            $enrichedMatches[] = [
                'match' => $match,
                'player1' => $player1,
                'player2' => $player2
            ];
        }
        
        return $this->view->render($response, 'seasons/view.twig', [
            'title' => $season->getName(),
            'season' => $season,
            'standings' => $standings,
            'seasonStatistics' => $seasonStatistics,
            'recentMatches' => $enrichedMatches,
            'isActive' => $season->isActive(),
            'player_service' => $this->playerService
        ]);
    }

    /**
     * Formular zum Erstellen einer neuen Saison
     */
    public function createSeasonForm(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'seasons/create.twig', [
            'title' => 'Neue Saison anlegen'
        ]);
    }

    /**
     * Saison erstellen (POST)
     */
    public function createSeason(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        $name = $data['name'] ?? '';
        $startMonthStr = $data['startDate'] ?? '';
        
        if (empty($name)) {
            return $this->view->render($response->withStatus(400), 'seasons/create.twig', [
                'title' => 'Neue Saison anlegen',
                'error' => 'Der Name darf nicht leer sein.',
                'formData' => $data
            ]);
        }
        
        $startDate = null;
        if (!empty($startMonthStr)) {
            try {
                // Konvertiere das "YYYY-MM"-Format aus dem Formular in ein Datum
                // Setze den Tag auf den 1. des Monats
                $startDate = new \DateTimeImmutable($startMonthStr . '-01');
            } catch (\Exception $e) {
                return $this->view->render($response->withStatus(400), 'seasons/create.twig', [
                    'title' => 'Neue Saison anlegen',
                    'error' => 'Das Startdatum ist ungültig.',
                    'formData' => $data
                ]);
            }
        }
        
        try {
            $season = $this->seasonService->createSeason($name, $startDate);
            
            // Single Source of Truth: Keine Notwendigkeit, Statistiken zu berechnen
            // Sie werden zur Laufzeit aus matches.json berechnet
        } catch (\RuntimeException $e) {
            return $this->view->render($response->withStatus(500), 'seasons/create.twig', [
                'title' => 'Neue Saison anlegen',
                'error' => 'Die Saison konnte nicht erstellt werden: ' . $e->getMessage(),
                'formData' => $data
            ]);
        }
        
        // In Slim 4 RouteContext verwenden, um URLs zu generieren
        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();
        $url = $routeParser->urlFor('seasons.view', ['id' => $season->getId()]);
        
        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }

    /**
     * Saison beenden (POST)
     */
    public function endSeason(Request $request, Response $response, array $args): Response
    {
        $seasonId = $args['id'] ?? '';
        $season = $this->seasonService->getSeasonById($seasonId);
        
        if (!$season) {
            return $this->view->render($response->withStatus(404), 'error.twig', [
                'title' => 'Saison nicht gefunden',
                'message' => 'Die zu beendende Saison konnte nicht gefunden werden.'
            ]);
        }
        
        $success = $this->seasonService->endSeason($seasonId);
        
        if (!$success) {
            return $this->view->render($response->withStatus(500), 'error.twig', [
                'title' => 'Fehler',
                'message' => 'Die Saison konnte nicht beendet werden.'
            ]);
        }
        
        // In Slim 4 RouteContext verwenden, um URLs zu generieren
        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();
        $url = $routeParser->urlFor('seasons.view', ['id' => $seasonId]);
        
        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }
} 