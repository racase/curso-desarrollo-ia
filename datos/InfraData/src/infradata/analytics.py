"""Consultas analíticas sobre las lecturas de sensores."""

from dataclasses import dataclass
from datetime import UTC, date, datetime, time, timedelta

from infradata.db import connect


@dataclass(frozen=True)
class DailySummary:
    """Estadísticas de un sensor durante un día UTC."""

    site: str
    sensor: str
    day: date
    unit: str
    minimum: float
    maximum: float
    mean: float
    count: int


def date_bounds(since: date | None, until: date | None) -> tuple[datetime | None, datetime | None]:
    """Convierte fechas inclusivas en límites UTC semiabiertos."""
    for value in (since, until):
        if value is not None and (not isinstance(value, date) or isinstance(value, datetime)):
            raise ValueError("Los límites deben ser fechas sin hora.")
    if since is not None and until is not None and since > until:
        raise ValueError("La fecha inicial no puede ser posterior a la fecha final.")
    start = datetime.combine(since, time.min, UTC) if since is not None else None
    try:
        end = datetime.combine(until + timedelta(days=1), time.min, UTC) if until else None
    except OverflowError:
        raise ValueError("La fecha final no permite representar el día siguiente.") from None
    return start, end


def daily_summary(
    *,
    site: str | None = None,
    sensor: str | None = None,
    since: date | None = None,
    until: date | None = None,
) -> list[DailySummary]:
    """Consulta las estadísticas diarias con filtros opcionales combinados."""
    start, end = date_bounds(since, until)
    predicates = []
    params: list[str | datetime] = []
    for value, predicate in (
        (site, "s.site = %s"),
        (sensor, "s.code = %s"),
        (start, "r.time >= %s"),
        (end, "r.time < %s"),
    ):
        if value is not None:
            predicates.append(predicate)
            params.append(value)
    where = " WHERE " + " AND ".join(predicates) if predicates else ""
    # Solo se concatenan predicados constantes; los valores son parámetros psycopg.
    query = (
        """
        SELECT s.site, s.code, (r.time AT TIME ZONE 'UTC')::date AS day, s.unit,
               min(r.value), max(r.value), avg(r.value), count(*)
        FROM readings AS r
        JOIN sensors AS s ON s.id = r.sensor_id
    """
        + where
        + """
        GROUP BY s.id, s.site, s.code, s.unit, (r.time AT TIME ZONE 'UTC')::date
        ORDER BY day, s.code
    """
    )
    with connect() as conn:
        rows = conn.execute(query, params).fetchall()
    return [DailySummary(*row) for row in rows]
