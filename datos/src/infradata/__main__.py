"""Command line entry point for InfraData."""

import argparse
import sys
from datetime import UTC, date
from pathlib import Path

import psycopg

from infradata.anomalies import anomalous_readings
from infradata.db import connect
from infradata.export import export_weekly_csv
from infradata.gaps import reading_gaps
from infradata.summary import daily_summaries


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


def _date(value: str) -> date:
    try:
        return date.fromisoformat(value)
    except ValueError as exc:
        raise argparse.ArgumentTypeError("La fecha debe tener formato AAAA-MM-DD") from exc


def _parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="python -m infradata",
        usage="Uso: python -m infradata {check,summary,gaps,anomalies,weekly-csv} ...",
    )
    commands = parser.add_subparsers(dest="command", required=True)
    commands.add_parser("check", help="Comprobar los datos cargados")
    summary = commands.add_parser("summary", help="Consultar resúmenes diarios")
    summary.add_argument("--site", help="Sede")
    summary.add_argument("--sensor", help="Código del sensor")
    summary.add_argument("--from-date", type=_date, help="Primer día UTC (AAAA-MM-DD)")
    summary.add_argument("--to-date", type=_date, help="Último día UTC (AAAA-MM-DD)")
    gaps = commands.add_parser("gaps", help="Consultar periodos sin lecturas")
    gaps.add_argument("--minutes", type=int, default=5, help="Umbral en minutos (por defecto: 5)")
    gaps.add_argument("--site", help="Sede")
    gaps.add_argument("--sensor", help="Código del sensor")
    anomalies = commands.add_parser("anomalies", help="Consultar lecturas anómalas")
    anomalies.add_argument("--site", help="Sede")
    anomalies.add_argument("--sensor", help="Código del sensor")
    weekly = commands.add_parser("weekly-csv", help="Exportar el resumen semanal de una sede")
    weekly.add_argument("--site", required=True, help="Sede")
    weekly.add_argument("--week", type=_date, required=True, help="Lunes de la semana UTC")
    weekly.add_argument("--output", type=Path, required=True, help="Ruta del CSV nuevo")
    return parser


def main(argv: list[str] | None = None) -> int:
    """Execute the selected command and return its process exit code."""
    parser = _parser()
    try:
        args = parser.parse_args(sys.argv[1:] if argv is None else argv)
    except SystemExit as exc:
        return int(exc.code)

    if args.command == "check":
        return check()
    try:
        with connect() as conn:
            if args.command == "weekly-csv":
                count = export_weekly_csv(conn, site=args.site, week=args.week, output=args.output)
            elif args.command == "summary":
                rows = daily_summaries(
                    conn,
                    site=args.site,
                    sensor=args.sensor,
                    from_date=args.from_date,
                    to_date=args.to_date,
                )
            elif args.command == "gaps":
                rows = reading_gaps(conn, minutes=args.minutes, site=args.site, sensor=args.sensor)
            else:
                rows = anomalous_readings(conn, site=args.site, sensor=args.sensor)
    except ValueError as exc:
        print(str(exc), file=sys.stderr)
        return 2
    except psycopg.Error as exc:
        print(f"No se pudo consultar la base de datos: {exc}", file=sys.stderr)
        return 1
    except OSError as exc:
        print(f"No se pudo crear el CSV: {exc}", file=sys.stderr)
        return 1

    if args.command == "weekly-csv":
        print(f"CSV creado: {args.output} ({count} filas)")
        return 0

    if not rows:
        print("No hay resultados para los filtros indicados.")
        return 0
    if args.command == "summary":
        for row in rows:
            print(
                f"{row.day:%Y-%m-%d}  {row.site}  {row.code}  "
                f"mín={row.minimum:.2f}  máx={row.maximum:.2f}  "
                f"media={row.average:.2f}  lecturas={row.count}  unidad={row.unit}"
            )
    elif args.command == "gaps":
        for row in rows:
            print(
                f"{row.code}  {row.site}  "
                f"inicio={row.start.astimezone(UTC):%Y-%m-%d %H:%M} UTC  "
                f"fin={row.end.astimezone(UTC):%Y-%m-%d %H:%M} UTC  "
                f"duración={row.minutes:g} min"
            )
    else:
        reason_names = {
            "out_of_range": "fuera de rango",
            "fixed": "valor fijo",
            "jump": "salto brusco",
        }
        for row in rows:
            reasons = ", ".join(reason_names[reason] for reason in row.reasons)
            print(
                f"{row.time.astimezone(UTC):%Y-%m-%d %H:%M} UTC  {row.site}  "
                f"{row.code}  valor={row.value:.2f} {row.unit}  motivos={reasons}"
            )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
