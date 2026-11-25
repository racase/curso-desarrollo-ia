"""Pruebas de agregación con datos reversibles en PostgreSQL."""

from contextlib import contextmanager
from datetime import date
from uuid import uuid4

import psycopg
import pytest

from infradata import analytics
from infradata.__main__ import main
from infradata.db import connect

pytestmark = pytest.mark.integration


@pytest.mark.parametrize(
    ("sensor", "day", "unit", "minimum", "maximum", "mean", "count"),
    [
        ("VAL-T01", date(2026, 8, 1), "°C", 18.6, 27.46, 23.013472222222234, 288),
        ("SAG-P02", date(2026, 9, 3), "bar", 6.11, 7.27, 6.66657142857143, 210),
    ],
)
def test_seed_reference_statistics(conn, sensor, day, unit, minimum, maximum, mean, count):
    # Valores obtenidos mediante SQL independiente, registrado en referencias.md del cambio.
    rows = analytics.daily_summary(sensor=sensor, since=day, until=day)
    assert len(rows) == 1
    row = rows[0]
    assert (row.sensor, row.day, row.unit, row.count) == (sensor, day, unit, count)
    assert row.minimum == minimum
    assert row.maximum == maximum
    assert row.mean == pytest.approx(mean, abs=0.000001, rel=0)


@pytest.fixture
def conn(monkeypatch):
    try:
        connection = connect(connect_timeout=2)
    except psycopg.OperationalError:
        pytest.skip("database not reachable; run `docker compose up -d`")

    @contextmanager
    def shared_connection():
        with connection.transaction():
            yield connection

    monkeypatch.setattr(analytics, "connect", shared_connection)
    try:
        # Mantener una transacción externa para revertir todos los datos de prueba.
        connection.execute("SELECT 1")
        yield connection
    finally:
        connection.rollback()
        connection.close()


@pytest.fixture
def sample(conn):
    site = f"Prueba {uuid4().hex}"
    codes = [f"{site}-A", f"{site}-B"]
    for code in codes:
        sensor_id = conn.execute(
            """INSERT INTO sensors (code, name, kind, unit, site, installed_at)
               VALUES (%s, 'Prueba', 'temperature', '°C', %s, '2026-01-01') RETURNING id""",
            (code, site),
        ).fetchone()[0]
        # Fechas dentro de chunks existentes, sin alterar el conjunto permanente.
        for stamp, value in [
            ("2026-09-01 00:00+00", 10),
            ("2026-09-01 12:00+00", 20),
            ("2026-09-01 23:55+00", 30),
            ("2026-09-02 00:00+00", -40),
            ("2026-09-04 00:00+00", 20),
        ]:
            conn.execute(
                "INSERT INTO readings (time, sensor_id, value) VALUES (%s, %s, %s)",
                (stamp, sensor_id, value),
            )
    return site, codes


@pytest.mark.parametrize("zone", ["UTC", "Europe/Madrid"])
def test_utc_grouping_inclusive_bounds_and_anomalies(conn, sample, zone):
    conn.execute("SELECT set_config('TimeZone', %s, true)", (zone,))
    site, codes = sample
    rows = analytics.daily_summary(
        site=site, sensor=codes[0], since=date(2026, 9, 1), until=date(2026, 9, 2)
    )
    assert rows == [
        analytics.DailySummary(site, codes[0], date(2026, 9, 1), "°C", 10, 30, 20, 3),
        analytics.DailySummary(site, codes[0], date(2026, 9, 2), "°C", -40, -40, -40, 1),
    ]
    assert (
        analytics.daily_summary(sensor=codes[0], since=date(2026, 9, 1), until=date(2026, 9, 1))
        == rows[:1]
    )


def test_optional_filters_order_and_empty_days(conn, sample):
    site, codes = sample
    rows = analytics.daily_summary(site=site)
    assert [(r.day, r.sensor) for r in rows] == [
        (day, code)
        for day in [date(2026, 9, 1), date(2026, 9, 2), date(2026, 9, 4)]
        for code in codes
    ]
    assert analytics.daily_summary(sensor=codes[0]) == rows[::2]
    assert analytics.daily_summary(site=site, until=date(2026, 9, 1)) == rows[:2]
    assert analytics.daily_summary(site=site, since=date(2026, 9, 4)) == rows[-2:]
    assert analytics.daily_summary(site=site, since=date(2026, 9, 3), until=date(2026, 9, 3)) == []
    assert analytics.daily_summary(site=site, sensor="VAL-T01") == []
    assert analytics.daily_summary(site=site.lower()) == []
    assert analytics.daily_summary(sensor=codes[0].lower()) == []
    assert analytics.daily_summary(sensor="' OR true --") == []
    assert analytics.daily_summary(site="inexistente") == []
    # La consulta sin filtros contiene todos los grupos sintéticos, sin filas individuales.
    all_rows = analytics.daily_summary()
    assert [row for row in all_rows if row.site == site] == rows
    assert [(r.day, r.sensor) for r in all_rows] == sorted((r.day, r.sensor) for r in all_rows)


def test_cli_shows_real_count_and_expected_count(conn, sample, capsys):
    site, codes = sample
    assert (
        main(
            [
                "resumen-diario",
                "--sensor",
                codes[0],
                "--desde",
                "2026-09-01",
                "--hasta",
                "2026-09-01",
            ]
        )
        == 0
    )
    output = capsys.readouterr()
    assert output.err == ""
    assert output.out.splitlines() == [
        "Sede | Sensor | Día UTC | Unidad | Mínimo | Máximo | Media | Lecturas | Esperadas",
        f"{site} | {codes[0]} | 2026-09-01 | °C | 10.00 | 30.00 | 20.00 | 3 | 288",
    ]
