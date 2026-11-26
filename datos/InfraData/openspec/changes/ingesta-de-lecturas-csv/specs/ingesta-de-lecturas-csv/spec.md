## ADDED Requirements

### Requirement: Interpretación del CSV del formador

El sistema SHALL aceptar UTF-8 con separador `;`, comillas dobles CSV, finales LF o CRLF
y cabecera exacta `codigo_sensor;fecha_hora_local;valor`. SHALL interpretar tres campos
por registro, fechas `DD/MM/AAAA HH:MM` en `Europe/Madrid` y números con signo opcional
y coma decimal opcional, sin miles, punto decimal ni notación científica. SHALL quitar
espacios exteriores para validar, conservando los originales para el informe.
SHALL rechazar individualmente los registros con número de campos incorrecto.
SHALL abortar el fichero sin insertar ante codificación, cabecera o sintaxis CSV ilegibles.

#### Scenario: Entrada válida del formador
- **GIVEN** un sensor conocido `VAL-T01` sin lectura en el instante indicado
- **WHEN** se procesa `VAL-T01;31/10/2026 01:00;23,12`
- **THEN** se incorpora valor numérico 23.12 a `2026-10-31T00:00:00Z` para ese sensor

#### Scenario: Cabecera o codificación incorrecta
- **WHEN** el CSV no es UTF-8 o su cabecera no coincide con la acordada
- **THEN** termina con código 2 y ninguna lectura incorporada

#### Scenario: Registro con campos adicionales
- **WHEN** un registro tiene cuatro campos y el siguiente tiene tres campos válidos
- **THEN** se rechaza el primero como `numero_de_campos_invalido` y se procesa el siguiente

#### Scenario: Fichero vacío de registros
- **WHEN** el fichero contiene únicamente la cabecera válida
- **THEN** se completa con cero filas, incorporadas y rechazadas e informe con cabecera

### Requirement: Validación de sensores y valores

El sistema SHALL rechazar sensores desconocidos, valores vacíos, no numéricos o no finitos.
SHALL comparar códigos con distinción de mayúsculas y mantener valores numéricos válidos,
incluidos ceros, negativos y anomalías. SHALL informar un motivo principal por fila,
con precedencia: número de campos, sensor, fecha, valor y duplicidad o conflicto.
Una fila inválida SHALL NOT reservar una clave de duplicidad ni crear sensores.

#### Scenario: Errores de datos independientes
- **WHEN** hay filas con sensor desconocido, valor vacío y valor `n/d`
- **THEN** no se incorporan y sus motivos son `sensor_desconocido`, `valor_vacio`
  y `valor_no_numerico`, respectivamente

#### Scenario: Valor válido anómalo
- **WHEN** una fila válida de sensor conocido contiene `-40`
- **THEN** se incorpora sin corregirla ni rechazarla por anomalía

#### Scenario: Fila inválida antes de una válida con la misma clave
- **WHEN** una fila tiene valor vacío y la siguiente es válida para el mismo sensor e instante
- **THEN** la primera se rechaza y la segunda puede incorporarse

### Requirement: Fechas y cambios de hora

El sistema SHALL convertir horas locales válidas a UTC mediante las reglas IANA de
`Europe/Madrid`, sin asumir un desplazamiento fijo. SHALL rechazar fechas inválidas,
horas ambiguas y horas inexistentes con motivos distintos. SHALL producir el mismo
instante con independencia de la zona del proceso o sesión de PostgreSQL.

#### Scenario: Invierno y verano
- **WHEN** se normalizan `31/10/2026 01:00` y `01/07/2026 12:00`
- **THEN** se obtienen `2026-10-31T00:00:00Z` y `2026-07-01T10:00:00Z`, respectivamente

#### Scenario: Hora repetida en otoño
- **WHEN** una fila contiene `25/10/2026 02:30`
- **THEN** se rechaza con motivo `hora_ambigua` sin elegir una repetición

#### Scenario: Hora inexistente en primavera
- **WHEN** una fila contiene `29/03/2026 02:30`
- **THEN** se rechaza con motivo `hora_inexistente` sin desplazarla

#### Scenario: Fecha inválida
- **WHEN** una fila contiene `30/02/2026 12:00` o un formato distinto al acordado
- **THEN** se rechaza como `fecha_invalida`

### Requirement: Identidad y conflictos de lecturas

El sistema SHALL identificar una lectura mediante sensor e instante UTC. SHALL rechazar
igual clave y valor como duplicado y una clave igual con valor distinto como conflicto,
sin sobrescribir. La igualdad SHALL comparar valores convertidos al tipo de almacenamiento,
sin tolerancias. Dentro del fichero SHALL prevalecer la primera fila con campos válidos
y compararse las siguientes con ella, aunque aquella ya exista en base de datos.

#### Scenario: Duplicado y conflicto internos
- **WHEN** aparecen en orden valores `23,1`, `23,10` y `24,0` para una clave nueva
- **THEN** se incorpora solo el primero y se rechazan los otros como `duplicado_en_csv`
  y `conflicto_en_csv`, respectivamente

#### Scenario: Lectura previamente cargada
- **GIVEN** valor 23.1 ya almacenado para una clave
- **WHEN** otra ingesta ofrece esa clave con valor `23,10` o `24,0`
- **THEN** se rechaza como `ya_cargada` o `conflicto_con_existente`, respectivamente
- **AND** el valor almacenado sigue siendo 23.1

#### Scenario: Reingesta del mismo fichero
- **WHEN** se carga dos veces el mismo fichero sin cambios
- **THEN** la segunda carga incorpora cero lecturas y todas sus filas se identifican
  en el informe como rechazos de validación, duplicidad o conflicto según corresponda

### Requirement: Unicidad garantizada por la base de datos

El sistema SHALL disponer de restricción única `(sensor_id, time)` en la hypertable
para garantizar unicidad ante concurrencia. La migración SHALL aplicarse explícitamente
a volúmenes existentes, previa aprobación, y abortar sin borrar ni fusionar duplicados
preexistentes. La CLI SHALL NOT migrar automáticamente el esquema.

#### Scenario: Ingestas concurrentes
- **WHEN** dos transacciones intentan incorporar una clave nueva a la vez
- **THEN** se almacena como máximo una lectura y la perdedora informa duplicidad
  o conflicto según el valor confirmado por la ganadora

#### Scenario: Duplicados anteriores a la migración
- **WHEN** la comprobación SQL previa encuentra más de una fila por sensor e instante
- **THEN** la migración aborta con diagnóstico y conserva todas las filas originales

#### Scenario: Instalación nueva y volumen existente
- **WHEN** se inicializa una base nueva o se aplica la migración aprobada sin duplicados previos
- **THEN** ambas disponen de la restricción y conservan la hypertable y el índice existentes

### Requirement: Carga parcial y errores operativos

El sistema SHALL incorporar juntas en una transacción las filas válidas y rechazar las
inválidas, duplicadas o en conflicto. SHALL revertir todas las inserciones de esa ejecución
si ocurre un error operativo antes del commit. SHALL distinguir un commit confirmado
de un resultado desconocido por fallo de conexión, sin afirmar rollback sin evidencia.

#### Scenario: Fichero mixto
- **GIVEN** dos filas válidas de claves nuevas y tres filas inválidas
- **WHEN** se completa la ingesta
- **THEN** se incorporan dos lecturas y el informe contiene los tres rechazos

#### Scenario: Fallo SQL antes de confirmar
- **WHEN** falla una consulta después de insertar alguna fila pero antes del commit
- **THEN** se revierte la transacción, se devuelve código 1 y no se anuncia carga completada

#### Scenario: Error estructural tardío
- **WHEN** se encuentra sintaxis CSV ilegible después de registros válidos
- **THEN** se devuelve código 2 y ninguna lectura de esa ejecución queda incorporada

### Requirement: Informe identificable de rechazos

El sistema SHALL generar un informe UTF-8 con `;` y cabecera
`fila;codigo_sensor;fecha_hora_local;valor;registro_original;motivo`, en orden de entrada.
SHALL incluir cada rechazo exactamente una vez, los campos originales y el registro completo,
escapados como CSV, y la línea física inicial contando la cabecera como línea 1.
SHALL conservar el origen y rechazar destinos existentes o equivalentes al origen.
El informe sin rechazos SHALL contener solo la cabecera.

#### Scenario: Trazabilidad y campos adicionales
- **WHEN** se rechaza el primer registro, que empieza en línea 2 y tiene cuatro campos
- **THEN** el informe identifica `fila=2`, conserva el registro completo y explica el rechazo

#### Scenario: Protección del fichero de entrada
- **WHEN** el destino del informe coincide con el origen, incluso mediante un enlace
- **THEN** se rechaza la ejecución sin alterar el origen ni incorporar lecturas

#### Scenario: Publicación del informe fallida tras confirmar
- **WHEN** el commit se confirmó pero falla publicar el informe temporal ya escrito
- **THEN** se devuelve código 1, se indica que la carga está confirmada y se conserva
  el temporal con su ruta para recuperar el informe

### Requirement: CLI y compatibilidad

La CLI SHALL ofrecer `ingestar-csv RUTA --informe RUTA_INFORME`, resumen
`Filas: N | Incorporadas: I | Rechazadas: R` con `N = I + R` y ruta del informe.
SHALL devolver 0 al completar, incluidos rechazos; 2 ante errores de uso, rutas o formato;
y 1 ante fallos operativos. SHALL usar castellano, stderr para errores y no revelar
credenciales ni tracebacks. SHALL conservar los contratos de los comandos existentes.

#### Scenario: Ejecución completada con rechazos
- **WHEN** se completa una carga de cinco filas con dos incorporadas y tres rechazadas
- **THEN** muestra `Filas: 5 | Incorporadas: 2 | Rechazadas: 3`, ruta del informe y código 0

#### Scenario: Informe obligatorio
- **WHEN** falta `--informe` o una opción no tiene valor
- **THEN** se devuelve código 2 antes de conectar a la base de datos

#### Scenario: Base de datos inaccesible
- **WHEN** falla la conexión
- **THEN** se devuelve código 1 con mensaje en castellano, sin resumen de éxito

#### Scenario: Regresión de comandos existentes
- **WHEN** se ejecutan `check`, `resumen-diario` o `huecos`
- **THEN** conservan sus argumentos, presentación y códigos de salida
