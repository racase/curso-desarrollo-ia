---
type: runtime architecture
title: InfraData CLI, TimescaleDB, and Dataset
description: Architecture of the minimal InfraData Python runtime, from CLI dispatch and environment-based psycopg connections through the TimescaleDB schema, deterministic fixtures, UTC handling, and failure behavior.
tags: [infradata, python, timescaledb, postgresql, psycopg, cli, dataset]
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
  - id: openwiki-source-a331d8d27e6e5a4c3bf3397b
    resource: repo://datos/README.md
  - id: openwiki-source-c6beaa8626c4edbe8753bdf9
    resource: repo://datos/src/infradata/__main__.py
  - id: openwiki-source-ec90579d019124ba1f91719d
    resource: repo://datos/src/infradata/config.py
  - id: openwiki-source-42b447b6d92e8ff2daa080c0
    resource: repo://datos/src/infradata/db.py
  - id: openwiki-source-d5b7c4ef1228ce719a6df9ec
    resource: repo://datos/tests/test_cli.py
  - id: openwiki-source-3de9162e37632fd14988e7d2
    resource: repo://datos/tests/test_config.py
  - id: openwiki-source-493b4e7cdaeab531f2bf9bf9
    resource: repo://datos/tests/test_db.py
generated: { by: "openwiki/0.9.0", at: "2026-11-24T09:12:59.855Z" }
---

# InfraData CLI, TimescaleDB, and Dataset

InfraData is intentionally a thin training runtime: one Python command verifies a preloaded sensor dataset, while PostgreSQL and TimescaleDB own storage and future analytical work. The Python path contains no analytics layer, ORM, migration framework, or long-running service. Its boundaries are the process arguments, `DATABASE_URL`, psycopg, and the schema initialized by Docker Compose.

For setup commands and volume recreation, see [Local development](../operations/local-development.md). For the place of `datos/` in the wider repository, see [Repository landscape](repository-landscape.md), and for test layers see [Validation strategy](../testing/validation-strategy.md).

## Runtime path

Running `python -m infradata check` loads [`src/infradata/__main__.py`](../../datos/src/infradata/__main__.py). `main()` accepts exactly the argument list `["check"]`; any other shape, including no arguments or additional options, prints `Uso: python -m infradata check` to standard error and returns exit status `2`. The module raises `SystemExit(main())`, so the returned integer becomes the process status.

For `check`, the path is deliberately direct:

1. `check()` calls `connect()` with no explicit URL.
2. [`config.database_url()`](../../datos/src/infradata/config.py) reads `DATABASE_URL`. A missing **or empty** value falls back to `postgresql://infradata:infradata@localhost:5433/infradata`.
3. [`db.connect()`](../../datos/src/infradata/db.py) calls `psycopg.connect` and supplies `connect_timeout=5` by default. Callers can override both the URL and timeout; a falsey explicit URL also falls back to configuration.
4. Inside the connection context, the command executes two fixed summary queries: a sensor count, then the reading count and minimum/maximum timestamps.
5. It renders both bounds after `astimezone(UTC)`, making the displayed timezone explicit regardless of the session or host timezone, and returns `0`.

```mermaid
sequenceDiagram
    actor User
    participant Module as Python module
    participant CLI as main and check
    participant Config as environment config
    participant DB as db connect
    participant Driver as psycopg
    participant TSDB as TimescaleDB

    User->>Module: python -m infradata check
    Module->>CLI: main with process arguments
    CLI->>CLI: match exact check argument
    CLI->>DB: connect with default timeout
    DB->>Config: database_url
    Config-->>DB: environment URL or local default
    DB->>Driver: psycopg.connect
    Driver->>TSDB: open connection
    TSDB-->>Driver: connected
    CLI->>TSDB: count sensors
    TSDB-->>CLI: sensor count
    CLI->>TSDB: count and bounds of readings
    TSDB-->>CLI: count, minimum time, maximum time
    CLI->>CLI: convert bounds to UTC and format output
    CLI-->>User: summary and exit status 0
    opt psycopg error during connection or query
        Driver-->>CLI: psycopg.Error
        CLI-->>User: diagnostic on stderr and exit status 1
    end
```

*The `check` sequence crosses only argument dispatch, environment resolution, psycopg, two database summaries, and UTC presentation.*

With the standard fixture, output reports 12 sensors, 313,760 readings, and bounds from `2026-08-01 00:00 UTC` through `2026-10-30 23:55 UTC`.

## Configuration and container boundary

[`docker-compose.yml`](../../datos/docker-compose.yml) runs `docker.io/timescale/timescaledb:latest-pg16` as `infradata-db`. PostgreSQL listens on `5432` inside the container and is published as host port `5433`, avoiding the repository's other PostgreSQL service. Database name, user, and password are all `infradata`, matching the Python default URL. The health check uses `pg_isready` every five seconds, with a three-second timeout and 20 retries.

The named volume `infradata-pgdata` owns durable database state. The read-only `./db/init` mount is exposed as `/docker-entrypoint-initdb.d`; its scripts run **only when the database volume is first created**, not on each container start. Editing an init script therefore does not update an existing volume. Rebuilding from the scripts requires removing the volume before starting the service again.

The init filenames establish creation order: [`01_schema.sql`](../../datos/db/init/01_schema.sql) enables TimescaleDB and creates the model before [`02_seed.sql`](../../datos/db/init/02_seed.sql) inserts fixtures. These files define the common course dataset and **must not be changed without asking first**.

## Relational and time-series model

`sensors` is the dimension table. Its generated integer `id` is the primary key; `code` is required and unique. `name`, `unit`, `site`, and `installed_at` are required, and `kind` is constrained to `temperature`, `pressure`, `flow`, or `level`.

`readings` carries only a required `timestamptz` time, a required foreign key to `sensors.id`, and a required double-precision value. Every reading therefore belongs to an existing sensor. There is intentionally no standalone reading identifier: the table models timestamped observations and does not declare `(sensor_id, time)` unique.

```mermaid
erDiagram
    sensors ||--o{ readings : produces
    sensors {
        serial id PK
        text code UK
        text name
        text kind
        text unit
        text site
        date installed_at
    }
    readings {
        timestamptz time
        integer sensor_id FK
        double_precision value
    }
```

*The compact model separates sensor metadata from the time-series observations stored in `readings`.*

`create_hypertable('readings', 'time')` turns the reading table into a TimescaleDB hypertable partitioned on time. This is the storage basis for time-range operations and TimescaleDB temporal functions. The explicit `readings_sensor_time_idx` index orders `(sensor_id, time DESC)`, supporting retrieval of one sensor's newest readings or a descending sensor-specific time range. It complements hypertable time partitioning rather than replacing it.

Future temporal aggregation belongs in **parameterized SQL** using TimescaleDB functions such as `time_bucket`, rather than fetching the entire reading set for bulk Python processing. Parameters must be passed through psycopg placeholders (`%s`), not interpolated with f-strings.

## Deterministic course fixtures

The seed is synthetic and shared by every course participant. It inserts 12 sensors across Planta Valencia, Planta Sagunto, and Almacén Paterna. A `generate_series` produces one timestamp every five minutes, inclusive from `2026-08-01 00:00:00+00` to `2026-10-30 23:55:00+00`, and a cross join produces a baseline observation for every sensor at every timestamp.

Values combine a sensor-kind baseline, a daily sine cycle, and bounded pseudorandom noise, rounded to two decimal places. `setseed(0.42)` plus fixed dates and sensor insertion order makes the generated fixture repeatable when a fresh volume runs the init scripts. The final 313,760 count is lower than the full grid because the seed then removes 736 observations across three declared outages.

The gaps and anomalous values are **deliberate dataset fixtures, not defects to clean up**:

| Fixture | Interval or point | Meaning in the dataset |
|---|---|---|
| `SAG-P02` gap | `2026-09-03 08:00+00` to before `14:30+00` | 78 absent five-minute readings |
| `PAT-L01` gap | `2026-10-12 00:00+00` to before `2026-10-14 06:00+00` | 648 absent readings |
| `VAL-F01` gap | `2026-08-20 22:15+00` to before `23:05+00` | 10 absent readings |
| `VAL-T01` anomaly | `96.4` from `2026-09-18 03:10+00` to before `03:40+00` | sustained high fixture |
| `SAG-P01` anomaly | `0` from `2026-10-05 11:00+00` to before `11:20+00` | sustained zero fixture |
| `SAG-T01` anomaly | `-40` at `2026-08-27 16:45+00` | isolated low fixture |

All seed timestamps carry an explicit `+00` offset, the schema uses `timestamptz`, project conventions require UTC, and the CLI normalizes timestamps to `datetime.UTC` before display. New queries should preserve timezone-aware values and make UTC boundaries explicit.

## Failure behavior and operational expectations

The command distinguishes dispatch errors from database failures:

- Unknown, missing, or extra CLI arguments produce usage on standard error and status `2`, without attempting a connection.
- A `psycopg.Error` raised while connecting or executing either summary query is caught. The command prints the database error and a suggestion to run `docker compose up -d` to standard error, then returns `1`.
- A successful summary prints four lines to standard output and returns `0`.

The handler is intentionally narrow. It does not retry, wait on the Compose health check, validate schema version, or catch arbitrary Python exceptions. In particular, the formatter assumes at least one reading exists; an empty `readings` table would yield `None` bounds and fail outside the `psycopg.Error` path. This is acceptable for a checker of the fixed initialized dataset, but a broader operational CLI would need explicit empty-dataset handling.

The connection is intended for context-manager use so it is always closed. Keep query execution inside that scope; do not leak a connection or hide connection ownership in an analytics abstraction unless the runtime's lifecycle changes.

## Tests and safe extension points

The focused unit tests establish configuration and dispatch contracts without a database: [`test_config.py`](../../datos/tests/test_config.py) verifies missing, empty, and overriding `DATABASE_URL` cases, while [`test_cli.py`](../../datos/tests/test_cli.py) verifies unknown-command status and usage output.

[`test_db.py`](../../datos/tests/test_db.py) is marked `integration`. Its module-scoped fixture uses a two-second connection timeout and skips the module on `psycopg.OperationalError` when the container is unavailable. Against a running database it checks the 12-sensor invariant, UTC date bounds, and registration of `readings` in `timescaledb_information.hypertables`. Thus a normal test run can still pass with integration coverage skipped; dataset and hypertable validation requires the Compose database.

Safe additions should preserve the existing boundaries:

- add pure Python behavior with unit tests when no database is required;
- add database behavior with integration tests and parameterized SQL;
- keep timestamps timezone-aware and aggregate/filter in TimescaleDB;
- treat the seed's gaps and anomalies as test inputs;
- ask before changing `db/init/*.sql`, because recreating a volume would change the shared course baseline.
