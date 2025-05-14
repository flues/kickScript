<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MatchService;
use App\Services\PlayerService;
use App\Services\SeasonService;
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

    public function __construct(
        Twig $view,
        MatchService $matchService,
        PlayerService $playerService,
        ?SeasonService $seasonService = null
    ) {
        $this->view = $view;
        $this->matchService = $matchService;
        $this->playerService = $playerService;
        $this->seasonService = $seasonService;
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
            'currentTimestamp' => $currentTimestamp // An das Template übergeben
        ]);
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
                $notes
            );
            
            // Aktualisiere die aktive Saison mit dem neuen Match, wenn SeasonService verfügbar ist
            if ($this->seasonService !== null) {
                $this->seasonService->updateSeasonWithMatch($match);
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
            'formData' => $formData
        ]);
    }
} 