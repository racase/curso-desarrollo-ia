"""Find intervals between consecutive sensor readings."""

from dataclasses import dataclass
from datetime import datetime

import psycopg


@dataclass(frozen=True)
class ReadingGap:
    """An interval bounded by two consecutive readings."""

    code: str
    site: str
    start: datetime
    end: datetime
    minutes: float


def reading_gaps(
    conn: psycopg.Connection,
    *,
    minutes: int = 5,
    site: str | None = None,
    sensor: str | None = None,
) -> list[ReadingGap]:
    """Return intervals longer than ``minutes`` for selected sensors."""
    if minutes <= 0:
        raise ValueError("El umbral debe ser mayor que cero")

    query = [
        "WITH ordered AS ("
        "SELECT r.sensor_id, r.time AS start_time, "
        "lead(r.time) OVER (PARTITION BY r.sensor_id ORDER BY r.time) AS end_time "
        "FROM readings AS r JOIN sensors AS s ON s.id = r.sensor_id WHERE true"
    ]
    params: list[object] = []
    if site is not None:
        query.append(" AND s.site = %s")
        params.append(site)
    if sensor is not None:
        query.append(" AND s.code = %s")
        params.append(sensor)
    query.append(
        ") SELECT s.code, s.site, o.start_time, o.end_time, "
        "extract(epoch FROM (o.end_time - o.start_time)) / 60.0 "
        "FROM ordered AS o JOIN sensors AS s ON s.id = o.sensor_id "
        "WHERE o.end_time - o.start_time > %s * interval '1 minute' "
        "ORDER BY o.start_time, s.code"
    )
    params.append(minutes)
    rows = conn.execute("".join(query), params).fetchall()
    return [
        ReadingGap(code, row_site, start, end, float(duration))
        for code, row_site, start, end, duration in rows
    ]
