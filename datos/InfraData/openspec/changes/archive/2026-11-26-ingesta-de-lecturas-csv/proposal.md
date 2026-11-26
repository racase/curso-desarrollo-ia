## Why

El issue [07](../../../../docs/issues/07-ingesta-de-lecturas-csv.md) requiere incorporar los CSV del sistema de adquisición con tiempos UTC y rechazos trazables. Actualmente la CLI solo consulta datos y `readings` permite duplicar un sensor e instante, por lo que repetir una carga no sería seguro.

## What Changes

- Añadir `python -m infradata ingest-csv FICHERO --rejects INFORME` con contrato basado en el CSV de `data/incoming/`: UTF-8, separador `;`, columnas `codigo_sensor;fecha_hora_local;valor`, fecha local `DD/MM/AAAA HH:MM` y coma decimal.
- Validar sensores conocidos, valores finitos y fechas; normalizar Europe/Madrid a UTC y rechazar horas ambiguas o inexistentes.
- Cargar parcialmente las filas válidas, conservando la primera válida por sensor e instante y las lecturas ya existentes, incluso con valores distintos.
- Emitir un informe CSV de rechazos con identificación de fila, datos originales y motivo, además de un resumen de resultados.
- Proponer unicidad de `(sensor_id, time)` para garantizar idempotencia incluso con cargas concurrentes. Su ejecución requiere aprobación previa del usuario; no eliminar duplicados históricos automáticamente.
- Validar la ingesta en una base independiente inicializada con el conjunto del curso, conservando la original.
- Fuera de alcance: nuevos sensores, corrección automática, anomalías, agregaciones, API y formatos alternativos.

## Capabilities

### New Capabilities

- `csv-reading-ingestion`: contrato CSV, validación y conversión UTC, carga parcial idempotente e informe de rechazos.

### Modified Capabilities

Ninguna. Se conservan los requisitos de `daily-sensor-summary` y `sensor-reading-gaps`.

## Impact

- Nuevo módulo `src/infradata/ingestion.py` e integración en `src/infradata/__main__.py`, reutilizando `db.connect` y `DATABASE_URL`.
- Cambio propuesto en `db/init/01_schema.sql` y script SQL explícito para instalaciones existentes; añadir unicidad solo tras aprobación y comprobación previa en SQL.
- Pruebas unitarias, CLI e integración aislada; documentación de formato, códigos de salida, informe y preparación del entorno.
- Sin nuevas dependencias Python: usar `csv`, `datetime`, `zoneinfo`, `decimal` y el `psycopg` existente. Comprobar disponibilidad de la zona Europe/Madrid y fallar claramente si falta.
