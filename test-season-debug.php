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
use App\Models\Season;

echo "=== Season Debug Test ===\n\n";

try {
    // Container erstellen
    $container = ContainerConfig::createContainer();
    
    // SeasonService holen
    $seasonService = $container->get(SeasonService::class);
    
    echo "1. Alle Saisons laden:\n";
    $seasons = $seasonService->getAllSeasons();
    echo "Anzahl Saisons: " . count($seasons) . "\n";
    
    foreach ($seasons as $season) {
        echo "- ID: " . $season->getId() . "\n";
        echo "  Name: " . $season->getName() . "\n";
        echo "  Start: " . $season->getStartDate()->format('d.m.Y H:i:s') . "\n";
        echo "  Ende: " . ($season->getEndDate() ? $season->getEndDate()->format('d.m.Y H:i:s') : 'null') . "\n";
        echo "  Effektives Ende: " . $season->getEffectiveEndDate()->format('d.m.Y H:i:s') . "\n";
        echo "  Aktiv: " . ($season->isActive() ? 'ja' : 'nein') . "\n";
        echo "  Tage aktiv: " . $season->getDurationInDays() . "\n";
        echo "\n";
    }
    
    echo "2. Aktive Saison:\n";
    $activeSeason = $seasonService->getActiveSeason();
    if ($activeSeason) {
        echo "Aktive Saison gefunden: " . $activeSeason->getName() . "\n";
        echo "Start: " . $activeSeason->getStartDate()->format('d.m.Y H:i:s') . "\n";
        echo "Tage seit Start: " . $activeSeason->getDurationInDays() . "\n";
        
        // Test der Berechnung
        $now = new \DateTimeImmutable();
        $diff = $activeSeason->getStartDate()->diff($now);
        echo "Manuelle Berechnung: " . $diff->days . " Tage\n";
        echo "Heute: " . $now->format('d.m.Y H:i:s') . "\n";
    } else {
        echo "Keine aktive Saison gefunden!\n";
    }
    
    echo "\n3. Test der Season-Statistiken:\n";
    if ($activeSeason) {
        $statistics = $seasonService->getSeasonStatistics($activeSeason->getId());
        echo "Statistiken: " . json_encode($statistics, JSON_PRETTY_PRINT) . "\n";
    }
    
} catch (Exception $e) {
    echo "FEHLER: " . $e->getMessage() . "\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test beendet ===\n"; 