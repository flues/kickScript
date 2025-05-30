# Cursor Rules Struktur

Diese Verzeichnis enthält die Cursor-Regeln für das Kickerliga Management System, migriert vom veralteten `.cursor-rules` Format zum neuen MDC-basierten Project Rules System.

## Regel-Dateien

### `basic-project-guidelines.mdc` (Always Apply)
- Grundlegende Verhaltensregeln und Coding Standards
- Wird bei jeder AI-Interaktion automatisch angewendet
- Sprache, Code-Style, Sicherheit, Development Workflow

### `project-structure.mdc` (Auto Attached)
- Projektstruktur und Architektur-Guidelines
- Wird automatisch bei PHP/Twig-Dateien angewendet
- Datenspeicherung, Migration-Richtlinien

### `frontend-design.mdc` (Auto Attached)
- Frontend-Design und UI-Guidelines
- Wird bei CSS/JS/Template-Dateien angewendet
- Bootstrap Theme, Video Background System

### `documentation.mdc` (Auto Attached)
- Dokumentations-Guidelines
- Wird bei .docs-Dateien und README-Dateien angewendet
- Dokumentationsstruktur, Git-Management

### `implemented-features.mdc` (Manual)
- Übersicht implementierter Features
- Nur bei expliziter Erwähnung mit @implemented-features angewendet
- Status der Core Features

## Migration von .cursor-rules

Die alte `.cursor-rules` Datei wurde in logische Bereiche aufgeteilt:
- Bessere Kontextualisierung durch Glob-Pattern
- Gezielere Anwendung je nach Arbeitsbereich
- Moderne MDC-Formatierung mit Metadaten

Die alte Datei kann nach erfolgreicher Migration gelöscht werden. 