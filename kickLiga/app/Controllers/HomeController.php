<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DataService;
use App\Services\PlayerService;
use App\Services\MatchService;
use App\Services\SeasonService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class HomeController
{
    private Twig $view;
    private DataService $dataService;
    private PlayerService $playerService;
    private ?MatchService $matchService;
    private ?SeasonService $seasonService;

    /**
     * Controller Konstruktor mit Dependency Injection
     */
    public function __construct(
        Twig $view, 
        DataService $dataService, 
        PlayerService $playerService, 
        ?MatchService $matchService = null,
        ?SeasonService $seasonService = null
    ) {
        $this->view = $view;
        $this->dataService = $dataService;
        $this->playerService = $playerService;
        $this->matchService = $matchService;
        $this->seasonService = $seasonService;
    }

    /**
     * Home-Seite anzeigen
     */
    public function home(Request $request, Response $response): Response
    {
        // Hole die Top-Spieler und die Gesamtzahl der Spieler
        $topPlayers = $this->playerService->getTopPlayers(5);
        $allPlayers = $this->playerService->getAllPlayers();
        $playerCount = count($allPlayers);
        
        // Hole die letzten Spiele, falls der MatchService verfügbar ist
        $recentMatches = [];
        $matchCount = 0;
        if ($this->matchService !== null) {
            $recentMatchesRaw = $this->matchService->getRecentMatches(5);
            $matchCount = count($this->matchService->getAllMatches());
            
            // Erweitere die Match-Daten um Spielerinformationen für das Template
            foreach ($recentMatchesRaw as $match) {
                $player1 = $this->playerService->getPlayerById($match->getPlayer1Id());
                $player2 = $this->playerService->getPlayerById($match->getPlayer2Id());
                
                $recentMatches[] = [
                    'id' => $match->getId(),
                    'playedAt' => $match->getPlayedAt(),
                    'player1Id' => $match->getPlayer1Id(),
                    'player2Id' => $match->getPlayer2Id(),
                    'player1' => $player1,
                    'player2' => $player2,
                    'scorePlayer1' => $match->getScorePlayer1(),
                    'scorePlayer2' => $match->getScorePlayer2(),
                    'player1Side' => $match->getPlayer1Side(),
                    'player2Side' => $match->getPlayer2Side(),
                    'player1IsWinner' => $match->isPlayer1Winner(),
                    'player2IsWinner' => $match->isPlayer2Winner(),
                    'eloChange' => $match->getEloChange(),
                    'notes' => $match->getNotes()
                ];
            }
        }
        
        // Hole die aktive Saison und deren Daten (Single Source of Truth)
        $activeSeason = null;
        $seasonCount = 0;
        $seasonStandings = [];
        $seasonStatistics = null;
        
        if ($this->seasonService !== null) {
            $activeSeason = $this->seasonService->getActiveSeason();
            $seasonCount = count($this->seasonService->getAllSeasons());
            
            if ($activeSeason) {
                // Berechne Saison-Daten zur Laufzeit
                $seasonStandings = $this->seasonService->getSeasonStandings($activeSeason->getId());
                $seasonStatistics = $this->seasonService->getSeasonStatistics($activeSeason->getId());
                
                // Erstelle erweiterte Season-Daten für Template
                $activeSeason = (object) [
                    'id' => $activeSeason->getId(),
                    'name' => $activeSeason->getName(),
                    'startDate' => $activeSeason->getStartDate(),
                    'endDate' => $activeSeason->getEndDate(),
                    'isActive' => $activeSeason->isActive(),
                    'durationInDays' => $activeSeason->getDurationInDays(),
                    'effectiveEndDate' => $activeSeason->getEffectiveEndDate()
                ];
            }
        }
        
        return $this->view->render($response, 'home.twig', [
            'title' => 'Kickerliga Management System',
            'topPlayers' => $topPlayers,
            'playerCount' => $playerCount,
            'recentMatches' => $recentMatches,
            'matchCount' => $matchCount,
            'activeSeason' => $activeSeason,
            'seasonCount' => $seasonCount,
            'seasonStandings' => $seasonStandings,
            'seasonStatistics' => $seasonStatistics
        ]);
    }

    /**
     * Testseite für DataService
     */
    public function testDataService(Request $request, Response $response): Response
    {
        // Teste das Schreiben und Lesen von Daten
        $testData = [
            'test' => true,
            'message' => 'Hello World',
            'timestamp' => time()
        ];

        $success = $this->dataService->write('test', $testData);
        $readData = $this->dataService->read('test');

        return $this->view->render($response, 'test.twig', [
            'title' => 'DataService Test',
            'writeSuccess' => $success,
            'originalData' => $testData,
            'readData' => $readData
        ]);
    }
} 