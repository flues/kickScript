<?php

declare(strict_types=1);

// Setze die Standardzeitzone für die Anwendung
date_default_timezone_set('Europe/Berlin');

use App\Config\ContainerConfig;
use App\Controllers\HomeController;
use App\Controllers\PlayerController;
use Slim\Factory\AppFactory;
use Slim\Views\TwigMiddleware;
use Slim\Routing\RouteCollectorProxy;
use Nyholm\Psr7\Factory\Psr17Factory;
use DI\Container;

require __DIR__ . '/../vendor/autoload.php';

// Autoloader für unsere App-Klassen
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Create Container using our config
$container = ContainerConfig::createContainer();

// Create PSR-17 Factory
$psr17Factory = new Psr17Factory();

// Set Response Factory für AppFactory
AppFactory::setResponseFactory($psr17Factory);

// Container an die AppFactory übergeben
AppFactory::setContainer($container);

// Create App
$app = AppFactory::create();

// Verbesserte Methode zur Ermittlung des Base Path
// - Funktioniert zuverlässig auf lokalen Entwicklungsservern und in Produktionsumgebungen
if (isset($_SERVER['SCRIPT_NAME'])) {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $basePath = dirname($scriptName);
    
    // Wenn der Pfad nur "/" ist, setze ihn auf leeren String
    if ($basePath === '/' || $basePath === '\\') {
        $basePath = '';
    }
    
    // Setze den Base Path für die App
    $app->setBasePath($basePath);
}

// Lade die Routen
$routes = require __DIR__ . '/../app/routes.php';
$routes($app);

// Add Twig-View Middleware (nach dem Laden der Routen, damit die Routen verfügbar sind)
$twig = $container->get('view');
$app->add(TwigMiddleware::create($app, $twig));

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Run app
$app->run(); 