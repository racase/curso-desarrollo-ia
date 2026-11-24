---
type: architecture overview
title: Repository Landscape and System Boundaries
description: Architectural map of the InfraTicket and InfraData training projects, including their independent runtimes, entrypoints, data stores, public surfaces, and deliberate capability gaps.
tags: [architecture, repository, infraticket, infradata, system-boundaries]
verified:
  - by: openwiki/0.9.0
    at: 2026-11-24T09:12:59.855Z
sources:
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
  - id: openwiki-source-c6beaa8626c4edbe8753bdf9
    resource: repo://datos/src/infradata/__main__.py
  - id: openwiki-source-ec90579d019124ba1f91719d
    resource: repo://datos/src/infradata/config.py
  - id: openwiki-source-d5b7c4ef1228ce719a6df9ec
    resource: repo://datos/tests/test_cli.py
  - id: openwiki-source-493b4e7cdaeab531f2bf9bf9
    resource: repo://datos/tests/test_db.py
  - id: openwiki-source-23775c3de52f3ab95a13cb8b
    resource: repo://README.md
  - id: openwiki-source-dc508deb8cd8c341861cbec0
    resource: repo://web/app/Actions/Incidents/AssignIncident.php
  - id: openwiki-source-6eaabdd0affa62910aa5a6d6
    resource: repo://web/app/Actions/Incidents/CommentOnIncident.php
  - id: openwiki-source-8c33c2827a971f4851e8001f
    resource: repo://web/app/Actions/Incidents/CreateIncident.php
  - id: openwiki-source-cc85841557be910c7e07e9f5
    resource: repo://web/app/Actions/Incidents/TransitionIncident.php
  - id: openwiki-source-1c90deca93e6c0d2e6bc4d53
    resource: repo://web/app/Enums/IncidentStatus.php
  - id: openwiki-source-f41c836db6ac18cfc1260b21
    resource: repo://web/app/Models/Incident.php
  - id: openwiki-source-0f651d6f7e5cee410ec5934c
    resource: repo://web/app/Policies/IncidentCommentPolicy.php
  - id: openwiki-source-eb1ab9ae733c5dfe3610e05f
    resource: repo://web/app/Policies/IncidentPolicy.php
  - id: openwiki-source-59d1ed75b8b43cada50ae6c5
    resource: repo://web/app/Providers/Filament/AdminPanelProvider.php
  - id: openwiki-source-a4a8d7d76f95892ded61bff6
    resource: repo://web/composer.json
  - id: openwiki-source-4d008a5482d911e8f5a08308
    resource: repo://web/docker-compose.yml
  - id: openwiki-source-e08eb344293ed7df3a18e362
    resource: repo://web/docker/start.sh
  - id: openwiki-source-30bca2c4eef241a9ceae5a8d
    resource: repo://web/phpunit.xml
  - id: openwiki-source-52cc4c3bc39b1652ba919753
    resource: repo://web/README.md
  - id: openwiki-source-3fea7f37bedece75219970f5
    resource: repo://web/routes/web.php
  - id: openwiki-source-e91d0c05be362469071d0b16
    resource: repo://web/tests/Feature/IncidentAccessTest.php
generated: { by: "openwiki/0.9.0", at: "2026-11-24T09:12:59.855Z" }
---

# Repository Landscape and System Boundaries

This repository is a course workspace for spec-driven development, not a single deployable product. It contains two demonstration systems under `web/` and `datos/`, each with its own dependencies, startup procedure, agent instructions, tests, database, and change trajectory. Work from inside the project being changed so that its local `AGENTS.md` and tool configuration apply; the root [README](../../README.md) explicitly treats the folders as separate exercises.

The most important boundary is operational: **InfraTicket and InfraData share Git history, but they do not share application data and do not call one another at runtime.** There is no common application service, shared schema, cross-project client, or combined Compose network. Their similarly local PostgreSQL ports are intentionally separated (`5432` and `5433`). A feature that needs data from both would therefore be a new integration, not an extension of an existing call path.

## System map

```mermaid
flowchart LR
    Browser["Browser"] -->|"http://localhost:8000"| WebApp
    Shell["Developer shell"] -->|"python -m infradata check"| DataCli

    subgraph Ticket["web — InfraTicket"]
        WebApp["Laravel and Filament app container\nport 8000"]
        RootRoute["GET / redirects to /admin"]
        AdminPanel["Authenticated Filament panel\n/admin"]
        TicketDb["PostgreSQL 16\ninfraticket database"]
        RootRoute --> AdminPanel
        WebApp --> RootRoute
        AdminPanel --> TicketDb
    end

    subgraph Analytics["datos — InfraData"]
        DataCli["Python CLI on host"]
        DataDb["TimescaleDB on PostgreSQL 16\ninfradata database"]
        DataCli -->|"localhost:5433"| DataDb
    end

    HostPg["Host port 5432"] --> TicketDb
    DataVolume["infradata-pgdata"] --- DataDb
    TicketVolume["infraticket-pgdata"] --- TicketDb
```

*The two local execution paths terminate in separate databases and have no runtime edge between them.*

## Landscape at a glance

| Concern | InfraTicket (`web/`) | InfraData (`datos/`) |
|---|---|---|
| Responsibility | Role-aware incident intake, assignment, state tracking, history, and comments | A fixed sensor time-series dataset and a minimal connectivity/data-summary CLI |
| Main stack | PHP 8.3, Laravel 13, Filament 5, PostgreSQL 16; Vite/Tailwind build panel assets | Python 3.11+, Psycopg 3, PostgreSQL 16 with TimescaleDB |
| Runtime entrypoint | The `app` container runs [`docker/start.sh`](../../web/docker/start.sh), then `php artisan serve --host=0.0.0.0 --port=8000 --no-reload` | `uv run python -m infradata check`, dispatched by [`src/infradata/__main__.py`](../../datos/src/infradata/__main__.py) |
| User-facing surface | HTTP on `localhost:8000`; `/` redirects to `/admin`; unauthenticated panel access leads to `/admin/login`; incidents are under `/admin/incidents` | Terminal output only. There is no HTTP server, dashboard, or application API |
| Owned store | The `infraticket` PostgreSQL database in `infraticket-pgdata`, reached by the app as Compose service `db:5432` and published to the host as `5432` | The `infradata` TimescaleDB database in `infradata-pgdata`, published as host `5433` to avoid InfraTicket's port |
| Database lifecycle | Container startup installs dependencies if needed, creates/configures `.env`, runs migrations and idempotent demo-user seeding, then serves HTTP | SQL in `db/init/` runs only when a new database volume is initialized; destroying the volume is the reset mechanism |
| Primary extension point | Filament resources/pages plus policy-protected action classes and additive Laravel migrations | New Python modules/commands and SQL analytics built over `sensors` and `readings` |

## InfraTicket boundary

### Request and state ownership

[`routes/web.php`](../../web/routes/web.php) exposes only the root redirect directly. [`AdminPanelProvider`](../../web/app/Providers/Filament/AdminPanelProvider.php) owns the `/admin` panel, login, session/CSRF/authentication middleware, dashboard, and discovery of Filament resources, pages, and widgets. Every seeded role may enter the panel, while policies and resource queries decide which records and actions are available.

The implemented incident resource is more substantial than the root README's original “login and users” baseline:

- Any authenticated role can create an incident for itself. Server-side creation forces the initial `proposed` state, no technician, and medium priority unless another valid priority is supplied.
- Requesters see only their own incidents; technicians and administrators can see all incidents.
- Administrators assign technicians. A technician may claim an unassigned incident and may transition only an incident assigned to that technician.
- Status progresses linearly from `proposed` to `open`, `in_progress`, `resolved`, and `closed`. Entering `in_progress` requires an assignee. Transitions lock the row, reject stale expected state, and append history in the same transaction.
- Users who can view an incident may add immutable comments. General incident update/delete and comment update/delete are denied.

The Laravel database owns users, incidents, assignments, status history, and comments. Foreign keys preserve user attribution; incident history and comments cascade with their incident. Default local configuration also selects database-backed sessions, cache, and queues, although ordinary tests replace these with in-memory or synchronous drivers.

### Startup and assets

[`web/docker-compose.yml`](../../web/docker-compose.yml) defines the app and PostgreSQL containers. The app waits for a healthy database, mounts the working tree, and keeps Composer dependencies in a separate `infraticket-vendor` volume. Startup is intentionally development-oriented: it installs Composer packages on first use, generates an application key when absent, migrates and seeds on every start, and launches Artisan's server. The `--no-reload` flag preserves Compose's `DB_HOST=db` environment override.

Vite and Tailwind are an asset-build toolchain, not another backend service. `npm run dev` runs Vite for development and `npm run build` emits static assets consumed by Laravel; neither command connects InfraTicket to InfraData.

For the deeper request and authorization model, see [InfraTicket Panel](infraticket-panel.md).

## InfraData boundary

InfraData has no long-running Python service. Its only implemented application entrypoint accepts exactly `check`; unknown arguments print usage and return exit code `2`. `check` opens a Psycopg connection, counts sensors and readings, obtains the first and last timestamps, prints the summary, and returns `0`. A Psycopg failure prints a database/startup hint to standard error and returns `1`.

[`config.py`](../../datos/src/infradata/config.py) uses `DATABASE_URL` when non-empty and otherwise connects to `postgresql://infradata:infradata@localhost:5433/infradata`; [`db.py`](../../datos/src/infradata/db.py) applies a five-second connection timeout by default. This host-oriented URL is correct because Compose containerizes only the database, not the Python process.

The database initializer enables TimescaleDB, creates metadata table `sensors`, creates time-series table `readings`, converts `readings` into a hypertable, and adds a sensor/time index. The deterministic seed owns 12 sensors across three sites and readings every five minutes from August through October 2026, with deliberate gaps and anomalous values. Initialization scripts run only for a fresh `infradata-pgdata` volume, so changing seed SQL does not retrofit an existing local database.

For the CLI and database flow in detail, see [InfraData Runtime](infradata-runtime.md).

## Implemented behavior versus planned work

Treat executable source, migrations, and tests as the statement of current behavior. The repository is intentionally used to practice adding features, so overview prose and OpenSpec material can describe an earlier baseline, completed design work, or a future exercise:

- **Implemented now in InfraTicket:** authentication, seeded roles, incident creation and viewing, role-based visibility, assignment, sequential transitions with history, and comments.
- **Intentionally absent from InfraTicket:** panel-based user management, sites, assets, and a status-summary widget. Attachments, notifications, private comments, reopening, and unrestricted incident/comment editing or deletion are also not implemented.
- **Implemented now in InfraData:** schema/seed initialization, connection configuration, and the `check` command. There is no anomaly detection, aggregation/reporting layer, web UI, API, or scheduled analytics job.
- **OpenSpec is not runtime:** archived specifications under `web/openspec/changes/archive/` document work that now has corresponding implementation. The current `gestionar-usuarios-sedes-y-resumen` change contains only empty capability directories, so it is a backlog scaffold—not evidence that user/site/asset/summary features exist. `datos/` currently has no OpenSpec tree.

This distinction matters when planning changes: do not infer a database table, route, permission, or cross-system integration from a proposal name. Confirm it in current source and migrations first.

## Independent operation and failure domains

Each project can be started, reset, tested, and broken without requiring the other:

- InfraTicket's Compose project publishes HTTP `8000` and PostgreSQL `5432`; InfraData publishes only its database on `5433`.
- `docker compose down -v` in one project destroys that project's named data volume, not the other's. Run Compose from the intended directory to avoid acting on the wrong project.
- InfraTicket startup stops on dependency, key-generation, migration, or seeding failure because `docker/start.sh` uses `set -e`. The app container also waits for its own database health check.
- InfraData's database health check governs its DB container, while the CLI handles an unavailable database as exit `1`; there is no retrying worker or service lifecycle beyond that invocation.
- Neither database is a replica, cache, or source of truth for the other. Copying sensor data into tickets—or ticket data into analytics—would require an explicit contract, transport, ownership decision, and tests.

See [Local Development](../operations/local-development.md) for project-specific startup and reset commands.

## Verification that protects the boundaries

InfraData's fast tests cover command dispatch and `DATABASE_URL` fallback/override. Integration tests connect to the Compose database when reachable (otherwise they skip) and verify the 12-sensor seed, expected time range, and TimescaleDB hypertable.

InfraTicket's normal PHPUnit configuration uses SQLite in memory with array-backed cache/session and a synchronous queue, keeping tests away from local PostgreSQL state. Feature tests exercise login, role visibility, protected creation defaults, policies, panel pages, persistence, comments, and state tracking. The separate PostgreSQL concurrency script exists for row-lock behavior that SQLite cannot validate; it creates and later removes an isolated temporary database rather than modifying the demo database.
