<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\PlayerController;
use App\Controllers\MatchController;
use App\Controllers\SeasonController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    // Home
    $app->get('/', [HomeController::class, 'home'])->setName('home');
    $app->get('/test-data', [HomeController::class, 'testDataService'])->setName('test-data');

    // Players
    $app->group('/players', function (RouteCollectorProxy $group) {
        $group->get('', [PlayerController::class, 'listPlayers'])->setName('players.list');
        $group->get('/new', [PlayerController::class, 'createPlayerForm'])->setName('players.create');
        $group->post('', [PlayerController::class, 'createPlayer'])->setName('players.store');
        $group->get('/{id}', [PlayerController::class, 'viewPlayer'])->setName('players.view');
        $group->get('/{id}/edit', [PlayerController::class, 'editPlayerForm'])->setName('players.edit');
        $group->post('/{id}', [PlayerController::class, 'updatePlayer'])->setName('players.update');
        $group->post('/{id}/delete', [PlayerController::class, 'deletePlayer'])->setName('players.delete');
    });

    // Matches
    $app->group('/matches', function (RouteCollectorProxy $group) {
        $group->get('', [MatchController::class, 'matchHistory'])->setName('matches.history');
        $group->get('/new', [MatchController::class, 'createMatchForm'])->setName('matches.create');
        $group->post('', [MatchController::class, 'createMatch'])->setName('matches.store');
        // Weitere Match-Routen hier (z.B. für Details, Bearbeiten, Löschen)
    });

    // Seasons
    $app->group('/seasons', function (RouteCollectorProxy $group) {
        $group->get('', [SeasonController::class, 'listSeasons'])->setName('seasons.list');
        $group->get('/new', [SeasonController::class, 'createSeasonForm'])->setName('seasons.create');
        $group->post('', [SeasonController::class, 'createSeason'])->setName('seasons.store');
        $group->get('/{id}', [SeasonController::class, 'viewSeason'])->setName('seasons.view');
        $group->post('/{id}/end', [SeasonController::class, 'endSeason'])->setName('seasons.end');
    });

    // Weitere Routengruppen hier (z.B. für Turniere, Admin-Bereich etc.)
}; 