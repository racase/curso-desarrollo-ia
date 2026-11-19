# Repository Guidelines

## Project Structure & Module Organization

InfraTicket is a course demo built with PHP 8.3+, Laravel 13, Filament 5, and PostgreSQL. Application code lives in `app/`: models in `Models/`, roles in `Enums/`, and panel configuration in `Providers/Filament/`. Place new Filament resources, pages, and widgets in `app/Filament/`. Routes live in `routes/`; migrations, factories, and seeders in `database/`. Frontend sources are in `resources/css/` and `resources/js/`; public assets belong in `public/`. Tests are split into `tests/Feature/` and `tests/Unit/`. Consult `docs/PROMPT_BACKLOG.md` for course backlog context and `openspec/` for change artifacts.

## Build, Test, and Development Commands

Run commands from `web/`. Docker is the primary development environment; Podman can replace Docker.

- `docker compose up -d --build`: build and start the app and database; startup installs PHP dependencies, migrates, and seeds.
- `docker compose logs -f app`: wait for “InfraTicket ready”, then visit `http://localhost:8000/admin`.
- `docker compose exec app php artisan test`: run the PHPUnit suite.
- `docker compose exec app vendor/bin/pint`: format PHP code.
- `npm install`, then `npm run dev` or `npm run build`: run Vite locally or build frontend assets when needed.
- `docker compose down`: stop the services.

Composer dependencies live in a Docker volume; execute PHP and Composer commands inside `app`.

## Coding Style & Naming Conventions

Follow `.editorconfig`: UTF-8, LF endings, final newlines, and four-space indentation. YAML uses two spaces, with a four-space Compose override. Use Laravel Pint for PHP formatting. Match existing namespaces, PascalCase class names, camelCase methods, and typed signatures. Use descriptive, timestamped migration filenames.

## Testing Guidelines

Use PHPUnit 12. Name files `*Test.php` and test methods `test_descriptive_behavior`. Cover application flows in Feature tests and isolated logic in Unit tests. Follow `AdminLoginTest.php` for Livewire authentication assertions and `RefreshDatabase` usage. Tests use in-memory SQLite, separate from PostgreSQL. No coverage threshold is configured. Run the suite before submitting changes.

## Commit & Pull Request Guidelines

History mixes plain imperative subjects with prefixes such as `docs:` and `chore:`. Write short, action-oriented subjects and use a suitable prefix consistently. PRs should explain the change, link relevant issues or OpenSpec artifacts, report validation commands and results, and include screenshots for visible panel changes. Call out migrations or configuration changes.

## Security & Configuration

Keep secrets out of commits; document configuration through `.env.example`. Seeded accounts and Docker credentials are for local demos. `php artisan migrate:fresh --seed` deletes existing database data; use it only for intentional local resets.
