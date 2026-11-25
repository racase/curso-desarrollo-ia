"""Comandos de comprobación y resumen diario de InfraData."""

import argparse
import re
import sys
from datetime import UTC, date

import psycopg

from infradata.analytics import daily_summary, date_bounds
from infradata.db import connect

EXPECTED_DAILY_READINGS = 288


class UsageError(ValueError):
    """Argumentos de CLI no válidos."""


class SpanishArgumentParser(argparse.ArgumentParser):
    """Presenta los errores de argumentos en castellano."""

    def error(self, message: str) -> None:
        raise UsageError(
            "Argumentos no válidos: comprueba las opciones y sus valores "
            "(--sede, --sensor, --desde, --hasta)."
        )


def parse_date(value: str) -> date:
    """Lee una fecha con formato estricto YYYY-MM-DD."""
    if not re.fullmatch(r"[0-9]{4}-[0-9]{2}-[0-9]{2}", value):
        raise ValueError("Las fechas deben tener formato YYYY-MM-DD.")
    try:
        return date.fromisoformat(value)
    except ValueError:
        raise ValueError(f"Fecha no válida: {value}. Usa YYYY-MM-DD.") from None


def resumen_diario(args: list[str]) -> int:
    """Consulta y muestra las estadísticas diarias por sensor."""
    parser = SpanishArgumentParser(
        prog="python -m infradata resumen-diario", add_help=False, allow_abbrev=False
    )
    for name in ("sede", "sensor", "desde", "hasta"):
        parser.add_argument(f"--{name}")
    parser.add_argument("-h", "--help", action="store_true")
    try:
        options = parser.parse_args(args)
        if options.help:
            print(
                "Uso: python -m infradata resumen-diario "
                "[--sede SEDE] [--sensor CÓDIGO] [--desde YYYY-MM-DD] [--hasta YYYY-MM-DD]"
            )
            return 0
        since = parse_date(options.desde) if options.desde is not None else None
        until = parse_date(options.hasta) if options.hasta is not None else None
        date_bounds(since, until)
        rows = daily_summary(site=options.sede, sensor=options.sensor, since=since, until=until)
    except ValueError as exc:
        print(f"Uso: python -m infradata resumen-diario [opciones]\n{exc}", file=sys.stderr)
        return 2
    except psycopg.Error:
        print("No se pudo consultar la base de datos.", file=sys.stderr)
        print("¿Está levantado el contenedor? Prueba: docker compose up -d", file=sys.stderr)
        return 1

    if not rows:
        print("No hay lecturas para los filtros indicados.")
        return 0
    print("Sede | Sensor | Día UTC | Unidad | Mínimo | Máximo | Media | Lecturas | Esperadas")
    for row in rows:
        print(
            f"{row.site} | {row.sensor} | {row.day.isoformat()} | {row.unit} | "
            f"{row.minimum:.2f} | {row.maximum:.2f} | {row.mean:.2f} | "
            f"{row.count} | {EXPECTED_DAILY_READINGS}"
        )
    return 0


def check() -> int:
    """Print a summary of the dataset. Returns a process exit code."""
    try:
        with connect() as conn:
            sensors = conn.execute("SELECT count(*) FROM sensors").fetchone()[0]
            readings, first, last = conn.execute(
                "SELECT count(*), min(time), max(time) FROM readings"
            ).fetchone()
    except psycopg.Error as exc:
        print(f"No se pudo consultar la base de datos: {exc}", file=sys.stderr)
        print("¿Está levantado el contenedor? Prueba: docker compose up -d", file=sys.stderr)
        return 1

    print(f"Sensores: {sensors}")
    print(f"Lecturas: {readings}")
    print(f"Desde:    {first.astimezone(UTC):%Y-%m-%d %H:%M} UTC")
    print(f"Hasta:    {last.astimezone(UTC):%Y-%m-%d %H:%M} UTC")
    return 0


def main(argv: list[str] | None = None) -> int:
    args = sys.argv[1:] if argv is None else argv
    if args == ["check"]:
        return check()
    if args and args[0] == "resumen-diario":
        return resumen_diario(args[1:])
    print("Uso: python -m infradata {check|resumen-diario}", file=sys.stderr)
    return 2


if __name__ == "__main__":
    raise SystemExit(main())
