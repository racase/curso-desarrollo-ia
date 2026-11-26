"""Real transactions, concurrency and the course CSV on an explicitly separate database."""

import csv
import os
from concurrent.futures import ThreadPoolExecutor
from contextlib import contextmanager
from pathlib import Path
from threading import Barrier
from uuid import uuid4

import psycopg
import pytest
from psycopg import sql

from infradata import ingestion as ing

pytestmark = pytest.mark.integration
ROOT = Path(__file__).resolve().parents[1]
HEADER = ";".join(ing.HEADER) + "\n"


def require_ddl_approval():
    if os.environ.get("INGESTION_TEST_SCHEMA_APPROVED") != "1":
        pytest.skip("DDL de pruebas pendiente de aprobación: INGESTION_TEST_SCHEMA_APPROVED=1")


@pytest.fixture
def database(ingestion_test_url, monkeypatch):
    """Use a dedicated sensor; remove only its rows on the separate database."""
    url = ingestion_test_url
    code = "INGEST-" + uuid4().hex
    with psycopg.connect(url) as conn:
        sensor_id = conn.execute(
            "INSERT INTO sensors(code,name,kind,unit,site,installed_at) "
            "VALUES (%s,'Ingest test','temperature','°C','Test','2026-01-01') RETURNING id",
            (code,),
        ).fetchone()[0]

    @contextmanager
    def connection():
        with psycopg.connect(url) as conn:
            conn.execute("SET TIME ZONE 'Europe/Madrid'")
            yield conn

    monkeypatch.setattr(ing, "connect", connection)
    try:
        yield code, sensor_id, url
    finally:
        with psycopg.connect(url) as conn:
            conn.execute("DELETE FROM readings WHERE sensor_id=%s", (sensor_id,))
            conn.execute("DELETE FROM sensors WHERE id=%s", (sensor_id,))


def source_file(tmp_path, text):
    source = tmp_path / "source.csv"
    source.write_text(HEADER + text, encoding="utf-8")
    return source


def reasons(path):
    with path.open(encoding="utf-8", newline="") as stream:
        return {int(r["fila"]): r["motivo"] for r in csv.DictReader(stream, delimiter=";")}


def test_partial_utc_and_repeat(database, tmp_path):
    code, sensor_id, url = database
    source = source_file(
        tmp_path,
        f"{code};31/10/2026 00:30;23,31\n{code};31/10/2026 00:30;24,00\n"
        f"{code};25/10/2026 02:30;1\n{code};29/03/2026 02:30;1\nunknown;x;y\n",
    )
    assert ing.ingest_csv(source, tmp_path / "first.csv") == ing.IngestionResult(5, 1, 4)
    with psycopg.connect(url) as conn:
        row = conn.execute(
            "SELECT time AT TIME ZONE 'UTC', value FROM readings WHERE sensor_id=%s", (sensor_id,)
        ).fetchone()
        assert row[0].isoformat() == "2026-10-30T23:30:00"
        assert row[1] == 23.31
    assert ing.ingest_csv(source, tmp_path / "second.csv") == ing.IngestionResult(5, 0, 5)
    assert reasons(tmp_path / "second.csv")[2] == "duplicado_base"
    changed = source_file(tmp_path, f"{code};31/10/2026 00:30;99,00\n")
    assert ing.ingest_csv(changed, tmp_path / "third.csv").inserted == 0
    with psycopg.connect(url) as conn:
        assert (
            conn.execute("SELECT value FROM readings WHERE sensor_id=%s", (sensor_id,)).fetchone()[
                0
            ]
            == 23.31
        )


def test_real_rollback(database, tmp_path, monkeypatch):
    code, sensor_id, url = database
    original = ing.validate

    def fail_second(record, sensors, zone):
        if record.line == 3:
            raise OSError("read failure")
        return original(record, sensors, zone)

    monkeypatch.setattr(ing, "validate", fail_second)
    source = source_file(tmp_path, f"{code};31/10/2026 01:00;1\n{code};31/10/2026 01:05;2\n")
    with pytest.raises(OSError):
        ing.ingest_csv(source, tmp_path / "report.csv")
    with psycopg.connect(url) as conn:
        assert (
            conn.execute(
                "SELECT count(*) FROM readings WHERE sensor_id=%s", (sensor_id,)
            ).fetchone()[0]
            == 0
        )


def test_concurrent_loads(database, tmp_path, monkeypatch):
    code, sensor_id, url = database
    source = source_file(tmp_path, f"{code};31/10/2026 01:00;1\n")
    barrier = Barrier(2)
    original = ing.validate

    def synchronized(record, sensors, zone):
        reading = original(record, sensors, zone)
        barrier.wait(timeout=10)
        return reading

    monkeypatch.setattr(ing, "validate", synchronized)
    with ThreadPoolExecutor(max_workers=2) as pool:
        futures = [
            pool.submit(ing.ingest_csv, source, tmp_path / f"report-{i}.csv") for i in range(2)
        ]
        results = [f.result(timeout=20) for f in futures]
    assert sorted(r.inserted for r in results) == [0, 1]
    with psycopg.connect(url) as conn:
        assert (
            conn.execute(
                "SELECT count(*) FROM readings WHERE sensor_id=%s", (sensor_id,)
            ).fetchone()[0]
            == 1
        )


def test_course_csv_twice(ingestion_test_url, tmp_path, monkeypatch):
    """Rollback a real course load so the separate seed remains reusable."""
    with psycopg.connect(ingestion_test_url) as conn:
        baseline = conn.execute("SELECT count(*), min(time), max(time) FROM readings").fetchone()
        assert baseline[0] == 313760, "Esta prueba requiere una copia limpia del seed"

        class RollbackOnly:
            def execute(self, *args, **kwargs):
                return conn.execute(*args, **kwargs)

            def commit(self):
                pass  # Both ingestions see real inserts in this transaction; rollback at end.

        @contextmanager
        def connection():
            yield RollbackOnly()

        monkeypatch.setattr(ing, "connect", connection)
        try:
            source = ROOT / "data/incoming/lecturas_2026-10-31_2026-11-06.csv"
            first = ing.ingest_csv(source, tmp_path / "first.csv")
            assert first.inserted > 0
            assert {k: reasons(tmp_path / "first.csv")[k] for k in (4, 6, 7, 8)} == {
                4: "duplicado_fichero",
                6: "sensor_desconocido",
                7: "valor_invalido",
                8: "valor_invalido",
            }
            assert conn.execute("SELECT count(*) FROM readings").fetchone()[0] == (
                baseline[0] + first.inserted
            )
            second = ing.ingest_csv(source, tmp_path / "second.csv")
            assert second.inserted == 0
            assert second.processed == first.processed
            assert (
                conn.execute(
                    "SELECT value FROM readings r JOIN sensors s ON s.id=r.sensor_id "
                    "WHERE s.code='VAL-T01' AND r.time='2026-10-31T00:05:00Z'"
                ).fetchone()[0]
                == 23.31
            )
        finally:
            conn.rollback()
        assert (
            conn.execute("SELECT count(*), min(time), max(time) FROM readings").fetchone()
            == baseline
        )


def test_migration_and_missing_uniqueness(ingestion_test_url, tmp_path, monkeypatch):
    """Exercise DDL in a disposable schema on the isolated server, after approval."""
    require_ddl_approval()
    schema = "ingestion_" + uuid4().hex
    migration = (ROOT / "db/migrations/01_readings_unique.sql").read_text()
    with psycopg.connect(ingestion_test_url, autocommit=True) as conn:
        conn.execute(sql.SQL("CREATE SCHEMA {}").format(sql.Identifier(schema)))
        try:
            conn.execute(sql.SQL("SET search_path TO {}, public").format(sql.Identifier(schema)))
            conn.execute(
                "CREATE TABLE readings (sensor_id integer, time timestamptz NOT NULL, "
                "value double precision)"
            )
            conn.execute("SELECT create_hypertable('readings', 'time')")
            conn.execute("CREATE TABLE sensors (id integer, code text)")
            conn.execute("INSERT INTO sensors VALUES (1, 'TEST')")
            assert not conn.execute(ing.UNIQUE_INDEX_SQL).fetchone()[0]

            @contextmanager
            def connection():
                yield conn

            monkeypatch.setattr(ing, "connect", connection)
            with pytest.raises(ing.IngestionError, match="unicidad"):
                ing.ingest_csv(
                    source_file(tmp_path, "TEST;31/10/2026 01:00;1\n"), tmp_path / "missing.csv"
                )
            conn.execute(
                "INSERT INTO readings VALUES (1, '2026-01-01T00:00:00Z', 1), "
                "(1, '2026-01-01T00:00:00Z', 2)"
            )
            with pytest.raises(psycopg.Error, match="Duplicados históricos"):
                conn.execute(migration)
            conn.execute("ROLLBACK")
            assert conn.execute("SELECT count(*) FROM readings").fetchone()[0] == 2
            conn.execute("DELETE FROM readings")
            conn.execute(
                "CREATE INDEX readings_sensor_time_unique_idx ON readings(sensor_id, time)"
            )
            with pytest.raises(psycopg.Error, match="homónimo"):
                conn.execute(migration)
            conn.execute("ROLLBACK")
            conn.execute("DROP INDEX readings_sensor_time_unique_idx")
            conn.execute(migration)
            assert conn.execute(ing.UNIQUE_INDEX_SQL).fetchone()[0]
            conn.execute(migration)  # Idempotent migration verifies existing definition.
        finally:
            conn.execute("ROLLBACK")
            conn.execute("SET search_path TO public")
            conn.execute(sql.SQL("DROP SCHEMA {} CASCADE").format(sql.Identifier(schema)))
