"""Validación CSV y estados observables del informe y la transacción."""

import csv
from contextlib import contextmanager
from datetime import UTC, datetime
from types import SimpleNamespace
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

import psycopg
import pytest

from infradata import ingestion
from infradata.__main__ import main

HEADER = "codigo_sensor;fecha_hora_local;valor\n"


def read_report(path):
    with path.open(encoding="utf-8", newline="") as stream:
        return list(csv.DictReader(stream, delimiter=";"))


@pytest.fixture
def database(monkeypatch):
    """Doble transaccional para probar archivos y mensajes sin PostgreSQL."""

    class Database:
        def __init__(self):
            self.rows = {}
            self.pending = {}
            self.constraint = True
            self.error = None
            self.commit_error = False
            self.calls = 0

        def execute(self, query, params=None):
            if query == ingestion.UNIQUE_CONSTRAINT:
                row = (1,) if self.constraint else None
            elif query == "SELECT code, id FROM sensors":
                return SimpleNamespace(fetchall=lambda: [("A", 1)])
            elif query.lstrip().startswith("INSERT"):
                if self.error:
                    raise self.error
                sensor, stamp, value = params
                key = (sensor, stamp)
                row = None if key in self.pending else (1,)
                if row:
                    self.pending[key] = value
            elif query.startswith("SELECT value"):
                row = (self.pending[params],) if params in self.pending else None
            else:
                row = None
            return SimpleNamespace(fetchone=lambda: row)

        def commit(self):
            if self.commit_error:
                raise psycopg.OperationalError("SECRET")
            self.rows = self.pending.copy()

    db = Database()

    @contextmanager
    def connect():
        db.calls += 1
        db.pending = db.rows.copy()
        try:
            yield db
        finally:
            db.pending = {}

    monkeypatch.setattr(ingestion, "connect", connect)
    return db


@pytest.fixture
def paths(tmp_path):
    source = tmp_path / "entrada.csv"
    source.write_text(HEADER, encoding="utf-8")
    return source, tmp_path / "rechazos.csv"


@pytest.mark.parametrize(
    ("local", "expected"),
    [
        ("31/10/2026 01:00", datetime(2026, 10, 31, tzinfo=UTC)),
        ("01/07/2026 12:00", datetime(2026, 7, 1, 10, tzinfo=UTC)),
    ],
)
@pytest.mark.parametrize("process_zone", ["UTC", "Europe/Madrid", "America/New_York"])
def test_local_time_uses_iana_rules(monkeypatch, local, expected, process_zone):
    monkeypatch.setenv("TZ", process_zone)
    assert ingestion.local_to_utc(local, ZoneInfo("Europe/Madrid")) == expected


@pytest.mark.parametrize(
    ("local", "reason"),
    [
        ("25/10/2026 02:30", "hora_ambigua"),
        ("29/03/2026 02:30", "hora_inexistente"),
        ("30/02/2026 12:00", "fecha_invalida"),
        ("1/07/2026 12:00", "fecha_invalida"),
        ("01/07/2026 12:00:00", "fecha_invalida"),
        ("01/01/0000 00:00", "fecha_invalida"),
    ],
)
def test_problematic_local_times_are_rejected(local, reason):
    with pytest.raises(ingestion.RowError, match=reason):
        ingestion.local_to_utc(local, ZoneInfo("Europe/Madrid"))


@pytest.mark.parametrize(
    ("value", "reason"),
    [
        ("", "valor_vacio"),
        ("n/d", "valor_no_numerico"),
        ("1.2", "valor_no_numerico"),
        ("1e3", "valor_no_numerico"),
        ("NaN", "valor_no_finito"),
        ("-Infinity", "valor_no_finito"),
        ("9" * 400, "valor_no_finito"),
    ],
)
def test_invalid_numbers(value, reason):
    with pytest.raises(ingestion.RowError, match=reason):
        ingestion.numeric_value(value)


def test_mixed_input_precedence_originals_and_first_valid_row(database, paths):
    source, report = paths
    source.write_text(
        HEADER + "A;31/10/2026 01:00;\n"
        " A ;31/10/2026 01:00; 23,1 \n"
        "A;31/10/2026 01:00;23,10\nA;31/10/2026 01:00;24\n"
        "UNKNOWN;invalid;n/d\nA;invalid;\nA;31/10/2026 01:05;n/d\n"
        "A;31/10/2026 01:10;-40\nA;31/10/2026 01:15;0\n"
        "A;31/10/2026 01:20;4;extra\n\nA\n",
        encoding="utf-8",
    )
    result = ingestion.ingest_csv(source, report)
    assert (result.total, result.inserted, result.rejected) == (12, 3, 9)
    assert sorted(database.rows.values()) == [-40, 0, 23.1]
    rows = read_report(report)
    assert [r["motivo"] for r in rows] == [
        "valor_vacio",
        "duplicado_en_csv",
        "conflicto_en_csv",
        "sensor_desconocido",
        "fecha_invalida",
        "valor_no_numerico",
        "numero_de_campos_invalido",
        "numero_de_campos_invalido",
        "numero_de_campos_invalido",
    ]
    assert rows[0]["fila"] == "2"
    assert rows[-3]["registro_original"].endswith(";extra")
    assert rows[-2]["registro_original"] == ""
    assert rows[-1]["valor"] == ""
    # La clave ya cargada no se sobrescribe y la repetición interna se compara con la primera.
    source.write_text(HEADER + "A;31/10/2026 01:00;24\nA;31/10/2026 01:00;24\n")
    second = report.with_name("segundo.csv")
    assert ingestion.ingest_csv(source, second).inserted == 0
    assert [r["motivo"] for r in read_report(second)] == [
        "conflicto_con_existente",
        "duplicado_en_csv",
    ]
    source.write_text(HEADER + "A;31/10/2026 01:00;23,10\n")
    third = report.with_name("tercero.csv")
    assert ingestion.ingest_csv(source, third).inserted == 0
    assert read_report(third)[0]["motivo"] == "ya_cargada"


def test_multiline_quoted_fields_and_physical_line_numbers(database, paths):
    source, report = paths
    original = '"A\nB";31/10/2026 01:00;4'
    source.write_text(HEADER + original + "\nA;31/10/2026 01:00;n/d\n")
    ingestion.ingest_csv(source, report)
    rows = read_report(report)
    assert [r["fila"] for r in rows] == ["2", "4"]
    assert rows[0]["registro_original"] == original
    assert rows[0]["codigo_sensor"] == "A\nB"


@pytest.mark.parametrize("ending", ["\n", "\r\n"])
def test_header_only_and_windows_line_endings(database, paths, ending):
    source, report = paths
    source.write_bytes(HEADER.replace("\n", ending).encode())
    result = ingestion.ingest_csv(source, report)
    assert (result.total, result.inserted, result.rejected) == (0, 0, 0)
    assert read_report(report) == []
    source.write_bytes((HEADER + "A;31/10/2026 01:00;1\n").replace("\n", ending).encode())
    assert ingestion.ingest_csv(source, report.with_name("otro.csv")).inserted == 1


@pytest.mark.parametrize(
    "payload", [b"", b"bad;header\n", b"\xff", HEADER.encode() + b'"unfinished']
)
def test_invalid_files_do_not_commit(database, paths, payload):
    source, report = paths
    source.write_bytes(payload)
    with pytest.raises(ingestion.InputError):
        ingestion.ingest_csv(source, report)
    assert not database.rows
    assert not report.exists()
    assert not list(report.parent.glob(".infradata*"))


def test_late_structural_error_rolls_back(database, paths):
    source, report = paths
    source.write_text(HEADER + 'A;31/10/2026 01:00;2\n"unfinished')
    with pytest.raises(ingestion.InputError):
        ingestion.ingest_csv(source, report)
    assert not database.rows
    assert not report.exists()


@pytest.mark.parametrize("kind", ["same", "hardlink", "symlink", "dangling", "existing"])
def test_destination_protection_before_connect(database, paths, kind):
    source, report = paths
    if kind == "same":
        report = source
    elif kind == "hardlink":
        report.hardlink_to(source)
    elif kind == "symlink":
        report.symlink_to(source)
    elif kind == "dangling":
        report.symlink_to(source.with_name("absent.csv"))
    else:
        report.write_text("conservar")
    original = source.read_bytes()
    with pytest.raises(ingestion.InputError):
        ingestion.ingest_csv(source, report)
    assert database.calls == 0
    assert source.read_bytes() == original


@pytest.mark.parametrize("failure", ["constraint", "sql", "fsync"])
def test_precommit_failures_leave_no_report_or_inserts(database, paths, monkeypatch, failure):
    source, report = paths
    source.write_text(HEADER + "A;31/10/2026 01:00;1\n")
    if failure == "constraint":
        database.constraint = False
    elif failure == "sql":
        database.error = psycopg.ProgrammingError("SECRET")
    else:

        def fail(fd):
            raise OSError("SECRET")

        monkeypatch.setattr(ingestion.os, "fsync", fail)
    with pytest.raises((ingestion.IngestionError, psycopg.Error, OSError)):
        ingestion.ingest_csv(source, report)
    assert not database.rows
    assert not report.exists()
    assert not list(report.parent.glob(".infradata*"))


@pytest.mark.parametrize("failure", ["publication", "commit"])
def test_uncertain_commit_and_publication_preserve_recovery_file(
    database, paths, monkeypatch, failure, capsys
):
    source, report = paths
    source.write_text(HEADER + "A;31/10/2026 01:00;1\n")
    if failure == "commit":
        database.commit_error = True
    else:

        def fail(*args):
            report.write_text("archivo aparecido durante la carga")
            raise FileExistsError("SECRET")

        monkeypatch.setattr(ingestion.os, "link", fail)
    assert main(["ingestar-csv", str(source), "--informe", str(report)]) == 1
    output = capsys.readouterr()
    assert output.out == ""
    assert "SECRET" not in output.err
    assert ("desconocido" if failure == "commit" else "confirmada") in output.err
    temporary = list(report.parent.glob(".infradata*"))
    assert len(temporary) == 1
    assert str(temporary[0]) in output.err
    if failure == "publication":
        assert len(database.rows) == 1
        assert report.read_text() == "archivo aparecido durante la carga"


@pytest.mark.parametrize(
    "args", [[], ["x"], ["x", "--informe"], ["--unknown"], ["x", "--inf", "y"]]
)
def test_cli_invalid_arguments_do_not_connect(database, capsys, args):
    assert main(["ingestar-csv", *args]) == 2
    assert database.calls == 0
    assert "Uso:" in capsys.readouterr().err


def test_cli_summary_help_and_operational_errors(database, paths, monkeypatch, capsys):
    source, report = paths
    source.write_text(HEADER + "A;31/10/2026 01:00;1\nA;invalid;1\n")
    assert main(["ingestar-csv", str(source), "--informe", str(report)]) == 0
    output = capsys.readouterr()
    assert output.err == ""
    assert "Filas: 2 | Incorporadas: 1 | Rechazadas: 1" in output.out
    assert str(report) in output.out
    assert main(["ingestar-csv", "--help"]) == 0
    assert "Uso:" in capsys.readouterr().out

    def missing_zone(name):
        raise ZoneInfoNotFoundError("SECRET")

    monkeypatch.setattr(ingestion, "ZoneInfo", missing_zone)
    assert main(["ingestar-csv", str(source), "--informe", str(report.with_name("new.csv"))]) == 1
    assert "IANA" in capsys.readouterr().err


def test_cli_missing_source_and_parent(database, paths, capsys):
    source, report = paths
    for origin, destination in [
        (source.with_name("absent.csv"), report),
        (source, report / "absent" / "report.csv"),
    ]:
        assert main(["ingestar-csv", str(origin), "--informe", str(destination)]) == 2
    assert database.calls == 0
    assert "Uso:" in capsys.readouterr().err


def test_cli_database_failure_does_not_disclose_details(paths, monkeypatch, capsys):
    source, report = paths

    def unavailable():
        raise psycopg.OperationalError("credencial_interna")

    monkeypatch.setattr(ingestion, "connect", unavailable)
    assert main(["ingestar-csv", str(source), "--informe", str(report)]) == 1
    output = capsys.readouterr()
    assert output.out == ""
    assert "fallo de base de datos" in output.err
    assert "credencial_interna" not in output.err
    assert "Traceback" not in output.err
    assert not report.exists()
