"""Contract and fixed-course-dataset checks against PostgreSQL."""

from contextlib import contextmanager
from datetime import date

import psycopg
import pytest

from infradata import analytics
from infradata.analytics import daily_summary
from infradata.db import connect

pytestmark = pytest.mark.integration


@pytest.fixture(autouse=True, scope="module")
def database_available():
    try:
        with connect(connect_timeout=2):
            pass
    except psycopg.OperationalError:
        pytest.skip("database not reachable; run `docker compose up -d`")


@pytest.mark.parametrize(
    "sensor, day, unit, count, minimum, maximum, mean",
    [
        ("VAL-T01", date(2026, 8, 1), "°C", 288, 18.60, 27.46, 23.013472222222216),
        ("SAG-P02", date(2026, 9, 3), "bar", 210, 6.11, 7.27, 6.666571428571434),
        ("PAT-L01", date(2026, 10, 14), "%", 216, 44.27, 75.77, 56.85449074074074),
    ],
)
def test_fixed_dataset_reference(sensor, day, unit, count, minimum, maximum, mean):
    rows = daily_summary(sensor=sensor, start_date=day, end_date=day)
    assert len(rows) == 1
    row = rows[0]
    assert (row.day, row.sensor, row.unit, row.count) == (day, sensor, unit, count)
    assert row.minimum == pytest.approx(minimum, abs=1e-9, rel=0)
    assert row.maximum == pytest.approx(maximum, abs=1e-9, rel=0)
    assert row.mean == pytest.approx(mean, abs=1e-9, rel=0)


def test_filters_and_sorting():
    rows = daily_summary()
    assert len(rows) == 1090  # 12 sensors x 91 days, minus two completely empty days.
    assert [(r.day, r.sensor) for r in rows] == sorted((r.day, r.sensor) for r in rows)
    filters = [
        ({"site": "Planta Sagunto"}, lambda r: r.site == "Planta Sagunto"),
        ({"sensor": "SAG-P02"}, lambda r: r.sensor == "SAG-P02"),
        ({"start_date": date(2026, 9, 3)}, lambda r: r.day >= date(2026, 9, 3)),
        ({"end_date": date(2026, 9, 3)}, lambda r: r.day <= date(2026, 9, 3)),
        (
            {
                "site": "Planta Sagunto",
                "sensor": "SAG-P02",
                "start_date": date(2026, 9, 3),
                "end_date": date(2026, 9, 3),
            },
            lambda r: (
                r.site == "Planta Sagunto" and r.sensor == "SAG-P02" and r.day == date(2026, 9, 3)
            ),
        ),
    ]
    for options, matches in filters:
        actual = daily_summary(**options)
        expected = [row for row in rows if matches(row)]
        assert len(actual) == len(expected)
        for result, reference in zip(actual, expected, strict=True):
            assert (
                result.day,
                result.sensor,
                result.name,
                result.site,
                result.unit,
                result.minimum,
                result.maximum,
                result.count,
            ) == (
                reference.day,
                reference.sensor,
                reference.name,
                reference.site,
                reference.unit,
                reference.minimum,
                reference.maximum,
                reference.count,
            )
            assert result.mean == pytest.approx(reference.mean, abs=1e-9, rel=0)


@pytest.mark.parametrize(
    "options",
    [
        {"site": "Planta Valencia", "sensor": "SAG-P02"},
        {"sensor": "missing"},
        {"site": "missing"},
        {"sensor": "sag-p02"},
        {"site": "planta Sagunto"},
        {"sensor": "SAG-P02' OR TRUE --"},
        {"sensor": "PAT-L01", "start_date": date(2026, 10, 12), "end_date": date(2026, 10, 13)},
    ],
)
def test_no_matching_readings(options):
    assert daily_summary(**options) == []


def test_empty_days_are_omitted():
    rows = daily_summary(
        sensor="PAT-L01", start_date=date(2026, 10, 12), end_date=date(2026, 10, 14)
    )
    assert [(r.day, r.count) for r in rows] == [(date(2026, 10, 14), 216)]


def test_anomalous_readings_are_included():
    (row,) = daily_summary(
        sensor="VAL-T01", start_date=date(2026, 9, 18), end_date=date(2026, 9, 18)
    )
    assert row.maximum == 96.4
    assert row.count == 288


def test_utc_boundaries_ignore_session_timezone(monkeypatch):
    with connect() as conn:
        try:
            conn.execute("SET LOCAL TIME ZONE 'Europe/Madrid'")
            # Negative explicit id avoids advancing the persistent sensor sequence.
            conn.execute("""
                INSERT INTO sensors (id, code, name, kind, unit, site, installed_at)
                VALUES (-1001, 'TEST-UTC', 'UTC boundary', 'temperature', '°C',
                        'Test', '2026-01-01')
            """)
            conn.execute("""
                INSERT INTO readings (time, sensor_id, value) VALUES
                    ('2026-09-02 23:55:00+00', -1001, 99),
                    ('2026-09-03 00:00:00+00', -1001, 10),
                    ('2026-09-03 23:55:00+00', -1001, 10),
                    ('2026-09-04 00:00:00+00', -1001, 20),
                    ('2026-09-05 00:00:00+00', -1001, 30)
            """)

            @contextmanager
            def same_connection():
                yield conn

            monkeypatch.setattr(analytics, "connect", same_connection)
            rows = daily_summary(
                sensor="TEST-UTC", start_date=date(2026, 9, 3), end_date=date(2026, 9, 4)
            )
            assert [(r.day, r.minimum, r.maximum, r.mean, r.count) for r in rows] == [
                (date(2026, 9, 3), 10, 10, 10, 2),
                (date(2026, 9, 4), 20, 20, 20, 1),
            ]
        finally:
            conn.rollback()
