## Purpose

Permitir incorporar lecturas CSV de sensores conocidos con validación, conversión de Europe/Madrid a UTC, carga parcial idempotente e informe trazable de rechazos, conservando el conjunto original del curso durante las pruebas.

## ADDED Requirements

### Requirement: Comando y contrato de entrada CSV

El sistema SHALL ofrecer `python -m infradata ingest-csv FICHERO --rejects INFORME`. SHALL aceptar UTF-8 con BOM opcional, separador `;`, comillas CSV estándar, LF o CRLF y cabecera exacta ordenada `codigo_sensor;fecha_hora_local;valor`. SHALL recortar espacios exteriores para validar los campos conservando su contenido original en el informe. Cada registro SHALL tener tres campos; los registros vacíos o con otra cantidad SHALL rechazarse individualmente cuando sea posible continuar el análisis CSV. Una cabecera incorrecta, fichero vacío sin cabecera, error de codificación o sintaxis CSV irrecuperable SHALL invalidar la operación completa sin inserciones confirmadas. Un fichero con solo la cabecera SHALL ser válido.

#### Scenario: Contrato aportado por el curso
- **WHEN** se procesa un fichero con la cabecera acordada y `VAL-T01;31/10/2026 01:05;23,31`
- **THEN** se interpreta el código `VAL-T01`, una fecha local con precisión de minuto y el valor numérico `23.31`

#### Scenario: Cabecera incompatible
- **WHEN** la cabecera usa otros nombres, orden, delimitador o contiene columnas adicionales
- **THEN** la operación devuelve 2 y no confirma ninguna inserción

#### Scenario: Estructura de fila incorrecta
- **WHEN** un registro tiene dos o cuatro campos o está vacío entre registros válidos
- **THEN** se rechaza con `estructura_invalida` y se siguen procesando los registros válidos

#### Scenario: Solo cabecera
- **WHEN** el fichero contiene únicamente la cabecera correcta
- **THEN** se devuelve 0, se genera un informe con solo cabecera y el resumen indica cero procesadas, insertadas y rechazadas

### Requirement: Validación de sensor y valor

El sistema SHALL aceptar solo códigos existentes en `sensors.code`, con coincidencia exacta y distinción de mayúsculas. SHALL aceptar valores con gramática `[+-]?[0-9]+(,[0-9]+)?` y representación finita en `double precision`, sin separadores de miles, punto decimal ni exponentes. SHALL rechazar valores vacíos, no numéricos, no finitos o fuera del rango representable, sin corregirlos ni crear sensores. SHALL conservar el sensor y el valor de las filas aceptadas con la precisión del tipo existente y sin límites de anomalías adicionales.

#### Scenario: Sensor desconocido y valores inválidos
- **WHEN** aparecen `VAL-T09`, un valor vacío, `n/d`, `NaN`, `Infinity` o `23.12`
- **THEN** las filas correspondientes se rechazan como `sensor_desconocido` o `valor_invalido` y no se insertan

#### Scenario: Valores negativos o anómalos numéricos
- **WHEN** un sensor conocido tiene valores `-2,50` o `96,40` con una fecha válida
- **THEN** se aceptan sin aplicar criterios de anomalías

### Requirement: Normalización temporal sin asignaciones arbitrarias

El sistema SHALL interpretar fechas estrictas `DD/MM/AAAA HH:MM` como horas locales de Europe/Madrid y persistir su instante UTC. SHALL rechazar fechas calendáricas inválidas, formatos distintos, horas ambiguas y horas inexistentes con motivos diferenciados. SHALL producir los mismos instantes independientemente de la zona del sistema o sesión PostgreSQL. La ausencia de datos de zona Europe/Madrid SHALL causar un error operativo antes de escribir lecturas.

#### Scenario: Hora de invierno y frontera UTC
- **WHEN** se procesa `31/10/2026 00:30` para un sensor conocido
- **THEN** se persiste `2026-10-30T23:30:00Z`, aunque cambie el día UTC

#### Scenario: Hora de verano
- **WHEN** se procesa `01/08/2026 02:00` para un sensor conocido
- **THEN** se persiste `2026-08-01T00:00:00Z`

#### Scenario: Fecha inválida y formato no admitido
- **WHEN** se procesa `31/02/2026 12:00` o `2026-10-31T01:00:00+01:00`
- **THEN** la fila se rechaza con `fecha_invalida`

#### Scenario: Hora ambigua al volver al horario de invierno
- **WHEN** se procesa `25/10/2026 02:30`
- **THEN** la fila se rechaza con `hora_ambigua` sin seleccionar ninguno de los dos instantes posibles

#### Scenario: Hora inexistente al entrar en horario de verano
- **WHEN** se procesa `29/03/2026 02:30`
- **THEN** la fila se rechaza con `hora_inexistente` sin desplazarla automáticamente

### Requirement: Identidad de lectura e idempotencia

El sistema SHALL identificar una lectura por `(sensor_id, instante UTC)`, independientemente del valor. SHALL conservar la lectura ya persistida y, entre filas válidas del fichero, la primera por orden de entrada. Las filas inválidas SHALL NOT reservar una clave. Las siguientes filas válidas de la misma clave SHALL rechazarse como `duplicado_fichero`; la primera válida SHALL rechazarse como `duplicado_base` si su clave ya existe en la base. SHALL garantizar unicidad también con ejecuciones concurrentes mediante una garantía de unicidad en la base. La ingesta SHALL fallar sin insertar si no existe dicha garantía y SHALL NOT modificar el esquema automáticamente.

#### Scenario: Duplicados idénticos y valores distintos
- **WHEN** un fichero contiene tres filas válidas del mismo sensor e instante con valores `23,31`, `23,31` y `24,00`, y la clave no existe
- **THEN** se inserta únicamente `23.31` de la primera fila y se rechazan las otras dos con `duplicado_fichero`

#### Scenario: Lectura existente con valor distinto
- **WHEN** la base tiene valor `23.31` para una clave y el fichero aporta `24,00` para ella
- **THEN** se conserva `23.31` y se informa `duplicado_base`

#### Scenario: Una fila inválida precede a otra válida
- **WHEN** una fila con valor vacío precede a una fila válida de la misma clave no persistida
- **THEN** se rechaza la primera por valor y se inserta la segunda

#### Scenario: Repetición del mismo fichero
- **WHEN** se repite una carga que insertó lecturas correctamente
- **THEN** no se añade ninguna lectura, las primeras filas válidas de claves existentes se rechazan con `duplicado_base` y sus repeticiones internas con `duplicado_fichero`

#### Scenario: Cargas concurrentes
- **WHEN** dos cargas intentan insertar simultáneamente una clave nueva
- **THEN** como máximo una inserta esa lectura y la otra informa `duplicado_base` sin reemplazar el valor ganador

### Requirement: Carga parcial y fallos globales

El sistema SHALL confirmar conjuntamente todas las filas aceptadas al terminar correctamente la operación y SHALL omitir las rechazadas. Los errores de fila SHALL NOT impedir la carga de las válidas. Los fallos operativos o de contrato global anteriores a la confirmación SHALL revertir las inserciones de esa ejecución y emitir un diagnóstico sin resumen de éxito. Si se pierde la conexión durante el commit y no se conoce el resultado, SHALL informar de resultado desconocido y permitir reintento idempotente sin afirmar un rollback confirmado.

#### Scenario: Fichero mixto
- **WHEN** un fichero tiene dos filas nuevas válidas y tres inválidas
- **THEN** se confirman las dos válidas, se informan tres rechazos y el resumen indica cinco procesadas

#### Scenario: Fallo durante la carga
- **WHEN** tras procesar filas válidas ocurre un error SQL, de lectura o de escritura del informe antes del commit
- **THEN** se revierten las inserciones de la ejecución, se devuelve un error y cualquier informe generado se identifica como incompleto

### Requirement: Informe de rechazos trazable

El sistema SHALL generar un informe UTF-8 separado por `;` con cabecera `fila;codigo_sensor;fecha_hora_local;valor;registro_original;motivo;detalle`, una fila por rechazo y orden de entrada. `fila` SHALL identificar la línea física inicial del registro en el fichero fuente, contando la cabecera como línea 1. SHALL conservar los campos originales disponibles y el registro CSV original, incluso para estructuras incorrectas y registros multilínea. SHALL emitir un único motivo por registro según prioridad estructura, sensor, fecha/zona, valor y duplicado. Los motivos SHALL ser `estructura_invalida`, `sensor_desconocido`, `fecha_invalida`, `hora_ambigua`, `hora_inexistente`, `valor_invalido`, `duplicado_fichero` o `duplicado_base`; `detalle` SHALL explicar el motivo en español y referenciar la primera fila válida para duplicados internos. Sin rechazos SHALL escribir solo la cabecera. El destino SHALL ser nuevo y distinto del origen; un destino existente o no escribible SHALL causar error antes de modificar datos.

#### Scenario: Identificación en la muestra del curso
- **WHEN** se procesa el CSV de `data/incoming/` en una base aislada con el seed
- **THEN** las líneas 4, 6, 7 y 8 aparecen respectivamente como `duplicado_fichero`, `sensor_desconocido`, `valor_invalido` y `valor_invalido`, conservando sus datos originales

#### Scenario: Varios errores en una fila
- **WHEN** una fila tiene un sensor desconocido y un valor vacío
- **THEN** se informa una única vez con `sensor_desconocido`

#### Scenario: Informe protegido
- **WHEN** el informe ya existe o coincide con el fichero de entrada
- **THEN** se devuelve 1, no se sobrescribe ninguno y no se insertan lecturas

### Requirement: Resultado CLI y compatibilidad

El sistema SHALL devolver 0 para una carga confirmada sin rechazos, 3 para una carga confirmada con rechazos, 2 para argumentos o contrato global inválidos y 1 para errores operativos. En éxito SHALL imprimir por stdout los totales enteros de filas procesadas, insertadas y rechazadas, cumpliendo procesadas = insertadas + rechazadas. Los diagnósticos de error SHALL usar stderr sin afirmar éxito. La ayuda SHALL describir el comando, el contrato y la carga parcial. `check`, `daily-summary` y `gaps` SHALL conservar sus interfaces y requisitos actuales.

#### Scenario: Carga válida y carga con rechazos
- **WHEN** se carga un fichero completamente válido o uno mixto que finaliza correctamente
- **THEN** se devuelve respectivamente 0 o 3 y se muestran los totales confirmados

#### Scenario: Uso y conexión incorrectos
- **WHEN** falta `--rejects`, se usa una opción desconocida o falla la conexión
- **THEN** se devuelve respectivamente 2, 2 o 1 con explicación por stderr

### Requirement: Comprobaciones sobre una base independiente

Las comprobaciones de carga y migración SHALL usar una base independiente inicializada con el conjunto original del curso. Las pruebas de integración SHALL requerir `INGESTION_TEST_DATABASE_URL` explícita, verificar que no identifica la base original antes de escribir y omitir su ejecución si no está configurada. SHALL conservar datos y volumen originales. Todo cambio de esquema SHALL requerir aprobación previa del usuario. La migración SHALL detectar duplicados históricos en SQL y detenerse sin eliminarlos si existen.

#### Scenario: Entorno de pruebas ausente o inseguro
- **WHEN** no existe configuración explícita de pruebas o apunta al mismo destino real que la base original
- **THEN** las pruebas se omiten en el primer caso y abortan antes de DDL o DML en el segundo

#### Scenario: Esquema con duplicados históricos
- **WHEN** se prepara una migración aprobada y hay más de una lectura de una misma clave
- **THEN** la migración se detiene, identifica el conflicto y conserva todas las lecturas
