# Validación de detección de huecos

Validado el 2026-11-25 con Python 3.12 y PostgreSQL/TimescaleDB local.

| Comprobación | Resultado |
|---|---|
| `uv run pytest -m "not integration"` | 62 pruebas correctas |
| `uv run pytest -m integration` | 20 pruebas correctas, ninguna omitida en la ejecución con acceso a PostgreSQL |
| `uv run ruff check .` | Correcto |
| `uv run ruff format --check .` | Incidencia preexistente en celda 11 de `notebooks/exploracion.ipynb`; no se modifica el notebook |
| `uv run ruff format --check src/infradata/analytics.py src/infradata/__main__.py tests/test_gaps.py tests/test_gaps_integration.py` | Los cuatro archivos cumplen el formato |
| `openspec validate deteccion-de-huecos --strict` | Correcto |
| `git diff --check` sobre código, pruebas, README y cambio OpenSpec | Correcto |

Se usó `UV_CACHE_DIR=/tmp/infradata-uv-cache` para mantener la caché dentro del espacio
escribible. El primer intento de integración dentro del entorno restringido omitió las
20 pruebas por no poder acceder a PostgreSQL. La ejecución con acceso autorizado a la base
las ejecutó todas correctamente.

## Trazabilidad

| Criterio | Verificación |
|---|---|
| Tres huecos exactos y exhaustividad | `test_seed_gaps_are_exactly_the_three_independent_references`, parametrizado con 5 y 15 minutos |
| Cadencia, lectura ausente, duplicados, igualdad y precisión del umbral | `test_cadence_duplicates_threshold_and_no_rounding` |
| Filtros individuales, combinados, exactos y parametrizados | `test_seed_filters_partial_dates_and_strict_threshold` |
| Rango interior sin lecturas y conservación de duración completa | `test_empty_range_inside_seed_gap_keeps_full_duration`, con sesiones UTC y Europe/Madrid |
| Extremos abiertos, sensores vacíos, cotas recortadas y orden | `test_open_edges_empty_sensor_clipping_and_order`, con ambas zonas de sesión |
| Cota igual al umbral, duración cero y contactos con límites | `test_open_cotas_equal_threshold_and_exact_boundary_contacts` y referencias sin huecos de borde |
| Una sola lectura, dos extremos abiertos y límites del curso | `test_one_reading_has_two_open_edges_and_default_bounds` |
| Salida real desde CLI | `test_cli_seed_and_open_output` |
| Validación previa a conectar, salida, errores y ayuda | `tests/test_gaps.py` |
| Regresión de resumen diario, check, datos iniciales e hypertable | Pruebas existentes conservadas y ejecutadas |

Las referencias se obtuvieron mediante SQL independiente antes de implementar la consulta
de producción; véase [referencias.md](referencias.md). Las pruebas sintéticas usan sensores
únicos, fechas dentro de los chunks existentes y una transacción externa con rollback.
No se modifica el esquema, ni los scripts iniciales, ni las lecturas permanentes.

La ejecución final de `uv run python -m infradata huecos --umbral-minutos 15` mostró
las tres referencias exactas. Después, `uv run python -m infradata check` confirmó
12 sensores, 313760 lecturas y los mismos extremos del conjunto inicial.

El cálculo completo se realiza en SQL. Las fechas filtran intervalos después de obtener
las lecturas vecinas; Python recibe únicamente los huecos finales. Se mantienen como
limitaciones documentadas la cobertura predeterminada fija del curso y que una cota
mínima que no supera el umbral no permite descartar una interrupción real más larga.
