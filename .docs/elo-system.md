# ELO-Rating System

Dieses Dokument beschreibt die Implementierung des ELO-Ratingsystems für die Kickerliga.

## Grundprinzipien des ELO-Systems

Das ELO-System ist ein Bewertungssystem, das ursprünglich für Schach entwickelt wurde und nun für unsere Kicker-Liga angepasst wird. Die Kernprinzipien sind:

1. Jeder Spieler hat eine Bewertungszahl (Rating)
2. Die Änderung des Ratings nach einem Spiel basiert auf:
   - Dem erwarteten Ergebnis (basierend auf der Differenz der Ratings)
   - Dem tatsächlichen Ergebnis
   - Einem Gewichtungsfaktor (K-Faktor)

## Implementierungsdetails

### Startrating

Neue Spieler beginnen mit einem Rating von 1500 Punkten.

### Berechnung der ELO-Änderung

Nach jedem Spiel wird das Rating der Spieler wie folgt angepasst:

```
Neues Rating = Altes Rating + K * (Tatsächliches Ergebnis - Erwartetes Ergebnis)
```

Wobei:
- **K-Faktor**: 32 (Standardgewichtung)
- **Tatsächliches Ergebnis**: 1 für Sieg, 0 für Niederlage
- **Erwartetes Ergebnis**: Berechnet basierend auf dem Ratingunterschied

### Berechnung des erwarteten Ergebnisses

```
Erwartetes Ergebnis = 1 / (1 + 10^((GegnerRating - SpielerRating) / 400))
```

Diese Formel gibt eine Wahrscheinlichkeit zwischen 0 und 1 zurück, die die Gewinnchance basierend auf den Ratings darstellt.

### Tordifferenz-Modifikator

Um die Tordifferenz im ELO-System zu berücksichtigen, implementieren wir einen Modifikator:

```
Modifizierter K-Faktor = K * (1 + log10(Tordifferenz) / 5)
```

Für eine Tordifferenz von 1 ist der Modifikator 1.0, für:
- Tordifferenz 5: K-Faktor * 1.14
- Tordifferenz 10: K-Faktor * 1.2

Der modifizierte K-Faktor wird nach oben auf 48 begrenzt (bei sehr hohen Tordifferenzen).

## Code-Implementierung

Der ELO-Service wird in `app/Services/EloService.php` implementiert:

```php
<?php

declare(strict_types=1);

namespace App\Services;

class EloService
{
    private const DEFAULT_K_FACTOR = 32;
    private const DEFAULT_RATING = 1500;
    private const MAX_K_FACTOR = 48;

    public function calculateNewRatings(
        int $playerRating,
        int $opponentRating,
        bool $playerWon,
        int $goalDifference
    ): int {
        $expectedOutcome = $this->calculateExpectedOutcome($playerRating, $opponentRating);
        $actualOutcome = $playerWon ? 1.0 : 0.0;
        $kFactor = $this->getModifiedKFactor($goalDifference);
        
        $ratingChange = (int)round($kFactor * ($actualOutcome - $expectedOutcome));
        return $playerRating + $ratingChange;
    }
    
    private function calculateExpectedOutcome(int $playerRating, int $opponentRating): float
    {
        return 1.0 / (1.0 + pow(10, ($opponentRating - $playerRating) / 400.0));
    }
    
    private function getModifiedKFactor(int $goalDifference): float
    {
        if ($goalDifference <= 1) {
            return self::DEFAULT_K_FACTOR;
        }
        
        $modifier = 1.0 + (log10($goalDifference) / 5.0);
        $modifiedK = self::DEFAULT_K_FACTOR * $modifier;
        
        return min($modifiedK, self::MAX_K_FACTOR);
    }
    
    public function getDefaultRating(): int
    {
        return self::DEFAULT_RATING;
    }
}
```

## Anwendung im System

Der ELO-Service wird vom MatchService nach jedem registrierten Spiel aufgerufen:

1. Spielerverwaltung liest aktuelle ELO-Werte der beteiligten Spieler
2. Nach Spieleingabe werden die neuen ELO-Werte berechnet
3. Die Verlaufshistorie der ELO-Werte wird für jeden Spieler gespeichert
4. Die aktualisierte Rangliste wird neu generiert

## ELO-Historie

Für jeden Spieler wird eine ELO-Historie geführt, um die Entwicklung grafisch darstellen zu können:

```json
{
  "elo_history": [
    {
      "date": "2023-09-01",
      "rating": 1500,
      "change": 0
    },
    {
      "date": "2023-09-03",
      "rating": 1532,
      "change": 32,
      "opponent": "spieler2",
      "match_id": "match123"
    },
    // ... weitere Einträge
  ]
}
```

## UI-Integration

Die ELO-Ratings werden an verschiedenen Stellen der Benutzeroberfläche angezeigt:

1. **Spielerprofil**: Aktuelles Rating und Verlaufsgrafik
2. **Rangliste**: Sortierung nach aktuellem ELO-Rating
3. **Spiele**: Anzeige der Rating-Änderung nach jedem Spiel
4. **Turniere**: Berücksichtigung des Ratings bei der Bracket-Generierung

## Saisonale Anpassungen

Am Ende einer Saison werden die ELO-Ratings wie folgt angepasst:

1. Die ELO-Historie wird archiviert
2. Neue Saison-Startratings werden berechnet:
   ```
   Neues Saison-Rating = 1500 + (Altes Rating - 1500) * 0.5
   ```
3. Diese Berechnung reduziert extreme Ratings und gibt Spielern mit niedrigeren Ratings die Chance, in der neuen Saison aufzuholen 