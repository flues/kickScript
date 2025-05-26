<?php

declare(strict_types=1);

namespace App\Models;

use JsonSerializable;

/**
 * Season Model - Single Source of Truth
 * 
 * Speichert nur Metadaten (Name, Zeitraum, Status).
 * Alle Statistiken und Tabellen werden zur Laufzeit aus matches.json berechnet.
 */
class Season implements JsonSerializable
{
    private string $id;
    private string $name;
    private \DateTimeImmutable $startDate;
    private ?\DateTimeImmutable $endDate = null;
    private bool $isActive = true;

    /**
     * Konstruktor
     *
     * @param string $name Name der Saison
     * @param \DateTimeImmutable|null $startDate Startdatum der Saison
     */
    public function __construct(string $name, ?\DateTimeImmutable $startDate = null)
    {
        $this->id = 'season_' . uniqid();
        $this->name = $name;
        $this->startDate = $startDate ?? new \DateTimeImmutable();
    }

    /**
     * Erstellt ein Season-Objekt aus einem Array
     *
     * @param array $data Saisondaten
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $startDate = isset($data['startDate']) 
            ? new \DateTimeImmutable('@' . $data['startDate']) 
            : null;
            
        $season = new self($data['name'], $startDate);
        
        if (isset($data['id'])) {
            $season->id = $data['id'];
        }
        
        if (isset($data['endDate']) && $data['endDate'] !== null) {
            $season->endDate = new \DateTimeImmutable('@' . $data['endDate']);
        }
        
        if (isset($data['isActive'])) {
            $season->isActive = $data['isActive'];
        }
        
        return $season;
    }

    /**
     * Beendet die Saison
     *
     * @param \DateTimeImmutable|null $endDate End date of the season
     * @return self
     */
    public function endSeason(?\DateTimeImmutable $endDate = null): self
    {
        $this->endDate = $endDate ?? new \DateTimeImmutable();
        $this->isActive = false;
        return $this;
    }

    // === GETTER/SETTER für Metadaten ===

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    /**
     * Berechnet die Dauer der Saison in Tagen
     */
    public function getDurationInDays(): int
    {
        $endDate = $this->endDate ?? new \DateTimeImmutable();
        return $this->startDate->diff($endDate)->days;
    }

    /**
     * Prüft, ob ein Match in den Saisonzeitraum fällt
     */
    public function isMatchInSeason(\DateTimeImmutable $matchDate): bool
    {
        $seasonEnd = $this->endDate ?? new \DateTimeImmutable('last day of this month 23:59:59');
        return $matchDate >= $this->startDate && $matchDate <= $seasonEnd;
    }

    /**
     * JSON-Serialisierung - nur Metadaten
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'startDate' => $this->startDate->getTimestamp(),
            'endDate' => $this->endDate?->getTimestamp(),
            'isActive' => $this->isActive
        ];
    }
} 