"""Shared integration fixtures for the precalculated summary."""

import psycopg
import pytest

from infradata.db import connect
from infradata.precompute import migrate_daily_summary


@pytest.fixture(scope="session")
def migrated_database():
    """Install the summary once when the integration database is reachable."""
    try:
        conn = connect(connect_timeout=2)
    except psycopg.OperationalError:
        pytest.skip("database not reachable")
    try:
        conn.autocommit = True
        migrate_daily_summary(conn)
    finally:
        conn.close()


@pytest.fixture
def migrated_conn(migrated_database):
    """Provide a separate connection to the migrated database."""
    with connect() as conn:
        yield conn
