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
        $group->get('/coinflip', [MatchController::class, 'coinflipForm'])->setName('matches.coinflip');
        $group->post('/coinflip', [MatchController::class, 'performCoinflip'])->setName('matches.coinflip.perform');
        $group->post('/coinflip-ajax', [MatchController::class, 'performCoinflipAjax'])->setName('matches.coinflip.ajax');
        $group->post('/coinflip-winner-side', [MatchController::class, 'coinflipWinnerSideChoice'])->setName('matches.coinflip.winner.side');
        $group->post('/{id}/delete', [MatchController::class, 'deleteMatch'])->setName('matches.delete');
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