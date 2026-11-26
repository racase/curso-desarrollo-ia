"""Sensor summaries and missing-reading periods computed by PostgreSQL."""

from dataclasses import dataclass
from datetime import UTC, date, datetime, time, timedelta
from decimal import Decimal

from infradata.db import connect


@dataclass(frozen=True)
class DailySummary:
    """One sensor's available readings for a UTC day."""

    day: date
    sensor: str
    name: str
    site: str
    unit: str
    minimum: float
    maximum: float
    mean: float
    count: int


def daily_summary(
    *,
    site: str | None = None,
    sensor: str | None = None,
    start_date: date | None = None,
    end_date: date | None = None,
) -> list[DailySummary]:
    """Return daily aggregates with inclusive UTC date filters."""
    if start_date is not None and end_date is not None and start_date > end_date:
        raise ValueError("La fecha inicial no puede ser posterior a la final.")
    try:
        end = (
            datetime.combine(end_date + timedelta(days=1), time.min, UTC)
            if end_date is not None
            else None
        )
    except OverflowError as exc:
        raise ValueError("La fecha final no permite calcular el límite exclusivo.") from exc

    predicates: list[str] = []
    params: list[str | datetime] = []
    if site is not None:
        predicates.append("s.site = %s")
        params.append(site)
    if sensor is not None:
        predicates.append("s.code = %s")
        params.append(sensor)
    if start_date is not None:
        predicates.append("r.time >= %s")
        params.append(datetime.combine(start_date, time.min, UTC))
    if end is not None:
        predicates.append("r.time < %s")
        params.append(end)
    where = "WHERE " + " AND ".join(predicates) if predicates else ""
    query = f"""
        SELECT (r.time AT TIME ZONE 'UTC')::date AS day,
               s.code, s.name, s.site, s.unit,
               min(r.value), max(r.value), avg(r.value), count(*)
        FROM readings r
        JOIN sensors s ON s.id = r.sensor_id
        {where}
        GROUP BY day, s.id
        ORDER BY day, s.code
    """
    with connect() as conn:
        return [DailySummary(*row) for row in conn.execute(query, params).fetchall()]


@dataclass(frozen=True)
class ReadingGap:
    """One missing-reading interval within the effective observation range."""

    sensor: str
    site: str
    start: datetime
    end: datetime
    duration_minutes: Decimal
    clipped_start: bool
    clipped_end: bool
    open_start: bool
    open_end: bool


def reading_gaps(
    *,
    site: str | None = None,
    sensor: str | None = None,
    start_date: date | None = None,
    end_date: date | None = None,
    threshold_minutes: int = 15,
) -> list[ReadingGap]:
    """Find missing expected time at a fixed five-minute cadence, entirely in SQL."""
    if type(threshold_minutes) is not int or threshold_minutes < 0:
        raise ValueError("El umbral debe ser un entero no negativo de minutos.")
    if start_date is not None and end_date is not None and start_date > end_date:
        raise ValueError("La fecha inicial no puede ser posterior a la final.")
    start = datetime.combine(start_date, time.min, UTC) if start_date is not None else None
    try:
        end = (
            datetime.combine(end_date + timedelta(days=1), time.min, UTC)
            if end_date is not None
            else None
        )
    except OverflowError as exc:
        raise ValueError("La fecha final no permite calcular el límite exclusivo.") from exc

    # Build neighbours before clipping dates: a range may fall wholly inside a gap.
    query = """
        WITH observation AS (
            SELECT min(time) AS first, max(time) + interval '5 minutes' AS last
            FROM readings
        ), bounds AS (
            SELECT first, last,
                   greatest(first, %(start)s::timestamptz) AS start,
                   least(last, %(end)s::timestamptz) AS finish
            FROM observation
            WHERE first IS NOT NULL
        ), selected AS (
            SELECT id, code, site FROM sensors
            WHERE (%(site)s::text IS NULL OR site = %(site)s)
              AND (%(sensor)s::text IS NULL OR code = %(sensor)s)
        ), times AS (
            SELECT DISTINCT r.sensor_id, r.time
            FROM readings r JOIN selected s ON s.id = r.sensor_id
        ), neighbours AS (
            SELECT sensor_id, time,
                   lag(time) OVER (PARTITION BY sensor_id ORDER BY time) AS previous
            FROM times
        ), extents AS (
            SELECT s.id, min(t.time) AS first, max(t.time) AS last
            FROM selected s LEFT JOIN times t ON t.sensor_id = s.id
            GROUP BY s.id
        ), candidates AS (
            SELECT sensor_id, previous + interval '5 minutes' AS start,
                   time AS finish, false AS open_start, false AS open_end
            FROM neighbours
            WHERE time > previous + interval '5 minutes'
            UNION ALL
            SELECT e.id, b.first, coalesce(e.first, b.last), true, e.first IS NULL
            FROM extents e CROSS JOIN bounds b
            WHERE e.first IS NULL OR e.first > b.first
            UNION ALL
            SELECT e.id, e.last + interval '5 minutes', b.last, false, true
            FROM extents e CROSS JOIN bounds b
            WHERE e.last + interval '5 minutes' < b.last
        ), clipped AS (
            SELECT c.sensor_id, greatest(c.start, b.start) AS start,
                   least(c.finish, b.finish) AS finish,
                   c.start < b.start AS clipped_start,
                   c.finish > b.finish AS clipped_end, c.open_start, c.open_end
            FROM candidates c CROSS JOIN bounds b
            WHERE b.start < b.finish
        ), durations AS (
            SELECT *, extract(epoch FROM (finish - start)) / 60 AS duration_minutes
            FROM clipped WHERE start < finish
        )
        SELECT s.code, s.site, d.start, d.finish, d.duration_minutes,
               d.clipped_start, d.clipped_end, d.open_start, d.open_end
        FROM durations d JOIN selected s ON s.id = d.sensor_id
        WHERE d.duration_minutes > %(threshold)s::numeric
        ORDER BY d.start, s.code
    """
    params = {
        "start": start,
        "end": end,
        "site": site,
        "sensor": sensor,
        "threshold": threshold_minutes,
    }
    with connect() as conn:
        return [ReadingGap(*row) for row in conn.execute(query, params).fetchall()]
