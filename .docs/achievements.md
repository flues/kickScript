# Achievement-System

Dieses Dokument beschreibt das Achievement-System (Badges) der Kickerliga, das Spielern automatisch besondere Auszeichnungen für verschiedene Leistungen verleiht.

## Übersicht

Das Achievement-System dient dazu, Spieler für besondere Leistungen zu belohnen und die Motivation und das Engagement zu fördern. Achievements (Badges) werden automatisch basierend auf der Spielerperformance vergeben und auf Spielerprofilen angezeigt.

## Arten von Achievements

Basierend auf der README implementieren wir folgende Achievements:

| Badge | Name | Beschreibung | Bedingung |
|-------|------|--------------|-----------|
| 🏆 | Winning Streak | Siegesserie | 3+ oder 5+ Siege in Folge |
| 👑 | Höchster Sieg | Deutlicher Sieg | 10+ Tore Differenz |
| 💀 | Bad Keeper | Schwache Defensive | Meiste Gegentore |
| ⚽ | Torschützenkönig | Offensivstärke | Meiste erzielte Tore |
| ⭐ | Perfekte Bilanz | Nur Siege | 100% Siegquote (min. 3 Spiele) |
| 🚀 | Tormaschine | Treffsicherheit | Ø 5+ Tore/Spiel (min. 2 Spiele) |
| 🛡️ | Eiserne Abwehr | Starke Defensive | Ø <3 Gegentore/Spiel (min. 3 Spiele) |
| 😵 | Unglücksrabe | Pechsträhne | 0 Siege bei 5+ Spielen |
| 🎖️ | Veteran | Erfahrung | 10+ absolvierte Spiele |
| 📈 | Tordifferenz-König | Dominanz | +20 Tordifferenz insgesamt |
| ⚖️ | Ausgewogen | Balance | Gleiche Anzahl Tore/Gegentore (min. 5 Spiele) |

## Implementierungsdetails

### Achievement-Datenmodell

Jedes Achievement wird als Objekt in der Datenstruktur dargestellt:

```php
<?php

declare(strict_types=1);

namespace App\Models;

class Achievement
{
    private string $id;
    private string $name;
    private string $description;
    private string $icon;
    private string $condition;
    private int $level = 1;
    private bool $isActive = true;
    
    // Getter und Setter...
}
```

### Achievement-Speicherformat

Die Achievements werden in JSON-Dateien gespeichert. Jeder Spieler hat eine Liste seiner errungenen Badges:

```json
{
  "player_id": "player123",
  "achievements": [
    {
      "id": "winning_streak",
      "earned_date": "2023-09-15",
      "level": 1,
      "match_id": "match456"
    },
    {
      "id": "high_score",
      "earned_date": "2023-09-18",
      "level": 2,
      "match_id": "match789"
    }
  ]
}
```

Für Badges mit mehreren Stufen (z.B. Winning Streak 3+ und 5+) verwenden wir Levels.

### Achievement-Service

Der `AchievementService` ist verantwortlich für die Überprüfung und Vergabe von Achievements:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Player;
use App\Models\Match;

class AchievementService
{
    private $playerService;
    private $matchService;
    
    public function __construct(PlayerService $playerService, MatchService $matchService)
    {
        $this->playerService = $playerService;
        $this->matchService = $matchService;
    }
    
    public function checkAchievementsForPlayer(string $playerId): array
    {
        $player = $this->playerService->getPlayer($playerId);
        $matches = $this->matchService->getMatchesForPlayer($playerId);
        
        $earnedAchievements = [];
        
        // Prüfe alle möglichen Achievements
        $earnedAchievements = array_merge(
            $earnedAchievements,
            $this->checkWinningStreak($player, $matches),
            $this->checkHighestWin($player, $matches),
            $this->checkBadKeeper($player, $matches),
            // ... weitere Achievement-Checks
        );
        
        return $earnedAchievements;
    }
    
    private function checkWinningStreak(Player $player, array $matches): array
    {
        // Implementierung für Winning Streak Achievement
        $earnedAchievements = [];
        $streakCount = 0;
        
        foreach ($matches as $match) {
            if ($match->isWinner($player->getId())) {
                $streakCount++;
            } else {
                $streakCount = 0;
            }
            
            if ($streakCount >= 3 && $streakCount < 5) {
                $earnedAchievements[] = [
                    'id' => 'winning_streak',
                    'level' => 1,
                    'match_id' => $match->getId()
                ];
            } elseif ($streakCount >= 5) {
                $earnedAchievements[] = [
                    'id' => 'winning_streak',
                    'level' => 2,
                    'match_id' => $match->getId()
                ];
            }
        }
        
        return $earnedAchievements;
    }
    
    // Weitere Methoden für andere Achievements...
}
```

## Integration im System

Das Achievement-System ist in verschiedene Teile des Systems integriert:

### Nach Spielen

Nach jedem registrierten Spiel:
1. Der `MatchController` ruft den `AchievementService` auf
2. Der Service überprüft alle möglichen neuen Achievements für beide Spieler
3. Neue Achievements werden gespeichert und dem Spieler zugewiesen

### Spielerprofile

Auf Spielerprofilen:
1. Alle verdienten Badges werden angezeigt
2. Badges sind nach Datum sortiert
3. Badges haben Tooltips mit Erklärungen
4. Spezielle Hervorhebung für seltene Achievements

### Startseite / Dashboard

Auf der Startseite:
1. Kürzlich verdiente Achievements werden in einem Feed angezeigt
2. Leaderboard für die Spieler mit den meisten Badges

## UI-Darstellung

Die Badges werden visuell attraktiv dargestellt:
- Farbige Icons basierend auf Phosphor Icons
- Unterschiedliche Rahmen je nach Seltenheit des Achievements
- Animations-Effekte beim Erhalt eines neuen Badges
- Tooltips mit detaillierten Informationen

## Erweiterbarkeit

Das System ist so konzipiert, dass leicht neue Achievements hinzugefügt werden können:
1. Neuen Achievement-Typ in der Konfigurationsdatei definieren
2. Prüflogik im AchievementService implementieren
3. Icon und Styling hinzufügen

Neue Achievements werden automatisch im System berücksichtigt, ohne dass bestehende Spielerdaten geändert werden müssen. 