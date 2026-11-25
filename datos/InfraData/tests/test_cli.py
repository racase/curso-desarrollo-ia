from contextlib import contextmanager
from datetime import UTC, date, datetime
from types import SimpleNamespace

import psycopg
import pytest

from infradata import __main__ as cli
from infradata.__main__ import main
from infradata.analytics import DailySummary


def test_unknown_command_shows_usage(capsys):
    assert main(["nope"]) == 2
    assert "Uso:" in capsys.readouterr().err


@pytest.mark.parametrize(
    "args",
    [
        ["--desde", "2026-02-30"],
        ["--desde", "01/09/2026"],
        ["--desde", "20260901"],
        ["--desde", "2026-9-1"],
        ["--desde", "2026-09-02", "--hasta", "2026-09-01"],
        ["--hasta", "9999-12-31"],
        ["--sensor"],
        ["--desconocido", "x"],
        ["--sen", "VAL-T01"],
    ],
)
def test_invalid_summary_arguments_do_not_query(monkeypatch, capsys, args):
    def unexpected_query(**kwargs):
        pytest.fail("No debe consultar con argumentos inválidos")

    monkeypatch.setattr(cli, "daily_summary", unexpected_query)
    assert main(["resumen-diario", *args]) == 2
    output = capsys.readouterr()
    assert output.out == ""
    assert "Uso:" in output.err
    assert "Traceback" not in output.err


@pytest.mark.parametrize("zone", ["UTC", "Europe/Madrid"])
def test_summary_output_expected_counts_and_precision(monkeypatch, capsys, zone):
    monkeypatch.setenv("TZ", zone)
    monkeypatch.setattr(
        cli,
        "daily_summary",
        lambda **kwargs: [
            DailySummary("Sede", "A", date(2026, 9, 1), "°C", 1, 2, 1.5, 288),
            DailySummary("Sede", "B", date(2026, 9, 1), "°C", 10, 30, 20, 3),
        ],
    )
    assert main(["resumen-diario"]) == 0
    output = capsys.readouterr()
    assert output.err == ""
    assert output.out.splitlines() == [
        "Sede | Sensor | Día UTC | Unidad | Mínimo | Máximo | Media | Lecturas | Esperadas",
        "Sede | A | 2026-09-01 | °C | 1.00 | 2.00 | 1.50 | 288 | 288",
        "Sede | B | 2026-09-01 | °C | 10.00 | 30.00 | 20.00 | 3 | 288",
    ]


@pytest.mark.parametrize(
    ("args", "expected"),
    [
        ([], dict(site=None, sensor=None, since=None, until=None)),
        (["--desde", "2026-09-01"], dict(since=date(2026, 9, 1))),
        (["--hasta", "2026-09-02"], dict(until=date(2026, 9, 2))),
        (
            [
                "--sede",
                "Planta Valencia",
                "--sensor",
                "VAL-T01",
                "--desde",
                "2026-09-01",
                "--hasta",
                "2026-09-02",
            ],
            dict(
                site="Planta Valencia",
                sensor="VAL-T01",
                since=date(2026, 9, 1),
                until=date(2026, 9, 2),
            ),
        ),
    ],
)
def test_summary_passes_filters_and_reports_empty_result(monkeypatch, capsys, args, expected):
    received = {}

    def query(**kwargs):
        received.update(kwargs)
        return []

    monkeypatch.setattr(cli, "daily_summary", query)
    assert main(["resumen-diario", *args]) == 0
    assert received == dict(site=None, sensor=None, since=None, until=None) | expected
    assert capsys.readouterr().out == "No hay lecturas para los filtros indicados.\n"


@pytest.mark.parametrize("error", [psycopg.OperationalError, psycopg.ProgrammingError])
def test_summary_database_failure(monkeypatch, capsys, error):
    def query(**kwargs):
        raise error("detalle interno")

    monkeypatch.setattr(cli, "daily_summary", query)
    assert main(["resumen-diario"]) == 1
    output = capsys.readouterr()
    assert output.out == ""
    assert "No se pudo consultar la base de datos" in output.err
    assert "detalle interno" not in output.err


def test_summary_help_is_in_spanish(capsys):
    assert main(["resumen-diario", "--help"]) == 0
    assert "Uso:" in capsys.readouterr().out


def test_check_output_is_preserved(monkeypatch, capsys):
    results = iter(
        [
            (12,),
            (313760, datetime(2026, 8, 1, tzinfo=UTC), datetime(2026, 10, 30, 23, 55, tzinfo=UTC)),
        ]
    )

    @contextmanager
    def connection():
        yield SimpleNamespace(execute=lambda query: SimpleNamespace(fetchone=lambda: next(results)))

    monkeypatch.setattr(cli, "connect", connection)
    assert main(["check"]) == 0
    assert capsys.readouterr().out == (
        "Sensores: 12\nLecturas: 313760\n"
        "Desde:    2026-08-01 00:00 UTC\nHasta:    2026-10-30 23:55 UTC\n"
    )


def test_check_database_error_is_preserved(monkeypatch, capsys):
    def connection():
        raise psycopg.OperationalError("fallo")

    monkeypatch.setattr(cli, "connect", connection)
    assert main(["check"]) == 1
    assert "No se pudo consultar la base de datos" in capsys.readouterr().err
