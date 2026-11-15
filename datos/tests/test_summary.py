"""Tests for daily sensor summaries."""

from datetime import UTC, date, datetime

import psycopg
import pytest

from infradata.db import connect
from infradata.summary import daily_summaries


class _Connection:
    def __init__(self, rows):
        self.rows = rows
        self.query = None
        self.params = None

    def execute(self, query, params):
        self.query = query
        self.params = params
        return self

    def fetchall(self):
        return self.rows


def test_daily_summary_filters_and_utc_day():
    conn = _Connection(
        [
            (
                "VAL-T01",
                "Temperatura horno 1",
                "Planta Valencia",
                "°C",
                datetime(2026, 8, 1, tzinfo=UTC),
                10.0,
                20.0,
                15.0,
                288,
            )
        ]
    )
    rows = daily_summaries(
        conn,
        site="Planta Valencia",
        sensor="VAL-T01",
        from_date=date(2026, 8, 1),
        to_date=date(2026, 8, 1),
    )
    assert rows[0].day == date(2026, 8, 1)
    assert rows[0].count == 288
    assert rows[0].unit == "°C"
    assert conn.params == [
        "Planta Valencia",
        "VAL-T01",
        datetime(2026, 8, 1, tzinfo=UTC),
        datetime(2026, 8, 2, tzinfo=UTC),
    ]
    assert "time_bucket('1 day', r.time)" in conn.query


def test_daily_summary_rejects_reversed_dates():
    with pytest.raises(ValueError, match="fecha inicial"):
        daily_summaries(_Connection([]), from_date=date(2026, 8, 2), to_date=date(2026, 8, 1))


@pytest.mark.integration
def test_seed_daily_summary_counts_missing_readings():
    try:
        connection = connect(connect_timeout=2)
    except psycopg.OperationalError:
        pytest.skip("database not reachable")
    with connection:
        rows = daily_summaries(
            connection,
            sensor="SAG-P02",
            from_date=date(2026, 9, 3),
            to_date=date(2026, 9, 3),
        )
    assert len(rows) == 1
    assert rows[0].count == 210
    assert rows[0].minimum <= rows[0].average <= rows[0].maximum
