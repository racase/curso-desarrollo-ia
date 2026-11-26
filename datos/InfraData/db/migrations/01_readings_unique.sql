-- Aplicar explícitamente a volúmenes existentes, previa aprobación del usuario.
-- No elimina ni fusiona lecturas. El bloqueo impide escrituras durante la migración.
BEGIN;
LOCK TABLE readings IN SHARE ROW EXCLUSIVE MODE;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM readings GROUP BY sensor_id, time HAVING count(*) > 1
    ) THEN
        RAISE EXCEPTION 'Hay lecturas duplicadas por sensor e instante; migración cancelada. '
            'Revisar con GROUP BY sensor_id, time HAVING count(*) > 1; no se han borrado datos.';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'readings'::regclass AND conname = 'readings_sensor_time_unique'
    ) THEN
        ALTER TABLE readings ADD CONSTRAINT readings_sensor_time_unique UNIQUE (sensor_id, time);
    ELSIF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'readings'::regclass AND conname = 'readings_sensor_time_unique'
          AND contype = 'u' AND convalidated AND NOT condeferrable
          AND conkey = ARRAY[
            (SELECT attnum FROM pg_attribute
             WHERE attrelid = 'readings'::regclass AND attname = 'sensor_id'),
            (SELECT attnum FROM pg_attribute
             WHERE attrelid = 'readings'::regclass AND attname = 'time')
          ]::smallint[]
    ) THEN
        RAISE EXCEPTION 'readings_sensor_time_unique tiene una definición incompatible; '
            'revisar el esquema antes de continuar.';
    END IF;
END $$;

COMMIT;
