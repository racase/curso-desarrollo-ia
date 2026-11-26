## Why

El issue 01 (`docs/issues/01-resumen-diario-por-sensor.md`) requiere comparar el comportamiento diario de los sensores sin inspeccionar sus lecturas individualmente. El proyecto ya dispone del dataset y del comando `check`, pero todavía no ofrece consultas analíticas.

## What Changes

- Añadir `python -m infradata daily-summary` con mínimo, máximo, media y recuento por sensor y día UTC, identificando código, nombre, sede y unidad.
- Permitir filtros combinables por sede, código de sensor y fechas inicial y final inclusivas.
- Calcular sobre las lecturas disponibles mediante SQL, sin interpolaciones ni exclusión de anomalías; omitir días sin lecturas.
- Definir salida determinista con tres decimales y errores de uso y conexión comprobables.
- Incorporar referencias del dataset, pruebas y ejemplos de uso para cerrar los criterios del issue 01.

Fuera de alcance: detección de huecos o anomalías, exportaciones, informes, API y resúmenes precalculados.

## Capabilities

### New Capabilities

- `daily-sensor-summary`: consulta diaria agregada por sensor desde la CLI, con filtros combinables y límites UTC explícitos.

### Modified Capabilities

Ninguna; no existen especificaciones funcionales previas en `openspec/specs/`.

## Impact

- `src/infradata/__main__.py`: ampliar el despacho de comandos manteniendo `check`.
- Nuevo módulo `src/infradata/analytics.py`: consulta SQL parametrizada y resultados agregados usando `infradata.db.connect`.
- `tests/`: pruebas de CLI e integración con PostgreSQL/TimescaleDB; `README.md`: documentación del comando.
- Sin cambios de esquema, semillas ni configuración, y sin nuevas dependencias: bastan `argparse`, la biblioteca estándar y `psycopg` existentes.
