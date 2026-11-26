"""Explicitly isolated database fixtures for ingestion integration tests."""

import os

import psycopg
import pytest
from psycopg.conninfo import conninfo_to_dict

from infradata.config import DEFAULT_DATABASE_URL, database_url


def check_test_database(url: str) -> None:
    """Fail closed if a test connection could refer to either original database."""
    # Different real database names remain different even with local host aliases,
    # credentials, proxies or multiple host addresses. Reject same-name databases
    # conservatively, even if hosted on a separate instance.
    protected_names = {
        conninfo_to_dict(value).get("dbname", "infradata")
        for value in (DEFAULT_DATABASE_URL, database_url())
    }
    params = conninfo_to_dict(url)
    if not params.get("dbname") or params["dbname"] in protected_names:
        raise ValueError("La base de pruebas debe tener otro nombre que la base original.")
    with psycopg.connect(url, connect_timeout=3) as conn:
        actual_name = conn.execute("SELECT current_database()").fetchone()[0]
        if actual_name in protected_names:
            raise ValueError("La conexión real identifica la base original.")


@pytest.fixture(scope="module")
def ingestion_test_url():
    url = os.environ.get("INGESTION_TEST_DATABASE_URL")
    if not url:
        pytest.skip("INGESTION_TEST_DATABASE_URL no configurada: no se escribe en la base original")
    check_test_database(url)
    return url
