<?php

declare(strict_types=1);

// Setze die Standardzeitzone für die Anwendung
date_default_timezone_set('Europe/Berlin');

require __DIR__ . '/kickLiga/vendor/autoload.php';

// Autoloader für unsere App-Klassen
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/kickLiga/app/';
    
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

use App\Config\ContainerConfig;
use App\Services\SeasonService;
use App\Controllers\HomeController;
use App\Controllers\SeasonController;
use App\Models\Season;

echo "=== Umfassender Season Test ===\n\n";

try {
    // Container erstellen
    $container = ContainerConfig::createContainer();
    
    // Services holen
    $seasonService = $container->get(SeasonService::class);
    $homeController = $container->get(HomeController::class);
    $seasonController = $container->get(SeasonController::class);
    
    echo "1. Raw Season Data aus seasons.json:\n";
    $dataService = $container->get(\App\Services\DataService::class);
    $rawSeasonData = $dataService->read('seasons');
    echo "Raw Data: " . json_encode($rawSeasonData, JSON_PRETTY_PRINT) . "\n\n";
    
    echo "2. Season-Objekte aus SeasonService:\n";
    $seasons = $seasonService->getAllSeasons();
    echo "Anzahl Saisons: " . count($seasons) . "\n";
    
    foreach ($seasons as $season) {
        echo "- ID: " . $season->getId() . "\n";
        echo "  Name: '" . $season->getName() . "'\n";
        echo "  Start: " . $season->getStartDate()->format('d.m.Y H:i:s') . "\n";
        echo "  Ende: " . ($season->getEndDate() ? $season->getEndDate()->format('d.m.Y H:i:s') : 'null') . "\n";
        echo "  isActive(): " . ($season->isActive() ? 'true' : 'false') . "\n";
        echo "  JSON: " . json_encode($season->jsonSerialize()) . "\n";
        echo "\n";
    }
    
    echo "3. Aktive Saison Test:\n";
    $activeSeason = $seasonService->getActiveSeason();
    if ($activeSeason) {
        echo "Aktive Saison gefunden:\n";
        echo "- Name: '" . $activeSeason->getName() . "'\n";
        echo "- isActive(): " . ($activeSeason->isActive() ? 'true' : 'false') . "\n";
        echo "- JSON: " . json_encode($activeSeason->jsonSerialize()) . "\n";
    } else {
        echo "PROBLEM: Keine aktive Saison gefunden!\n";
        
        // Debug: Prüfe alle Saisons auf isActive
        echo "Debug - Alle Saisons isActive Status:\n";
        foreach ($seasons as $season) {
            echo "- " . $season->getName() . ": isActive=" . ($season->isActive() ? 'true' : 'false') . "\n";
        }
    }
    
    echo "\n4. Test Season::fromArray() Methode:\n";
    if (!empty($rawSeasonData)) {
        $firstSeasonData = reset($rawSeasonData);
        echo "Erste Season Raw Data: " . json_encode($firstSeasonData) . "\n";
        
        $testSeason = Season::fromArray($firstSeasonData);
        echo "Nach fromArray():\n";
        echo "- Name: '" . $testSeason->getName() . "'\n";
        echo "- isActive(): " . ($testSeason->isActive() ? 'true' : 'false') . "\n";
        echo "- JSON: " . json_encode($testSeason->jsonSerialize()) . "\n";
    }
    
    echo "\n5. Test HomeController Data:\n";
    // Simuliere Request/Response für HomeController
    $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();
    $request = $psr17Factory->createServerRequest('GET', '/');
    $response = $psr17Factory->createResponse();
    
    // Hole die Daten, die der HomeController verwenden würde
    $playerService = $container->get(\App\Services\PlayerService::class);
    $matchService = $container->get(\App\Services\MatchService::class);
    
    $topPlayers = $playerService->getTopPlayers(5);
    $activeSeason = $seasonService->getActiveSeason();
    
    echo "HomeController würde folgende activeSeason bekommen:\n";
    if ($activeSeason) {
        echo "- Name: '" . $activeSeason->getName() . "'\n";
        echo "- Typ: " . get_class($activeSeason) . "\n";
        
        // Test der Erweiterung wie im HomeController
        $extendedSeason = (object) array_merge((array) $activeSeason, [
            'durationInDays' => $activeSeason->getDurationInDays(),
            'effectiveEndDate' => $activeSeason->getEffectiveEndDate()
        ]);
        
        echo "Nach Erweiterung:\n";
        echo "- Name: '" . (isset($extendedSeason->name) ? $extendedSeason->name : 'FEHLT!') . "'\n";
        echo "- durationInDays: " . (isset($extendedSeason->durationInDays) ? $extendedSeason->durationInDays : 'FEHLT!') . "\n";
    } else {
        echo "PROBLEM: activeSeason ist null!\n";
    }
    
} catch (Exception $e) {
    echo "FEHLER: " . $e->getMessage() . "\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test beendet ===\n"; 