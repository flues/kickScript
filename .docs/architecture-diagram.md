# 🏗️ Kickerliga System Architecture

## 📊 Architecture Diagram

This diagram shows the complete system architecture of the Kickerliga Management System with all major components and data flows.

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
        SF --> RT[🛠️ Routes<br/>app/routes.php]
        SF --> TM[🎨 Twig Middleware<br/>Template Engine]
    end

    subgraph "🛠️ Routing Layer"
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
        PC --> PS[👥 PlayerService<br/>Player Management]
        MC --> MS[⚽ MatchService<br/>Match Processing]
        MC --> CFS[🪙 CoinflipService<br/>Fair Side Selection]
        SC --> SS[🏆 SeasonService<br/>Season Logic]
        
        PS --> ES[📈 EloService<br/>Rating Calculations]
        MS --> ES
        PS --> AS[🏅 AchievementService<br/>Reward System]
        CS --> DS[💾 DataService<br/>JSON File Operations]
    end

    subgraph "📄 Data Models"
        PS --> PM[👥 Player Model<br/>Player Entity]
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
```
