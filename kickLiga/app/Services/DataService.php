<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Log\LoggerInterface;
use RuntimeException;

class DataService
{
    private string $dataDir;
    private ?LoggerInterface $logger;

    /**
     * DataService Konstruktor
     *
     * @param string $dataDir Pfad zum Datenverzeichnis
     * @param LoggerInterface|null $logger Logger-Instanz
     */
    public function __construct(string $dataDir, ?LoggerInterface $logger = null)
    {
        $this->dataDir = rtrim($dataDir, '/');
        $this->logger = $logger;
        
        // Stelle sicher, dass das Datenverzeichnis existiert
        if (!is_dir($this->dataDir)) {
            if (!mkdir($this->dataDir, 0755, true)) {
                throw new RuntimeException("Konnte das Datenverzeichnis nicht erstellen: {$this->dataDir}");
            }
        }
    }

    /**
     * Liest Daten aus einer JSON-Datei
     *
     * @param string $filename Dateiname ohne Pfad und Erweiterung
     * @return array Die gelesenen Daten
     * @throws RuntimeException Wenn die Datei nicht gelesen werden kann
     */
    public function read(string $filename): array
    {
        $filepath = $this->getFilePath($filename);
        
        if (!file_exists($filepath)) {
            if ($this->logger) {
                $this->logger->info("Datei nicht gefunden, leeres Array zurückgegeben: {$filepath}");
            }
            return [];
        }
        
        $content = file_get_contents($filepath);
        if ($content === false) {
            $error = error_get_last();
            $errorMsg = $error ? $error['message'] : 'Unbekannter Fehler';
            if ($this->logger) {
                $this->logger->error("Fehler beim Lesen der Datei {$filepath}: {$errorMsg}");
            }
            throw new RuntimeException("Konnte Datei nicht lesen: {$filepath}");
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = json_last_error_msg();
            if ($this->logger) {
                $this->logger->error("JSON-Fehler in Datei {$filepath}: {$errorMsg}");
            }
            throw new RuntimeException("Ungültiges JSON in Datei {$filepath}: {$errorMsg}");
        }
        
        return $data;
    }

    /**
     * Schreibt Daten in eine JSON-Datei mit File-Locking
     *
     * @param string $filename Dateiname ohne Pfad und Erweiterung
     * @param array $data Die zu speichernden Daten
     * @return bool True bei Erfolg
     * @throws RuntimeException Wenn die Datei nicht geschrieben werden kann
     */
    public function write(string $filename, array $data): bool
    {
        $filepath = $this->getFilePath($filename);
        $tempFile = $filepath . '.tmp';
        $backupFile = $filepath . '.bak';
        
        // Erstelle das Verzeichnis, falls es nicht existiert
        $dir = dirname($filepath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException("Konnte Verzeichnis nicht erstellen: {$dir}");
        }
        
        // Konvertiere Daten zu JSON mit formatierter Ausgabe
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = json_last_error_msg();
            if ($this->logger) {
                $this->logger->error("Fehler beim Konvertieren nach JSON: {$errorMsg}");
            }
            throw new RuntimeException("Konnte Daten nicht nach JSON konvertieren: {$errorMsg}");
        }
        
        // Schreibe in temporäre Datei
        $tmpHandle = fopen($tempFile, 'w');
        if ($tmpHandle === false) {
            throw new RuntimeException("Konnte temporäre Datei nicht öffnen: {$tempFile}");
        }
        
        // Exklusiver Lock
        if (!flock($tmpHandle, LOCK_EX)) {
            fclose($tmpHandle);
            throw new RuntimeException("Konnte Lock nicht erhalten für: {$tempFile}");
        }
        
        $writeResult = fwrite($tmpHandle, $jsonData);
        if ($writeResult === false) {
            flock($tmpHandle, LOCK_UN);
            fclose($tmpHandle);
            throw new RuntimeException("Fehler beim Schreiben in temporäre Datei: {$tempFile}");
        }
        
        // Leere den Buffer und schließe das Handle
        fflush($tmpHandle);
        flock($tmpHandle, LOCK_UN);
        fclose($tmpHandle);
        
        // Erstelle ein Backup, falls die Originaldatei existiert
        if (file_exists($filepath)) {
            if (!rename($filepath, $backupFile)) {
                if ($this->logger) {
                    $this->logger->warning("Konnte kein Backup erstellen: {$backupFile}");
                }
            }
        }
        
        // Benenne die temporäre Datei in die Zieldatei um
        if (!rename($tempFile, $filepath)) {
            // Versuche, das Backup wiederherzustellen
            if (file_exists($backupFile)) {
                rename($backupFile, $filepath);
            }
            throw new RuntimeException("Konnte temporäre Datei nicht umbenennen: {$tempFile} -> {$filepath}");
        }
        
        if ($this->logger) {
            $this->logger->info("Daten erfolgreich in {$filepath} gespeichert");
        }
        
        return true;
    }

    /**
     * Erstellt einen vollständigen Dateipfad aus dem Dateinamen
     *
     * @param string $filename Dateiname ohne Pfad und Erweiterung
     * @return string Vollständiger Dateipfad
     */
    private function getFilePath(string $filename): string
    {
        // Stelle sicher, dass der Dateiname keine Verzeichnistraversierungen enthält
        $filename = str_replace(['..', '/', '\\'], '', $filename);
        return $this->dataDir . '/' . $filename . '.json';
    }

    /**
     * Erstellt ein Backup aller Datendateien im Sicherungsverzeichnis
     *
     * @param string|null $backupDir Optionales Backup-Verzeichnis
     * @return bool True bei Erfolg
     * @throws RuntimeException Wenn das Backup nicht erstellt werden kann
     */
    public function createBackup(?string $backupDir = null): bool
    {
        if ($backupDir === null) {
            $backupDir = $this->dataDir . '/backups/' . date('Y-m-d_H-i-s');
        }
        
        if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) {
            throw new RuntimeException("Konnte Backup-Verzeichnis nicht erstellen: {$backupDir}");
        }
        
        $jsonFiles = glob($this->dataDir . '/*.json');
        if (empty($jsonFiles)) {
            if ($this->logger) {
                $this->logger->info("Keine Dateien für Backup gefunden");
            }
            return true;
        }
        
        foreach ($jsonFiles as $file) {
            $filename = basename($file);
            $destination = $backupDir . '/' . $filename;
            
            if (!copy($file, $destination)) {
                if ($this->logger) {
                    $this->logger->error("Fehler beim Kopieren von {$file} nach {$destination}");
                }
                throw new RuntimeException("Konnte Datei nicht für Backup kopieren: {$file}");
            }
        }
        
        if ($this->logger) {
            $this->logger->info("Backup erfolgreich erstellt in: {$backupDir}");
        }
        
        return true;
    }
} 