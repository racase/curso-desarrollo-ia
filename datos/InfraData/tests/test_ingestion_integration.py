"""Ingesta real en una base efímera, sin alterar el conjunto local del curso."""

import csv
from concurrent.futures import ThreadPoolExecutor
from contextlib import contextmanager
from datetime import UTC, datetime
from pathlib import Path
from threading import Barrier
from uuid import uuid4

import psycopg
import pytest
from psycopg import sql
from psycopg.conninfo import make_conninfo

from infradata import ingestion
from infradata.config import database_url
from infradata.db import connect

pytestmark = pytest.mark.integration
ROOT = Path(__file__).resolve().parents[1]
MIGRATION = (ROOT / "db/migrations/01_readings_unique.sql").read_text()
HEADER = "codigo_sensor;fecha_hora_local;valor\n"


@pytest.fixture(scope="module")
def isolated_url():
    try:
        admin = connect(connect_timeout=2)
    except psycopg.OperationalError:
        pytest.skip("Base no disponible; ejecutar docker compose up -d")
    name = f"infradata_test_ingestion_{uuid4().hex}"
    url = make_conninfo(database_url(), dbname=name)
    admin.autocommit = True
    try:
        admin.execute(sql.SQL("CREATE DATABASE {}").format(sql.Identifier(name)))
        with connect(url) as conn:
            conn.execute((ROOT / "db/init/01_schema.sql").read_text(), prepare=False)
        yield url
    finally:
        # Solo la base efímera creada por esta fixture; nunca la base configurada.
        admin.execute(
            sql.SQL("DROP DATABASE IF EXISTS {} WITH (FORCE)").format(sql.Identifier(name))
        )
        admin.close()


@pytest.fixture
def db(isolated_url, monkeypatch):
    with connect(isolated_url) as conn:
        conn.execute("TRUNCATE readings, sensors RESTART IDENTITY")
        conn.execute(
            """INSERT INTO sensors (code, name, kind, unit, site, installed_at)
               VALUES ('A', 'Prueba', 'temperature', '°C', 'Prueba', '2026-01-01')"""
        )
    monkeypatch.setattr(ingestion, "connect", lambda: connect(isolated_url))
    return isolated_url


def load(tmp_path, content, name="rechazos.csv"):
    source = tmp_path / "entrada.csv"
    report = tmp_path / name
    source.write_text(HEADER + content, encoding="utf-8")
    result = ingestion.ingest_csv(source, report)
    with report.open(encoding="utf-8", newline="") as stream:
        rows = list(csv.DictReader(stream, delimiter=";"))
    return result, rows


@pytest.mark.parametrize("zone", ["UTC", "Europe/Madrid"])
def test_partial_load_reingestion_and_original_values(db, tmp_path, monkeypatch, zone):
    @contextmanager
    def connection():
        with connect(db) as conn:
            conn.execute("SELECT set_config('TimeZone', %s, false)", (zone,))
            conn.commit()
            yield conn

    monkeypatch.setattr(ingestion, "connect", connection)
    content = (
        "A;31/10/2026 01:00;23,1\nA;31/10/2026 01:00;23,10\n"
        "A;31/10/2026 01:00;24\nUNKNOWN;31/10/2026 01:00;1\n"
        "A;31/10/2026 01:05;\nA;31/10/2026 01:10;n/d\n"
        "A;31/10/2026 01:15;-40\nA;25/10/2026 02:30;1\n"
        "A;29/03/2026 02:30;1\n"
    )
    result, rows = load(tmp_path, content)
    assert (result.total, result.inserted, result.rejected) == (9, 2, 7)
    assert [r["motivo"] for r in rows] == [
        "duplicado_en_csv",
        "conflicto_en_csv",
        "sensor_desconocido",
        "valor_vacio",
        "valor_no_numerico",
        "hora_ambigua",
        "hora_inexistente",
    ]
    with connect(db) as conn:
        assert conn.execute("SELECT time, value FROM readings ORDER BY time").fetchall() == [
            (datetime(2026, 10, 31, tzinfo=UTC), 23.1),
            (datetime(2026, 10, 31, 0, 15, tzinfo=UTC), -40),
        ]
    second, rows = load(tmp_path, content, "segundo.csv")
    assert (second.inserted, second.rejected) == (0, 9)
    assert rows[0]["motivo"] == "ya_cargada"
    _, rows = load(tmp_path, "A;31/10/2026 01:00;50\n", "conflicto.csv")
    assert rows[0]["motivo"] == "conflicto_con_existente"


@pytest.mark.parametrize("failure", ["syntax", "sql"])
def test_real_transaction_rollback(db, tmp_path, monkeypatch, failure):
    original = ingestion.insert_reading
    calls = 0

    def insert(conn, sensor, stamp, value):
        nonlocal calls
        calls += 1
        if calls == 2 and failure == "sql":
            conn.execute("SELECT 1 / 0")
        original(conn, sensor, stamp, value)

    monkeypatch.setattr(ingestion, "insert_reading", insert)
    content = "A;31/10/2026 01:00;1\n"
    content += '"unfinished' if failure == "syntax" else "A;31/10/2026 01:05;2\n"
    with pytest.raises((ingestion.InputError, psycopg.Error)):
        load(tmp_path, content)
    with connect(db) as conn:
        assert conn.execute("SELECT count(*) FROM readings").fetchone()[0] == 0
    assert not (tmp_path / "rechazos.csv").exists()


@pytest.mark.parametrize("second_value", [1, 2])
def test_simultaneous_ingestion_has_one_winner(db, tmp_path, monkeypatch, second_value):
    barrier = Barrier(2)
    original = ingestion.insert_reading

    def simultaneous(conn, sensor, stamp, value):
        barrier.wait(timeout=10)
        original(conn, sensor, stamp, value)

    monkeypatch.setattr(ingestion, "insert_reading", simultaneous)
    sources = [tmp_path / "uno.csv", tmp_path / "dos.csv"]
    reports = [tmp_path / "rechazos_uno.csv", tmp_path / "rechazos_dos.csv"]
    for source, value in zip(sources, [1, second_value], strict=True):
        source.write_text(HEADER + f"A;31/10/2026 01:00;{value}\n")
    with ThreadPoolExecutor(max_workers=2) as pool:
        futures = [
            pool.submit(ingestion.ingest_csv, s, r) for s, r in zip(sources, reports, strict=True)
        ]
        results = [future.result(timeout=20) for future in futures]
    assert sum(r.inserted for r in results) == 1
    assert sum(r.rejected for r in results) == 1
    rejected = []
    for report in reports:
        with report.open() as stream:
            rejected.extend(csv.DictReader(stream, delimiter=";"))
    assert rejected[0]["motivo"] == (
        "ya_cargada" if second_value == 1 else "conflicto_con_existente"
    )
    with connect(db) as conn:
        assert conn.execute("SELECT count(*) FROM readings").fetchone()[0] == 1


def test_missing_constraint_is_not_migrated_automatically(db, tmp_path):
    with connect(db) as conn:
        conn.execute("ALTER TABLE readings DROP CONSTRAINT readings_sensor_time_unique")
    try:
        with pytest.raises(ingestion.IngestionError, match="migración"):
            load(tmp_path, "A;31/10/2026 01:00;1\n")
        with connect(db) as conn:
            assert conn.execute("SELECT count(*) FROM readings").fetchone()[0] == 0
    finally:
        with connect(db) as conn:
            conn.execute(MIGRATION, prepare=False)


def test_migration_aborts_with_duplicates_then_preserves_seed_and_hypertable(db):
    with connect(db) as conn:
        conn.execute("ALTER TABLE readings DROP CONSTRAINT readings_sensor_time_unique")
        conn.execute(
            """INSERT INTO readings VALUES ('2026-10-31 00:00+00', 1, 1),
                                            ('2026-10-31 00:00+00', 1, 2)"""
        )
    with connect(db) as conn:
        with pytest.raises(psycopg.errors.RaiseException, match="duplicadas"):
            conn.execute(MIGRATION, prepare=False)
        conn.rollback()
        assert conn.execute("SELECT count(*) FROM readings").fetchone()[0] == 2
        assert not conn.execute(ingestion.UNIQUE_CONSTRAINT).fetchone()
    # Solo se limpian los datos sintéticos de esta base efímera para probar el caso sin duplicados.
    with connect(db) as conn:
        conn.execute("TRUNCATE readings, sensors RESTART IDENTITY")
        conn.execute((ROOT / "db/init/02_seed.sql").read_text(), prepare=False)
    with connect(db) as conn:
        before = conn.execute(
            "SELECT count(*), min(time), max(time), sum(value::numeric) FROM readings"
        ).fetchone()
        conn.execute(MIGRATION, prepare=False)
        # La segunda ejecución es inocua.
        conn.execute(MIGRATION, prepare=False)
        assert conn.execute(ingestion.UNIQUE_CONSTRAINT).fetchone()
        assert (
            conn.execute(
                "SELECT count(*), min(time), max(time), sum(value::numeric) FROM readings"
            ).fetchone()
            == before
        )
        assert before[0] == 313760
        assert conn.execute(
            "SELECT 1 FROM timescaledb_information.hypertables WHERE hypertable_name = 'readings'"
        ).fetchone()
        assert conn.execute(
            "SELECT 1 FROM pg_indexes WHERE tablename = 'readings' "
            "AND indexname = 'readings_sensor_time_idx'"
        ).fetchone()
