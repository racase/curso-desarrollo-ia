"""Consultas analíticas sobre las lecturas de sensores."""

from dataclasses import dataclass
from datetime import UTC, date, datetime, time, timedelta
from typing import Literal

from infradata.db import connect

COURSE_START = date(2026, 8, 1)
COURSE_END = date(2026, 10, 30)
GapStatus = Literal[
    "completo", "abierto al inicio", "abierto al final", "abierto en ambos extremos"
]


@dataclass(frozen=True)
class ReadingGap:
    """Intervalo entre lecturas, exacto o mínimo según el estado."""

    site: str
    sensor: str
    previous: datetime | None
    following: datetime | None
    duration: timedelta
    status: GapStatus


def gap_bounds(
    since: date | None = None, until: date | None = None, threshold_minutes: int = 5
) -> tuple[datetime, datetime]:
    """Valida el umbral y resuelve el rango efectivo UTC del curso."""
    if type(threshold_minutes) is not int or threshold_minutes <= 0:
        raise ValueError("El umbral debe ser un número entero positivo de minutos.")
    start, end = date_bounds(
        COURSE_START if since is None else since, COURSE_END if until is None else until
    )
    assert start is not None and end is not None
    return start, end


def reading_gaps(
    *,
    site: str | None = None,
    sensor: str | None = None,
    since: date | None = None,
    until: date | None = None,
    threshold_minutes: int = 5,
) -> list[ReadingGap]:
    """Detecta en SQL huecos solapados con el rango, conservando el contexto."""
    start, end = gap_bounds(since, until, threshold_minutes)
    predicates = []
    params: list[str | datetime | int] = [start, end]
    for value, predicate in ((site, "site = %s"), (sensor, "code = %s")):
        if value is not None:
            predicates.append(predicate)
            params.append(value)
    where = " WHERE " + " AND ".join(predicates) if predicates else ""
    # Las fechas se aplican después de la vecindad: no eliminar lecturas de contexto.
    query = (
        """
        WITH bounds AS (SELECT %s::timestamptz AS a, %s::timestamptz AS b),
        selected AS (SELECT id, site, code FROM sensors
        """
        + where
        + """
        ), ordered AS (
            SELECT s.site, s.code, r.time AS following,
                   lag(r.time) OVER (PARTITION BY s.id ORDER BY r.time) AS previous
            FROM selected s JOIN readings r ON r.sensor_id = s.id
        ), candidates AS (
            SELECT site, code, previous, following FROM ordered
            UNION ALL
            SELECT s.site, s.code, max(r.time), NULL::timestamptz
            FROM selected s LEFT JOIN readings r ON r.sensor_id = s.id
            GROUP BY s.id, s.site, s.code
        ), measured AS (
            SELECT c.*,
                   CASE WHEN previous IS NOT NULL AND following IS NOT NULL
                        THEN following - previous
                        ELSE least(coalesce(following, b), b)
                             - greatest(coalesce(previous, a), a)
                   END AS duration,
                   CASE WHEN previous IS NULL AND following IS NULL
                             THEN 'abierto en ambos extremos'
                        WHEN previous IS NULL THEN 'abierto al inicio'
                        WHEN following IS NULL THEN 'abierto al final'
                        ELSE 'completo'
                   END AS status,
                   coalesce(previous, a) AS sort_start,
                   coalesce(following, b) AS sort_end
            FROM candidates c CROSS JOIN bounds
            WHERE coalesce(previous, a) < b AND coalesce(following, b) > a
        )
        SELECT site, code, previous, following, duration, status FROM measured
        WHERE extract(epoch FROM duration) > %s::numeric * 60
        ORDER BY sort_start, code, sort_end
        """
    )
    params.append(threshold_minutes)
    with connect() as conn:
        rows = conn.execute(query, params).fetchall()
    return [ReadingGap(*row) for row in rows]


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
