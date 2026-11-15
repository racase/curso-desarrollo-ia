"""Tests for the weekly CSV export."""

import csv
from datetime import UTC, date, datetime

import psycopg
import pytest

from infradata.db import connect
from infradata.export import HEADERS, export_weekly_csv


class _Connection:
    def execute(self, query, params):
        self.params = params
        return self

    def fetchall(self):
        return [
            (
                "VAL-T01",
                "Temperatura; horno",
                "Planta Valencia",
                "°C",
                datetime(2026, 8, 3, tzinfo=UTC),
                1.25,
                2.5,
                1.875,
                288,
            )
        ]


def test_weekly_csv_format_and_no_overwrite(tmp_path):
    output = tmp_path / "weekly.csv"
    conn = _Connection()
    assert (
        export_weekly_csv(conn, site="Planta Valencia", week=date(2026, 8, 3), output=output) == 1
    )
    data = output.read_bytes()
    assert data.startswith(b"\xef\xbb\xbf")
    assert b"\r\n" in data
    with output.open(encoding="utf-8-sig", newline="") as stream:
        rows = list(csv.reader(stream, delimiter=";"))
    assert rows[0] == list(HEADERS)
    assert rows[1] == [
        "2026-08-03",
        "Planta Valencia",
        "VAL-T01",
        "Temperatura; horno",
        "°C",
        "1,25",
        "2,50",
        "1,88",
        "288",
    ]
    assert conn.params[0] == "Planta Valencia"
    assert conn.params[-1] == datetime(2026, 8, 10, tzinfo=UTC)
    with pytest.raises(FileExistsError):
        export_weekly_csv(conn, site="Planta Valencia", week=date(2026, 8, 3), output=output)
    assert output.read_bytes() == data


def test_weekly_csv_requires_monday(tmp_path):
    output = tmp_path / "weekly.csv"
    with pytest.raises(ValueError, match="lunes"):
        export_weekly_csv(
            _Connection(), site="Planta Valencia", week=date(2026, 8, 4), output=output
        )
    assert not output.exists()


@pytest.mark.integration
def test_seed_weekly_csv_matches_daily_summaries(tmp_path):
    try:
        connection = connect(connect_timeout=2)
    except psycopg.OperationalError:
        pytest.skip("database not reachable")
    output = tmp_path / "weekly.csv"
    with connection:
        count = export_weekly_csv(
            connection, site="Planta Valencia", week=date(2026, 8, 3), output=output
        )
    with output.open(encoding="utf-8-sig", newline="") as stream:
        rows = list(csv.DictReader(stream, delimiter=";"))
    assert count == len(rows) == 28
    assert {row["sede"] for row in rows} == {"Planta Valencia"}
    assert {row["fecha"] for row in rows} == {
        date(2026, 8, day).isoformat() for day in range(3, 10)
    }
