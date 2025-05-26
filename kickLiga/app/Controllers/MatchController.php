<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\GameMatch;
use App\Services\MatchService;
use App\Services\PlayerService;
use App\Services\SeasonService;
use App\Services\AchievementService;
use App\Services\CoinflipService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Slim\Routing\RouteContext;

class MatchController
{
    private Twig $view;
    private MatchService $matchService;
    private PlayerService $playerService;
    private ?SeasonService $seasonService;
    private ?AchievementService $achievementService;
    private CoinflipService $coinflipService;

    public function __construct(
        Twig $view,
        MatchService $matchService,
        PlayerService $playerService,
        CoinflipService $coinflipService,
        ?SeasonService $seasonService = null,
        ?AchievementService $achievementService = null
    ) {
        $this->view = $view;
        $this->matchService = $matchService;
        $this->playerService = $playerService;
        $this->coinflipService = $coinflipService;
        $this->seasonService = $seasonService;
        $this->achievementService = $achievementService;
    }

    /**
     * Zeigt die Spielhistorie an.
     */
    public function matchHistory(Request $request, Response $response): Response
    {
        $matches = $this->matchService->getAllMatches();
        
        // Spiele nach Datum sortieren (neueste zuerst)
        usort($matches, function ($a, $b) {
            return $b->getPlayedAt()->getTimestamp() - $a->getPlayedAt()->getTimestamp();
        });

        return $this->view->render($response, 'matches/history.twig', [
            'title' => 'Spielhistorie',
            'matches' => $matches
        ]);
    }

    /**
     * Zeigt das Formular zum Erstellen eines neuen Spiels an.
     */
    public function createMatchForm(Request $request, Response $response): Response
    {
        $players = $this->playerService->getAllPlayers();
        // Spieler nach Namen sortieren für eine bessere Übersicht im Dropdown
        usort($players, function ($a, $b) {
            return strcmp($a->getDisplayName(), $b->getDisplayName());
        });

        // Aktuellen Zeitstempel für das Template vorbereiten (HTML datetime-local Format)
        $now = new \DateTimeImmutable();
        $currentTimestamp = $now->format('Y-m-d\TH:i');

        return $this->view->render($response, 'matches/create.twig', [
            'title' => 'Neues Spiel erfassen',
            'players' => $players,
            'currentTimestamp' => $currentTimestamp,
            'validSides' => GameMatch::VALID_SIDES
        ]);
    }

    /**
     * Zeigt das Coinflip-Interface für die Seitenwahl an.
     */
    public function coinflipForm(Request $request, Response $response): Response
    {
        $players = $this->playerService->getAllPlayers();
        usort($players, function ($a, $b) {
            return strcmp($a->getDisplayName(), $b->getDisplayName());
        });

        return $this->view->render($response, 'matches/coinflip.twig', [
            'title' => 'Münzwurf für Seitenwahl',
            'players' => $players,
            'coinOptions' => ['kopf' => 'Kopf', 'zahl' => 'Zahl']
        ]);
    }

    /**
     * Führt den Münzwurf durch und zeigt das Ergebnis an.
     */
    public function performCoinflip(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        $player1Id = $data['player1Id'] ?? '';
        $player2Id = $data['player2Id'] ?? '';
        $player1Choice = $data['player1Choice'] ?? '';

        // Validierung
        if (empty($player1Id) || empty($player2Id)) {
            return $this->renderCoinflipFormWithError($response, 'Bitte wählen Sie beide Spieler aus.', $data);
        }

        if ($player1Id === $player2Id) {
            return $this->renderCoinflipFormWithError($response, 'Spieler 1 und Spieler 2 dürfen nicht identisch sein.', $data);
        }

        if (empty($player1Choice) || !in_array($player1Choice, ['kopf', 'zahl'])) {
            return $this->renderCoinflipFormWithError($response, 'Bitte wählen Sie Kopf oder Zahl.', $data);
        }

        try {
            // Münzwurf durchführen
            $coinflipResult = $this->coinflipService->performCoinflipWithSideAssignment($player1Choice);
            
            // Spieler-Objekte laden für die Anzeige
            $player1 = $this->playerService->getPlayerById($player1Id);
            $player2 = $this->playerService->getPlayerById($player2Id);
            
            if (!$player1 || !$player2) {
                return $this->renderCoinflipFormWithError($response, 'Einer der Spieler wurde nicht gefunden.', $data);
            }

            // Beschreibung erstellen
            $description = $this->coinflipService->generateResultDescription(
                $coinflipResult, 
                $player1->getDisplayName(), 
                $player2->getDisplayName()
            );

            return $this->view->render($response, 'matches/coinflip-result.twig', [
                'title' => 'Münzwurf Ergebnis',
                'player1' => $player1,
                'player2' => $player2,
                'coinflipResult' => $coinflipResult,
                'description' => $description,
                'currentTimestamp' => (new \DateTimeImmutable())->format('Y-m-d\TH:i')
            ]);

        } catch (\Exception $e) {
            return $this->renderCoinflipFormWithError($response, 'Fehler beim Münzwurf: ' . $e->getMessage(), $data);
        }
    }

    /**
     * Ajax-Münzwurf für die direkte Integration in das Match-Formular
     */
    public function performCoinflipAjax(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        $player1Id = $data['player1Id'] ?? '';
        $player2Id = $data['player2Id'] ?? '';
        $player1Choice = $data['player1Choice'] ?? '';

        // Validierung
        $errors = [];
        
        if (empty($player1Id) || empty($player2Id)) {
            $errors[] = 'Bitte wählen Sie beide Spieler aus.';
        }

        if ($player1Id === $player2Id) {
            $errors[] = 'Spieler 1 und Spieler 2 dürfen nicht identisch sein.';
        }

        if (empty($player1Choice) || !in_array($player1Choice, ['kopf', 'zahl'])) {
            $errors[] = 'Bitte wählen Sie Kopf oder Zahl.';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'errors' => $errors
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            // Spieler laden für die Beschreibung
            $player1 = $this->playerService->getPlayerById($player1Id);
            $player2 = $this->playerService->getPlayerById($player2Id);
            
            if (!$player1 || !$player2) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'errors' => ['Einer der Spieler wurde nicht gefunden.']
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Münzwurf durchführen (nur Gewinner bestimmen, keine automatische Seitenzuweisung)
            $coinflipResult = $this->coinflipService->performCoinflipWithWinner($player1Choice);
            
            // Beschreibung erstellen (ohne Seitenzuweisung)
            $description = $this->coinflipService->generateWinnerDescription(
                $coinflipResult, 
                $player1->getDisplayName(), 
                $player2->getDisplayName()
            );

            // Gewinner-Informationen für Frontend
            $winnerId = $coinflipResult['winner'] === 1 ? $player1Id : $player2Id;
            $winnerName = $coinflipResult['winner'] === 1 ? $player1->getDisplayName() : $player2->getDisplayName();

            // Erfolgreiche Antwort
            $response->getBody()->write(json_encode([
                'success' => true,
                'coinflipResult' => $coinflipResult,
                'description' => $description,
                'winner' => [
                    'id' => $winnerId,
                    'name' => $winnerName,
                    'playerNumber' => $coinflipResult['winner']
                ],
                'player1' => [
                    'id' => $player1->getId(),
                    'name' => $player1->getDisplayName()
                ],
                'player2' => [
                    'id' => $player2->getId(),
                    'name' => $player2->getDisplayName()
                ]
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'errors' => ['Fehler beim Münzwurf: ' . $e->getMessage()]
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Verarbeitet das Formular zum Erstellen eines neuen Spiels.
     */
    public function createMatch(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $player1Id = $data['player1Id'] ?? '';
        $player2Id = $data['player2Id'] ?? '';
        $scorePlayer1 = isset($data['scorePlayer1']) ? (int)$data['scorePlayer1'] : null;
        $scorePlayer2 = isset($data['scorePlayer2']) ? (int)$data['scorePlayer2'] : null;
        $playedAtStr = $data['playedAt'] ?? '';
        $notes = $data['notes'] ?? null;
        $player1Side = $data['player1Side'] ?? GameMatch::SIDE_BLUE;
        $player2Side = $data['player2Side'] ?? GameMatch::SIDE_WHITE;

        // Coinflip-Daten wenn vorhanden
        $coinflipData = null;
        if (isset($data['coinflipData']) && !empty($data['coinflipData'])) {
            $coinflipData = json_decode($data['coinflipData'], true);
        }

        // Validierung
        if (empty($player1Id) || empty($player2Id)) {
            return $this->renderCreateFormWithError($response, 'Bitte wählen Sie beide Spieler aus.', $data);
        }

        if ($player1Id === $player2Id) {
            return $this->renderCreateFormWithError($response, 'Spieler 1 und Spieler 2 dürfen nicht identisch sein.', $data);
        }

        if ($scorePlayer1 === null || $scorePlayer1 < 0 || $scorePlayer2 === null || $scorePlayer2 < 0) {
            return $this->renderCreateFormWithError($response, 'Die Ergebnisse müssen positive Zahlen sein.', $data);
        }

        // Validierung der Seitenwahl
        try {
            $this->matchService->validateSides($player1Side, $player2Side);
        } catch (\RuntimeException $e) {
            return $this->renderCreateFormWithError($response, 'Fehler bei der Seitenwahl: ' . $e->getMessage(), $data);
        }

        $playedAt = null;
        if (!empty($playedAtStr)) {
            try {
                $playedAt = new \DateTimeImmutable($playedAtStr);
            } catch (\Exception $e) {
                return $this->renderCreateFormWithError($response, 'Das Datum des Spiels ist ungültig.', $data);
            }
        }

        try {
            $match = $this->matchService->createMatch(
                $player1Id,
                $player2Id,
                $scorePlayer1,
                $scorePlayer2,
                $playedAt,
                $notes,
                $player1Side,
                $player2Side,
                $coinflipData
            );
            
            // Aktualisiere die aktive Saison mit dem neuen Match, wenn SeasonService verfügbar ist
            if ($this->seasonService !== null) {
                $this->seasonService->updateSeasonWithMatch($match);
            }
            
            // Überprüfe Achievements für beide Spieler nach dem Match
            if ($this->achievementService !== null) {
                $this->achievementService->checkAchievementsAfterMatch($player1Id, $player2Id);
            }
        } catch (\RuntimeException $e) {
            return $this->renderCreateFormWithError($response, 'Fehler beim Speichern des Spiels: ' . $e->getMessage(), $data);
        }
        
        // In Slim 4 RouteContext verwenden, um URLs zu generieren
        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();
        $url = $routeParser->urlFor('matches.history');
        
        // TODO: Success message (Flash message)
        return $response->withHeader('Location', $url)->withStatus(302);
    }

    private function renderCreateFormWithError(Response $response, string $errorMessage, array $formData): Response
    {
        $players = $this->playerService->getAllPlayers();
        usort($players, function ($a, $b) {
            return strcmp($a->getDisplayName(), $b->getDisplayName());
        });

        return $this->view->render($response->withStatus(400), 'matches/create.twig', [
            'title' => 'Neues Spiel erfassen',
            'players' => $players,
            'error' => $errorMessage,
            'formData' => $formData,
            'validSides' => GameMatch::VALID_SIDES
        ]);
    }

    private function renderCoinflipFormWithError(Response $response, string $errorMessage, array $formData): Response
    {
        $players = $this->playerService->getAllPlayers();
        usort($players, function ($a, $b) {
            return strcmp($a->getDisplayName(), $b->getDisplayName());
        });

        return $this->view->render($response->withStatus(400), 'matches/coinflip.twig', [
            'title' => 'Münzwurf für Seitenwahl',
            'players' => $players,
            'error' => $errorMessage,
            'formData' => $formData,
            'coinOptions' => ['kopf' => 'Kopf', 'zahl' => 'Zahl']
        ]);
    }

    /**
     * Ajax-Seitenwahl für den Münzwurf-Gewinner
     */
    public function coinflipWinnerSideChoice(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        $coinflipDataJson = $data['coinflipData'] ?? '';
        $winnerSideChoice = $data['winnerSideChoice'] ?? '';

        // Validierung
        $errors = [];
        
        if (empty($coinflipDataJson)) {
            $errors[] = 'Münzwurf-Daten fehlen.';
        }

        if (empty($winnerSideChoice) || !in_array($winnerSideChoice, ['blau', 'weiss'])) {
            $errors[] = 'Bitte wählen Sie eine gültige Seite.';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'errors' => $errors
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            // Münzwurf-Daten dekodieren
            $coinflipData = json_decode($coinflipDataJson, true);
            if (!$coinflipData) {
                throw new \InvalidArgumentException('Ungültige Münzwurf-Daten');
            }

            $winner = $coinflipData['winner'];

            // Seitenzuweisung basierend auf Gewinner-Wahl erstellen
            $sideAssignment = $this->coinflipService->assignSidesByWinnerChoice($winner, $winnerSideChoice);

            // Vollständige Coinflip-Daten mit Seitenzuweisung erstellen
            $completeCoinflipData = [
                'coinflipResult' => $coinflipData['coinflipResult'],
                'sideAssignment' => array_merge($sideAssignment, [
                    'winner' => $winner,
                    'coinResult' => $coinflipData['coinflipResult'],
                    'player1Choice' => $coinflipData['player1Choice']
                ]),
                'timestamp' => $coinflipData['timestamp']
            ];

            // Erfolgreiche Antwort mit finalen Seitenzuweisungen
            $response->getBody()->write(json_encode([
                'success' => true,
                'sideAssignment' => $sideAssignment,
                'completeCoinflipData' => $completeCoinflipData
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'errors' => ['Fehler bei der Seitenwahl: ' . $e->getMessage()]
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
} 