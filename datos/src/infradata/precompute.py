"""Install and refresh the daily continuous aggregate."""

import psycopg

CREATE_VIEW = """
CREATE MATERIALIZED VIEW IF NOT EXISTS daily_reading_summary
WITH (timescaledb.continuous, timescaledb.materialized_only = false) AS
SELECT time_bucket('1 day', time) AS day,
       sensor_id,
       min(value) AS minimum,
       max(value) AS maximum,
       avg(value::numeric) AS average,
       count(*) AS reading_count
FROM readings
GROUP BY day, sensor_id
WITH NO DATA
"""

CREATE_INDEX = """
CREATE INDEX IF NOT EXISTS daily_reading_summary_sensor_day_idx
ON daily_reading_summary (sensor_id, day DESC)
"""

CHECK_POLICY = """
SELECT schedule_interval = %s::interval
       AND config->'start_offset' = 'null'::jsonb
       AND (config->>'end_offset')::interval = %s::interval
FROM timescaledb_information.jobs
WHERE hypertable_name = %s AND proc_name = %s
"""

REMOVE_POLICY = """
SELECT remove_continuous_aggregate_policy(%s::regclass, if_exists => TRUE)
"""

ADD_POLICY = """
SELECT add_continuous_aggregate_policy(
    %s::regclass,
    start_offset => NULL,
    end_offset => %s::interval,
    schedule_interval => %s::interval,
    if_not_exists => TRUE
)
"""

REFRESH_CLOSED_DAYS = """
CALL refresh_continuous_aggregate(
    %s::regclass,
    NULL::timestamptz,
    date_trunc('day', now() AT TIME ZONE 'UTC') AT TIME ZONE 'UTC'
)
"""


def migrate_daily_summary(conn: psycopg.Connection) -> None:
    """Install and populate the continuous aggregate on an autocommit connection."""
    if not conn.autocommit:
        raise ValueError("La migración requiere una conexión en autocommit")
    conn.execute(CREATE_VIEW)
    conn.execute(CREATE_INDEX)
    policy = conn.execute(
        CHECK_POLICY,
        ("1 minute", "0 minutes", "daily_reading_summary", "policy_refresh_continuous_aggregate"),
    ).fetchone()
    if policy is None or not policy[0]:
        if policy is not None:
            conn.execute(REMOVE_POLICY, ("daily_reading_summary",))
        conn.execute(ADD_POLICY, ("daily_reading_summary", "0 minutes", "1 minute"))
    conn.execute(REFRESH_CLOSED_DAYS, ("daily_reading_summary",))
