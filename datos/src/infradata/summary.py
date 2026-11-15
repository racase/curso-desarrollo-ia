"""Daily sensor summaries computed by the database."""

from dataclasses import dataclass
from datetime import UTC, date, datetime, time, timedelta

import psycopg


@dataclass(frozen=True)
class DailySummary:
    """Aggregated readings for one sensor on one UTC day."""

    code: str
    name: str
    site: str
    unit: str
    day: date
    minimum: float
    maximum: float
    average: float
    count: int


def daily_summaries(
    conn: psycopg.Connection,
    *,
    site: str | None = None,
    sensor: str | None = None,
    from_date: date | None = None,
    to_date: date | None = None,
) -> list[DailySummary]:
    """Return daily readings aggregated in UTC with optional filters."""
    if from_date is not None and to_date is not None and from_date > to_date:
        raise ValueError("La fecha inicial no puede ser posterior a la final")

    query = [
        "SELECT s.code, s.name, s.site, s.unit, "
        "time_bucket('1 day', r.time) AS day, "
        "min(r.value), max(r.value), avg(r.value), count(*) "
        "FROM readings AS r JOIN sensors AS s ON s.id = r.sensor_id WHERE true"
    ]
    params: list[object] = []
    if site is not None:
        query.append(" AND s.site = %s")
        params.append(site)
    if sensor is not None:
        query.append(" AND s.code = %s")
        params.append(sensor)
    if from_date is not None:
        query.append(" AND r.time >= %s")
        params.append(datetime.combine(from_date, time.min, UTC))
    if to_date is not None:
        query.append(" AND r.time < %s")
        params.append(datetime.combine(to_date + timedelta(days=1), time.min, UTC))
    query.append(" GROUP BY s.id, time_bucket('1 day', r.time) ORDER BY day, s.code")

    rows = conn.execute("".join(query), params).fetchall()
    return [
        DailySummary(
            code=code,
            name=name,
            site=row_site,
            unit=unit,
            day=day.astimezone(UTC).date(),
            minimum=minimum,
            maximum=maximum,
            average=average,
            count=count,
        )
        for code, name, row_site, unit, day, minimum, maximum, average, count in rows
    ]
