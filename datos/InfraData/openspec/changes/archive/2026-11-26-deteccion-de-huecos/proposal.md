## Why

InfraData todavía no permite consultar las interrupciones de envío descritas en issue02. Mantenimiento necesita identificar todos los periodos sin lecturas de cada sensor con una duración inequívoca y límites en UTC.

## What Changes

- Añadir `python -m infradata gaps`, con filtros combinables por sede, sensor y fechas UTC.
- Medir cada hueco desde la primera lectura esperada ausente hasta la siguiente lectura: descontar la cadencia normal de 5 minutos del intervalo entre envíos.
- Aplicar un umbral configurable, por defecto 15 minutos, con comparación estricta sobre la duración dentro del rango consultado.
- Incluir huecos parciales y abiertos, distinguirlos mediante indicadores y consultar lecturas vecinas fuera del rango para reconocer sus extremos.
- Acotar la consulta a una ventana común de observación para no inventar interrupciones fuera del historial disponible.
- Completar los criterios de issue02 con las referencias del seed: VAL-F01, 50 minutos; SAG-P02, 390 minutos; PAT-L01, 3240 minutos.

## Capabilities

### New Capabilities

- `sensor-reading-gaps`: detección exhaustiva en SQL, duración, rango de observación, extremos parciales o abiertos, filtros y salida de CLI.

### Modified Capabilities

Ninguna. `daily-sensor-summary` conserva sus requisitos.

## Impact

- Implementación futura en `src/infradata/analytics.py` y `src/infradata/__main__.py`, con pruebas en `tests/`.
- Documentación futura en `README.md` y `docs/issues/02-deteccion-de-huecos.md`.
- Reutilizar PostgreSQL, psycopg y argparse; sin dependencias nuevas, migraciones ni cambios de esquema o seed.
- Fuera de alcance: interpolación, corrección de datos, causas de interrupción, alertas, informes y cadencias variables.
