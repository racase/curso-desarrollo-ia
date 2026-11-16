"""Tests for the visual notebook's data preparation."""

from datetime import UTC, date, datetime

import pytest

from infradata.exploration import (
    COURSE_END,
    COURSE_START,
    READINGS_PER_DAY,
    Sensor,
    completion_grid,
    course_completion,
    sensor_readings,
    sensors,
)
from infradata.summary import DailySummary


class Connection:
    def __init__(self, rows):
        self.rows = rows
        self.query = ""
        self.params = None

    def execute(self, query, params=None):
        self.query = query
        self.params = params
        return self

    def fetchall(self):
        return self.rows


def test_completion_grid_includes_missing_days_and_sensors():
    summary = DailySummary("A", "A", "Site", "°C", date(2026, 8, 1), 1, 2, 1.5, 144)
    codes, days, values = completion_grid(
        [Sensor("A", "A", "°C"), Sensor("B", "B", "bar")],
        [summary],
        date(2026, 8, 1),
        date(2026, 8, 2),
    )
    assert codes == ["A", "B"]
    assert days == ["2026-08-01", "2026-08-02"]
    assert values == [[50.0, 0.0], [0.0, 0.0]]
    assert READINGS_PER_DAY == 288


def test_sensor_readings_filters_by_code_and_utc_days():
    timestamp = datetime(2026, 8, 1, tzinfo=UTC)
    conn = Connection([(timestamp, 12.5)])
    assert sensor_readings(conn, "A", date(2026, 8, 1), date(2026, 8, 2)) == [(timestamp, 12.5)]
    assert conn.params == (
        "A",
        timestamp,
        datetime(2026, 8, 3, tzinfo=UTC),
    )
    assert "s.code = %s" in conn.query
    assert "ORDER BY r.time" in conn.query


def test_reversed_dates_are_rejected():
    with pytest.raises(ValueError, match="fecha inicial"):
        completion_grid([], [], date(2026, 8, 2), date(2026, 8, 1))
    with pytest.raises(ValueError, match="fecha inicial"):
        sensor_readings(Connection([]), "A", date(2026, 8, 2), date(2026, 8, 1))


@pytest.mark.integration
def test_course_heatmap_uses_precomputed_summary(migrated_conn):
    codes, days, values = course_completion(migrated_conn)
    assert len(codes) == 12
    assert len(days) == (COURSE_END - COURSE_START).days + 1
    assert values[codes.index("SAG-P02")][days.index("2026-09-03")] == round(210 / 288 * 100, 2)
    assert values[codes.index("PAT-L01")][days.index("2026-10-13")] == 0.0


@pytest.mark.integration
def test_every_sensor_has_a_selectable_series(migrated_conn):
    for sensor in sensors(migrated_conn):
        rows = sensor_readings(migrated_conn, sensor.code)
        assert rows
        assert rows == sorted(rows)
