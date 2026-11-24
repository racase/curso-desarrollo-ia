---
type: operations guide
title: Local Development, Configuration, and Data Lifecycle
description: Safe local startup, configuration, asset compilation, migration, reset, and troubleshooting procedures for InfraTicket and InfraData with Docker or Podman.
tags: [local-development, docker, podman, postgresql, migrations, vite]
verified:
  - by: openwiki/0.9.0
    at: 2026-11-24T09:12:59.855Z
sources:
  - id: openwiki-source-bd32c305b97c8f00c8c9d003
    resource: repo://datos/AGENTS.md
  - id: openwiki-source-5dbbf38e4e550c96ed480254
    resource: repo://datos/db/init/01_schema.sql
  - id: openwiki-source-848c37e59cf1d035ea551f25
    resource: repo://datos/db/init/02_seed.sql
  - id: openwiki-source-ba3ed89020c95c3ba33711f8
    resource: repo://datos/docker-compose.yml
  - id: openwiki-source-b6aeee880cc0d22a564662d5
    resource: repo://datos/pyproject.toml
  - id: openwiki-source-a331d8d27e6e5a4c3bf3397b
    resource: repo://datos/README.md
  - id: openwiki-source-ec90579d019124ba1f91719d
    resource: repo://datos/src/infradata/config.py
  - id: openwiki-source-3de9162e37632fd14988e7d2
    resource: repo://datos/tests/test_config.py
  - id: openwiki-source-493b4e7cdaeab531f2bf9bf9
    resource: repo://datos/tests/test_db.py
  - id: openwiki-source-510c67327e954146a4ec3082
    resource: repo://web/.env.example
  - id: openwiki-source-4b882df24163960bf11ab6cb
    resource: repo://web/.gitignore
  - id: openwiki-source-b501339a80121fd4d8be1202
    resource: repo://web/AGENTS.md
  - id: openwiki-source-87231db10db7257da55cc462
    resource: repo://web/database/seeders/DatabaseSeeder.php
  - id: openwiki-source-4d008a5482d911e8f5a08308
    resource: repo://web/docker-compose.yml
  - id: openwiki-source-e08eb344293ed7df3a18e362
    resource: repo://web/docker/start.sh
  - id: openwiki-source-30bca2c4eef241a9ceae5a8d
    resource: repo://web/phpunit.xml
  - id: openwiki-source-52cc4c3bc39b1652ba919753
    resource: repo://web/README.md
  - id: openwiki-source-a0bf9c7aabfd77488524e1a5
    resource: repo://web/resources/css/filament/admin/theme.css
  - id: openwiki-source-c82483afee374545e8c15f35
    resource: repo://web/tests/Support/check-postgres-concurrency.php
  - id: openwiki-source-b7f4e06d429d7fddc3cd032d
    resource: repo://web/vite.config.js
generated: { by: "openwiki/0.9.0", at: "2026-11-24T09:12:59.855Z" }
---

# Local Development, Configuration, and Data Lifecycle

The repository contains two independent local stacks:

- **InfraTicket** in `web/`: a Laravel and Filament application, its PostgreSQL database, and host- or container-built Vite assets.
- **InfraData** in `datos/`: a Python package that connects to a PostgreSQL/TimescaleDB dataset initialized by SQL.

Run each command from the directory named in its section. Compose project names, relative bind mounts, and `.env` discovery depend on the working directory. Docker is shown below; for Podman, replace `docker` with `podman` (including `docker compose` with `podman compose`). See also the [quickstart](../quickstart.md), [InfraTicket panel architecture](../architecture/infraticket-panel.md), [InfraData runtime](../architecture/infradata-runtime.md), and [validation strategy](../testing/validation-strategy.md).

## Service and port map

| Stack | Service | Container port | Host endpoint | Purpose |
|---|---|---:|---|---|
| InfraTicket | `app` | 8000 | <http://localhost:8000/admin> | Laravel development server and Filament panel |
| InfraTicket | `db` | 5432 | `localhost:5432` | PostgreSQL 16 for application state |
| InfraData | `db` | 5432 | `localhost:5433` | TimescaleDB/PostgreSQL 16 for sensor data |

The distinct PostgreSQL host ports are intentional: InfraTicket owns host port **5432**, while InfraData maps host **5433** to port 5432 in its own container. Both stacks can therefore run together. Container-to-container InfraTicket traffic does not use `localhost`: Compose injects `DB_HOST=db` into `app`, and the database is reached by service name on the Compose network.

## InfraTicket: start and stop

**Working directory: `web/`**

```bash
cd web
docker compose up -d --build
docker compose logs -f app
```

Wait for `InfraTicket ready at http://localhost:8000/admin`, then stop following the log with Ctrl+C and open <http://localhost:8000/admin>. Following logs does not stop the containers. To stop the stack while retaining database and dependency volumes:

```bash
docker compose down
```

The application image supplies PHP 8.3, Composer, PostgreSQL/Intl/Zip extensions, and the development server. The Compose service bind-mounts the whole `web/` checkout at `/app`, so source changes are immediately visible in the container. It then overlays `/app/vendor` with the named `infraticket-vendor` volume. This avoids unpacking thousands of Composer files through a Windows shared folder, but it also means the host's `vendor/` directory does **not** represent the dependencies visible inside `app`.

On every application-container start, `docker/start.sh` performs this sequence:

```mermaid
flowchart TD
    Start["Start app container"] --> Vendor{"vendor/autoload.php exists"}
    Vendor -- No --> Composer["composer install"]
    Vendor -- Yes --> Env{".env exists"}
    Composer --> Env
    Env -- No --> Copy["copy .env.example to .env"]
    Env -- Yes --> Key{"APP_KEY starts with base64"}
    Copy --> Key
    Key -- No --> Generate["php artisan key:generate --force"]
    Key -- Yes --> Migrate["php artisan migrate --seed --force"]
    Generate --> Migrate
    Migrate --> Serve["artisan serve on 0.0.0.0:8000 with no reload"]
```

*The InfraTicket startup path preserves existing files and data, installs only missing dependencies, applies pending migrations, seeds missing demo users, and then serves the panel.*

The database health check gates application startup. `artisan serve` uses `--no-reload` deliberately: the reloader would drop the container's `DB_HOST=db` override and fall back to `.env`'s host-oriented `127.0.0.1` value.

### Why repeated startup is safe

`php artisan migrate --seed --force` is run every time, not just on first boot. Laravel records applied migrations, so only pending migrations run. `DatabaseSeeder` checks each demo email before creating its user, making repeated seeding non-duplicating. It does not update an existing demo user's name or role; reset or edit that record explicitly if seed definitions change.

The named `infraticket-pgdata` volume keeps PostgreSQL state across `docker compose down`. The `infraticket-vendor` volume similarly keeps Composer dependencies, so subsequent starts normally skip `composer install`.

## InfraTicket configuration and credentials

The first startup copies `web/.env.example` to `web/.env` only when `.env` is absent, then generates `APP_KEY` unless the file already contains a base64 key. The resulting `.env` sits in the bind-mounted checkout and survives container replacement. Edit `.env` for local application settings; recreate the container after changing values that must enter the process environment, and clear cached Laravel configuration if configuration appears stale:

```bash
# from web/
docker compose exec app php artisan config:clear
```

Important defaults and overrides are:

- `.env.example` selects PostgreSQL and points host-side PHP at `127.0.0.1:5432` with the local `infraticket` database identity.
- Compose overrides only `DB_HOST` to `db` inside `app`; the remaining `DB_*` values come from `.env`.
- `APP_URL` defaults to `http://localhost:8000`, and the panel is mounted at `/admin`.
- Sessions, cache, and queues use database-backed drivers in the local example, so migrations must complete before normal use.

The checked-in database values and seeded users are **demo credentials only**. They are suitable for an isolated course environment, not production or any exposed host. Do not commit `.env`, real passwords, API keys, production connection strings, or generated application keys; document safe placeholders through `.env.example` instead.

## InfraTicket frontend assets

PHP/Composer and Node have separate lifecycles. The PHP image does not install Node. Vite has three entry points: the application CSS, the Filament admin theme, and application JavaScript. The admin theme imports Filament's CSS from `vendor/`, which is why asset compilation must be able to see the Composer dependency volume.

### Static build with Node in a container

This is the most self-contained option after `infraticket-app` is running. It shares the application's mounts, retains npm dependencies in a separate volume, and writes the generated files into the bind-mounted `public/build` directory.

**Working directory: `web/`**

```bash
podman run --rm --volumes-from infraticket-app -w /app -v infraticket-node-modules:/app/node_modules docker.io/library/node:22-bookworm-slim sh -c "npm ci && npm run build"
```

With Docker, substitute `docker` for `podman`. The resulting static manifest and assets are served by Laravel; neither `public/build` nor `node_modules` is committed.

### Static build on the host

Host Node must be 22.12 or newer. Because `/app/vendor` is a named container volume, first copy only the Filament subtree needed by the theme into the ignored host `vendor/` path.

**Working directory: `web/`**

```bash
docker compose cp app:/app/vendor/filament/. ./vendor/filament
npm ci
npm run build
```

Repeat the copy after updating Filament. Preserve `vendor/filament/filament/resources/css/theme.css`; accidentally nesting an extra `filament` directory breaks the theme import.

### HMR while editing

After preparing host dependencies as above, run:

```bash
# from web/
npm run dev
```

Vite's development server provides hot module replacement, and the Laravel Vite integration records that server in `public/hot`. When finishing an HMR session, stop Vite and create a fresh static build:

```bash
npm run build
```

A stale `public/hot` is a characteristic failure mode: if Vite was interrupted but the marker remains, Laravel continues to request assets from a development server that is no longer running even when `public/build` exists. Remove the marker and rebuild:

```bash
rm -f public/hot
npm run build
```

If the panel reports a missing Vite manifest, no static build exists at `public/build/manifest.json`; run one of the static build workflows. If Vite cannot resolve the Filament theme import, verify the host copy or compile in the Node container that shares `infraticket-vendor`.

## InfraTicket schema updates and destructive resets

Treat migration and reset commands as fundamentally different operations.

### Additive update: preserve current data

**Working directory: `web/`**

```bash
docker compose exec app php artisan migrate
```

Use this for an existing local installation after pulling new migrations. It applies pending migrations without intentionally dropping existing tables. Normal container startup already runs the equivalent migration plus the idempotent demo seeder.

### Destructive database reset: erase application data

```bash
# from web/
docker compose exec app php artisan migrate:fresh --seed
```

`migrate:fresh` drops the configured database's tables and recreates them before seeding. It destroys incidents, comments, histories, sessions, jobs, and any other PostgreSQL application data. Use it only when an intentional local reset is acceptable; it is not an upgrade command.

A still broader reset removes both named volumes:

```bash
# from web/
docker compose down -v
docker compose up -d --build
```

This deletes PostgreSQL state **and** the Composer vendor volume. The next startup performs a clean database initialization and a fresh `composer install`. To repair only PHP dependencies while preserving PostgreSQL, stop the stack, remove only the Compose vendor volume shown by `docker volume ls`, and start again; verify the exact generated volume name before removal.

## InfraData: start, configure, and verify

**Working directory: `datos/`**

```bash
cd datos
docker compose up -d
uv sync
uv run python -m infradata check
```

The first database creation loads the TimescaleDB extension, creates `sensors`, creates `readings` as a hypertable, and generates the fixed synthetic dataset. The check command connects, counts both tables, and prints the reading range. The expected baseline is 12 sensors and 313760 readings from `2026-08-01 00:00 UTC` through `2026-10-30 23:55 UTC`.

InfraData's Python process normally runs on the host. With no non-empty `DATABASE_URL`, it uses:

```text
postgresql://infradata:infradata@localhost:5433/infradata
```

Set `DATABASE_URL` to use a different database:

```bash
# from datos/
DATABASE_URL='postgresql://user:password@host:port/database' uv run python -m infradata check
```

Do not commit a real URL or paste it into logs and documentation. The checked-in local URL is a disposable demo default, not a secure deployment configuration. If the Python process itself runs in another container on the InfraData Compose network, use that network's database service name and container port 5432 rather than the host mapping; network attachment must be configured explicitly.

Without `uv`, create and activate a Python 3.11+ virtual environment, then install the package and developer tools:

```bash
# from datos/
python -m venv .venv
# Linux/WSL:
source .venv/bin/activate
# Windows PowerShell activation is: .venv\Scripts\activate
pip install -e . pytest ruff
python -m infradata check
```

## InfraData initialization is not migration

The read-only `datos/db/init` mount targets `/docker-entrypoint-initdb.d`. Its schema and seed SQL run only when PostgreSQL initializes a **new, empty data volume**. Restarting the container, recreating it, or editing `db/init/*.sql` does not apply those files to the existing `infradata-pgdata` volume. InfraData currently has no additive migration runner.

To stop services while preserving the dataset:

```bash
# from datos/
docker compose down
```

To intentionally throw away and regenerate the complete synthetic dataset:

```bash
# from datos/ -- destructive
docker compose down -v
docker compose up -d
```

The second sequence deletes the volume; the next startup sees an empty data directory and reruns both initialization scripts. Do not present it as a migration or use it when local changes must be retained. Also do not manually replay the seed SQL against a populated database: sensor uniqueness and generated readings make those initialization scripts unsuitable as repeatable updates.

## Validation commands

### InfraTicket, from `web/`

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
```

The normal PHPUnit suite is configured for in-memory SQLite with array-backed cache and sessions, so it does not modify the local PostgreSQL demo. For PostgreSQL-specific locking and race behavior:

```bash
docker compose exec app php tests/Support/check-postgres-concurrency.php
```

That script creates a randomly named temporary database, migrates and exercises it, then removes that database; it does not reset the normal `infraticket` database.

### InfraData, from `datos/`

```bash
uv run pytest
uv run ruff check .
uv run ruff format --check .
uv run python -m infradata check
```

The database tests carry the `integration` marker and skip when the TimescaleDB service is unreachable. To actually validate the seed and hypertable, start the database first rather than treating a skipped integration suite as full validation.

## Troubleshooting

### A database port is already allocated

Check which stack or local PostgreSQL process owns the port. InfraTicket requires host 5432 and InfraData requires host 5433 as currently configured:

```bash
# run in the relevant stack directory
docker compose ps
docker compose logs db
```

Do not change only the port mapping: host clients must use the corresponding `.env`/`DATABASE_URL` value. Containers on a Compose network continue to use service port 5432.

### InfraTicket stays in startup or exits

Inspect the ordered startup output:

```bash
# from web/
docker compose ps
docker compose logs app
docker compose logs db
```

The app waits for PostgreSQL's health check. Common causes after that point are an invalid `.env`, a failed Composer install, a bad migration, or a database identity inconsistent with the Compose service. Keep `DB_HOST=db` in the container; `127.0.0.1` there means the application container itself, not PostgreSQL.

If `vendor/autoload.php` is absent or the vendor volume is corrupt, remove only the vendor volume as described above. Do not delete the PostgreSQL volume merely to repair dependencies.

### InfraTicket loads HTML but not styling or JavaScript

1. If Vite is meant to be live, confirm `npm run dev` is still running.
2. If static assets are intended, remove stale `public/hot` and run `npm run build`.
3. If the build cannot import Filament CSS, refresh `./vendor/filament` from the app container or use the shared-volume Node build.
4. Inspect the browser's requested asset URL: a dead Vite URL indicates stale HMR state, while a missing manifest indicates no static build.

### InfraData cannot connect or still shows old data

Run `docker compose ps` and `docker compose logs db` from `datos/`, then confirm the client uses host port 5433. The CLI prints a connection hint and returns a nonzero status on a Psycopg error. If edited initialization SQL appears to have no effect, that is expected for an existing volume; either make an explicit additive database change or, for disposable data only, perform the documented destructive volume reset.

## Repository references

- [InfraTicket local README](../../web/README.md)
- [InfraTicket Compose definition](../../web/docker-compose.yml)
- [InfraTicket startup script](../../web/docker/start.sh)
- [InfraData local README](../../datos/README.md)
- [InfraData Compose definition](../../datos/docker-compose.yml)
