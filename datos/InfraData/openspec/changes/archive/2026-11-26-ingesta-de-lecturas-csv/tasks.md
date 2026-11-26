## 1. Contrato, validación y tiempos UTC

- [x] 1.1 Crear `src/infradata/ingestion.py` con tipos para filas aceptadas, rechazos y resultado, y lector CSV conforme al contrato de `specs/csv-reading-ingestion/spec.md`, conservando registro original y línea física inicial.
- [x] 1.2 Implementar cabecera exacta, UTF-8/BOM, separador, número de campos, espacios exteriores y distinción entre errores de fila y errores globales de sintaxis/codificación.
- [x] 1.3 Implementar validación de códigos existentes y valores con coma decimal, finitud y rango del tipo persistido, sin nuevas dependencias ni filtros de anomalías.
- [x] 1.4 Implementar conversión Europe/Madrid mediante candidatos fold y recorrido local→UTC→local, rechazando fechas inválidas, horas ambiguas e inexistentes; detectar zona ausente antes de escribir.
- [x] 1.5 Añadir pruebas unitarias del contrato, valores, precedencia de motivos, registros vacíos/multilínea y conversiones de invierno, verano, frontera UTC y cambios horarios del 29/03 y 25/10 de 2026.

## 2. SQL propuesto y preparación del entorno aislado

- [x] 2.1 Preparar el cambio de `db/init/01_schema.sql` con índice único `(sensor_id, time)` y un script en `db/migrations/` para instalaciones existentes; comprobar duplicados en SQL dentro de una transacción protegida frente a escrituras, detenerse sin borrar datos y verificar cualquier índice homónimo. No ejecutar DDL todavía.
- [x] 2.2 Preparar configuración y fixtures para una base de pruebas independiente en el servidor Docker local existente con el seed del curso y `INGESTION_TEST_DATABASE_URL` obligatoria, omitida si falta y bloqueada si identifica el destino original, aunque cambien credenciales o alias locales.
- [x] 2.3 Presentar el SQL concreto y el destino de pruebas y obtener aprobación explícita antes de cualquier DDL, incluida inicialización del entorno independiente. Si no se ha autorizado, continuar solo con trabajo sin DDL y registrar la integración pendiente.
- [x] 2.4 Tras la aprobación, crear exclusivamente la base independiente en el servidor Docker existente, aplicar el esquema y seed, y comprobar en SQL unicidad, doce sensores, recuento y periodo iniciales; verificar también la migración desde el esquema anterior y su parada sin modificaciones ante duplicados históricos en una fixture aislada.

## 3. Persistencia e informe

- [x] 3.1 Reutilizar `db.connect` y `DATABASE_URL`, comprobar la garantía de unicidad sin ejecutar DDL y resolver códigos a identificadores sin materializar el historial de lecturas en Python.
- [x] 3.2 Implementar la primera fila válida por clave, la precedencia de duplicado interno y la inserción parametrizada `ON CONFLICT DO NOTHING RETURNING` dentro de una única transacción, sin actualizar valores existentes.
- [x] 3.3 Implementar creación exclusiva del informe previo a inserciones y sus columnas, motivos estables, orden y detalle; escribir y vaciar el informe antes del commit y conservar los campos originales.
- [x] 3.4 Gestionar errores SQL, de lectura/escritura y de contrato global con rollback, sin resumen de éxito; distinguir el resultado desconocido si se pierde conexión durante commit y diagnosticar informes incompletos.
- [x] 3.5 Añadir pruebas unitarias de primera fila inválida seguida de válida, protección de origen/destino, fallos de informe y contrato, rollback y diagnóstico de commit desconocido.

## 4. CLI y documentación

- [x] 4.1 Añadir `ingest-csv FICHERO --rejects INFORME` a `__main__.py`, ayuda, resumen de procesadas/insertadas/rechazadas y códigos 0/3/2/1, conservando los comandos existentes.
- [x] 4.2 Añadir pruebas CLI para argumentos, contrato inválido, carga limpia/mixta, solo cabecera, informe existente, errores operativos y ayuda; comprobar que los fallos de argumentos no abren conexión.
- [x] 4.3 Documentar en `README.md` el contrato, carga parcial, identidad y precedencia de duplicados, cambios de hora, informe, códigos de salida, requisito de zona y ejecución sobre base aislada. Explicar aprobación de esquema, migración explícita de volúmenes existentes y rollback sin borrar lecturas.

## 5. Integración y validación final

- [x] 5.1 Añadir pruebas marcadas `integration` sobre la base independiente para conservación de sensor/valor, conversión UTC con zona de sesión distinta, carga mixta, claves existentes con valores distintos y repetición sin nuevas inserciones.
- [x] 5.2 Probar dos conexiones concurrentes sobre una misma clave, comprobando una sola inserción y rechazo de la otra; comprobar rollback real tras un fallo anterior al commit y error sin carga si falta unicidad.
- [x] 5.3 Cargar el CSV de `data/incoming/` solo en la copia del curso; verificar en SQL los datos incorporados y su repetición, y comprobar los motivos de las líneas 4, 6, 7 y 8 en los informes. No modificar el CSV original.
- [x] 5.4 Ejecutar `uv run pytest -m "not integration"`, `uv run ruff check .` y `uv run ruff format --check .`. Ejecutar las pruebas de ingesta con destino aislado explícito y las regresiones de integración existentes apuntando también a una copia con el seed original, antes de la ingesta o tras restaurar exclusivamente esa copia.
- [x] 5.5 Verificar que las comprobaciones no modificaron las lecturas de la base original ni eliminaron su volumen (la copia autorizada comparte servidor Docker) y registrar resultados, pruebas omitidas y cualquier aprobación o migración pendiente.


## Evidencia de validación (2026-11-26)

- El usuario autorizó crear `infradata_ingestion_test` en el mismo contenedor `infradata-db`, puerto 5433. Se reutilizó el servidor existente, sin otro contenedor ni volumen.
- La copia se inicializó con el esquema y seed del curso: 12 sensores, 313760 lecturas, del 2026-08-01 00:00 UTC al 2026-10-30 23:55 UTC.
- Unitarias: `uv run pytest -m "not integration" -q`: 80 superadas.
- Ingesta: `tests/test_ingestion_integration.py`: 5 superadas con URL de pruebas explícita y aprobación de DDL. Incluye carga parcial, UTC, repetición, concurrencia, rollback, CSV completo y migración. La prueba de migración se reforzó con una hypertable real y volvió a pasar.
- Regresiones: `test_db.py`, `test_daily_summary_integration.py` y `test_gaps_integration.py` con `DATABASE_URL` de la copia: 40 superadas.
- Ruff lint y formato: correctos. OpenSpec: validación estricta correcta.
- `infradata` ya tenía `readings_sensor_time_unique`, compatible con la ingesta; no se aplicó DDL en esa base. Una ejecución CLI con solo cabecera confirmó compatibilidad sin insertar lecturas.
- Tras las pruebas, el recuento, mínimo, máximo y huella SQL de las lecturas originales coincidieron con la comprobación previa. El CSV original no se modificó ni se cargó en la base del curso. Las cargas completas de comprobación se revirtieron en la copia.
- No quedan pruebas omitidas ni aprobaciones pendientes para esta implementación; la migración de otras instalaciones requiere aprobación de su destino. La copia de pruebas queda disponible en el mismo servidor.
