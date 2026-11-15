"""Tests for anomaly classification."""

from datetime import UTC, datetime

import psycopg
import pytest

from infradata.anomalies import anomalous_readings
from infradata.db import connect


class _Connection:
    def __init__(self):
        self.query = None
        self.params = None

    def execute(self, query, params):
        self.query = query
        self.params = params
        return self

    def fetchall(self):
        return [
            (
                "VAL-T01",
                "Planta Valencia",
                "°C",
                datetime(2026, 9, 18, 3, 10, tzinfo=UTC),
                96.4,
                ["out_of_range", "fixed", "jump"],
            )
        ]


def test_anomalies_use_parameterized_limits_and_filters():
    conn = _Connection()
    rows = anomalous_readings(conn, site="Planta Valencia", sensor="VAL-T01")
    assert rows[0].reasons == ("out_of_range", "fixed", "jump")
    assert conn.params[-2:] == ["Planta Valencia", "VAL-T01"]
    assert "lag(r.value)" in conn.query
    assert "cardinality(reasons) > 0" in conn.query


@pytest.mark.integration
def test_seed_anomalies_cover_three_rules():
    try:
        connection = connect(connect_timeout=2)
    except psycopg.OperationalError:
        pytest.skip("database not reachable")
    with connection:
        temperature = anomalous_readings(connection, sensor="VAL-T01")
        pressure = anomalous_readings(connection, sensor="SAG-P01")
        spike = anomalous_readings(connection, sensor="SAG-T01")

    first = next(row for row in temperature if row.time == datetime(2026, 9, 18, 3, 10, tzinfo=UTC))
    assert set(first.reasons) == {"out_of_range", "fixed", "jump"}
    zero = next(row for row in pressure if row.time == datetime(2026, 10, 5, 11, tzinfo=UTC))
    assert "fixed" in zero.reasons
    assert "out_of_range" not in zero.reasons
    assert spike[0].value == -40
    assert set(spike[0].reasons) == {"out_of_range", "jump"}
