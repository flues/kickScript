# 🏗️ Kickerliga System Architektur

## 📊 Architektur-Diagramm

Dieses Diagramm zeigt die vollständige Systemarchitektur der Kickerliga Management System mit allen wichtigen Komponenten und Datenflüssen.

```mermaid
graph TB
    subgraph "🌐 Web Layer"
        U[👤 User] --> B[🌐 Browser]
        B --> WS[🌍 Web Server<br/>Apache/Nginx]
        WS --> EP[📍 Entry Point<br/>public/index.php]
    end

    subgraph "🚀 Application Bootstrap"
        EP --> AL[📦 Autoloader<br/>PSR-4 Classes]
        EP --> CC[⚙️ Container Config<br/>DI Container]
        EP --> SF[🏗️ Slim Framework 4<br/>App Factory]
        SF --> RT[🛣️ Routes<br/>app/routes.php]
        SF --> TM[🎨 Twig Middleware<br/>Template Engine]
    end

    subgraph "🛣️ Routing Layer"
        RT --> HR[🏠 Home Routes<br/>'/' dashboard]
        RT --> PR[👥 Player Routes<br/>'/players/*']
        RT --> MR[⚽ Match Routes<br/>'/matches/*']
        RT --> SR[🏆 Season Routes<br/>'/seasons/*']
    end

    subgraph "🎮 Controller Layer"
        HR --> HC[🏠 HomeController<br/>Dashboard & Stats]
        PR --> PC[👥 PlayerController<br/>CRUD Operations]
        MR --> MC[⚽ MatchController<br/>Match Management]
        SR --> SC[🏆 SeasonController<br/>Season Management]
    end

    subgraph "⚙️ Service Layer - Business Logic"
        HC --> CS[📊 ComputationService<br/>Core Analytics Engine]
        PC --> PS[👤 PlayerService<br/>Player Management]
        MC --> MS[⚽ MatchService<br/>Match Processing]
        MC --> CFS[🪙 CoinflipService<br/>Fair Side Selection]
        SC --> SS[🏆 SeasonService<br/>Season Logic]
        
        PS --> ES[📈 EloService<br/>Rating Calculations]
        MS --> ES
        PS --> AS[🏅 AchievementService<br/>Reward System]
        CS --> DS[💾 DataService<br/>JSON File Operations]
    end

    subgraph "📊 Data Models"
        PS --> PM[👤 Player Model<br/>Player Entity]
        MS --> MM[⚽ GameMatch Model<br/>Match Entity]
        SS --> SM[🏆 Season Model<br/>Season Entity]
    end

    subgraph "💾 Data Storage Layer - Single Source of Truth"
        DS --> MJ[📄 matches.json<br/>🔥 Primary Data Source]
        DS --> PJ[📄 players_meta.json<br/>Static Player Info]
        DS --> SJ[📄 seasons.json<br/>Season Definitions]
        
        MJ --> SSOT[⭐ SSOT Principle<br/>All stats computed<br/>from matches.json]
    end

    subgraph "🎨 Presentation Layer"
        HC --> HT[🏠 Home Templates<br/>Dashboard Views]
        PC --> PT[👥 Player Templates<br/>Player CRUD Views]
        MC --> MT[⚽ Match Templates<br/>Match Forms & History]
        SC --> ST[🏆 Season Templates<br/>Season Management]
    end

    subgraph "📱 Frontend Assets"
        HT --> CSS[🎨 Bootstrap 5<br/>Dark Theme UI]
        PT --> CSS
        MT --> CSS
        ST --> CSS
        
        HT --> JS[⚡ JavaScript<br/>Chart.js & Interactions]
        PT --> JS
        MT --> JS
        ST --> JS
        
        CSS --> VBG[🎥 Video Backgrounds<br/>Immersive UX]
        JS --> PI[🔗 Phosphor Icons<br/>Modern UI Icons]
    end

    subgraph "🔥 Core Features"
        SSOT --> ELO[📈 ELO Rating System<br/>Dynamic Calculations]
        SSOT --> ACH[🏅 Achievement System<br/>12 Different Types]
        SSOT --> TST[⚖️ Table Side Tracking<br/>Blue vs White Analysis]
        SSOT --> STATS[📊 Real-time Statistics<br/>Win Rates & Trends]
    end

    subgraph "🚀 Key Innovations"
        SSOT --> MEM[💡 Memory Optimization<br/>128MB+ → <10MB]
        TST --> FAIR[⚖️ Fairness Analysis<br/>Side Selection Tracking]
        CFS --> COIN[🪙 Coinflip System<br/>Fair Side Assignment]
        AS --> REWARD[🎁 Dynamic Rewards<br/>Real-time Achievement Calc]
    end

    style SSOT fill:#ff6b6b,stroke:#fff,stroke-width:3px,color:#fff
    style ELO fill:#4ecdc4,stroke:#fff,stroke-width:2px,color:#fff
    style ACH fill:#45b7d1,stroke:#fff,stroke-width:2px,color:#fff
    style TST fill:#96ceb4,stroke:#fff,stroke-width:2px,color:#fff
    style FAIR fill:#ffeaa7,stroke:#333,stroke-width:2px,color:#333
    style MEM fill:#fd79a8,stroke:#fff,stroke-width:2px,color:#fff
```

## 🏗️ Architektur-Übersicht

### 🔥 Kern-Innovation: Single Source of Truth (SSOT)
- **Zentrale Datenhaltung**: Alle Spielerstatistiken, ELO-Ratings und Achievements werden in Echtzeit aus `matches.json` berechnet
- **Datenintegrität**: Eliminiert Dateninkonsistenzen durch einheitliche Datenquelle
- **Speicher-Optimierung**: Reduziert Speicherverbrauch von 128MB+ auf <10MB
- **Sicheres Löschen**: Ermöglicht sicheres Löschen von Matches mit automatischer Neuberechnung aller abhängigen Daten

### 🚀 Hauptkomponenten

#### **Web Layer**
- Standard Webserver-Setup mit Slim Framework 4 als Einstiegspunkt
- PSR-4 Autoloading und Dependency Injection Container

#### **Routing & Controller**
- **4 Hauptbereiche**: Home (Dashboard), Players, Matches, Seasons
- Jeder Controller verwaltet CRUD-Operationen für seinen Bereich

#### **Service Layer** (Business Logic)
- **ComputationService**: Zentrale Analytics-Engine für SSOT-Datenverarbeitung
- **EloService**: Implementiert dynamisches ELO-Rating mit Tordifferenz-Modifikatoren
- **AchievementService**: Verwaltet 12 verschiedene Achievement-Typen
- **CoinflipService**: Bietet faires Tischseiten-Auswahlsystem
- **DataService**: Verwaltet JSON-Dateioperationen mit File-Locking

#### **Datenspeicherung**
- **JSON-basierte Speicherung** statt traditioneller Datenbanken
- **matches.json**: Primäre Datenquelle mit allen Match-Aufzeichnungen
- **players_meta.json**: Statische Spielerinformationen (Namen, etc.)
- **seasons.json**: Saison-Definitionen und -Konfigurationen

## 🔥 Erweiterte Features

1. **⚖️ Tischseiten-Tracking**: Umfassende Blau vs. Weiß Seiten-Analyse für Fairness
2. **🪙 Coinflip-System**: Interaktive faire Seitenauswahl mit Animationen
3. **📈 ELO-Rating**: Dynamische Berechnungen mit Tordifferenz-Boni
4. **🏅 Achievement-System**: 12 verschiedene Belohnungstypen mit Echtzeit-Berechnung
5. **📊 Echtzeit-Analytics**: Alle Statistiken werden on-demand aus Match-Daten berechnet
6. **🎨 Moderne UI**: Bootstrap 5 Dark Theme mit Video-Hintergründen und Chart.js-Visualisierungen

## 📝 Verwendung des Diagramms

Dieses Diagramm kann verwendet werden für:
- **Entwickler-Onboarding**: Schnelles Verständnis der Systemarchitektur
- **Code-Reviews**: Referenz für Architektur-Entscheidungen
- **Dokumentation**: Visuelle Erklärung des Systems für Stakeholder
- **Refactoring**: Identifikation von Abhängigkeiten und Optimierungspotential

## 🔗 Verwandte Dokumentation

- [Hauptprojektdokumentation](project.md)
- [Single Source of Truth Feature](feature-single-source-of-truth.md)
- [ELO-System Details](elo-system.md)
- [Achievement-System](achievements.md)
- [Tischseiten-Tracking](feature-tischseiten-tracking.md)

---

*Generiert am: ${new Date().toLocaleDateString('de-DE')} für Kickerliga Management System v1.0* 