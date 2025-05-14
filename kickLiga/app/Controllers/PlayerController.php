<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Player;
use App\Services\PlayerService;
use App\Services\MatchService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Slim\Routing\RouteContext;

class PlayerController
{
    private Twig $view;
    private PlayerService $playerService;
    private ?MatchService $matchService;

    /**
     * Controller Konstruktor mit Dependency Injection
     */
    public function __construct(Twig $view, PlayerService $playerService, ?MatchService $matchService = null)
    {
        $this->view = $view;
        $this->playerService = $playerService;
        $this->matchService = $matchService;
    }

    /**
     * Liste aller Spieler
     */
    public function listPlayers(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams()['search'] ?? '';
        
        if (!empty($query)) {
            $players = $this->playerService->searchPlayers($query);
        } else {
            $players = $this->playerService->getAllPlayers();
        }
        
        // Sortiere Spieler nach ELO-Rating
        $players = $this->playerService->sortPlayersByElo($players);

        return $this->view->render($response, 'players/list.twig', [
            'title' => 'Spielerliste',
            'players' => $players,
            'searchQuery' => $query,
            'playerCount' => count($players)
        ]);
    }

    /**
     * Spielerdetails anzeigen
     */
    public function viewPlayer(Request $request, Response $response, array $args): Response
    {
        $playerId = $args['id'] ?? '';
        $player = $this->playerService->getPlayerById($playerId);
        
        if (!$player) {
            return $this->view->render($response->withStatus(404), 'error.twig', [
                'title' => 'Spieler nicht gefunden',
                'message' => 'Der gesuchte Spieler konnte nicht gefunden werden.'
            ]);
        }
        
        // Hole die letzten Matches des Spielers, wenn der MatchService verfügbar ist
        $recentMatches = [];
        if ($this->matchService !== null) {
            $allMatches = $this->matchService->getMatchesByPlayerId($playerId);
            
            // Sortiere Matches nach Datum (neueste zuerst)
            usort($allMatches, function ($a, $b) {
                return $b->getPlayedAt()->getTimestamp() - $a->getPlayedAt()->getTimestamp();
            });
            
            // Begrenze auf die letzten 5 Matches
            $recentMatches = array_slice($allMatches, 0, 5);
        }
        
        return $this->view->render($response, 'players/view.twig', [
            'title' => $player->getDisplayName(),
            'player' => $player,
            'recentMatches' => $recentMatches,
            'player_service' => $this->playerService
        ]);
    }

    /**
     * Formular zum Erstellen eines neuen Spielers
     */
    public function createPlayerForm(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'players/create.twig', [
            'title' => 'Neuen Spieler anlegen'
        ]);
    }

    /**
     * Spieler erstellen (POST)
     */
    public function createPlayer(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        $name = $data['name'] ?? '';
        $nickname = !empty($data['nickname']) ? $data['nickname'] : null;
        $avatar = !empty($data['avatar']) ? $data['avatar'] : null;
        
        if (empty($name)) {
            return $this->view->render($response->withStatus(400), 'players/create.twig', [
                'title' => 'Neuen Spieler anlegen',
                'error' => 'Der Name darf nicht leer sein.',
                'formData' => $data
            ]);
        }
        
        $player = new Player($name, $nickname, $avatar);
        $success = $this->playerService->savePlayer($player);
        
        if (!$success) {
            return $this->view->render($response->withStatus(500), 'players/create.twig', [
                'title' => 'Neuen Spieler anlegen',
                'error' => 'Der Spieler konnte nicht gespeichert werden.',
                'formData' => $data
            ]);
        }
        
        // In Slim 4 RouteContext verwenden, um URLs zu generieren
        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();
        $url = $routeParser->urlFor('players.view', ['id' => $player->getId()]);
        
        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }

    /**
     * Formular zum Bearbeiten eines Spielers
     */
    public function editPlayerForm(Request $request, Response $response, array $args): Response
    {
        $playerId = $args['id'] ?? '';
        $player = $this->playerService->getPlayerById($playerId);
        
        if (!$player) {
            return $this->view->render($response->withStatus(404), 'error.twig', [
                'title' => 'Spieler nicht gefunden',
                'message' => 'Der zu bearbeitende Spieler konnte nicht gefunden werden.'
            ]);
        }
        
        return $this->view->render($response, 'players/edit.twig', [
            'title' => 'Spieler bearbeiten: ' . $player->getDisplayName(),
            'player' => $player
        ]);
    }

    /**
     * Spieler aktualisieren (POST)
     */
    public function updatePlayer(Request $request, Response $response, array $args): Response
    {
        $playerId = $args['id'] ?? '';
        $player = $this->playerService->getPlayerById($playerId);
        
        if (!$player) {
            return $this->view->render($response->withStatus(404), 'error.twig', [
                'title' => 'Spieler nicht gefunden',
                'message' => 'Der zu aktualisierende Spieler konnte nicht gefunden werden.'
            ]);
        }
        
        $data = $request->getParsedBody();
        
        $name = $data['name'] ?? '';
        $nickname = !empty($data['nickname']) ? $data['nickname'] : null;
        $avatar = !empty($data['avatar']) ? $data['avatar'] : null;
        
        if (empty($name)) {
            return $this->view->render($response->withStatus(400), 'players/edit.twig', [
                'title' => 'Spieler bearbeiten',
                'error' => 'Der Name darf nicht leer sein.',
                'player' => $player
            ]);
        }
        
        $player->setName($name)
               ->setNickname($nickname)
               ->setAvatar($avatar);
        
        $success = $this->playerService->savePlayer($player);
        
        if (!$success) {
            return $this->view->render($response->withStatus(500), 'players/edit.twig', [
                'title' => 'Spieler bearbeiten',
                'error' => 'Der Spieler konnte nicht aktualisiert werden.',
                'player' => $player
            ]);
        }
        
        // In Slim 4 RouteContext verwenden, um URLs zu generieren
        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();
        $url = $routeParser->urlFor('players.view', ['id' => $player->getId()]);
        
        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }

    /**
     * Spieler löschen (POST)
     */
    public function deletePlayer(Request $request, Response $response, array $args): Response
    {
        $playerId = $args['id'] ?? '';
        $success = $this->playerService->deletePlayer($playerId);
        
        if (!$success) {
            return $this->view->render($response->withStatus(404), 'error.twig', [
                'title' => 'Spieler nicht gefunden',
                'message' => 'Der zu löschende Spieler konnte nicht gefunden werden.'
            ]);
        }
        
        // In Slim 4 RouteContext verwenden, um URLs zu generieren
        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();
        $url = $routeParser->urlFor('players.list');
        
        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }
} 