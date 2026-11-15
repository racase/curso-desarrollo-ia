"""Tests for the daily continuous aggregate migration and freshness."""

from datetime import timedelta
from uuid import uuid4

import pytest

from infradata.db import connect
from infradata.precompute import migrate_daily_summary
from infradata.summary import daily_summaries, direct_daily_summaries


class _Connection:
    def __init__(self, autocommit=True, policy=None):
        self.autocommit = autocommit
        self.policy = policy
        self.statements = []

    def execute(self, query, params=None):
        self.statements.append((query, params))
        return self

    def fetchone(self):
        return self.policy


def test_migration_runs_all_steps_with_policy_parameters():
    conn = _Connection()
    migrate_daily_summary(conn)
    assert len(conn.statements) == 5
    assert "timescaledb.continuous" in conn.statements[0][0]
    assert "avg(value::numeric)" in conn.statements[0][0]
    assert conn.statements[2][1] == (
        "1 minute",
        "0 minutes",
        "daily_reading_summary",
        "policy_refresh_continuous_aggregate",
    )
    assert conn.statements[3][1] == ("daily_reading_summary", "0 minutes", "1 minute")
    assert conn.statements[4][1] == ("daily_reading_summary",)


def test_migration_replaces_stale_policy_but_preserves_current_one():
    stale = _Connection(policy=(False,))
    migrate_daily_summary(stale)
    assert "remove_continuous_aggregate_policy" in stale.statements[3][0]
    current = _Connection(policy=(True,))
    migrate_daily_summary(current)
    assert len(current.statements) == 4
    assert "refresh_continuous_aggregate" in current.statements[-1][0]


def test_migration_requires_autocommit():
    with pytest.raises(ValueError, match="autocommit"):
        migrate_daily_summary(_Connection(autocommit=False))


@pytest.mark.integration
def test_migration_is_idempotent_and_policy_covers_history(migrated_database):
    conn = connect()
    try:
        conn.autocommit = True
        migrate_daily_summary(conn)
        jobs = conn.execute(
            "SELECT schedule_interval, (config->>'end_offset')::interval, "
            "(config->>'start_offset') IS NULL FROM timescaledb_information.jobs "
            "WHERE hypertable_name = %s AND proc_name = %s",
            ("daily_reading_summary", "policy_refresh_continuous_aggregate"),
        ).fetchall()
    finally:
        conn.close()
    assert len(jobs) == 1
    assert jobs[0][0] == timedelta(minutes=1)
    assert jobs[0][1] == timedelta(0)
    assert jobs[0][2] is True


@pytest.mark.integration
def test_current_day_reading_is_visible_without_refresh(migrated_conn):
    class RollbackProbe(Exception):
        pass

    try:
        with migrated_conn.transaction():
            sensor_id = migrated_conn.execute(
                "INSERT INTO sensors (code, name, kind, unit, site, installed_at) "
                "VALUES (%s, %s, %s, %s, %s, current_date) RETURNING id",
                ("TEST-PRECOMP", "Sensor de prueba", "temperature", "°C", "Sede de prueba"),
            ).fetchone()[0]
            migrated_conn.execute(
                "INSERT INTO readings (time, sensor_id, value) VALUES (now(), %s, %s)",
                (sensor_id, 21.5),
            )
            current = daily_summaries(migrated_conn, sensor="TEST-PRECOMP")
            direct = direct_daily_summaries(migrated_conn, sensor="TEST-PRECOMP")
            assert current == direct
            assert len(current) == 1
            assert current[0].count == 1
            raise RollbackProbe
    except RollbackProbe:
        pass


@pytest.mark.integration
def test_historical_reading_is_refreshed_by_policy(migrated_database):
    code = "TEST-HISTORY-" + uuid4().hex[:12]
    sensor_id = None
    with connect() as conn:
        job_id = conn.execute(
            "SELECT job_id FROM timescaledb_information.jobs WHERE hypertable_name = %s",
            ("daily_reading_summary",),
        ).fetchone()[0]

    try:
        with connect() as conn:
            sensor_id = conn.execute(
                "INSERT INTO sensors (code, name, kind, unit, site, installed_at) "
                "VALUES (%s, %s, %s, %s, %s, %s) RETURNING id",
                (
                    code,
                    "Sensor histórico de prueba",
                    "temperature",
                    "°C",
                    "Sede de prueba",
                    "2026-08-01",
                ),
            ).fetchone()[0]
            conn.execute(
                "INSERT INTO readings (time, sensor_id, value) VALUES (%s, %s, %s)",
                ("2026-08-01 12:30:00+00", sensor_id, 42.5),
            )

        refresh = connect()
        try:
            refresh.autocommit = True
            refresh.execute("CALL run_job(%s)", (job_id,))
        finally:
            refresh.close()

        with connect() as conn:
            assert daily_summaries(conn, sensor=code) == direct_daily_summaries(conn, sensor=code)
            assert daily_summaries(conn, sensor=code)[0].count == 1
    finally:
        if sensor_id is not None:
            with connect() as conn:
                conn.execute("DELETE FROM readings WHERE sensor_id = %s", (sensor_id,))
                conn.execute("DELETE FROM sensors WHERE id = %s", (sensor_id,))
            refresh = connect()
            try:
                refresh.autocommit = True
                refresh.execute("CALL run_job(%s)", (job_id,))
            finally:
                refresh.close()
