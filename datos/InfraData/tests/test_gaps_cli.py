"""CLI contract and pre-connection validation for missing-reading periods."""

from datetime import date, datetime, timedelta, timezone
from decimal import Decimal
from unittest.mock import Mock

import psycopg
import pytest

from infradata import __main__ as cli
from infradata import analytics
from infradata.analytics import ReadingGap


@pytest.mark.parametrize(
    "options",
    [
        ["--threshold-minutes", "-1"],
        ["--threshold-minutes", "1.5"],
        ["--threshold-minutes", "nan"],
        ["--start-date", "2026-02-30"],
        ["--start-date", "20260903"],
        ["--start-date", "2026-9-03"],
        ["--end-date", "9999-12-31"],
        ["--start-date", "2026-09-04", "--end-date", "2026-09-03"],
        ["--unknown"],
        ["--sen", "SAG-P02"],
    ],
)
def test_invalid_gaps_never_connect(options, monkeypatch, capsys):
    connection = Mock(side_effect=AssertionError("must not connect"))
    monkeypatch.setattr(analytics, "connect", connection)
    assert cli.main(["gaps", *options]) == 2
    output = capsys.readouterr()
    assert output.err
    assert output.out == ""
    connection.assert_not_called()


@pytest.mark.parametrize("threshold", [-1, 1.5, "15", True, None])
def test_api_rejects_non_integer_threshold_before_connect(threshold, monkeypatch):
    connection = Mock(side_effect=AssertionError("must not connect"))
    monkeypatch.setattr(analytics, "connect", connection)
    with pytest.raises(ValueError, match="entero no negativo"):
        analytics.reading_gaps(threshold_minutes=threshold)
    connection.assert_not_called()


def test_gaps_filters_and_output(monkeypatch, capsys):
    # Presentation must convert timezone-aware timestamps to UTC.
    local = timezone(timedelta(hours=2))
    query = Mock(
        return_value=[
            ReadingGap(
                "PAT-L01",
                "Almacén Paterna",
                datetime(2026, 10, 14, 2, tzinfo=local),
                datetime(2026, 10, 14, 8, tzinfo=local),
                Decimal("360"),
                True,
                False,
                False,
                True,
            )
        ]
    )
    monkeypatch.setattr(cli, "reading_gaps", query)
    assert (
        cli.main(
            [
                "gaps",
                "--site",
                "Almacén Paterna",
                "--sensor",
                "PAT-L01",
                "--start-date",
                "2026-10-14",
                "--end-date",
                "2026-10-14",
                "--threshold-minutes",
                "0",
            ]
        )
        == 0
    )
    query.assert_called_once_with(
        site="Almacén Paterna",
        sensor="PAT-L01",
        start_date=date(2026, 10, 14),
        end_date=date(2026, 10, 14),
        threshold_minutes=0,
    )
    assert capsys.readouterr().out.splitlines() == [
        "sensor\tsede\tinicio_utc\tfin_utc\tduracion_minutos\t"
        "recortado_inicio\trecortado_fin\tabierto_inicio\tabierto_fin",
        "PAT-L01\tAlmacén Paterna\t2026-10-14T00:00:00Z\t2026-10-14T06:00:00Z\t"
        "360.000\tsi\tno\tno\tsi",
    ]


def test_gaps_defaults_and_empty_output(monkeypatch, capsys):
    query = Mock(return_value=[])
    monkeypatch.setattr(cli, "reading_gaps", query)
    assert cli.main(["gaps"]) == 0
    query.assert_called_once_with(
        site=None,
        sensor=None,
        start_date=None,
        end_date=None,
        threshold_minutes=15,
    )
    assert capsys.readouterr().out == "Sin huecos para los filtros indicados.\n"


@pytest.mark.parametrize("error", [psycopg.OperationalError, psycopg.ProgrammingError])
def test_gaps_database_errors_have_no_partial_output(error, monkeypatch, capsys):
    monkeypatch.setattr(cli, "reading_gaps", Mock(side_effect=error("consulta fallida")))
    assert cli.main(["gaps"]) == 1
    output = capsys.readouterr()
    assert output.out == ""
    assert "No se pudo consultar" in output.err


def test_gaps_help(capsys):
    assert cli.main(["gaps", "--help"]) == 0
    help_text = capsys.readouterr().out
    for text in (
        "--site",
        "--sensor",
        "--start-date",
        "--end-date",
        "--threshold-minutes",
        "5 minutos",
        "recortar",
        "15",
    ):
        assert text in help_text
