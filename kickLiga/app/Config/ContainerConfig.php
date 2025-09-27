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
use App\Services\ComputationService;
use DI\Container;
use DI\ContainerBuilder;
use Dotenv\Dotenv;
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
        // Load environment variables from possible .env locations.
        // Prefer the kickLiga directory, but also check the repository root (one level up).
        $projectRoot = realpath(__DIR__ . '/../../'); // typically .../kickLiga
        $candidatePaths = [];
        if ($projectRoot && is_dir($projectRoot)) {
            $candidatePaths[] = $projectRoot; // kickLiga/
            $parent = realpath($projectRoot . '/..');
            if ($parent && is_dir($parent)) {
                $candidatePaths[] = $parent; // repo root (one level up)
            }
        }

        foreach ($candidatePaths as $envPath) {
            $envFile = $envPath . '/.env';
            if (!file_exists($envFile)) {
                continue;
            }

            // Prefer vlucas/phpdotenv if available
            if (class_exists(\Dotenv\Dotenv::class)) {
                try {
                    $dotenv = \Dotenv\Dotenv::createImmutable($envPath);
                    $dotenv->load();
                    // stop after first successful load
                    break;
                } catch (\Throwable $e) {
                    // If dotenv cannot be loaded from this path, continue to next
                    continue;
                }
            }

            // Fallback: simple parser if phpdotenv isn't installed
            try {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }

                    // Split at first '='
                    $parts = explode('=', $line, 2);
                    if (count($parts) !== 2) {
                        continue;
                    }

                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    // Remove surrounding quotes
                    if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                        $value = substr($value, 1, -1);
                    }

                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
                // stop after first successful parse
                break;
            } catch (\Throwable $e) {
                // ignore and continue to next candidate
                continue;
            }
        }

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
            
            // EloService (keine Abhängigkeiten außer Logger)
            EloService::class => function (Container $container) {
                return new EloService($container->get(LoggerInterface::class));
            },
            
            // ComputationService (benötigt nur DataService und EloService)
            ComputationService::class => function (Container $container) {
                return new ComputationService(
                    $container->get(DataService::class),
                    $container->get(EloService::class),
                    $container->get(LoggerInterface::class)
                );
            },
            
            // PlayerService (benötigt DataService und ComputationService)
            PlayerService::class => function (Container $container) {
                return new PlayerService(
                    $container->get(DataService::class),
                    $container->get(ComputationService::class),
                    $container->get(LoggerInterface::class)
                );
            },
            
            // MatchService (benötigt DataService, EloService und ComputationService)
            MatchService::class => function (Container $container) {
                return new MatchService(
                    $container->get(DataService::class),
                    null, // PlayerService entfernt um zirkuläre Abhängigkeit zu vermeiden
                    $container->get(EloService::class),
                    $container->get(ComputationService::class),
                    $container->get(LoggerInterface::class)
                );
            },
            
            // SeasonService (benötigt DataService und ComputationService)
            SeasonService::class => function (Container $container) {
                return new SeasonService(
                    $container->get(DataService::class),
                    $container->get(ComputationService::class),
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

            // Gemini AI Service
            \App\Services\GeminiService::class => function (Container $container) {
                $apiKey = getenv('GEMINI_API_KEY') ?: null;
                return new \App\Services\GeminiService($apiKey, $container->get(LoggerInterface::class));
            },
            
            // Twig View
            'view' => function (Container $container) {
                $twig = Twig::create(__DIR__ . '/../../templates', [
                    'cache' => false,
                    'debug' => true,
                    'auto_reload' => true
                ]);

                // Services werden bei Bedarf in den Controllern injiziert
                // um zirkuläre Abhängigkeiten zu vermeiden

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