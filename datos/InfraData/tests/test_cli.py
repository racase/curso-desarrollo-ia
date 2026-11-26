from contextlib import contextmanager
from datetime import UTC, date, datetime
from unittest.mock import Mock

import psycopg
import pytest

from infradata import __main__ as cli
from infradata import analytics
from infradata.__main__ import main
from infradata.analytics import DailySummary


def test_unknown_command_shows_usage(capsys):
    assert main(["nope"]) == 2
    assert "Uso:" in capsys.readouterr().err


@pytest.mark.parametrize(
    "options",
    [
        ["--start-date", "2026-02-30"],
        ["--start-date", "20260903"],
        ["--start-date", "2026-9-03"],
        ["--start-date", "2026-09-04", "--end-date", "2026-09-03"],
        ["--end-date", "9999-12-31"],
        ["--unknown"],
        ["--sen", "VAL-T01"],
    ],
)
def test_invalid_summary_never_connects(options, monkeypatch, capsys):
    connect = Mock(side_effect=AssertionError("must not connect"))
    monkeypatch.setattr(analytics, "connect", connect)
    assert main(["daily-summary", *options]) == 2
    assert capsys.readouterr().err
    connect.assert_not_called()


def test_summary_filters_and_presentation(monkeypatch, capsys):
    query = Mock(
        return_value=[
            DailySummary(
                date(2026, 9, 3),
                "SAG-P02",
                "Presión",
                "Planta Sagunto",
                "bar",
                6.11,
                7.27,
                6.666571428571434,
                210,
            ),
            DailySummary(
                date(2026, 9, 4), "SAG-P02", "Presión", "Planta Sagunto", "bar", 6, 7, 6.5, 288
            ),
        ]
    )
    monkeypatch.setattr(cli, "daily_summary", query)
    assert (
        main(
            [
                "daily-summary",
                "--site",
                "Planta Sagunto",
                "--sensor",
                "SAG-P02",
                "--start-date",
                "2026-09-03",
                "--end-date",
                "2026-09-04",
            ]
        )
        == 0
    )
    query.assert_called_once_with(
        site="Planta Sagunto",
        sensor="SAG-P02",
        start_date=date(2026, 9, 3),
        end_date=date(2026, 9, 4),
    )
    captured = capsys.readouterr()
    assert captured.err == ""
    assert captured.out.splitlines() == [
        "dia_utc\tsensor\tnombre\tsede\tunidad\tmin\tmax\tmedia\tlecturas",
        "2026-09-03\tSAG-P02\tPresión\tPlanta Sagunto\tbar\t6.110\t7.270\t6.667\t210",
        "2026-09-04\tSAG-P02\tPresión\tPlanta Sagunto\tbar\t6.000\t7.000\t6.500\t288",
    ]


def test_empty_summary(monkeypatch, capsys):
    monkeypatch.setattr(cli, "daily_summary", Mock(return_value=[]))
    assert main(["daily-summary"]) == 0
    assert capsys.readouterr().out == "Sin lecturas para los filtros indicados.\n"


@pytest.mark.parametrize("error", [psycopg.OperationalError, psycopg.ProgrammingError])
def test_summary_database_error(monkeypatch, capsys, error):
    monkeypatch.setattr(cli, "daily_summary", Mock(side_effect=error("consulta fallida")))
    assert main(["daily-summary"]) == 1
    captured = capsys.readouterr()
    assert captured.out == ""
    assert "No se pudo consultar" in captured.err


@pytest.mark.parametrize("args", [["--help"], ["daily-summary", "--help"]])
def test_help(args, capsys):
    assert main(args) == 0
    assert "daily-summary" in capsys.readouterr().out


def test_check_keeps_output(monkeypatch, capsys):
    conn = Mock()
    conn.execute.side_effect = [
        Mock(fetchone=Mock(return_value=(12,))),
        Mock(
            fetchone=Mock(
                return_value=(
                    313760,
                    datetime(2026, 8, 1, tzinfo=UTC),
                    datetime(2026, 10, 30, 23, 55, tzinfo=UTC),
                )
            )
        ),
    ]

    @contextmanager
    def connect():
        yield conn

    monkeypatch.setattr(cli, "connect", connect)
    assert main(["check"]) == 0
    assert capsys.readouterr().out == (
        "Sensores: 12\nLecturas: 313760\nDesde:    2026-08-01 00:00 UTC\n"
        "Hasta:    2026-10-30 23:55 UTC\n"
    )


def test_check_keeps_connection_error(monkeypatch, capsys):
    monkeypatch.setattr(cli, "connect", Mock(side_effect=psycopg.OperationalError("offline")))
    assert main(["check"]) == 1
    assert "docker compose up -d" in capsys.readouterr().err
