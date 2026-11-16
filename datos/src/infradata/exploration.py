"""Data preparation for the visual exploration notebook."""

from dataclasses import dataclass
from datetime import UTC, date, datetime, time, timedelta

import psycopg

from infradata.summary import DailySummary, daily_summaries

COURSE_START = date(2026, 8, 1)
COURSE_END = date(2026, 10, 30)
READINGS_PER_DAY = 24 * 60 // 5


@dataclass(frozen=True)
class Sensor:
    """Sensor metadata needed by the notebook."""

    code: str
    name: str
    unit: str


def sensors(conn: psycopg.Connection) -> list[Sensor]:
    """Return every course sensor, including sensors with no readings."""
    rows = conn.execute("SELECT code, name, unit FROM sensors ORDER BY code").fetchall()
    return [Sensor(*row) for row in rows]


def completion_grid(
    sensor_list: list[Sensor], summaries: list[DailySummary], start: date, end: date
) -> tuple[list[str], list[str], list[list[float]]]:
    """Build the complete UTC calendar, treating missing summary rows as zero."""
    if start > end:
        raise ValueError("La fecha inicial no puede ser posterior a la final")
    days = [start + timedelta(days=offset) for offset in range((end - start).days + 1)]
    counts = {(item.code, item.day): item.count for item in summaries}
    values = [
        [round(100 * counts.get((sensor.code, day), 0) / READINGS_PER_DAY, 2) for day in days]
        for sensor in sensor_list
    ]
    return [sensor.code for sensor in sensor_list], [day.isoformat() for day in days], values


def course_completion(conn: psycopg.Connection) -> tuple[list[str], list[str], list[list[float]]]:
    """Load the heatmap from the precomputed daily summary."""
    return completion_grid(
        sensors(conn),
        daily_summaries(conn, from_date=COURSE_START, to_date=COURSE_END),
        COURSE_START,
        COURSE_END,
    )


def sensor_readings(
    conn: psycopg.Connection, code: str, start: date = COURSE_START, end: date = COURSE_END
) -> list[tuple[datetime, float]]:
    """Load the selected sensor's raw time series within inclusive UTC days."""
    if start > end:
        raise ValueError("La fecha inicial no puede ser posterior a la final")
    rows = conn.execute(
        "SELECT r.time, r.value FROM readings AS r "
        "JOIN sensors AS s ON s.id = r.sensor_id "
        "WHERE s.code = %s AND r.time >= %s AND r.time < %s ORDER BY r.time",
        (
            code,
            datetime.combine(start, time.min, UTC),
            datetime.combine(end + timedelta(days=1), time.min, UTC),
        ),
    ).fetchall()
    return [(timestamp.astimezone(UTC), value) for timestamp, value in rows]
