# Validación de la implementación

- `uv run pytest -m "not integration"`: 29 pruebas correctas.
- `uv run pytest -m integration`: 8 pruebas correctas en la primera ejecución.
- `uv run pytest`: ejecución final con 38 pruebas correctas, incluidas las 9 de integración
  tras añadir la prueba de extremo a extremo de la CLI.
- `uv run ruff check .`: correcto.
- `uv run ruff format --check src tests`: correcto.
- `openspec validate resumen-diario-por-sensor --strict`: correcto.

`uv run ruff format --check .` detecta una celda preexistente sin formatear en
`notebooks/exploracion.ipynb` (celda 11). El notebook no se ha modificado como parte de este
cambio; el formato del código y de las pruebas sí pasa.

Las consultas y las pruebas con PostgreSQL se ejecutaron con acceso fuera del entorno
aislado. Los datos sintéticos se insertan en transacciones que se revierten al terminar;
se mantienen las comprobaciones de los sensores iniciales, periodo de lecturas e hypertable.
No se han modificado el esquema ni las dependencias.

## Trazabilidad de aceptación

| Criterio del issue 01 | Pruebas |
|---|---|
| 1. Estadísticas de referencia y unidad | `test_seed_reference_statistics`, referencias en `referencias.md` |
| 2. Asignación por día UTC | `test_utc_grouping_inclusive_bounds_and_anomalies`, sesiones UTC y Europe/Madrid |
| 3. Filtros combinados | `test_utc_grouping_inclusive_bounds_and_anomalies`, `test_optional_filters_order_and_empty_days` |
| 4. Recuento real y media de días incompletos | `test_seed_reference_statistics`, `test_cli_shows_real_count_and_expected_count` |
| Ampliación: columna Esperadas = 288 | `test_summary_output_expected_counts_and_precision`, `test_cli_shows_real_count_and_expected_count` |

## Desviación del orden previsto

Las referencias fijas se obtuvieron con SQL independiente antes de ejecutar las pruebas,
pero el módulo se había escrito mientras se habilitaba el acceso a la base. No se han
generado expectativas a partir del resultado de la función probada.
