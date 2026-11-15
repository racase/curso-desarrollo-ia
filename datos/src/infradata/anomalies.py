"""Classify suspicious sensor readings in the database."""

from dataclasses import dataclass
from datetime import datetime

import psycopg

LIMITS = (
    ("temperature", -20.0, 80.0, 20.0),
    ("pressure", 0.0, 20.0, 3.0),
    ("flow", 0.0, 200.0, 25.0),
    ("level", 0.0, 100.0, 25.0),
)


@dataclass(frozen=True)
class AnomalousReading:
    """A reading with one or more anomaly reasons."""

    code: str
    site: str
    unit: str
    time: datetime
    value: float
    reasons: tuple[str, ...]


def anomalous_readings(
    conn: psycopg.Connection, *, site: str | None = None, sensor: str | None = None
) -> list[AnomalousReading]:
    """Return classified readings for selected sensors."""
    query = [
        """
        WITH limits(kind, minimum, maximum, jump) AS (
            VALUES (%s::text, %s::float8, %s::float8, %s::float8),
                   (%s::text, %s::float8, %s::float8, %s::float8),
                   (%s::text, %s::float8, %s::float8, %s::float8),
                   (%s::text, %s::float8, %s::float8, %s::float8)
        ), neighbors AS (
            SELECT s.code, s.site, s.unit, s.kind, r.sensor_id, r.time, r.value,
                   lag(r.value) OVER (PARTITION BY r.sensor_id ORDER BY r.time)
                       AS previous_value,
                   lag(r.time) OVER (PARTITION BY r.sensor_id ORDER BY r.time)
                       AS previous_time
            FROM readings AS r
            JOIN sensors AS s ON s.id = r.sensor_id
            WHERE true
        """
    ]
    params: list[object] = [item for limit in LIMITS for item in limit]
    if site is not None:
        query.append(" AND s.site = %s")
        params.append(site)
    if sensor is not None:
        query.append(" AND s.code = %s")
        params.append(sensor)
    query.append(
        """
        ), runs AS (
            SELECT n.*,
                   sum(CASE
                           WHEN n.previous_value IS DISTINCT FROM n.value
                             OR n.time - n.previous_time <> interval '5 minutes'
                           THEN 1 ELSE 0
                       END) OVER (PARTITION BY n.sensor_id ORDER BY n.time) AS run_id
            FROM neighbors AS n
        ), classified AS (
            SELECT r.code, r.site, r.unit, r.time, r.value,
                   array_remove(ARRAY[
                       CASE WHEN r.value < l.minimum OR r.value > l.maximum
                            THEN 'out_of_range' END,
                       CASE WHEN max(r.time) OVER (PARTITION BY r.sensor_id, r.run_id)
                                       - min(r.time) OVER (PARTITION BY r.sensor_id, r.run_id)
                                       >= interval '15 minutes'
                            THEN 'fixed' END,
                       CASE WHEN r.time - r.previous_time = interval '5 minutes'
                                 AND abs(r.value - r.previous_value) > l.jump
                            THEN 'jump' END
                   ], NULL) AS reasons
            FROM runs AS r
            JOIN limits AS l ON l.kind = r.kind
        )
        SELECT code, site, unit, time, value, reasons
        FROM classified
        WHERE cardinality(reasons) > 0
        ORDER BY time, code
        """
    )
    rows = conn.execute("".join(query), params).fetchall()
    return [
        AnomalousReading(code, row_site, unit, time, value, tuple(reasons))
        for code, row_site, unit, time, value, reasons in rows
    ]
