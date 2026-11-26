"""Missing-reading contracts against PostgreSQL and the fixed course dataset."""

from contextlib import contextmanager
from datetime import UTC, date, datetime, timedelta
from decimal import Decimal

import psycopg
import pytest

from infradata import analytics
from infradata.analytics import reading_gaps
from infradata.db import connect

pytestmark = pytest.mark.integration


def utc(value):
    return datetime.fromisoformat(value).replace(tzinfo=UTC)


def flags(row):
    return row.clipped_start, row.clipped_end, row.open_start, row.open_end


@pytest.fixture(autouse=True, scope="module")
def database_available():
    try:
        with connect(connect_timeout=2):
            pass
    except psycopg.OperationalError:
        pytest.skip("database not reachable; run `docker compose up -d`")


@pytest.fixture
def transaction(monkeypatch):
    """Isolate every test write, including empty-history checks, with rollback."""
    with connect() as conn:

        @contextmanager
        def same_connection():
            yield conn

        monkeypatch.setattr(analytics, "connect", same_connection)
        try:
            yield conn
        finally:
            conn.rollback()


def add_sensor(conn, times, code="TEST-GAPS", sensor_id=-2001):
    conn.execute(
        """INSERT INTO sensors (id, code, name, kind, unit, site, installed_at)
           VALUES (%s, %s, 'Gap test', 'temperature', '°C', 'Test', '2026-01-01')""",
        (sensor_id, code),
    )
    with conn.cursor() as cursor:
        cursor.executemany(
            "INSERT INTO readings (sensor_id, time, value) VALUES (%s, %s, 1)",
            [(sensor_id, instant) for instant in times],
        )


def test_all_fixed_dataset_gaps():
    rows = reading_gaps()
    assert [(r.sensor, r.site, r.start, r.end, r.duration_minutes) for r in rows] == [
        (
            "VAL-F01",
            "Planta Valencia",
            utc("2026-08-20T22:15"),
            utc("2026-08-20T23:05"),
            Decimal(50),
        ),
        (
            "SAG-P02",
            "Planta Sagunto",
            utc("2026-09-03T08:00"),
            utc("2026-09-03T14:30"),
            Decimal(390),
        ),
        (
            "PAT-L01",
            "Almacén Paterna",
            utc("2026-10-12T00:00"),
            utc("2026-10-14T06:00"),
            Decimal(3240),
        ),
    ]
    assert all(flags(row) == (False, False, False, False) for row in rows)
    assert reading_gaps(sensor="VAL-F01", threshold_minutes=50) == []
    assert len(reading_gaps(sensor="VAL-F01", threshold_minutes=49)) == 1


@pytest.mark.parametrize(
    "options, sensors",
    [
        ({"site": "Planta Sagunto"}, ["SAG-P02"]),
        (
            {
                "sensor": "SAG-P02",
                "site": "Planta Sagunto",
                "start_date": date(2026, 9, 3),
                "end_date": date(2026, 9, 3),
            },
            ["SAG-P02"],
        ),
        ({"start_date": date(2026, 9, 1)}, ["SAG-P02", "PAT-L01"]),
        ({"end_date": date(2026, 9, 30)}, ["VAL-F01", "SAG-P02"]),
        ({"site": "Planta Valencia", "sensor": "SAG-P02"}, []),
        ({"sensor": "missing"}, []),
        ({"site": "missing"}, []),
        ({"sensor": "sag-p02"}, []),
        ({"site": "planta Sagunto"}, []),
        ({"sensor": "SAG-P02' OR TRUE --"}, []),
        ({"start_date": date(2026, 11, 1)}, []),
        ({"end_date": date(2026, 7, 31)}, []),
    ],
)
def test_filters(options, sensors):
    assert [row.sensor for row in reading_gaps(**options)] == sensors


@pytest.mark.parametrize(
    "first, last, duration, expected_flags",
    [
        (12, 13, 2880, (False, True, False, False)),
        (14, 14, 360, (True, False, False, False)),
        (13, 13, 1440, (True, True, False, False)),
    ],
)
def test_empty_ranges_and_clipping(first, last, duration, expected_flags):
    options = {
        "sensor": "PAT-L01",
        "start_date": date(2026, 10, first),
        "end_date": date(2026, 10, last),
    }
    (row,) = reading_gaps(**options)
    assert row.start == utc(f"2026-10-{first:02d}T00:00")
    expected_end = utc("2026-10-14T06:00") if last == 14 else utc(f"2026-10-{last + 1}T00:00")
    assert row.end == expected_end
    assert row.duration_minutes == duration
    assert flags(row) == expected_flags
    assert reading_gaps(**options, threshold_minutes=duration) == []


def test_strict_threshold_and_subminute_precision(transaction):
    start = utc("2026-09-03T00:00")
    offsets = [0, 5, 25, 50, 60, 80 + 1 / 60]
    add_sensor(transaction, [start + timedelta(minutes=n) for n in offsets])
    options = {"sensor": "TEST-GAPS", "start_date": date(2026, 9, 3), "end_date": date(2026, 9, 3)}
    rows = reading_gaps(**options)
    closed = [r for r in rows if not r.open_end]
    assert [r.duration_minutes for r in closed] == pytest.approx([Decimal(20), Decimal(901) / 60])
    zero_rows = reading_gaps(**options, threshold_minutes=0)
    assert [r.duration_minutes for r in zero_rows if not r.open_end] == pytest.approx(
        [Decimal(15), Decimal(20), Decimal(5), Decimal(901) / 60]
    )


def test_normal_cadence_and_final_expected_interval():
    assert reading_gaps(sensor="VAL-T02", threshold_minutes=0) == []


def test_duplicate_timestamps_do_not_change_gaps(transaction, monkeypatch):
    add_sensor(
        transaction,
        [
            utc("2026-09-03T00:00"),
            utc("2026-09-03T00:05"),
            utc("2026-09-03T00:25"),
            utc("2026-09-04T00:00"),
        ],
    )
    options = {
        "sensor": "TEST-GAPS",
        "start_date": date(2026, 9, 3),
        "end_date": date(2026, 9, 3),
        "threshold_minutes": 0,
    }
    reference = reading_gaps(**options)

    class DuplicateInput:
        def execute(self, query, params):
            # A virtual input relation also works on databases with a unique index.
            # No schema change or persistent duplicate rows are required.
            query = query.replace(
                "WITH observation AS",
                """WITH readings AS (
                    SELECT * FROM public.readings
                    UNION ALL
                    SELECT * FROM public.readings WHERE sensor_id = -2001
                ), observation AS""",
                1,
            )
            return transaction.execute(query, params)

    @contextmanager
    def duplicate_connection():
        yield DuplicateInput()

    monkeypatch.setattr(analytics, "connect", duplicate_connection)
    assert reading_gaps(**options) == reference


def test_open_edges_and_global_window_independent_of_filter(transaction):
    add_sensor(transaction, [utc("2026-09-03T12:00")])
    rows = reading_gaps(sensor="TEST-GAPS")
    assert len(rows) == 2
    assert (rows[0].start, rows[0].end) == (
        utc("2026-08-01T00:00"),
        utc("2026-09-03T12:00"),
    )
    assert flags(rows[0]) == (False, False, True, False)
    assert (rows[1].start, rows[1].end) == (
        utc("2026-09-03T12:05"),
        utc("2026-10-31T00:00"),
    )
    assert flags(rows[1]) == (False, False, False, True)
    rows = reading_gaps(sensor="TEST-GAPS", start_date=date(2026, 9, 3), end_date=date(2026, 9, 3))
    assert [r.duration_minutes for r in rows] == [720, 715]
    assert [flags(r) for r in rows] == [(True, False, True, False), (False, True, False, True)]


def test_sensor_without_readings_and_tied_order(transaction):
    add_sensor(transaction, [], code="TEST-Z", sensor_id=-2001)
    add_sensor(transaction, [], code="TEST-A", sensor_id=-2002)
    rows = reading_gaps(site="Test", start_date=date(2026, 9, 3), end_date=date(2026, 9, 3))
    assert [r.sensor for r in rows] == ["TEST-A", "TEST-Z"]
    for row in rows:
        assert row.start == utc("2026-09-03T00:00")
        assert row.end == utc("2026-09-04T00:00")
        assert row.duration_minutes == 1440
        assert flags(row) == (True, True, True, True)


def test_exclusive_end_reading_closes_gap_and_timezone_independence(transaction):
    add_sensor(transaction, [utc("2026-09-03T23:00"), utc("2026-09-04T00:00")])
    options = {"sensor": "TEST-GAPS", "start_date": date(2026, 9, 3), "end_date": date(2026, 9, 3)}
    reference = reading_gaps(**options)
    last = reference[-1]
    assert last.start == utc("2026-09-03T23:05")
    assert last.end == utc("2026-09-04T00:00")
    assert last.duration_minutes == 55
    assert flags(last) == (False, False, False, False)
    transaction.execute("SET LOCAL TIME ZONE 'Europe/Madrid'")
    assert reading_gaps(**options) == reference


def test_globally_empty_readings_has_no_observation_window(transaction):
    # DELETE is confined to this transaction and rolled back by the fixture.
    transaction.execute("DELETE FROM readings")
    assert reading_gaps() == []
    assert reading_gaps(start_date=date(2026, 9, 3), end_date=date(2026, 9, 3)) == []
