"""Daily sensor aggregates computed by PostgreSQL."""

from dataclasses import dataclass
from datetime import UTC, date, datetime, time, timedelta

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
