# resumen-diario Specification

## Purpose

Permitir consultar desde la CLI las estadísticas diarias de cada sensor en UTC, con filtros
por sede, sensor y fechas, comparando las lecturas disponibles con las 288 esperadas.

## Requirements
### Requirement: Consulta diaria desde la CLI

El sistema SHALL ofrecer `python -m infradata resumen-diario` sin alterar el comportamiento
de `python -m infradata check`. La consulta SHALL devolver por sensor y día UTC la sede,
el código de sensor, la unidad, el mínimo, el máximo, la media y el número de lecturas.
La salida SHALL incluir también las lecturas esperadas por sensor y día UTC, con valor fijo 288.
El orden SHALL ser fecha ascendente y, dentro de cada fecha, código de sensor ascendente.

#### Scenario: Consulta de todo el histórico
- **WHEN** se ejecuta `python -m infradata resumen-diario` sin filtros
- **THEN** se muestran todos los grupos sensor/día con lecturas, ordenados por fecha y código
- **AND** el proceso termina con código 0

#### Scenario: Compatibilidad con check
- **WHEN** se ejecuta `python -m infradata check`
- **THEN** se conservan su salida de sensores, lecturas y fechas y sus códigos de salida

### Requirement: Agregación sobre las lecturas disponibles

El sistema SHALL calcular mínimo, máximo, media aritmética y recuento en SQL, sin traer
lecturas individuales a Python para agregarlas. SHALL incluir todas las lecturas del grupo,
también los valores anómalos, y usar el recuento real para calcular la media.
SHALL devolver la unidad del sensor sin convertirla y conservar la precisión del cálculo
antes de presentar los resultados.

#### Scenario: Valores controlados
- **GIVEN** un sensor de unidad `°C` con lecturas 10, 20 y 30 en un mismo día UTC
- **WHEN** se consulta ese sensor y ese día
- **THEN** se devuelve mínimo 10, máximo 30, media 20, recuento 3 y unidad `°C`

#### Scenario: Referencia del conjunto fijo
- **GIVEN** el conjunto inicial del curso y las referencias fijas registradas antes de implementar
- **WHEN** se consulta el sensor y día completo seleccionados como referencia
- **THEN** mínimo, máximo, media, recuento y unidad coinciden con esa referencia,
  comparando la media interna con tolerancia absoluta de 0.000001

#### Scenario: Día incompleto
- **GIVEN** el sensor y día incompleto seleccionados del conjunto inicial como referencia
- **WHEN** se consulta ese grupo
- **THEN** su recuento y estadísticas coinciden con la referencia de lecturas disponibles
- **AND** no se crean lecturas ni se usa 288 como denominador de la media

### Requirement: Filtros opcionales combinables

El sistema SHALL admitir `--sede` por nombre exacto y `--sensor` por código exacto,
con distinción de mayúsculas y acentos, y fechas opcionales `--desde` y `--hasta`.
Los filtros presentes SHALL combinarse con AND. Los valores SHALL parametrizarse al consultar.
Un filtro ausente SHALL dejar esa dimensión sin restricción.

#### Scenario: Filtros simultáneos
- **WHEN** se ejecuta `python -m infradata resumen-diario --sede "Planta Valencia" --sensor VAL-T01 --desde 2026-09-01 --hasta 2026-09-02`
- **THEN** solo aparecen grupos de `VAL-T01` de `Planta Valencia` en esos dos días UTC

#### Scenario: Un solo filtro
- **WHEN** se consulta únicamente con `--sede "Planta Sagunto"`
- **THEN** aparecen solo sensores de esa sede, sin restringir las fechas del histórico

#### Scenario: Identificadores sin coincidencia
- **WHEN** se consulta una sede o sensor desconocido, o una sede y sensor incompatibles
- **THEN** se obtiene el comportamiento de consulta sin resultados

### Requirement: Días y extremos del rango en UTC

El sistema SHALL interpretar las fechas exclusivamente como `YYYY-MM-DD` y como días UTC.
Ambos extremos del rango SHALL ser inclusivos: desde medianoche UTC del primer día hasta,
sin incluir, medianoche UTC posterior al último. La agrupación SHALL ser independiente
de la zona horaria local y de la sesión PostgreSQL. Cada extremo SHALL poder omitirse.

#### Scenario: Lecturas a ambos lados de medianoche
- **GIVEN** lecturas a `2026-09-01T23:55:00Z` y `2026-09-02T00:00:00Z`
- **WHEN** se consulta del `2026-09-01` al `2026-09-01`
- **THEN** solo se incluye la primera lectura
- **AND** se obtiene el mismo resultado con sesiones en UTC y Europe/Madrid

#### Scenario: Inclusión de ambos días extremos
- **GIVEN** lecturas a medianoche del primer día, al final del último y a medianoche del siguiente
- **WHEN** se consultan el primer y último día como extremos
- **THEN** se incluyen las lecturas de ambos días y se excluye la del día siguiente

#### Scenario: Rango abierto
- **WHEN** se indica solo `--hasta 2026-09-02`
- **THEN** se incluyen las lecturas anteriores a `2026-09-03T00:00:00Z` sin límite inferior

#### Scenario: Solo fecha inicial
- **WHEN** se indica solo `--desde 2026-09-02`
- **THEN** se incluyen las lecturas desde `2026-09-02T00:00:00Z` sin límite superior

### Requirement: Presentación y ausencia de datos

La CLI SHALL mostrar una cabecera
`Sede | Sensor | Día UTC | Unidad | Mínimo | Máximo | Media | Lecturas | Esperadas`, seguida de una fila
por resultado con separador ` | `. Las fechas SHALL mostrarse como `YYYY-MM-DD`, el recuento
como entero, y mínimo, máximo y media con dos decimales fijos y punto decimal, usando el
formato Python `.2f` solo al presentar. Los días sin lecturas SHALL omitirse, sin ceros,
medias inventadas ni filas de relleno. Una consulta vacía SHALL mostrar únicamente
`No hay lecturas para los filtros indicados.` en stdout y terminar con código 0.

#### Scenario: Precisión de presentación
- **GIVEN** un grupo con mínimo 1, máximo 2 y media 1.5
- **WHEN** se muestra su resumen en la CLI
- **THEN** los tres valores se presentan como `1.00`, `2.00` y `1.50`

#### Scenario: Un día vacío entre dos días con lecturas
- **WHEN** se consulta un sensor en un rango con un día intermedio sin lecturas
- **THEN** aparecen los días con datos y se omite el día vacío

#### Scenario: Ningún grupo con datos
- **WHEN** ningún grupo cumple los filtros
- **THEN** se muestra el mensaje de ausencia de lecturas, sin cabecera, y se devuelve código 0

### Requirement: Lecturas esperadas por día UTC

La CLI SHALL mostrar la columna `Esperadas` inmediatamente después de `Lecturas`, con
el entero 288 en todas las filas, correspondiente a una lectura cada cinco minutos
durante un día UTC completo. Este valor SHALL ser independiente del recuento disponible
y de la zona horaria local. SHALL servir como referencia visual para los días incompletos,
sin rellenar lecturas ausentes, generar filas para días vacíos ni modificar la media.

#### Scenario: Día completo con las lecturas esperadas
- **GIVEN** un grupo sensor/día UTC con 288 lecturas
- **WHEN** se muestra su resumen en la CLI
- **THEN** las columnas `Lecturas` y `Esperadas` muestran `288` y `288`, respectivamente

#### Scenario: Comparación de un día incompleto
- **GIVEN** un grupo sensor/día UTC con tres lecturas de valores 10, 20 y 30
- **WHEN** se muestra su resumen en la CLI
- **THEN** las columnas `Lecturas` y `Esperadas` muestran `3` y `288`, respectivamente
- **AND** la media presentada sigue siendo `20.00`, calculada sobre las tres lecturas

#### Scenario: Referencia independiente del horario local
- **WHEN** se muestra el mismo resumen con zona horaria local UTC o Europe/Madrid
- **THEN** la columna `Esperadas` muestra `288` en todas las filas en ambos casos

### Requirement: Errores de uso y de base de datos

El sistema SHALL rechazar fechas no válidas, formatos distintos a `YYYY-MM-DD`, rangos
invertidos y opciones incompletas o desconocidas antes de conectar a la base de datos,
con mensaje en castellano por stderr y código 2. SHALL tratar una fecha final cuyo día
siguiente no sea representable como error de uso. Ante un fallo de conexión o consulta
SHALL mostrar un error en castellano por stderr y terminar con código 1, sin traceback.

#### Scenario: Fecha o rango inválidos
- **WHEN** se indica `--desde 2026-02-30`, `--desde 01/09/2026` o un inicio posterior al fin
- **THEN** se devuelve código 2 y un mensaje en castellano, sin abrir conexión

#### Scenario: Opción incompleta o desconocida
- **WHEN** se indica `--sensor` sin valor o una opción no admitida
- **THEN** se devuelve código 2 y un mensaje de uso en castellano, sin abrir conexión

#### Scenario: Fecha final no representable
- **WHEN** se indica `--hasta 9999-12-31` y no puede construirse el día siguiente
- **THEN** se devuelve código 2 con un mensaje en castellano, sin traceback ni conexión

#### Scenario: Base de datos inaccesible
- **WHEN** falla la conexión o ejecución de la consulta
- **THEN** se informa en castellano por stderr, no se imprime un resumen parcial y se devuelve código 1
