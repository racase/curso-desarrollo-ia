"""CSV exports for daily sensor summaries."""

import csv
from datetime import date, timedelta
from pathlib import Path

import psycopg

from infradata.summary import daily_summaries

HEADERS = (
    "fecha",
    "sede",
    "codigo_sensor",
    "nombre_sensor",
    "unidad",
    "minimo",
    "maximo",
    "media",
    "lecturas",
)


def export_weekly_csv(conn: psycopg.Connection, *, site: str, week: date, output: Path) -> int:
    """Export one site's Monday-to-Sunday summaries; return data row count."""
    if not site.strip():
        raise ValueError("La sede no puede estar vacía")
    if week.weekday() != 0:
        raise ValueError("La fecha de la semana debe ser un lunes")

    rows = daily_summaries(conn, site=site, from_date=week, to_date=week + timedelta(days=6))
    with output.open("x", encoding="utf-8-sig", newline="") as stream:
        writer = csv.writer(stream, delimiter=";")
        writer.writerow(HEADERS)
        for row in rows:
            writer.writerow(
                (
                    row.day.isoformat(),
                    row.site,
                    row.code,
                    row.name,
                    row.unit,
                    f"{row.minimum:.2f}".replace(".", ","),
                    f"{row.maximum:.2f}".replace(".", ","),
                    f"{row.average:.2f}".replace(".", ","),
                    row.count,
                )
            )
    return len(rows)
