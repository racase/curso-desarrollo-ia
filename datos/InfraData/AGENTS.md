# Repository Guidelines

## Project Structure & Module Organization

- `src/infradata/` contains the Python package: configuration in `config.py`, connection helpers in `db.py`, and the CLI in `__main__.py`.
- `tests/` contains configuration, CLI, and database tests.
- `db/init/` contains ordered schema and seed SQL, executed when the database volume is first initialized. `db/mcp/lectura.sql` configures read-only database access.
- `data/incoming/` holds sensor CSV inputs; `notebooks/` contains exploratory analysis.
- `openspec/` holds specifications and change artifacts; `docs/` contains the exercise backlog.

## Build, Test, and Development Commands

Run commands from this directory with Python 3.11+ and Docker available:

- `uv sync`: create the virtual environment and install project and development dependencies.
- `docker compose up -d`: start PostgreSQL with TimescaleDB and initialize the demo dataset.
- `uv run python -m infradata check`: check connectivity and print sensor counts and the dataset period.
- `uv run pytest`: run all tests; database tests skip when the database is unreachable.
- `uv run pytest -m "not integration"`: run tests without database integration checks.
- `uv run ruff check .`: check lint rules and import ordering.
- `uv run ruff format .`: format Python code.

## Coding Style & Naming Conventions

Use four-space indentation and a 100-character line limit. Follow existing type annotations and concise docstrings. Use `snake_case` for modules, functions, and variables, and `UPPER_CASE` for constants. Ruff enforces pycodestyle, Pyflakes, import sorting, modernization, and bugbear rules. Manage database connections with context managers and display timestamps in UTC.

## Testing Guidelines

Use pytest with `tests/test_*.py` files and descriptive `test_*` functions. All tests requiring the database must be marked with `pytest.mark.integration`. Add focused tests for changed behavior; no coverage threshold is configured. Start the database before validating SQL or dataset changes.

## Project Rules

- Count days in UTC; use UTC boundaries for daily periods and aggregations.
- Ask the user for approval before changing the database schema.
- Justify every new dependency, explaining why it is needed.
- Perform aggregations in the database using SQL; do not load readings into memory to aggregate them.

## Commit & Pull Request Guidelines

Recent commits use short, descriptive subjects, mostly in Spanish, without a consistent prefix convention. Keep commits focused. In pull requests, describe the behavior change, reference relevant issues or OpenSpec artifacts, and report test and lint results. Explain schema, seed-data, or configuration changes explicitly.

## Security & Configuration

Override `DATABASE_URL` for other databases; never commit real credentials. Compose exposes the demo database on port `5433`. Treat `docker compose down -v` as destructive: it deletes the persisted database volume.
