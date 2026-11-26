## Context

El issue 07 y las respuestas del usuario fijan carga parcial, identidad por sensor e instante UTC, conservación de la lectura existente y de la primera válida dentro del fichero, rechazo de fechas inválidas y horas locales ambiguas o inexistentes, y pruebas en una base independiente. El CSV de referencia define el contrato de entrada. Se ha aceptado incluir el cambio de esquema en el plan, pendiente de aprobación antes de ejecutarlo.

La CLI actual usa `argparse` y ofrece `check`, `daily-summary` y `gaps`. `db.connect` gestiona conexiones `psycopg` mediante contexto y respeta `DATABASE_URL`. `readings` es una hypertable con columnas `time`, `sensor_id` y `value`, sin clave única; el índice actual es de búsqueda. Los SQL de `db/init` solo se ejecutan al inicializar el volumen. Las pruebas existentes de base usan por defecto el conjunto original, por lo que las nuevas pruebas de ingesta necesitan una conexión independiente explícita.

## Goals / Non-Goals

**Goals:** incorporar CSV compatibles de forma idempotente, conservar valores y sensores, normalizar UTC correctamente, informar todos los rechazos de fila y proteger el conjunto original en las comprobaciones.

**Non-Goals:** crear sensores, corregir valores, interpretar otros dialectos CSV, analizar anomalías, agregar lecturas o implementar recepción continua. No se aplicará DDL ni se cargará el fichero real durante esta propuesta.

## Decisions

### Interfaz y contrato explícitos

Añadir `ingest-csv FICHERO --rejects INFORME` y un módulo `ingestion.py` separado de `analytics.py`. La entrada usa UTF-8 con BOM opcional, `;`, comillas CSV estándar, LF o CRLF y cabecera exacta ordenada `codigo_sensor;fecha_hora_local;valor`. Cada registro contiene tres campos; registros vacíos o con número de campos distinto se rechazan individualmente si el parser puede continuar. Un error de sintaxis CSV irrecuperable o de codificación invalida toda la operación. Se aceptan campos entrecomillados y se recortan espacios exteriores de los tres campos para validar, conservando los originales en el informe.

El código coincide exactamente, con distinción de mayúsculas, con `sensors.code`. La fecha tiene forma estricta `DD/MM/AAAA HH:MM`, sin segundos ni offset. El valor sigue `[+-]?[0-9]+(,[0-9]+)?`, sin miles, punto decimal ni notación científica; se convierte con `Decimal` y se valida su representación finita como `double precision`. No se imponen límites físicos de anomalías. Esta gramática concreta es una decisión de diseño basada en la muestra; evita inferencias de formato distintas entre ejecuciones. La alternativa de autodetectar delimitadores y fechas dificultaría reproducir los rechazos.

### Conversión temporal por candidatos UTC

Usar `ZoneInfo('Europe/Madrid')` y construir candidatos con `fold=0` y `fold=1`. Convertir cada candidato a UTC y de vuelta a la zona local; conservar los que reproducen la fecha y hora original y deduplicarlos por instante UTC. Cero candidatos implica hora inexistente; dos distintos, ambigua; uno, conversión válida. Validar previamente la fecha y su formato exacto. Rechazar los dos primeros casos en lugar de elegir un offset. La zona de sesión PostgreSQL no condiciona los instantes enviados con zona.

No se añaden dependencias: se reutilizan la biblioteca estándar y `psycopg`. Comprobar la disponibilidad de Europe/Madrid antes de escribir datos y mostrar un error operativo si faltan datos de zona; no instalar paquetes automáticamente.

### Validación y carga parcial en una transacción

Leer el CSV secuencialmente y consultar los sensores conocidos; no cargar el historial de lecturas en Python. Mantener las claves válidas ya vistas en el fichero o usar staging si fuera necesario por tamaño; para el volumen del curso basta un conjunto de claves. Las filas inválidas no reservan una clave. La primera fila válida de una clave es la candidata, aunque choque con la base; las posteriores se rechazan como duplicadas del fichero. La prioridad de rechazo será estructura, sensor, fecha/zona, valor y duplicado, con un único motivo por fila.

Insertar consultas parametrizadas con `ON CONFLICT (sensor_id, time) DO NOTHING RETURNING ...`: la ausencia de retorno identifica un duplicado ya persistido, también si otra carga gana una carrera. No actualizar valores existentes. Una única transacción confirma todas las filas aceptadas después de completar la lectura y escribir y vaciar el informe. Un fallo SQL, de lectura o de escritura revierte todas las inserciones de esa ejecución; la carga parcial solo corresponde a errores de validación de filas. No efectuar agregaciones de lecturas en Python; cualquier comprobación de historial o totales persistidos usa SQL.

Se descartan la consulta previa como única defensa frente a duplicados (vulnerable a carreras) y el upsert que reemplaza valores (contradice la política acordada).

### Informe y resultado operativo

Crear el destino de informe en modo exclusivo antes de modificar datos; rechazar un destino existente, que coincida con la entrada o cuyo padre no exista/no sea escribible. No sobrescribir la fuente. El informe usa UTF-8, `;` y columnas `fila;codigo_sensor;fecha_hora_local;valor;registro_original;motivo;detalle`. `fila` es la línea física inicial del registro, cabecera en línea 1; `registro_original` conserva el registro CSV original, también cuando no tiene tres campos. Los campos disponibles se conservan sin recortar. El escritor CSV escapa comillas, separadores y saltos de línea.

Motivos estables: `estructura_invalida`, `sensor_desconocido`, `fecha_invalida`, `hora_ambigua`, `hora_inexistente`, `valor_invalido`, `duplicado_fichero`, `duplicado_base`. `detalle` explica el rechazo en español y, para duplicados del fichero, identifica la primera fila válida. Un fichero sin rechazos produce solo cabecera. El resumen de éxito imprime filas procesadas, insertadas y rechazadas; procesadas = insertadas + rechazadas.

Salida 0: operación confirmada sin rechazos; 3: operación confirmada con rechazos (incluida una repetición completa); 2: argumentos o contrato global inválidos; 1: error operativo. En fallos no se imprime resumen de carga confirmada; el diagnóstico indica rollback y que cualquier informe generado está incompleto/no acredita una carga. Los fallos al confirmar una transacción por pérdida de conexión pueden dejar el resultado desconocido; indicarlo expresamente y permitir reintento idempotente con otro informe, sin afirmar que hubo rollback confirmado. No existe atomicidad distribuida entre PostgreSQL y el sistema de ficheros; el código de salida y el diagnóstico forman parte del resultado.

### Unicidad y pruebas aisladas

Proponer un índice único explícito `readings_sensor_time_unique_idx` en `(sensor_id, time)`, incluyendo la columna temporal de la hypertable. Añadirlo al esquema inicial y preparar un script de migración separado para instalaciones existentes. El proceso de ingesta comprueba que existe la garantía de unicidad y falla claramente si falta; nunca ejecuta DDL automáticamente.

Antes de migrar, comprobar duplicados con `GROUP BY sensor_id, time HAVING count(*) > 1` en SQL. Si aparecen, detener la migración y presentar el conflicto, sin borrar ni elegir lecturas históricas. El script comprueba la situación dentro de la misma transacción protegida frente a escrituras concurrentes. No reutilizar un índice homónimo sin verificar su definición. La necesidad de aprobación deriva de `AGENTS.md` y se aplica también al DDL del entorno de pruebas.

Las pruebas de integración de ingesta requieren `INGESTION_TEST_DATABASE_URL` explícita, distinta de la base original por destino real (servidor, puerto y base; no basta cambiar credenciales), y conectan el módulo probado a esa base. Preparar una base independiente en el servidor Docker local existente, inicializada con los mismos SQL del curso. Esta ubicación incorpora la preferencia indicada por el usuario durante la implementación; no requiere otro contenedor. Sin configuración, omitir esas pruebas; si apunta al original, abortar antes de DDL/DML. Las pruebas pueden recrear su entorno independiente, nunca usar `docker compose down -v` del curso.

## Risks / Trade-offs

- Índice único sobre datos previos con duplicados → comprobación SQL, parada sin cambios destructivos y decisión explícita posterior si existe conflicto.
- Coste de una inserción por candidata y memoria del conjunto de claves → suficiente como primera implementación para el CSV del curso; no materializar lecturas históricas, valorar staging solo si el volumen lo exige.
- Datos de zona ausentes → fallo previo claro; comprobarlo en pruebas y documentar el requisito del entorno.
- Informe y base sin transacción compartida → escritura antes del commit, códigos diferenciados y diagnóstico de informe incompleto o resultado de commit desconocido.
- Horas ambiguas rechazadas aunque fueran lecturas reales → el contrato no incluye offset para desambiguar; cualquier ampliación requerirá otro cambio.

## Migration Plan

1. Implementar y revisar el código, el SQL propuesto, la documentación y las pruebas unitarias sin tocar el esquema desplegado.
2. Pedir aprobación explícita antes de ejecutar cualquier cambio de esquema, presentando el índice y el destino concreto.
3. Preparar la base independiente del curso, ejecutar la comprobación SQL de duplicados y aplicar allí el cambio aprobado.
4. Ejecutar integración, repetición del CSV y regresión de consultas en esa base; conservar las lecturas de la base original.
5. Para una instalación existente, aplicar el script explícitamente después de aprobación, backup y comprobación de duplicados. Cambiar solo `db/init` no migra volúmenes existentes.
6. Rollback: volver a la CLI anterior; conservar los datos insertados y el índice. Retirar el índice requiere otra aprobación y pierde la garantía para nuevas ingestas. No borrar lecturas ni volúmenes automáticamente.

## Open Questions

Las decisiones funcionales de «Notas» quedan resueltas por el usuario. Quedan como condiciones de ejecución la aprobación del DDL y la configuración concreta del entorno de pruebas; no son autorización para modificar la base ahora.

## Hallazgos durante la implementación

El destino local `infradata-db` (`localhost:5433`, base `infradata`) ya tiene el índice único compatible `readings_sensor_time_unique`. La ingesta lo reconoce por definición, sin exigir un nombre concreto. No se necesita DDL sobre la base original. La copia de pruebas se plantea como otra base del mismo servidor, según la preferencia del usuario, El usuario autorizó explícitamente crear esta base de pruebas en el mismo contenedor antes de su inicialización.
