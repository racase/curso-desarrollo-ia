## 1. Casos de aceptación y esquema

- [x] 1.1 Preparar fixtures pequeños con expectativas fijas para el formato del formador, filas mixtas, duplicados, conflictos y reingesta; conservar el CSV original.
- [x] 1.2 Preparar el cambio de esquema inicial y una migración explícita para volúmenes existentes, con comprobación SQL de duplicados y bloqueo dentro de la misma transacción, sin borrar datos.
- [x] 1.3 Solicitar y obtener aprobación del usuario antes de ejecutar cambios de esquema; documentar que la aprobación de esta propuesta cubre incluir la restricción, no ejecutar ahora la migración.
- [x] 1.4 Verificar en una base aislada la restricción única, el aborto ante duplicados previos y la conservación de hypertable, índice y lecturas iniciales.

## 2. Validación y normalización

- [x] 2.1 Crear módulo reutilizable con resultados tipados, lectura CSV incremental y validación de cabecera, codificación, número de campos y sintaxis; distinguir errores de fila y fichero.
- [x] 2.2 Validar códigos, valores y precedencia de motivos; conservar campos y registro originales y no filtrar anomalías numéricas válidas.
- [x] 2.3 Implementar conversión Europe/Madrid a UTC con validación de candidatos por ida y vuelta; distinguir fecha inválida, hora ambigua e inexistente.
- [x] 2.4 Resolver primera fila válida por sensor e instante y comparar valores numéricos sin tolerancias; impedir que filas inválidas reserven claves.

## 3. Persistencia e informe

- [x] 3.1 Implementar carga parcial en una transacción mediante connect(), SQL parametrizado y ON CONFLICT, sin recuperar el histórico completo; exigir la restricción sin migrarla automáticamente.
- [x] 3.2 Clasificar duplicados y conflictos existentes, incluidas carreras concurrentes, sin sobrescribir lecturas; revertir ante fallos operativos previos al commit.
- [x] 3.3 Escribir el CSV de rechazos con cabecera, línea física inicial, originales y motivos estables; proteger origen y destinos existentes, incluidos enlaces.
- [x] 3.4 Preparar el informe temporal antes del commit y publicarlo sin sobrescritura después; conservarlo e indicar su ruta si falla la publicación, distinguiendo carga confirmada y resultado desconocido.

## 4. CLI y pruebas

- [x] 4.1 Añadir ingestar-csv con RUTA y --informe obligatorio, resumen y ruta del informe; gestionar códigos 0, 1 y 2 y mensajes en castellano sin credenciales ni traceback.
- [x] 4.2 Cubrir con pruebas unitarias formato, espacios, LF/CRLF, campos adicionales o ausentes, líneas vacías, fichero con solo cabecera, sintaxis ilegible tardía y precedencia de errores.
- [x] 4.3 Cubrir invierno y verano, fecha inválida, horas 25/10/2026 02:30 y 29/03/2026 02:30, zonas de proceso distintas y datos IANA ausentes.
- [x] 4.4 Cubrir igualdad numérica con distinto texto, primera fila inválida y diferencias de valor con la misma clave; verificar originales, numeración y motivos del informe.
- [x] 4.5 Usar monkeypatch y capsys para argumentos, salida, errores de rutas, protección mediante enlaces, fallos de publicación y commit desconocido; comprobar regresión de comandos existentes.
- [x] 4.6 Añadir pruebas integration de carga mixta, valores originales, sensores desconocidos, duplicados internos y existentes, conflictos, reingesta y rollback ante fallo SQL; usar datos aislados sin alterar el conjunto permanente.
- [x] 4.7 Verificar con integration dos conexiones concurrentes, igualdad y conflicto de valores, sesiones UTC y Europe/Madrid y ausencia de restricción; conservar pruebas iniciales e hypertables.

## 5. Documentación y validación

- [x] 5.1 Documentar en README.md contrato CSV, ejemplo de comando, carga parcial, reglas de duplicidad, fechas problemáticas, informe, códigos de salida y requisito IANA del entorno.
- [x] 5.2 Documentar aplicación y reversión de la migración, aprobación previa, bloqueo de escrituras, diagnóstico de duplicados y recuperación del informe tras un fallo posterior al commit.
- [x] 5.3 Ejecutar uv run pytest -m "not integration", uv run ruff check . y uv run ruff format --check .; ejecutar pruebas integration con una base aislada disponible y registrar resultados u omisiones.
- [x] 5.4 Ejecutar openspec validate ingesta-de-lecturas-csv --strict y comprobar que todos los escenarios tienen verificación correspondiente antes de archivar.
