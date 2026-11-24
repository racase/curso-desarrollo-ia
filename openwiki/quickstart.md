---
type: repository quickstart
title: Repository Quickstart
description: Safe starting point for agents working in the independent InfraTicket and InfraData course projects, with first commands, repository boundaries, and task routing.
tags: [quickstart, repository, infraticket, infradata, agents, safety]
verified:
  - by: openwiki/0.9.0
    at: 2026-11-24T09:12:59.855Z
sources:
  - id: openwiki-source-bd32c305b97c8f00c8c9d003
    resource: repo://datos/AGENTS.md
  - id: openwiki-source-ba3ed89020c95c3ba33711f8
    resource: repo://datos/docker-compose.yml
  - id: openwiki-source-b6aeee880cc0d22a564662d5
    resource: repo://datos/pyproject.toml
  - id: openwiki-source-a331d8d27e6e5a4c3bf3397b
    resource: repo://datos/README.md
  - id: openwiki-source-23775c3de52f3ab95a13cb8b
    resource: repo://README.md
  - id: openwiki-source-b501339a80121fd4d8be1202
    resource: repo://web/AGENTS.md
  - id: openwiki-source-4d008a5482d911e8f5a08308
    resource: repo://web/docker-compose.yml
  - id: openwiki-source-e08eb344293ed7df3a18e362
    resource: repo://web/docker/start.sh
  - id: openwiki-source-52cc4c3bc39b1652ba919753
    resource: repo://web/README.md
generated: { by: "openwiki/0.9.0", at: "2026-11-24T09:12:59.855Z" }
---

# Repository Quickstart

This repository is a training workspace containing **two independent projects**, not one application split into frontend and backend:

| Project | Working directory | Purpose and stack | Primary surface |
|---|---|---|---|
| **InfraTicket** | `web/` | Incident management with PHP 8.3+, Laravel 13, Filament 5, and PostgreSQL | Authenticated panel at <http://localhost:8000/admin> |
| **InfraData** | `datos/` | Sensor-data exercises with Python 3.11+, Psycopg, PostgreSQL, and TimescaleDB | Host-run `python -m infradata` CLI over a preloaded database |

They share a repository and course context, but have separate dependencies, Compose files, databases, tests, agent instructions, and working directories. There is no existing runtime call or shared schema between them. Do not treat InfraData as InfraTicket's analytics backend or infer that a change in one should modify the other. See [Repository Landscape](architecture/repository-landscape.md) for the full system boundary.

## Before changing anything

Start at the repository root by checking branch and worktree state without modifying either project:

```bash
git status --short --branch
```

**Never work directly on `main`.** If the current branch is `main`, create or switch to a task branch before editing. Preserve unrelated work already present in the checkout.

Then enter exactly one project and read its local instructions before running an agent or project command:

```bash
# InfraTicket work
cd web
cat AGENTS.md

# OR: InfraData work
cd datos
cat AGENTS.md
```

The root [README](../README.md) requires agents to be opened inside `web/` or `datos/` so the selected project's `AGENTS.md` and agent configuration apply. The local instructions are authoritative for commands and boundaries: [InfraTicket instructions](../web/AGENTS.md) and [InfraData instructions](../datos/AGENTS.md).

> **Repository-wide safety rules**
>
> - Do not commit `.env` files, real credentials, API keys, production connection strings, or generated secrets. Use safe placeholders in tracked examples such as `.env.example`.
> - Work on a branch, never directly on `main`.
> - For InfraData, do not change `datos/db/init/*.sql` without asking first. Those files define the shared course schema and dataset, and they run only when a fresh database volume is initialized.
> - Keep changes inside the selected project unless the task explicitly requires a repository-level change. In particular, `datos/AGENTS.md` prohibits changing anything outside `datos/` during InfraData work.

## Safest first run

### InfraTicket (`web/`)

From the repository root:

```bash
cd web
docker compose up -d --build
docker compose logs -f app
```

Wait for `InfraTicket ready at http://localhost:8000/admin`, stop following logs with Ctrl+C, and open <http://localhost:8000/admin>. Startup waits for PostgreSQL, installs missing Composer dependencies, creates and keys a local `.env` when needed, runs migrations and demo-user seeding, and serves the application on port `8000`. PHP and Composer commands should normally run inside the `app` container because Composer dependencies live in a named container volume.

A safe initial validation is:

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
```

Do not use `php artisan migrate:fresh --seed` as a routine update: it drops and recreates application tables and destroys local data. Use the ordinary migration path for additive schema updates. Theme and frontend changes also require an asset build and browser review; follow [Local Development](operations/local-development.md) rather than assuming the PHP container includes Node.

### InfraData (`datos/`)

From the repository root:

```bash
cd datos
docker compose up -d
uv sync
uv run python -m infradata check
```

Compose starts only the TimescaleDB database; the Python CLI runs on the host and connects to `localhost:5433` by default. On the standard dataset, `check` reports 12 sensors and 313760 readings from `2026-08-01 00:00 UTC` through `2026-10-30 23:55 UTC`.

A safe initial validation is:

```bash
uv run ruff check .
uv run ruff format --check .
uv run pytest -rs
```

Inspect pytest's skip reasons. InfraData integration tests skip when the database is unavailable, so a green run with skipped integration tests does not validate database behavior. Do not reset the volume or edit initialization SQL merely to make a test pass; first confirm the expected dataset and ask before changing `db/init/*.sql`.

Docker commands above can use Podman by replacing `docker` with `podman`, including `docker compose` with `podman compose`.

## Route the task before exploring

| If the task concerns… | Start here |
|---|---|
| Which project owns a capability, ports, data stores, entrypoints, or whether the systems integrate | [Repository Landscape](architecture/repository-landscape.md) |
| Local startup, `.env`/`DATABASE_URL`, ports, migrations, resets, Composer volumes, Vite builds, or troubleshooting | [Local Development, Configuration, and Data Lifecycle](operations/local-development.md) |
| Test selection, focused commands, SQLite/PostgreSQL differences, concurrency checks, integration skips, lint, or formatting | [Testing and Validation Strategy](testing/validation-strategy.md) |
| Incident entities, roles, visibility, permissions, validation, relationships, or deletion constraints | [Incident Domain Model and Authorization](concepts/incident-domain.md) |
| Incident creation, assignment, self-assignment, transitions, comments, history, locking, stale confirmations, or rollback | [Incident Management Workflows](workflows/incident-management.md) |
| Filament resources, Livewire pages, policies, application actions, panel discovery, or the custom panel theme | [InfraTicket Panel Architecture](architecture/infraticket-panel.md) |
| Sensor schema, seeded readings, UTC behavior, TimescaleDB hypertables, Psycopg, CLI dispatch, or database failures | [InfraData CLI, TimescaleDB, and Dataset](architecture/infradata-runtime.md) |

Use those pages for orientation, then verify behavior in current source and representative tests before editing. OpenSpec and course backlog material may describe planned or historical work; executable source, migrations, and tests define what exists now.

## Completion boundary

Finish in the same project directory in which the change was made. Run the narrowest focused test that proves the changed behavior, then the project's completion checks:

- **InfraTicket:** relevant Feature/Livewire tests, the complete `php artisan test` suite, and Pint; add `npm run build` plus browser review for visible panel/theme changes. Locking or transaction changes also need the isolated PostgreSQL concurrency harness described in the validation guide.
- **InfraData:** relevant database-free or integration pytest tests, full pytest with the database running, and Ruff lint/format checks. SQL and time-series behavior require integration tests that actually execute, not skip.

Before handing off, rerun `git status --short` and confirm that no `.env`, credentials, generated secrets, unintended database artifacts, or cross-project changes are included.
