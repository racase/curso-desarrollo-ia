-- Execute explicitly after approval, on the intended database. Never deletes data.
BEGIN;
LOCK TABLE readings IN SHARE ROW EXCLUSIVE MODE;
DO $$
DECLARE
    conflict record;
    existing regclass;
BEGIN
    SELECT to_regclass(format('%I.readings_sensor_time_unique_idx', n.nspname))
    INTO existing FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
    WHERE c.oid = 'readings'::regclass;
    SELECT sensor_id, time, count(*) AS copies INTO conflict
    FROM readings GROUP BY sensor_id, time HAVING count(*) > 1 LIMIT 1;
    IF FOUND THEN
        RAISE EXCEPTION 'Duplicados históricos: sensor %, instante %, copias %',
            conflict.sensor_id, conflict.time, conflict.copies;
    END IF;
    IF existing IS NOT NULL THEN
        IF NOT EXISTS (
            SELECT 1 FROM pg_index i
            WHERE i.indexrelid = existing AND i.indrelid = 'readings'::regclass
              AND i.indisunique AND i.indisvalid AND i.indisready AND i.indimmediate
              AND i.indpred IS NULL AND i.indexprs IS NULL AND i.indnkeyatts = 2
              AND (SELECT array_agg(a.attname::text ORDER BY a.attname)
                   FROM unnest(i.indkey::smallint[]) WITH ORDINALITY AS k(attnum, pos)
                   JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = k.attnum
                   WHERE k.pos <= i.indnkeyatts) = ARRAY['sensor_id', 'time']::text[]
        ) THEN
            RAISE EXCEPTION 'El índice homónimo tiene una definición incompatible';
        END IF;
    ELSE
        CREATE UNIQUE INDEX readings_sensor_time_unique_idx ON readings (sensor_id, time);
    END IF;
END $$;
COMMIT;
