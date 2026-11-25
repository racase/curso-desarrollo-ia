"""Validación y presentación de huecos sin PostgreSQL."""

from datetime import UTC, date, datetime, timedelta, timezone

import psycopg
import pytest

from infradata import __main__ as cli
from infradata import analytics


@pytest.mark.parametrize("threshold", [0, -1, 1.5, "15", True, None])
def test_invalid_threshold_does_not_connect(monkeypatch, threshold):
    def unexpected_connection():
        pytest.fail("No debe conectar con umbral inválido")

    monkeypatch.setattr(analytics, "connect", unexpected_connection)
    with pytest.raises(ValueError, match="entero positivo"):
        analytics.reading_gaps(threshold_minutes=threshold)


def test_gap_bounds_resolve_course_dates():
    assert analytics.gap_bounds() == (
        datetime(2026, 8, 1, tzinfo=UTC),
        datetime(2026, 10, 31, tzinfo=UTC),
    )
    assert analytics.gap_bounds(since=date(2026, 9, 1))[0].day == 1
    assert analytics.gap_bounds(until=date(2026, 9, 1))[1] == datetime(2026, 9, 2, tzinfo=UTC)


@pytest.mark.parametrize(
    "kwargs",
    [
        dict(since=date(2026, 11, 1)),
        dict(until=date(2026, 7, 31)),
        dict(until=date.max),
        dict(since="2026-09-01"),
        dict(since=datetime(2026, 9, 1)),
    ],
)
def test_invalid_effective_range_does_not_connect(monkeypatch, kwargs):
    def unexpected_connection():
        pytest.fail("No debe conectar con rango inválido")

    monkeypatch.setattr(analytics, "connect", unexpected_connection)
    with pytest.raises(ValueError):
        analytics.reading_gaps(**kwargs)


@pytest.mark.parametrize(
    "args",
    [["--umbral-minutos", value] for value in ["0", "-1", "1.5", "quince", "1_000", ""]]
    + [
        ["--desde", "2026-02-30"],
        ["--desde", "20260901"],
        ["--desde", "2026-11-01"],
        ["--hasta", "2026-07-31"],
        ["--hasta", "9999-12-31"],
        ["--desde", "2026-09-02", "--hasta", "2026-09-01"],
        ["--sensor"],
        ["--umbral-minutos"],
        ["--umbral", "5"],
        ["--desconocido"],
    ],
)
def test_invalid_gap_arguments_do_not_query(monkeypatch, capsys, args):
    def unexpected_query(**kwargs):
        pytest.fail("No debe consultar con argumentos inválidos")

    monkeypatch.setattr(cli, "reading_gaps", unexpected_query)
    assert cli.main(["huecos", *args]) == 2
    output = capsys.readouterr()
    assert output.out == ""
    assert "Uso:" in output.err
    assert "Traceback" not in output.err


def test_gap_filters_and_empty_output(monkeypatch, capsys):
    received = {}

    def query(**kwargs):
        received.update(kwargs)
        return []

    monkeypatch.setattr(cli, "reading_gaps", query)
    assert cli.main(["huecos"]) == 0
    assert received == dict(site=None, sensor=None, since=None, until=None, threshold_minutes=5)
    assert (
        cli.main(
            [
                "huecos",
                "--sede",
                "Planta Sagunto",
                "--sensor",
                "SAG-P02",
                "--desde",
                "2026-09-03",
                "--hasta",
                "2026-09-03",
                "--umbral-minutos",
                "15",
            ]
        )
        == 0
    )
    assert received == dict(
        site="Planta Sagunto",
        sensor="SAG-P02",
        since=date(2026, 9, 3),
        until=date(2026, 9, 3),
        threshold_minutes=15,
    )
    output = capsys.readouterr()
    assert output.err == ""
    assert output.out == "No hay huecos que superen el umbral para los filtros indicados.\n" * 2


def test_gap_output_formats_utc_long_durations_and_all_states(monkeypatch, capsys):
    stamp = datetime(2026, 9, 1, 2, tzinfo=timezone(timedelta(hours=2)))
    monkeypatch.setattr(
        cli,
        "reading_gaps",
        lambda **kwargs: [
            analytics.ReadingGap(
                "Sede",
                "A",
                stamp,
                stamp + timedelta(hours=54, minutes=5),
                timedelta(hours=54, minutes=5),
                "completo",
            ),
            analytics.ReadingGap(
                "Sede", "B", None, stamp, timedelta(minutes=20), "abierto al inicio"
            ),
            analytics.ReadingGap(
                "Sede", "C", stamp, None, timedelta(minutes=20), "abierto al final"
            ),
            analytics.ReadingGap(
                "Sede", "D", None, None, timedelta(days=1), "abierto en ambos extremos"
            ),
        ],
    )
    assert cli.main(["huecos"]) == 0
    output = capsys.readouterr()
    assert output.err == ""
    assert output.out.splitlines() == [
        "Sede | Sensor | Última lectura UTC | Siguiente lectura UTC | Duración | Estado",
        "Sede | A | 2026-09-01 00:00:00 UTC | 2026-09-03 06:05:00 UTC | 54:05:00 | completo",
        "Sede | B | desconocido | 2026-09-01 00:00:00 UTC | >= 00:20:00 | abierto al inicio",
        "Sede | C | 2026-09-01 00:00:00 UTC | desconocido | >= 00:20:00 | abierto al final",
        "Sede | D | desconocido | desconocido | >= 24:00:00 | abierto en ambos extremos",
    ]


@pytest.mark.parametrize("error", [psycopg.OperationalError, psycopg.ProgrammingError])
def test_gap_database_error(monkeypatch, capsys, error):
    def query(**kwargs):
        raise error("detalle interno")

    monkeypatch.setattr(cli, "reading_gaps", query)
    assert cli.main(["huecos"]) == 1
    output = capsys.readouterr()
    assert output.out == ""
    assert "No se pudo consultar la base de datos" in output.err
    assert "detalle interno" not in output.err


def test_gap_help_is_in_spanish_without_query(monkeypatch, capsys):
    def unexpected_query(**kwargs):
        pytest.fail("La ayuda no debe consultar")

    monkeypatch.setattr(cli, "reading_gaps", unexpected_query)
    assert cli.main(["huecos", "--help"]) == 0
    assert "5 minutos por defecto" in capsys.readouterr().out
