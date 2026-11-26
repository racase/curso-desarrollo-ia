# Validación de la ingesta CSV

Fecha: 2026-11-26.

## Autorización y migración

Tras preparar la migración, el usuario autorizó expresamente «pruebas aisladas y
migración local», cumpliendo la aprobación previa de `AGENTS.md`. Se probó primero
en una base efímera con TimescaleDB y después se aplicó
`db/migrations/01_readings_unique.sql` a la base local.

Se verificó la existencia de `readings_sensor_time_unique`, la conservación de la
hypertable, los 12 sensores y las 313760 lecturas locales. Antes y después de la
migración coincidieron el recuento, mínimo y máximo de `time` y `sum(value::numeric)`.
No se importó el CSV del formador a la base local ni se corrigieron lecturas.

La comprobación de preservación usa suma decimal exacta: una suma `double precision`
puede variar en los últimos decimales al cambiar el orden del recorrido, sin que
cambien las lecturas.

## Resultados

- `.venv/bin/pytest -q`: **136 pruebas superadas**, incluidas todas las de integración.
- `.venv/bin/pytest -q tests/test_ingestion_integration.py`: **8 superadas** en una
  base efímera, antes de aplicar la migración local.
- `.venv/bin/ruff check .`: correcto.
- `.venv/bin/ruff format --check` sobre los cinco archivos Python modificados: correcto.
- `.venv/bin/ruff format --check .`: detecta un problema previo de formato en la celda 11
  de `notebooks/exploracion.ipynb`; se conserva sin cambios por ser ajeno a esta implementación.
- `openspec validate ingesta-de-lecturas-csv --strict`: correcto.
- `git diff --check`: sin errores de espacios; Git avisa de normalización CRLF/LF
  del notebook existente, sin modificarlo.

Se usaron los ejecutables del entorno sincronizado con `uv sync`; no se añadieron
dependencias al proyecto.

## Cobertura de aceptación

- `tests/test_ingestion.py`: formato fijo, LF/CRLF, campos ausentes y adicionales,
  originales y líneas físicas, registros multilínea y vacíos, cabecera y UTF-8 inválidos,
  errores tardíos, precedencia de motivos y primera fila válida.
- El mismo archivo verifica valores, invierno/verano y cambios de hora, independencia
  de la zona del proceso, reingesta y conflictos, protección de rutas y enlaces,
  fallos antes y durante el commit, recuperación del temporal, mensajes y códigos de CLI,
  datos IANA ausentes y ocultación de detalles de conexión.
- `tests/test_ingestion_integration.py`: carga parcial, instantes y valores almacenados,
  reingesta, conflictos existentes, rollback real, dos conexiones concurrentes tanto con
  valores iguales como distintos y sesiones PostgreSQL en UTC y Europe/Madrid.
- Ese archivo verifica esquema inicial con hypertable, ausencia de migración automática,
  aborto ante duplicados sin perder datos, aplicación y repetición de la migración,
  conservación del índice, la hypertable y el conjunto inicial del curso.
- Se mantienen las pruebas de datos iniciales, resumen diario y huecos. La prueba de
  cadencia de huecos ahora comprueba el rechazo del duplicado con un savepoint y conserva
  sus expectativas de duración, comparación estricta y precisión de microsegundos.

Las bases `infradata_test_ingestion_<uuid>` se crean para las pruebas y se eliminan al
terminar. Los datos sintéticos de las pruebas existentes se revierten mediante rollback.
El cambio OpenSpec queda implementado y validado, sin archivarlo automáticamente.
