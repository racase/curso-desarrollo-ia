## Why

El equipo de datos necesita comparar el comportamiento diario de los sensores y los responsables
de sede necesitan acotar la consulta a sus equipos. InfraData ya dispone de las lecturas y del
comando `check`, pero todavía no ofrece esta consulta analítica.

Esta propuesta implementa el [issue 01](../../../../docs/issues/01-resumen-diario-por-sensor.md),
sin dependencias de otros issues.

## What Changes

- Añadir `python -m infradata resumen-diario`, con filtros opcionales y combinables
  `--sede`, `--sensor`, `--desde` y `--hasta`.
- Mostrar sede, código de sensor, día UTC, unidad, mínimo, máximo, media, recuento
  y una columna `Esperadas` con valor fijo 288 para comparar las lecturas recibidas
  con las esperadas e identificar visualmente los días incompletos.
- Definir fechas inclusivas, días sin lecturas omitidos y presentación con dos decimales.
- Calcular las estadísticas en la base de datos sobre las lecturas disponibles,
  conservando huecos y anomalías.
- Añadir pruebas de los cálculos, límites UTC, filtros, salida y errores, y documentar el comando.

Quedan fuera la detección de huecos o anomalías, exportaciones, API y resúmenes precalculados.

## Capabilities

### New Capabilities

- `resumen-diario`: consulta diaria por sensor desde CLI, agregación SQL, filtros, fechas UTC,
  formato de presentación y comportamiento ante ausencia de datos o errores.

### Modified Capabilities

Ninguna. No existen especificaciones previas en este proyecto; `check` conserva su comportamiento.

## Impact

- Extensión de `src/infradata/__main__.py` y nuevo módulo de consulta reutilizable
  `src/infradata/analytics.py`, apoyado en `db.connect()`.
- Nuevas pruebas unitarias y de integración en `tests/` y ejemplos en `README.md`.
- Lectura de `sensors` y `readings` existentes, sin cambios de esquema ni dependencias nuevas.
- Inicialización de OpenSpec local con el flujo `spec-driven`.
