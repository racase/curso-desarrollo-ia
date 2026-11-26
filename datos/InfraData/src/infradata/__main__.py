"""Command line entry point for dataset checks and sensor analytics."""

import argparse
import sys
from datetime import UTC, date

import psycopg

from infradata.analytics import daily_summary, reading_gaps
from infradata.db import connect


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


def iso_date(value: str) -> date:
    """Parse a calendar date in strict YYYY-MM-DD form."""
    try:
        parsed = date.fromisoformat(value)
        if parsed.isoformat() != value:
            raise ValueError
        return parsed
    except ValueError as exc:
        raise argparse.ArgumentTypeError("Fecha inválida; usa YYYY-MM-DD.") from exc


def gaps(args: argparse.Namespace) -> int:
    """Print missing-reading intervals with UTC limits and edge indicators."""
    rows = reading_gaps(
        site=args.site,
        sensor=args.sensor,
        start_date=args.start_date,
        end_date=args.end_date,
        threshold_minutes=args.threshold_minutes,
    )
    if not rows:
        print("Sin huecos para los filtros indicados.")
        return 0
    print(
        "sensor\tsede\tinicio_utc\tfin_utc\tduracion_minutos\t"
        "recortado_inicio\trecortado_fin\tabierto_inicio\tabierto_fin"
    )
    for row in rows:
        start = row.start.astimezone(UTC).strftime("%Y-%m-%dT%H:%M:%SZ")
        end = row.end.astimezone(UTC).strftime("%Y-%m-%dT%H:%M:%SZ")
        flags = "\t".join(
            "si" if flag else "no"
            for flag in (row.clipped_start, row.clipped_end, row.open_start, row.open_end)
        )
        print(f"{row.sensor}\t{row.site}\t{start}\t{end}\t{row.duration_minutes:.3f}\t{flags}")
    return 0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        prog="python -m infradata",
        usage="Uso: %(prog)s {check,daily-summary,gaps} ...",
        allow_abbrev=False,
    )
    commands = parser.add_subparsers(dest="command", required=True)
    commands.add_parser("check", help="Comprobar conexión y dataset")
    summary = commands.add_parser(
        "daily-summary", help="Resumen diario por sensor en UTC", allow_abbrev=False
    )
    summary.add_argument("--site", help="Nombre exacto de la sede")
    summary.add_argument("--sensor", help="Código exacto del sensor")
    summary.add_argument(
        "--start-date", type=iso_date, help="Fecha inicial inclusiva YYYY-MM-DD UTC"
    )
    summary.add_argument("--end-date", type=iso_date, help="Fecha final inclusiva YYYY-MM-DD UTC")
    gap_parser = commands.add_parser(
        "gaps",
        help="Periodos sin lecturas esperadas en UTC",
        description=(
            "Detectar huecos descontando la cadencia normal de 5 minutos. "
            "La duración y el umbral se aplican tras recortar al rango consultado; "
            "se indican extremos recortados y abiertos."
        ),
        allow_abbrev=False,
    )
    gap_parser.add_argument("--site", help="Nombre exacto de la sede")
    gap_parser.add_argument("--sensor", help="Código exacto del sensor")
    gap_parser.add_argument(
        "--start-date", type=iso_date, help="Fecha inicial inclusiva YYYY-MM-DD UTC"
    )
    gap_parser.add_argument(
        "--end-date", type=iso_date, help="Fecha final inclusiva YYYY-MM-DD UTC"
    )
    gap_parser.add_argument(
        "--threshold-minutes",
        type=int,
        default=15,
        help="Umbral entero no negativo; duración estrictamente mayor (por defecto: 15)",
    )
    try:
        args = parser.parse_args(argv)
        if args.command == "check":
            return check()
        try:
            if args.command == "gaps":
                return gaps(args)
            rows = daily_summary(
                site=args.site,
                sensor=args.sensor,
                start_date=args.start_date,
                end_date=args.end_date,
            )
        except ValueError as exc:
            parser.error(str(exc))
        except psycopg.Error as exc:
            print(f"No se pudo consultar la base de datos: {exc}", file=sys.stderr)
            return 1
    except SystemExit as exc:
        return int(exc.code)

    if not rows:
        print("Sin lecturas para los filtros indicados.")
        return 0
    print("dia_utc\tsensor\tnombre\tsede\tunidad\tmin\tmax\tmedia\tlecturas")
    for row in rows:
        print(
            f"{row.day.isoformat()}\t{row.sensor}\t{row.name}\t{row.site}\t{row.unit}\t"
            f"{row.minimum:.3f}\t{row.maximum:.3f}\t{row.mean:.3f}\t{row.count}"
        )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
