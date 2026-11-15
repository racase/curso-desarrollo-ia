"""Tests for daily sensor summaries."""

from datetime import UTC, date, datetime

import pytest

from infradata.summary import daily_summaries, direct_daily_summaries


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
    assert "FROM daily_reading_summary AS d" in conn.query


def test_daily_summary_rejects_reversed_dates():
    with pytest.raises(ValueError, match="fecha inicial"):
        daily_summaries(_Connection([]), from_date=date(2026, 8, 2), to_date=date(2026, 8, 1))


@pytest.mark.integration
def test_seed_daily_summary_counts_missing_readings(migrated_conn):
    rows = daily_summaries(
        migrated_conn,
        sensor="SAG-P02",
        from_date=date(2026, 9, 3),
        to_date=date(2026, 9, 3),
    )
    assert len(rows) == 1
    assert rows[0].count == 210
    assert rows[0].minimum <= rows[0].average <= rows[0].maximum


@pytest.mark.integration
def test_precomputed_summary_matches_all_direct_rows(migrated_conn):
    assert daily_summaries(migrated_conn) == direct_daily_summaries(migrated_conn)
    assert len(daily_summaries(migrated_conn)) == 1090


@pytest.mark.integration
def test_precomputed_filters_match_direct_rows(migrated_conn):
    filters = {
        "site": "Planta Sagunto",
        "sensor": "SAG-P02",
        "from_date": date(2026, 9, 1),
        "to_date": date(2026, 9, 5),
    }
    assert daily_summaries(migrated_conn, **filters) == direct_daily_summaries(
        migrated_conn, **filters
    )
