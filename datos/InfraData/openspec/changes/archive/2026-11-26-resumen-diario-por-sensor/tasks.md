## 1. Consulta SQL de resumen diario

- [x] 1.1 Crear `src/infradata/analytics.py` con un resultado tipado para día, identificación del sensor, sede, unidad y cuatro agregados, y una función con filtros opcionales que reutilice `db.connect` como context manager.
- [x] 1.2 Implementar filtros SQL parametrizados por sede, código y límites temporales UTC, agrupación por sensor y día UTC con `MIN`, `MAX`, `AVG`, `COUNT(*)` y orden por día y código. Devolver exclusivamente agregados, omitir días vacíos e incluir valores anómalos.

## 2. Comando y presentación

- [x] 2.1 Ampliar `src/infradata/__main__.py` con `argparse`, `daily-summary`, `--site`, `--sensor`, `--start-date` y `--end-date`, conservando el contrato de retorno de `main(argv)`, el uso con `Uso:` y el comportamiento de `check`.
- [x] 2.2 Validar formato ISO estricto y orden de fechas antes de conectar; convertir extremos inclusivos a medianoches UTC y fin exclusivo, rechazando límites no representables con código 2 y diagnóstico por stderr.
- [x] 2.3 Mostrar cabecera y filas tabuladas según la especificación, tres decimales y recuento entero; gestionar ausencia de filas con mensaje y código 0 y errores `psycopg` con stderr y código 1, sin salida parcial de éxito.

## 3. Pruebas del contrato y del dataset

- [x] 3.1 Ampliar pruebas unitarias de CLI para despacho, ayuda, propagación de filtros, formato y orden de salida, resultados vacíos, errores de conexión y validación sin abrir conexión (fecha inválida, formato distinto de `YYYY-MM-DD`, rango invertido, desbordamiento y opciones desconocidas); comprobar regresión de `check`.
- [x] 3.2 Añadir pruebas marcadas `integration` con valores de referencia fijos de `VAL-T01` (2026-08-01), `SAG-P02` (2026-09-03) y `PAT-L01` (2026-10-12 a 2026-10-14), según la tabla de `design.md`, usando tolerancia absoluta `1e-9` para medias y verificando la unidad.
- [x] 3.3 Verificar integración de filtros combinados coincidentes e incompatibles, cada filtro por separado, un único extremo temporal, consultas sin filtros y sensores o sedes desconocidos; comprobar que la anomalía `96.4` de `VAL-T01` el 2026-09-18 no se descarta.
- [x] 3.4 Añadir prueba de frontera UTC con lecturas controladas y zona de sesión `Europe/Madrid`, incluyendo medianoche inicial y excluyendo medianoche posterior al día final. Ejecutar consulta y preparación en la misma conexión mediante sustitución de la fábrica de conexión en la prueba; revertir toda inserción al terminar, sin DDL ni cambios persistentes.

## 4. Documentación y validación

- [x] 4.1 Documentar en `README.md` ejemplos de `daily-summary`, filtros combinados, límites inclusivos UTC, precisión, días ausentes, valores anómalos y referencias del dataset; actualizar la descripción inicial para reflejar la nueva analítica.
- [x] 4.2 Arrancar o verificar la base con `docker compose up -d` y ejecutar `uv run pytest`, `uv run ruff check .` y `uv run ruff format --check .`; resolver fallos y registrar cualquier integración omitida.
- [x] 4.3 Ejecutar manualmente el ejemplo de `SAG-P02` del 2026-09-03, el periodo vacío de `PAT-L01` y `python -m infradata check`; contrastar salida y códigos con los escenarios de la especificación.

## Validación realizada (2026-11-26)

- `docker compose up -d`: PostgreSQL disponible, sin eliminar volúmenes.
- `uv run pytest`: 36 pruebas correctas, ninguna omitida; incluye integración y frontera UTC con rollback.
- `uv run ruff check .` y `uv run ruff format --check .`: correctos. El formateador también normalizó una expresión del notebook existente, sin cambio funcional.
- CLI `SAG-P02`, 2026-09-03: mínimo 6.110, máximo 7.270, media 6.667 y 210 lecturas; salida 0.
- CLI `PAT-L01`, 2026-10-12 a 2026-10-13: mensaje sin lecturas; salida 0.
- CLI `check`: 12 sensores y 313760 lecturas, del 2026-08-01 al 2026-10-30 UTC; salida 0.
- `openspec validate resumen-diario-por-sensor --strict`: correcto.
