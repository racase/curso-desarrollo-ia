"""Contract and transaction tests without a database."""

import csv
from contextlib import contextmanager
from datetime import UTC, datetime
from io import StringIO
from unittest.mock import Mock
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

import psycopg
import pytest

from infradata import ingestion as ing
from infradata.__main__ import main

HEADER = ";".join(ing.HEADER) + "\n"


def test_records_capture_multiline_blank_and_wrong_structure():
    source = StringIO(HEADER + '"VAL\nT01";x;y\n\nx;y\n')
    rows = list(ing.records(source))
    assert [r.line for r in rows] == [2, 4, 5]
    assert rows[0].original == '"VAL\nT01";x;y\n'
    assert rows[1].fields == []
    assert rows[2].fields == ["x", "y"]


@pytest.mark.parametrize("text", ["", "other;header\n", HEADER + '"unfinished'])
def test_bad_contract(text):
    with pytest.raises(ing.CSVContractError):
        list(ing.records(StringIO(text)))


@pytest.mark.parametrize(
    "local,expected",
    [
        ("31/10/2026 00:30", "2026-10-30T23:30:00+00:00"),
        ("01/08/2026 02:00", "2026-08-01T00:00:00+00:00"),
    ],
)
def test_utc(local, expected):
    assert ing.local_to_utc(local, ZoneInfo("Europe/Madrid")) == datetime.fromisoformat(expected)


@pytest.mark.parametrize(
    "local,reason",
    [
        ("31/02/2026 12:00", "fecha_invalida"),
        ("1/08/2026 02:00", "fecha_invalida"),
        ("2026-10-31T01:00:00+01:00", "fecha_invalida"),
        ("25/10/2026 02:30", "hora_ambigua"),
        ("29/03/2026 02:30", "hora_inexistente"),
    ],
)
def test_dates_rejected(local, reason):
    with pytest.raises(ing.RowError) as error:
        ing.local_to_utc(local, ZoneInfo("Europe/Madrid"))
    assert error.value.reason == reason


@pytest.mark.parametrize("value", ["", "n/d", "NaN", "Infinity", "23.12", "1e3", "9" * 400])
def test_invalid_values(value):
    with pytest.raises(ing.RowError) as error:
        ing.validate(
            ing.CSVRecord(2, ["VAL-T01", "31/10/2026 01:00", value], ""),
            {"VAL-T01": 1},
            ZoneInfo("Europe/Madrid"),
        )
    assert error.value.reason == "valor_invalido"


def test_validation_precedence_and_trimming():
    zone = ZoneInfo("Europe/Madrid")
    for fields, reason in [
        (["x"], "estructura_invalida"),
        (["unknown", "bad", ""], "sensor_desconocido"),
        (["VAL-T01", "bad", ""], "fecha_invalida"),
    ]:
        with pytest.raises(ing.RowError) as error:
            ing.validate(ing.CSVRecord(2, fields, ""), {"VAL-T01": 1}, zone)
        assert error.value.reason == reason
    row = ing.validate(
        ing.CSVRecord(2, [" VAL-T01 ", "31/10/2026 01:00", " -96,40 "], ""), {"VAL-T01": 1}, zone
    )
    assert row == ing.Reading(1, datetime(2026, 10, 31, tzinfo=UTC), -96.4)


class FakeConnection:
    def __init__(self):
        self.values = {}
        self.committed = False
        self.rolled_back = False
        self.unique = True

    def execute(self, query, params=None):
        if query == ing.UNIQUE_INDEX_SQL:
            return Mock(fetchone=lambda: (self.unique,))
        if query.startswith("SELECT code"):
            return Mock(fetchall=lambda: [("VAL-T01", 1)])
        key = params[:2]
        if key in self.values:
            return Mock(fetchone=lambda: None)
        self.values[key] = params[2]
        return Mock(fetchone=lambda: (params[0],))

    def commit(self):
        self.committed = True


@pytest.fixture
def fake_db(monkeypatch):
    conn = FakeConnection()

    @contextmanager
    def connection():
        try:
            yield conn
        except BaseException:
            conn.rolled_back = True
            raise

    monkeypatch.setattr(ing, "connect", connection)
    return conn


def write_source(tmp_path, text):
    path = tmp_path / "input.csv"
    path.write_text(text, encoding="utf-8")
    return path


def report_rows(path):
    with path.open(encoding="utf-8", newline="") as stream:
        return list(csv.DictReader(stream, delimiter=";"))


def test_partial_and_repeat(tmp_path, fake_db):
    source = write_source(
        tmp_path,
        HEADER + "VAL-T01;31/10/2026 01:00;\n"
        "VAL-T01;31/10/2026 01:00;23,31\n"
        "VAL-T01;31/10/2026 01:00;24,00\n",
    )
    report = tmp_path / "rejects.csv"
    assert ing.ingest_csv(source, report) == ing.IngestionResult(3, 1, 2)
    rows = report_rows(report)
    assert [r["motivo"] for r in rows] == ["valor_invalido", "duplicado_fichero"]
    assert rows[1]["detalle"] == "Primera fila válida: 3."
    assert list(fake_db.values.values()) == [23.31]
    repeated = tmp_path / "repeat.csv"
    assert ing.ingest_csv(source, repeated) == ing.IngestionResult(3, 0, 3)
    assert report_rows(repeated)[1]["motivo"] == "duplicado_base"


@pytest.mark.parametrize("same", [True, False])
def test_report_protection(tmp_path, fake_db, same):
    source = write_source(tmp_path, HEADER)
    report = source if same else tmp_path / "existing.csv"
    report.write_text(HEADER)
    with pytest.raises((OSError, ing.IngestionError)):
        ing.ingest_csv(source, report)
    assert not fake_db.values
    assert not fake_db.committed
    assert source.read_text() == HEADER


def test_late_bad_csv_rolls_back(tmp_path, fake_db):
    source = write_source(tmp_path, HEADER + 'VAL-T01;31/10/2026 01:00;1\n"bad')
    with pytest.raises(ing.CSVContractError):
        ing.ingest_csv(source, tmp_path / "report.csv")
    assert fake_db.rolled_back
    assert not fake_db.committed


def test_missing_uniqueness(tmp_path, fake_db):
    fake_db.unique = False
    with pytest.raises(ing.IngestionError, match="unicidad"):
        ing.ingest_csv(write_source(tmp_path, HEADER), tmp_path / "report.csv")
    assert not fake_db.committed


def test_commit_unknown(tmp_path, fake_db, monkeypatch):
    monkeypatch.setattr(fake_db, "commit", Mock(side_effect=psycopg.OperationalError("lost")))
    with pytest.raises(ing.IngestionError, match="desconocido"):
        ing.ingest_csv(write_source(tmp_path, HEADER), tmp_path / "report.csv")


def test_report_write_failure_rolls_back(tmp_path, fake_db, monkeypatch):
    writer = Mock()
    writer.writerow.side_effect = [None, OSError("disk full")]
    monkeypatch.setattr(ing.csv, "writer", lambda *a, **kw: writer)
    source = write_source(tmp_path, HEADER + "VAL-T01;31/10/2026 01:00;1\nunknown;x;y\n")
    with pytest.raises(OSError, match="disk full"):
        ing.ingest_csv(source, tmp_path / "report.csv")
    assert fake_db.rolled_back
    assert not fake_db.committed


@pytest.mark.parametrize(
    "text,code",
    [
        (HEADER, 0),
        (HEADER + "VAL-T01;31/10/2026 01:00;1\n", 0),
        (HEADER + "unknown;x;y\n", 3),
        ("bad\n", 2),
    ],
)
def test_cli(tmp_path, fake_db, capsys, text, code):
    source = write_source(tmp_path, "\ufeff" + text)
    assert main(["ingest-csv", str(source), "--rejects", str(tmp_path / "r.csv")]) == code
    output = capsys.readouterr()
    if code in (0, 3):
        assert "Procesadas:" in output.out
        assert not output.err
    else:
        assert not output.out
        assert output.err


@pytest.mark.parametrize(
    "args", [["ingest-csv", "file"], ["ingest-csv", "file", "--unknown"], ["ingest-csv", "--help"]]
)
def test_cli_args_never_connect(args, monkeypatch, capsys):
    connect = Mock(side_effect=AssertionError("must not connect"))
    monkeypatch.setattr(ing, "connect", connect)
    assert main(args) == (0 if "--help" in args else 2)
    connect.assert_not_called()
    assert "ingest-csv" in str(capsys.readouterr())


def test_cli_zone_missing(tmp_path, monkeypatch, capsys):
    monkeypatch.setattr(ing, "ZoneInfo", Mock(side_effect=ZoneInfoNotFoundError("missing")))
    source = write_source(tmp_path, HEADER)
    assert main(["ingest-csv", str(source), "--rejects", str(tmp_path / "r.csv")]) == 1
    assert "incompleto" in capsys.readouterr().err


def test_invalid_encoding_rolls_back(tmp_path, fake_db):
    source = tmp_path / "bad.csv"
    source.write_bytes(HEADER.encode() + b"\xff\n")
    with pytest.raises(ing.CSVContractError):
        ing.ingest_csv(source, tmp_path / "report.csv")
    assert not fake_db.committed


def test_cli_existing_report(tmp_path, fake_db, capsys):
    source = write_source(tmp_path, HEADER)
    report = tmp_path / "existing.csv"
    report.write_text("preserve")
    assert main(["ingest-csv", str(source), "--rejects", str(report)]) == 1
    assert report.read_text() == "preserve"
    assert not capsys.readouterr().out


def test_sql_failure_rolls_back(tmp_path, fake_db, monkeypatch):
    original = fake_db.execute

    def execute(query, params=None):
        if params:
            raise psycopg.ProgrammingError("SQL failed")
        return original(query, params)

    monkeypatch.setattr(fake_db, "execute", execute)
    source = write_source(tmp_path, HEADER + "VAL-T01;31/10/2026 01:00;1\n")
    assert main(["ingest-csv", str(source), "--rejects", str(tmp_path / "report.csv")]) == 1
    assert fake_db.rolled_back
    assert not fake_db.committed
