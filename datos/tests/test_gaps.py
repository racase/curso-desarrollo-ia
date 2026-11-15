"""Tests for reading gap detection."""

from datetime import UTC, datetime

import psycopg
import pytest

from infradata.db import connect
from infradata.gaps import reading_gaps


class _Connection:
    def __init__(self):
        self.query = None
        self.params = None

    def execute(self, query, params):
        self.query = query
        self.params = params
        return self

    def fetchall(self):
        return [
            (
                "SAG-P02",
                "Planta Sagunto",
                datetime(2026, 9, 3, 7, 55, tzinfo=UTC),
                datetime(2026, 9, 3, 14, 30, tzinfo=UTC),
                395,
            )
        ]


def test_gaps_filter_and_duration():
    conn = _Connection()
    gaps = reading_gaps(conn, minutes=10, site="Planta Sagunto", sensor="SAG-P02")
    assert len(gaps) == 1
    assert gaps[0].minutes == 395
    assert conn.params == ["Planta Sagunto", "SAG-P02", 10]
    assert "lead(r.time)" in conn.query


def test_gaps_reject_nonpositive_threshold():
    with pytest.raises(ValueError, match="umbral"):
        reading_gaps(_Connection(), minutes=0)


@pytest.mark.integration
def test_seed_has_all_three_gaps():
    try:
        connection = connect(connect_timeout=2)
    except psycopg.OperationalError:
        pytest.skip("database not reachable")
    with connection:
        gaps = reading_gaps(connection)
    assert [(gap.code, gap.minutes) for gap in gaps] == [
        ("VAL-F01", 55),
        ("SAG-P02", 395),
        ("PAT-L01", 3245),
    ]
