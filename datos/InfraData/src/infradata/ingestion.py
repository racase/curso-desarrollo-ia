"""Validated, idempotent CSV ingestion with a per-record rejection report."""

import csv
import math
import re
from collections.abc import Iterator
from dataclasses import dataclass
from datetime import UTC, datetime
from decimal import Decimal
from pathlib import Path
from typing import TextIO
from zoneinfo import ZoneInfo

import psycopg

from infradata.db import connect

HEADER = ["codigo_sensor", "fecha_hora_local", "valor"]
REPORT_HEADER = ["fila", *HEADER, "registro_original", "motivo", "detalle"]
UNIQUE_INDEX_SQL = """
SELECT EXISTS (
    SELECT 1 FROM pg_index i
    WHERE i.indrelid = 'readings'::regclass
      AND i.indisunique AND i.indisvalid AND i.indisready AND i.indimmediate
      AND i.indpred IS NULL AND i.indexprs IS NULL AND i.indnkeyatts = 2
      AND (SELECT array_agg(a.attname::text ORDER BY a.attname)
           FROM unnest(i.indkey::smallint[]) WITH ORDINALITY AS k(attnum, pos)
           JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = k.attnum
           WHERE k.pos <= i.indnkeyatts) = ARRAY['sensor_id', 'time']::text[]
)
"""


class CSVContractError(ValueError):
    """The file cannot be interpreted using the agreed CSV contract."""


class IngestionError(RuntimeError):
    """The operation could not complete safely."""


class RowError(ValueError):
    """One recoverable row validation failure."""

    def __init__(self, reason: str, detail: str):
        super().__init__(detail)
        self.reason = reason


@dataclass(frozen=True)
class CSVRecord:
    line: int
    fields: list[str]
    original: str


@dataclass(frozen=True)
class Reading:
    sensor_id: int
    time: datetime
    value: float


@dataclass(frozen=True)
class Rejection:
    record: CSVRecord
    reason: str
    detail: str

    def report_row(self) -> list[str | int]:
        fields = (self.record.fields + ["", "", ""])[:3]
        return [self.record.line, *fields, self.record.original, self.reason, self.detail]


@dataclass(frozen=True)
class IngestionResult:
    processed: int
    inserted: int
    rejected: int


def records(source: TextIO) -> Iterator[CSVRecord]:
    """Read records while retaining physical source lines, including multiline fields."""
    captured: list[str] = []

    def lines():
        for line in source:
            captured.append(line)
            yield line

    reader = csv.reader(lines(), delimiter=";", strict=True)
    try:
        if next(reader, None) != HEADER:
            raise CSVContractError("Cabecera inválida; se esperaba " + ";".join(HEADER))
        while True:
            captured.clear()
            start = reader.line_num + 1
            fields = next(reader, None)
            if fields is None:
                break
            yield CSVRecord(start, fields, "".join(captured))
    except (csv.Error, UnicodeError) as exc:
        raise CSVContractError(f"CSV ilegible o mal formado: {exc}") from exc


def local_to_utc(value: str, zone: ZoneInfo) -> datetime:
    """Reject nonexistent and ambiguous local times instead of selecting an offset."""
    try:
        if not re.fullmatch(r"[0-9]{2}/[0-9]{2}/[0-9]{4} [0-9]{2}:[0-9]{2}", value):
            raise ValueError
        local = datetime.strptime(value, "%d/%m/%Y %H:%M")
        candidates = set()
        for fold in (0, 1):
            candidate = local.replace(tzinfo=zone, fold=fold).astimezone(UTC)
            if candidate.astimezone(zone).replace(tzinfo=None) == local:
                candidates.add(candidate)
    except (ValueError, OverflowError) as exc:
        raise RowError("fecha_invalida", "Fecha inválida; usa DD/MM/AAAA HH:MM.") from exc
    if not candidates:
        raise RowError("hora_inexistente", "La hora local no existe en Europe/Madrid.")
    if len(candidates) > 1:
        raise RowError("hora_ambigua", "La hora local tiene dos instantes en Europe/Madrid.")
    return candidates.pop()


def validate(record: CSVRecord, sensors: dict[str, int], zone: ZoneInfo) -> Reading:
    """Validate in the specified rejection precedence."""
    if len(record.fields) != 3:
        raise RowError("estructura_invalida", "Se esperaban exactamente tres campos.")
    code, timestamp, raw_value = (field.strip() for field in record.fields)
    if code not in sensors:
        raise RowError("sensor_desconocido", "El código de sensor no existe.")
    instant = local_to_utc(timestamp, zone)
    if not re.fullmatch(r"[+-]?[0-9]+(,[0-9]+)?", raw_value):
        raise RowError("valor_invalido", "Se esperaba un número con coma decimal, sin miles.")
    number = Decimal(raw_value.replace(",", "."))
    value = float(number)
    if not math.isfinite(value) or (number != 0 and value == 0):
        raise RowError("valor_invalido", "Valor fuera del rango de double precision.")
    return Reading(sensors[code], instant, value)


def _load(source: Iterator[CSVRecord], report: TextIO, conn, zone: ZoneInfo) -> IngestionResult:
    if not conn.execute(UNIQUE_INDEX_SQL).fetchone()[0]:
        raise IngestionError("Falta unicidad (sensor_id, time); aplica la migración aprobada.")
    sensors = dict(conn.execute("SELECT code, id FROM sensors").fetchall())
    writer = csv.writer(report, delimiter=";")
    writer.writerow(REPORT_HEADER)
    seen: dict[tuple[int, datetime], int] = {}
    processed = inserted = rejected = 0
    for record in source:
        processed += 1
        try:
            reading = validate(record, sensors, zone)
            key = (reading.sensor_id, reading.time)
            if key in seen:
                raise RowError("duplicado_fichero", f"Primera fila válida: {seen[key]}.")
            seen[key] = record.line
            row = conn.execute(
                "INSERT INTO readings (sensor_id, time, value) VALUES (%s, %s, %s) "
                "ON CONFLICT (sensor_id, time) DO NOTHING RETURNING sensor_id",
                (reading.sensor_id, reading.time, reading.value),
            ).fetchone()
            if row is None:
                raise RowError("duplicado_base", "Ya existe una lectura del sensor e instante.")
            inserted += 1
        except RowError as exc:
            rejected += 1
            writer.writerow(Rejection(record, exc.reason, str(exc)).report_row())
    return IngestionResult(processed, inserted, rejected)


def ingest_csv(filename: str | Path, rejects: str | Path) -> IngestionResult:
    """Commit accepted rows only after reading input and closing the rejection report."""
    source_path, report_path = Path(filename), Path(rejects)
    if source_path.resolve() == report_path.resolve():
        raise IngestionError("El informe debe ser distinto del fichero de entrada.")
    zone = ZoneInfo("Europe/Madrid")
    with source_path.open(encoding="utf-8-sig", newline="") as source:
        stream = records(source)
        # Validate the header before opening a connection, without losing the first row.
        first = next(stream, None)

        def all_records():
            if first is not None:
                yield first
            yield from stream

        with report_path.open("x", encoding="utf-8", newline="") as report, connect() as conn:
            result = _load(all_records(), report, conn, zone)
            report.flush()
            # Closing before commit also catches buffered write/close failures.
            report.close()
            try:
                conn.commit()
            except (psycopg.OperationalError, psycopg.InterfaceError) as exc:
                raise IngestionError(
                    "Resultado de commit desconocido; el informe no acredita la carga. "
                    "Reintenta de forma idempotente con otro informe."
                ) from exc
    return result
