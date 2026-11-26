"""Detección SQL con referencias fijas y datos controlados reversibles."""

from contextlib import contextmanager
from datetime import UTC, date, datetime, timedelta
from uuid import uuid4

import psycopg
import pytest

from infradata import analytics
from infradata.__main__ import main
from infradata.db import connect

pytestmark = pytest.mark.integration


@pytest.fixture
def conn(monkeypatch):
    try:
        connection = connect(connect_timeout=2)
    except psycopg.OperationalError:
        pytest.skip("Base de datos no disponible; ejecuta docker compose up -d")

    @contextmanager
    def shared_connection():
        with connection.transaction():
            yield connection

    monkeypatch.setattr(analytics, "connect", shared_connection)
    try:
        connection.execute("SELECT 1")
        yield connection
    finally:
        connection.rollback()
        connection.close()


@pytest.fixture
def sample(conn):
    site = f"Huecos {uuid4().hex}"

    def create(code, times):
        code = f"{site}-{code}"
        sensor_id = conn.execute(
            """INSERT INTO sensors (code, name, kind, unit, site, installed_at)
               VALUES (%s, 'Prueba', 'temperature', '°C', %s, '2026-01-01') RETURNING id""",
            (code, site),
        ).fetchone()[0]
        for stamp in times:
            conn.execute(
                "INSERT INTO readings (time, sensor_id, value) VALUES (%s, %s, -40)",
                (stamp, sensor_id),
            )
        return code

    return site, create


def stamp(day, hour=0, minute=0, second=0, microsecond=0):
    return datetime(2026, 9, day, hour, minute, second, microsecond, tzinfo=UTC)


@pytest.mark.parametrize("threshold", [5, 15])
def test_seed_gaps_are_exactly_the_three_independent_references(conn, threshold):
    rows = analytics.reading_gaps(threshold_minutes=threshold)
    assert [(r.sensor, r.previous, r.following, r.duration, r.status) for r in rows] == [
        (
            "VAL-F01",
            datetime(2026, 8, 20, 22, 10, tzinfo=UTC),
            datetime(2026, 8, 20, 23, 5, tzinfo=UTC),
            timedelta(minutes=55),
            "completo",
        ),
        ("SAG-P02", stamp(3, 7, 55), stamp(3, 14, 30), timedelta(minutes=395), "completo"),
        (
            "PAT-L01",
            datetime(2026, 10, 11, 23, 55, tzinfo=UTC),
            datetime(2026, 10, 14, 6, tzinfo=UTC),
            timedelta(minutes=3245),
            "completo",
        ),
    ]


def test_seed_filters_partial_dates_and_strict_threshold(conn):
    assert [r.sensor for r in analytics.reading_gaps(site="Planta Valencia")] == ["VAL-F01"]
    assert [r.sensor for r in analytics.reading_gaps(sensor="SAG-P02")] == ["SAG-P02"]
    rows = analytics.reading_gaps(site="Almacén Paterna", sensor="PAT-L01")
    assert [r.sensor for r in rows] == ["PAT-L01"]
    assert analytics.reading_gaps(site="Planta Valencia", sensor="PAT-L01") == []
    assert analytics.reading_gaps(site="planta Valencia") == []
    assert analytics.reading_gaps(sensor="sag-p02") == []
    assert analytics.reading_gaps(sensor="' OR true --") == []
    assert analytics.reading_gaps(site="Almacen Paterna") == []
    assert analytics.reading_gaps(sensor="inexistente") == []
    assert analytics.reading_gaps(sensor="VAL-T01") == []
    assert analytics.reading_gaps(sensor="VAL-F01", threshold_minutes=55) == []
    assert [r.sensor for r in analytics.reading_gaps(until=date(2026, 8, 20))] == ["VAL-F01"]
    assert analytics.reading_gaps(since=date(2026, 10, 12)) == rows


@pytest.mark.parametrize("zone", ["UTC", "Europe/Madrid"])
def test_empty_range_inside_seed_gap_keeps_full_duration(conn, zone):
    conn.execute("SELECT set_config('TimeZone', %s, true)", (zone,))
    rows = analytics.reading_gaps(
        sensor="PAT-L01",
        since=date(2026, 10, 12),
        until=date(2026, 10, 13),
        threshold_minutes=3000,
    )
    assert len(rows) == 1
    assert rows[0].previous == datetime(2026, 10, 11, 23, 55, tzinfo=UTC)
    assert rows[0].following == datetime(2026, 10, 14, 6, tzinfo=UTC)
    assert rows[0].duration == timedelta(minutes=3245)
    assert rows[0].status == "completo"


def test_cadence_duplicates_threshold_and_no_rounding(conn, sample):
    _, create = sample
    code = create(
        "cadencia",
        [
            stamp(1),
            stamp(1, 0, 5),
            stamp(1, 0, 15),
            stamp(1, 0, 30),
            stamp(1, 0, 45, microsecond=1),
            stamp(2),
        ],
    )
    # El esquema de ingesta impide ahora duplicados; el savepoint conserva el resto del caso.
    with pytest.raises(psycopg.errors.UniqueViolation), conn.transaction():
        conn.execute(
            """INSERT INTO readings (time, sensor_id, value)
               SELECT %s, id, -40 FROM sensors WHERE code = %s""",
            (stamp(1), code),
        )
    kwargs = dict(sensor=code, since=date(2026, 9, 1), until=date(2026, 9, 1))
    rows = analytics.reading_gaps(**kwargs)
    assert [r.duration for r in rows] == [
        timedelta(minutes=10),
        timedelta(minutes=15),
        timedelta(minutes=15, microseconds=1),
        timedelta(hours=23, minutes=15, microseconds=-1),
    ]
    assert all(r.status == "completo" for r in rows)
    rows = analytics.reading_gaps(**kwargs, threshold_minutes=15)
    assert rows[0].duration == timedelta(minutes=15, microseconds=1)
    assert len(rows) == 2


@pytest.mark.parametrize("zone", ["UTC", "Europe/Madrid"])
def test_open_edges_empty_sensor_clipping_and_order(conn, sample, zone):
    conn.execute("SELECT set_config('TimeZone', %s, true)", (zone,))
    site, create = sample
    start = create("A", [stamp(1, 0, 20), stamp(2)])
    end = create("B", [stamp(1), stamp(1, 23, 40)])
    empty = create("C", [])
    after = create("D", [stamp(3)])
    before = create("E", [stamp(1) - timedelta(days=1)])
    kwargs = dict(site=site, since=date(2026, 9, 1), until=date(2026, 9, 1), threshold_minutes=15)
    rows = analytics.reading_gaps(**kwargs)
    assert [(r.sensor, r.status) for r in rows] == [
        (before, "abierto al final"),
        (start, "abierto al inicio"),
        (end, "completo"),
        (empty, "abierto en ambos extremos"),
        (after, "abierto al inicio"),
        (start, "completo"),
        (end, "abierto al final"),
    ]
    # El orden usa el extremo real si existe, incluso cuando cae antes del rango.
    assert [(r.previous or stamp(1), r.sensor, r.following or stamp(2)) for r in rows] == sorted(
        (r.previous or stamp(1), r.sensor, r.following or stamp(2)) for r in rows
    )
    opened = {r.sensor: r for r in rows if r.status != "completo"}
    assert set(opened) == {start, end, empty, after, before}
    assert (opened[start].previous, opened[start].following, opened[start].duration) == (
        None,
        stamp(1, 0, 20),
        timedelta(minutes=20),
    )
    assert (opened[end].previous, opened[end].following, opened[end].duration) == (
        stamp(1, 23, 40),
        None,
        timedelta(minutes=20),
    )
    assert opened[empty].previous is None and opened[empty].following is None
    assert opened[empty].duration == timedelta(days=1)
    assert opened[after].following == stamp(3)
    assert opened[after].duration == opened[before].duration == timedelta(days=1)


def test_open_cotas_equal_threshold_and_exact_boundary_contacts(conn, sample):
    site, create = sample
    create("inicio", [stamp(1, 0, 15), stamp(2)])
    create("fin", [stamp(1), stamp(1, 23, 45)])
    # Estos huecos exteriores solo tocan los límites; no se solapan.
    create("contacto", [stamp(1) - timedelta(hours=1), stamp(1), stamp(2), stamp(2, 1)])
    rows = analytics.reading_gaps(
        site=site, since=date(2026, 9, 1), until=date(2026, 9, 1), threshold_minutes=15
    )
    assert len(rows) == 3
    assert all(r.status == "completo" for r in rows)
    assert all(r.previous >= stamp(1) and r.following <= stamp(2) for r in rows)


def test_one_reading_has_two_open_edges_and_default_bounds(conn, sample):
    _, create = sample
    code = create("unica", [stamp(1, 12)])
    rows = analytics.reading_gaps(sensor=code, since=date(2026, 9, 1), until=date(2026, 9, 1))
    assert [(r.status, r.duration) for r in rows] == [
        ("abierto al inicio", timedelta(hours=12)),
        ("abierto al final", timedelta(hours=12)),
    ]
    rows = analytics.reading_gaps(sensor=code)
    assert rows[0].duration == stamp(1, 12) - datetime(2026, 8, 1, tzinfo=UTC)
    assert rows[1].duration == datetime(2026, 10, 31, tzinfo=UTC) - stamp(1, 12)


def test_cli_seed_and_open_output(conn, sample, capsys):
    assert main(["huecos", "--sensor", "PAT-L01", "--umbral-minutos", "15"]) == 0
    assert capsys.readouterr().out.splitlines()[-1] == (
        "Almacén Paterna | PAT-L01 | 2026-10-11 23:55:00 UTC | "
        "2026-10-14 06:00:00 UTC | 54:05:00 | completo"
    )
    site, create = sample
    code = create("vacio", [])
    assert main(["huecos", "--sensor", code, "--desde", "2026-09-01", "--hasta", "2026-09-01"]) == 0
    assert capsys.readouterr().out.splitlines()[-1] == (
        f"{site} | {code} | desconocido | desconocido | >= 24:00:00 | abierto en ambos extremos"
    )
