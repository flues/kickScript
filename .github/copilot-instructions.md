# 🏓 Copilot Instructions for Kickerliga Management System

## Big Picture Architecture
- The project is a modular, web-based management system for a table football league, built with Slim Framework 4 (PHP) and Twig for templating.
- All business logic is organized in `kickLiga/app/`:
  - `Controllers/` handle HTTP requests and route logic.
  - `Models/` represent core data structures (Player, Season, GameMatch).
  - `Services/` encapsulate business logic (ELO calculation, achievements, data access, coinflip, etc.).
  - `Config/` contains dependency injection and configuration files.
- Data is stored in JSON files (see `kickLiga/data/`). All statistics and state are derived from `matches.json` (Single Source of Truth principle).
- Views are rendered using Twig templates in `kickLiga/templates/`.
- Static assets are served from `kickLiga/public/assets/`.

## Developer Workflows
- **Install dependencies:**
  - `composer install` in the project root.
- **Run locally:**
  - `php -S localhost:1337 -t public` (from `kickLiga/`).
- **Deploy:**
  - Upload all files (including `vendor/`) to the server. Set DocumentRoot to `public`.
- **Configuration:**
  - Environment variables via `.env` in the project root.
- **Permissions:**
  - Ensure `data/` and `logs/` are writable by the web server.

## Project-Specific Conventions
- **PSR-12 Extended Coding Style** is enforced for all PHP code.
- All data (players, seasons, achievements) is recalculated from `matches.json`—never store redundant state.
- Use dependency injection via PHP-DI (see `Config/ContainerConfig.php`).
- All output is escaped via Twig for XSS protection.
- Modular service classes: Each feature (ELO, achievements, coinflip, etc.) has its own service class.
- Routing is defined in `app/routes.php`.

## Integration Points & External Dependencies
- **Slim Framework 4**: Main backend framework.
- **Twig**: Templating engine for all views.
- **Bootstrap 5, Chart.js, Phosphor Icons**: Used in frontend templates.
- **Composer**: Dependency management.
- No SQL database—data is stored in JSON files with file locking for concurrency.

## Examples & Patterns
- To add a new feature, create a Service class in `Services/`, update routing in `routes.php`, and add a Controller if needed.
- To add a new page, create a Twig template and update the relevant Controller.
- For statistics, always read from `matches.json` and compute on-the-fly.
- For new models, add to `Models/` and update business logic in `Services/`.

## Key Files & Directories
- `kickLiga/app/Controllers/` — HTTP request handlers
- `kickLiga/app/Models/` — Data models
- `kickLiga/app/Services/` — Business logic
- `kickLiga/app/Config/ContainerConfig.php` — DI setup
- `kickLiga/app/routes.php` — Route definitions
- `kickLiga/templates/` — Twig templates
- `kickLiga/data/` — JSON data storage
- `kickLiga/public/` — Web root and assets

---

For further details, see the project README and `.docs/` documentation. If conventions or workflows are unclear, ask the user for clarification or examples.
