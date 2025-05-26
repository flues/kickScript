<?php

declare(strict_types=1);

namespace App\Config;

use App\Controllers\HomeController;
use App\Controllers\PlayerController;
use App\Controllers\MatchController;
use App\Controllers\SeasonController;
use App\Services\DataService;
use App\Services\PlayerService;
use App\Services\MatchService;
use App\Services\EloService;
use App\Services\SeasonService;
use App\Services\AchievementService;
use App\Services\CoinflipService;
use DI\Container;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class ContainerConfig
{
    /**
     * Erstellt und konfiguriert den Dependency Injection Container
     *
     * @return Container Der konfigurierte Container
     */
    public static function createContainer(): Container
    {
        $builder = new ContainerBuilder();
        
        // Definiere alle Container-Definitionen
        $definitions = [
            // Logger
            LoggerInterface::class => function () {
                $logger = new Logger('kickLiga');
                $logDir = __DIR__ . '/../../logs';
                
                if (!is_dir($logDir) && !mkdir($logDir, 0755, true)) {
                    // Standardausgabe verwenden, wenn das Logverzeichnis nicht erstellt werden kann
                    $logger->pushHandler(new StreamHandler('php://stderr', Logger::WARNING));
                    return $logger;
                }
                
                $logger->pushHandler(new StreamHandler(
                    $logDir . '/app.log',
                    Logger::DEBUG
                ));
                
                return $logger;
            },
            
            // DataService
            DataService::class => function (Container $container) {
                $dataDirectory = __DIR__ . '/../../data';
                return new DataService(
                    $dataDirectory,
                    $container->get(LoggerInterface::class)
                );
            },
            
            // PlayerService
            PlayerService::class => function (Container $container) {
                return new PlayerService(
                    $container->get(DataService::class),
                    $container->get(LoggerInterface::class)
                );
            },
            
            // EloService
            EloService::class => function (Container $container) {
                return new EloService($container->get(LoggerInterface::class));
            },
            
            // MatchService
            MatchService::class => function (Container $container) {
                return new MatchService(
                    $container->get(DataService::class),
                    $container->get(PlayerService::class),
                    $container->get(EloService::class),
                    $container->get(LoggerInterface::class)
                );
            },
            
            // SeasonService
            SeasonService::class => function (Container $container) {
                return new SeasonService(
                    $container->get(DataService::class),
                    $container->get(PlayerService::class),
                    $container->get(LoggerInterface::class)
                );
            },
            
            // AchievementService
            AchievementService::class => function (Container $container) {
                return new AchievementService(
                    $container->get(PlayerService::class),
                    $container->get(MatchService::class),
                    $container->get(LoggerInterface::class)
                );
            },
            
            // CoinflipService
            CoinflipService::class => function () {
                return new CoinflipService();
            },
            
            // Twig View
            'view' => function (Container $container) {
                $twig = Twig::create(__DIR__ . '/../../templates', [
                    'cache' => false,
                    'debug' => true,
                    'auto_reload' => true
                ]);

                // Services als globale Variablen für Twig verfügbar machen
                $twig->getEnvironment()->addGlobal('player_service', $container->get(PlayerService::class));
                $twig->getEnvironment()->addGlobal('season_service', $container->get(SeasonService::class));

                return $twig;
            },
            
            // Controller
            HomeController::class => function (Container $container) {
                $matchService = null;
                $seasonService = null;
                
                if ($container->has(MatchService::class)) {
                    $matchService = $container->get(MatchService::class);
                }
                
                if ($container->has(SeasonService::class)) {
                    $seasonService = $container->get(SeasonService::class);
                }
                
                return new HomeController(
                    $container->get('view'),
                    $container->get(DataService::class),
                    $container->get(PlayerService::class),
                    $matchService,
                    $seasonService
                );
            },
            
            PlayerController::class => function (Container $container) {
                return new PlayerController(
                    $container->get('view'),
                    $container->get(PlayerService::class),
                    $container->get(MatchService::class),
                    $container->get(AchievementService::class)
                );
            },

            MatchController::class => function (Container $container) {
                return new MatchController(
                    $container->get('view'),
                    $container->get(MatchService::class),
                    $container->get(PlayerService::class),
                    $container->get(CoinflipService::class),
                    $container->get(SeasonService::class),
                    $container->get(AchievementService::class)
                );
            },
            
            SeasonController::class => function (Container $container) {
                return new SeasonController(
                    $container->get('view'),
                    $container->get(SeasonService::class),
                    $container->get(MatchService::class),
                    $container->get(PlayerService::class)
                );
            }
        ];
        
        $builder->addDefinitions($definitions);
        
        return $builder->build();
    }
} 