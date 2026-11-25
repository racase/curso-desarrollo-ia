## 1. Referencias de aceptación

- [x] 1.1 En una base con el conjunto inicial del curso, seleccionar un sensor/día completo y otro incompleto; registrar consultas SQL independientes y valores fijos de mínimo, máximo, media, recuento y unidad en las pruebas de referencia, antes de implementar el cálculo.
- [x] 1.2 Preparar casos sintéticos para medianoche UTC, extremos inclusivos, días vacíos y media con valores conocidos, con datos aislados y rollback.

## 2. Consulta reutilizable

- [x] 2.1 Crear `analytics.py`, el resultado tipado `DailySummary` y `daily_summary` con filtros opcionales y validación de fechas.
- [x] 2.2 Implementar con `connect()` la agregación SQL por día UTC, filtros parametrizados y límites temporales semiabiertos; devolver solo resultados agregados, ordenados y sin redondear.

## 3. Interfaz de línea de comandos

- [x] 3.1 Añadir `resumen-diario` con filtros `--sede`, `--sensor`, `--desde` y `--hasta`, conservando el comportamiento de `check` y los códigos de salida existentes.
- [x] 3.2 Implementar cabecera, filas y dos decimales según la especificación, con la columna `Esperadas` inmediatamente después de `Lecturas` y valor entero fijo 288; añadir mensaje de consulta vacía y errores en castellano, validando argumentos antes de conectar.

## 4. Pruebas

- [x] 4.1 Añadir pruebas unitarias con `monkeypatch` y `capsys` para argumentos, salida, orden, precisión, consulta vacía, errores de base de datos y regresión de `check`; comprobar posición y valor 288 de `Esperadas` en días completos e incompletos, independencia de la zona local y media calculada sobre las lecturas reales.
- [x] 4.2 Añadir pruebas `integration` contra las referencias fijas y casos sintéticos; cubrir filtros individuales y combinados, días incompletos y vacíos, rangos abiertos e inclusivos y valores anómalos conservados.
- [x] 4.3 Verificar agrupación y límites con sesiones UTC y Europe/Madrid, usando datos reversibles y conservando los tests de datos iniciales e hypertables.

## 5. Documentación y validación

- [x] 5.1 Documentar en `README.md` ejemplos del comando, filtros, días UTC, extremos inclusivos, ausencia de datos, precisión y comparación de `Lecturas` con la columna `Esperadas` de valor fijo 288 para reconocer días incompletos.
- [x] 5.2 Ejecutar `uv run pytest -m "not integration"`, `uv run ruff check .` y `uv run ruff format --check .`; con una base disponible ejecutar `uv run pytest -m integration` y registrar resultados u omisiones.
- [x] 5.3 Ejecutar `openspec validate resumen-diario-por-sensor --strict` y comprobar la trazabilidad de los cuatro criterios del issue 01 con los escenarios y pruebas.
