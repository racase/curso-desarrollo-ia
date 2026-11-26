"""Ingesta transaccional de lecturas y trazabilidad de rechazos."""

import csv
import math
import os
import re
from collections.abc import Iterator
from dataclasses import dataclass
from datetime import UTC, datetime
from pathlib import Path
from tempfile import NamedTemporaryFile
from typing import TextIO
from zoneinfo import ZoneInfo

import psycopg

from infradata.db import connect

HEADER = ["codigo_sensor", "fecha_hora_local", "valor"]
REPORT_HEADER = ["fila", *HEADER, "registro_original", "motivo"]
UNIQUE_CONSTRAINT = """
    SELECT 1 FROM pg_constraint
    WHERE conrelid = 'readings'::regclass AND conname = 'readings_sensor_time_unique'
      AND contype = 'u' AND convalidated AND NOT condeferrable
      AND conkey = ARRAY[
        (SELECT attnum FROM pg_attribute
         WHERE attrelid = 'readings'::regclass AND attname = 'sensor_id'),
        (SELECT attnum FROM pg_attribute
         WHERE attrelid = 'readings'::regclass AND attname = 'time')
      ]::smallint[]
"""


class InputError(ValueError):
    """Entrada o argumentos no válidos."""


class IngestionError(RuntimeError):
    """Fallo operativo con estado de carga conocido o explícitamente desconocido."""


class RowError(ValueError):
    """Motivo estable de rechazo de una fila."""


@dataclass(frozen=True)
class IngestionResult:
    """Recuentos de una ingesta confirmada."""

    total: int
    inserted: int
    rejected: int
    report: Path


class RecordedLines:
    """Conserva las líneas físicas consumidas por cada registro CSV."""

    def __init__(self, stream: TextIO):
        self.stream = stream
        self.lines: list[str] = []

    def __iter__(self) -> Iterator[str]:
        return self

    def __next__(self) -> str:
        line = next(self.stream)
        self.lines.append(line)
        return line


def local_to_utc(value: str, zone: ZoneInfo) -> datetime:
    """Rechaza horas ambiguas o inexistentes antes de convertir a UTC."""
    if not re.fullmatch(r"[0-9]{2}/[0-9]{2}/[0-9]{4} [0-9]{2}:[0-9]{2}", value):
        raise RowError("fecha_invalida")
    try:
        local = datetime.strptime(value, "%d/%m/%Y %H:%M")
        candidates = set()
        for fold in (0, 1):
            candidate = local.replace(tzinfo=zone, fold=fold).astimezone(UTC)
            if candidate.astimezone(zone).replace(tzinfo=None) == local:
                candidates.add(candidate)
    except (ValueError, OverflowError):
        raise RowError("fecha_invalida") from None
    if not candidates:
        raise RowError("hora_inexistente")
    if len(candidates) > 1:
        raise RowError("hora_ambigua")
    return candidates.pop()


def numeric_value(value: str) -> float:
    """Interpreta el número decimal del formato acordado."""
    if not value:
        raise RowError("valor_vacio")
    if value.lower() in {"nan", "inf", "+inf", "-inf", "infinity", "+infinity", "-infinity"}:
        raise RowError("valor_no_finito")
    if not re.fullmatch(r"[+-]?[0-9]+(?:,[0-9]+)?", value):
        raise RowError("valor_no_numerico")
    number = float(value.replace(",", "."))
    if not math.isfinite(number):
        raise RowError("valor_no_finito")
    return number


def validate_row(
    row: list[str], sensors: dict[str, int], zone: ZoneInfo
) -> tuple[int, datetime, float]:
    """Valida campos en el orden de precedencia establecido."""
    if len(row) != 3:
        raise RowError("numero_de_campos_invalido")
    code, stamp, value = (field.strip() for field in row)
    if code not in sensors:
        raise RowError("sensor_desconocido")
    return sensors[code], local_to_utc(stamp, zone), numeric_value(value)


def insert_reading(conn: psycopg.Connection, sensor: int, stamp: datetime, value: float) -> None:
    """Inserta o clasifica el conflicto, también tras una inserción concurrente."""
    inserted = conn.execute(
        """INSERT INTO readings (sensor_id, time, value) VALUES (%s, %s, %s)
           ON CONFLICT (sensor_id, time) DO NOTHING RETURNING 1""",
        (sensor, stamp, value),
    ).fetchone()
    if inserted:
        return
    existing = conn.execute(
        "SELECT value FROM readings WHERE sensor_id = %s AND time = %s",
        (sensor, stamp),
    ).fetchone()
    if existing is None:
        raise IngestionError("La lectura en conflicto desapareció; vuelve a intentar la carga.")
    raise RowError("ya_cargada" if existing[0] == value else "conflicto_con_existente")


def process_rows(
    conn: psycopg.Connection,
    reader: csv.reader,
    lines: RecordedLines,
    report: TextIO,
    zone: ZoneInfo,
) -> tuple[int, int, int]:
    """Procesa sin recuperar el histórico ni conservar las filas completas."""
    sensors = dict(conn.execute("SELECT code, id FROM sensors").fetchall())
    seen: dict[tuple[int, datetime], float] = {}
    writer = csv.writer(report, delimiter=";")
    writer.writerow(REPORT_HEADER)
    total = inserted = rejected = 0
    while True:
        start = reader.line_num + 1
        lines.lines.clear()
        try:
            row = next(reader)
        except StopIteration:
            break
        total += 1
        try:
            sensor, stamp, value = validate_row(row, sensors, zone)
            key = (sensor, stamp)
            if key in seen:
                raise RowError("duplicado_en_csv" if seen[key] == value else "conflicto_en_csv")
            seen[key] = value
            insert_reading(conn, sensor, stamp, value)
            inserted += 1
        except RowError as exc:
            rejected += 1
            original = "".join(lines.lines).removesuffix("\n").removesuffix("\r")
            writer.writerow([start, *(row + [""] * 3)[:3], original, str(exc)])
    return total, inserted, rejected


def input_paths(source: Path, report: Path) -> tuple[Path, Path]:
    """Comprueba rutas sin abrir la base de datos ni sobrescribir archivos."""
    # lexists también protege enlaces colgantes en el destino.
    if os.path.lexists(report):
        raise InputError("El destino del informe ya existe; elige otro nombre.")
    source, report = source.resolve(), report.resolve()
    if source == report:
        raise InputError("El informe debe tener un destino distinto del CSV de entrada.")
    if not source.is_file():
        raise InputError("La ruta de entrada debe ser un fichero existente.")
    if not report.parent.is_dir():
        raise InputError("La carpeta de destino del informe no existe.")
    return source, report


def ingest_csv(source: Path, report: Path) -> IngestionResult:
    """Carga filas válidas y publica un informe sin sobrescritura."""
    source, report = input_paths(source, report)
    zone = ZoneInfo("Europe/Madrid")
    temporary: Path | None = None
    stage = "processing"
    try:
        with source.open(encoding="utf-8", newline="") as stream:
            lines = RecordedLines(stream)
            reader = csv.reader(lines, delimiter=";", strict=True)
            if next(reader, None) != HEADER:
                raise InputError("La cabecera CSV debe ser codigo_sensor;fecha_hora_local;valor.")
            with NamedTemporaryFile(
                mode="w",
                encoding="utf-8",
                newline="",
                dir=report.parent,
                prefix=".infradata-rechazos-",
                suffix=".csv",
                delete=False,
            ) as output:
                temporary = Path(output.name)
                with connect() as conn:
                    # READ COMMITTED permite ver al ganador de ON CONFLICT en el SELECT posterior.
                    conn.execute("SET TRANSACTION ISOLATION LEVEL READ COMMITTED")
                    if not conn.execute(UNIQUE_CONSTRAINT).fetchone():
                        raise IngestionError(
                            "Falta readings_sensor_time_unique. Aplica la migración "
                            "db/migrations/01_readings_unique.sql con aprobación previa."
                        )
                    total, inserted, rejected = process_rows(conn, reader, lines, output, zone)
                    output.flush()
                    os.fsync(output.fileno())
                    output.close()
                    stage = "committing"
                    conn.commit()
                    stage = "confirmed"
            # Un enlace duro publica atómicamente y falla si el destino ya existe.
            # El temporal está en la misma carpeta y, por tanto, en el mismo sistema de archivos.
            os.link(temporary, report)
            temporary.unlink()
            temporary = None
        return IngestionResult(total, inserted, rejected, report)
    except (csv.Error, UnicodeError):
        raise InputError(
            "El fichero no contiene CSV UTF-8 válido; no se incorporaron lecturas."
        ) from None
    except Exception as exc:
        if stage != "processing":
            state = (
                "La carga está confirmada."
                if stage == "confirmed"
                else "El resultado del commit es desconocido; verifica o reintenta la carga."
            )
            raise IngestionError(
                f"{state} No se completó la publicación del informe. "
                f"Temporal recuperable: {temporary}."
            ) from exc
        raise
    finally:
        if temporary is not None and stage == "processing":
            temporary.unlink(missing_ok=True)
