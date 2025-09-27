<?php
// Simple script to simulate a visitor calling the HomeController and triggering the lazy spawn.
require __DIR__ . '/../kickLiga/vendor/autoload.php';

use App\Controllers\HomeController;
use DI\ContainerBuilder;

$builder = new ContainerBuilder();
$container = $builder->build();

// Minimal rough wiring: create the controller with minimal dependencies from container if available.
$view = $container->has(\Slim\Views\Twig::class) ? $container->get(\Slim\Views\Twig::class) : null;
$dataService = $container->has(\App\Services\DataService::class) ? $container->get(\App\Services\DataService::class) : null;
$playerService = $container->has(\App\Services\PlayerService::class) ? $container->get(\App\Services\PlayerService::class) : null;
$matchService = $container->has(\App\Services\MatchService::class) ? $container->get(\App\Services\MatchService::class) : null;
$seasonService = $container->has(\App\Services\SeasonService::class) ? $container->get(\App\Services\SeasonService::class) : null;

$controller = new HomeController($view ?: new class {
    public function render($resp, $tpl, $vars = []) { return null; }
}, $dataService, $playerService, $matchService, $seasonService);

// Call the private method via reflection for testing purposes
$ref = new ReflectionClass($controller);
$m = $ref->getMethod('maybeSpawnDailyAnalysis');
$m->setAccessible(true);
$m->invoke($controller);

echo "maybeSpawnDailyAnalysis invoked.\n";
