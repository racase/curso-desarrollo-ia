"""Comandos de comprobación y analítica de InfraData."""

import argparse
import re
import sys
from datetime import UTC, date, datetime
from pathlib import Path
from zoneinfo import ZoneInfoNotFoundError

import psycopg

from infradata.analytics import daily_summary, date_bounds, gap_bounds, reading_gaps
from infradata.db import connect
from infradata.ingestion import IngestionError, InputError, ingest_csv

EXPECTED_DAILY_READINGS = 288


class UsageError(ValueError):
    """Argumentos de CLI no válidos."""


class SpanishArgumentParser(argparse.ArgumentParser):
    """Presenta los errores de argumentos en castellano."""

    def error(self, message: str) -> None:
        raise UsageError("Argumentos no válidos: comprueba las opciones y sus valores. Usa --help.")


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


def huecos(args: list[str]) -> int:
    """Consulta y presenta interrupciones en el envío de lecturas."""
    parser = SpanishArgumentParser(
        prog="python -m infradata huecos", add_help=False, allow_abbrev=False
    )
    for name in ("sede", "sensor", "desde", "hasta"):
        parser.add_argument(f"--{name}")
    parser.add_argument("--umbral-minutos", default="5")
    parser.add_argument("-h", "--help", action="store_true")
    try:
        options = parser.parse_args(args)
        if options.help:
            print(
                "Uso: python -m infradata huecos [--umbral-minutos N] "
                "[--sede SEDE] [--sensor CÓDIGO] [--desde YYYY-MM-DD] [--hasta YYYY-MM-DD]\n"
                "Umbral: entero positivo, 5 minutos por defecto; duración estrictamente mayor.\n"
                "Fechas: días completos UTC; extremos omitidos: 2026-08-01 y 2026-10-30."
            )
            return 0
        if not re.fullmatch(r"[0-9]+", options.umbral_minutos):
            raise ValueError("El umbral debe ser un número entero positivo de minutos.")
        threshold = int(options.umbral_minutos)
        since = parse_date(options.desde) if options.desde is not None else None
        until = parse_date(options.hasta) if options.hasta is not None else None
        gap_bounds(since, until, threshold)
        rows = reading_gaps(
            site=options.sede,
            sensor=options.sensor,
            since=since,
            until=until,
            threshold_minutes=threshold,
        )
    except ValueError as exc:
        print(f"Uso: python -m infradata huecos [opciones]\n{exc}", file=sys.stderr)
        return 2
    except psycopg.Error:
        print("No se pudo consultar la base de datos.", file=sys.stderr)
        print("¿Está levantado el contenedor? Prueba: docker compose up -d", file=sys.stderr)
        return 1

    if not rows:
        print("No hay huecos que superen el umbral para los filtros indicados.")
        return 0
    print("Sede | Sensor | Última lectura UTC | Siguiente lectura UTC | Duración | Estado")
    for row in rows:
        seconds = row.duration.days * 86400 + row.duration.seconds
        hours, seconds = divmod(seconds, 3600)
        minutes, seconds = divmod(seconds, 60)
        prefix = "" if row.status == "completo" else ">= "
        print(
            f"{row.site} | {row.sensor} | {format_timestamp(row.previous)} | "
            f"{format_timestamp(row.following)} | "
            f"{prefix}{hours:02d}:{minutes:02d}:{seconds:02d} | {row.status}"
        )
    return 0


def format_timestamp(value: datetime | None) -> str:
    """Presenta un extremo conocido en UTC o su ausencia."""
    return f"{value.astimezone(UTC):%Y-%m-%d %H:%M:%S} UTC" if value else "desconocido"


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


def ingestar_csv(args: list[str]) -> int:
    """Carga CSV del formador y genera un informe de rechazos."""
    parser = SpanishArgumentParser(
        prog="python -m infradata ingestar-csv", add_help=False, allow_abbrev=False
    )
    parser.add_argument("ruta", nargs="?")
    parser.add_argument("--informe")
    parser.add_argument("-h", "--help", action="store_true")
    try:
        options = parser.parse_args(args)
        if options.help:
            print("Uso: python -m infradata ingestar-csv RUTA --informe RUTA_INFORME")
            return 0
        if options.ruta is None or options.informe is None:
            raise InputError("Indica el CSV de entrada y --informe RUTA_INFORME.")
        result = ingest_csv(Path(options.ruta), Path(options.informe))
    except (UsageError, InputError) as exc:
        print(
            f"Uso: python -m infradata ingestar-csv RUTA --informe RUTA_INFORME\n{exc}",
            file=sys.stderr,
        )
        return 2
    except IngestionError as exc:
        print(str(exc), file=sys.stderr)
        return 1
    except ZoneInfoNotFoundError:
        print("Faltan datos IANA de Europe/Madrid; instala tzdata en el sistema.", file=sys.stderr)
        return 1
    except (psycopg.Error, OSError):
        print(
            "No se pudo completar la ingesta por un fallo de base de datos o archivos.",
            file=sys.stderr,
        )
        return 1
    print(
        f"Filas: {result.total} | Incorporadas: {result.inserted} | Rechazadas: {result.rejected}"
    )
    print(f"Informe: {result.report}")
    return 0


def main(argv: list[str] | None = None) -> int:
    args = sys.argv[1:] if argv is None else argv
    if args == ["check"]:
        return check()
    if args and args[0] == "resumen-diario":
        return resumen_diario(args[1:])
    if args and args[0] == "huecos":
        return huecos(args[1:])
    if args and args[0] == "ingestar-csv":
        return ingestar_csv(args[1:])
    print("Uso: python -m infradata {check|resumen-diario|huecos|ingestar-csv}", file=sys.stderr)
    return 2


if __name__ == "__main__":
    raise SystemExit(main())
