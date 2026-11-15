from contextlib import nullcontext
from datetime import UTC, date, datetime

import infradata.__main__ as cli
from infradata.anomalies import AnomalousReading
from infradata.gaps import ReadingGap
from infradata.summary import DailySummary

main = cli.main


def test_unknown_command_shows_usage(capsys):
    assert main(["nope"]) == 2
    assert "Uso:" in capsys.readouterr().err


def test_invalid_summary_date_shows_error(capsys):
    assert main(["summary", "--from-date", "2026-99-99"]) == 2
    assert "AAAA-MM-DD" in capsys.readouterr().err


def test_invalid_gap_threshold_shows_error(capsys):
    assert main(["gaps", "--minutes", "0"]) == 2
    assert "umbral" in capsys.readouterr().err


def test_summary_command_prints_values(monkeypatch, capsys):
    monkeypatch.setattr(cli, "connect", lambda: nullcontext(object()))
    monkeypatch.setattr(
        cli,
        "daily_summaries",
        lambda conn, **kwargs: [
            DailySummary(
                "VAL-T01", "Horno", "Planta Valencia", "°C", date(2026, 8, 1), 1, 3, 2, 288
            )
        ],
    )
    assert main(["summary", "--sensor", "VAL-T01"]) == 0
    assert "lecturas=288" in capsys.readouterr().out


def test_gaps_command_prints_interval(monkeypatch, capsys):
    monkeypatch.setattr(cli, "connect", lambda: nullcontext(object()))
    monkeypatch.setattr(
        cli,
        "reading_gaps",
        lambda conn, **kwargs: [
            ReadingGap(
                "SAG-P02",
                "Planta Sagunto",
                datetime(2026, 9, 3, 7, 55, tzinfo=UTC),
                datetime(2026, 9, 3, 14, 30, tzinfo=UTC),
                395,
            )
        ],
    )
    assert main(["gaps"]) == 0
    assert "duración=395 min" in capsys.readouterr().out


def test_anomalies_command_prints_reasons(monkeypatch, capsys):
    monkeypatch.setattr(cli, "connect", lambda: nullcontext(object()))
    monkeypatch.setattr(
        cli,
        "anomalous_readings",
        lambda conn, **kwargs: [
            AnomalousReading(
                "SAG-T01",
                "Planta Sagunto",
                "°C",
                datetime(2026, 8, 27, 16, 45, tzinfo=UTC),
                -40,
                ("out_of_range", "jump"),
            )
        ],
    )
    assert main(["anomalies"]) == 0
    assert "fuera de rango, salto brusco" in capsys.readouterr().out


def test_weekly_command_reports_count(monkeypatch, capsys, tmp_path):
    monkeypatch.setattr(cli, "connect", lambda: nullcontext(object()))
    monkeypatch.setattr(cli, "export_weekly_csv", lambda conn, **kwargs: 28)
    output = tmp_path / "weekly.csv"
    assert (
        main(
            [
                "weekly-csv",
                "--site",
                "Planta Valencia",
                "--week",
                "2026-08-03",
                "--output",
                str(output),
            ]
        )
        == 0
    )
    assert "28 filas" in capsys.readouterr().out
