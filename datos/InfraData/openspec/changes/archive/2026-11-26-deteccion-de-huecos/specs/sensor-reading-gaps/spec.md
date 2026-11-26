## Purpose

Permitir a mantenimiento identificar todos los periodos sin lecturas esperadas por sensor mediante consultas SQL y una CLI con duración, filtros, umbral configurable y límites UTC, distinguiendo huecos recortados y abiertos.

## ADDED Requirements

### Requirement: Duración del tiempo esperado sin lecturas

El sistema SHALL ofrecer `python -m infradata gaps`, con cadencia esperada fija de 5 minutos. Entre timestamps consecutivos distintos `p` y `n` de un sensor SHALL considerar un hueco `[p + 5 minutos, n)` solo si tiene duración positiva. SHALL calcular detección, intersecciones y duraciones en SQL sin cargar lecturas crudas en Python. Los valores de las lecturas, incluidos los anómalos, SHALL ser irrelevantes para la detección.

#### Scenario: Hueco corto de referencia
- **WHEN** se consulta `gaps --sensor VAL-F01` sobre el seed fijo
- **THEN** se devuelve un único hueco desde `2026-08-20T22:15:00Z` hasta `2026-08-20T23:05:00Z`, con duración de 50 minutos y los cuatro indicadores de extremos falsos

#### Scenario: Cadencia normal y duplicados
- **WHEN** los timestamps distintos de un sensor están separados por 5 minutos y existen timestamps repetidos
- **THEN** no se devuelve ningún hueco por esa secuencia

### Requirement: Umbral configurable con comparación estricta

El sistema SHALL aceptar `--threshold-minutes` como entero no negativo, con valor por defecto 15. SHALL incluir únicamente huecos cuya duración dentro del rango efectivo sea estrictamente mayor que el umbral, antes de cualquier redondeo de presentación.

#### Scenario: Igualdad y superioridad
- **WHEN** existen huecos de 15 y 20 minutos y se usa el umbral por defecto
- **THEN** solo se devuelve el de 20 minutos

#### Scenario: Umbral cero
- **WHEN** se indica `--threshold-minutes 0` y falta una lectura esperada entre dos envíos separados por 10 minutos
- **THEN** se devuelve un hueco de 5 minutos

#### Scenario: Umbral aplicado a la duración de referencia
- **WHEN** se consulta VAL-F01 con umbral 50 o con umbral 49 sobre el seed fijo
- **THEN** con 50 no hay huecos y con 49 aparece el hueco de 50 minutos

### Requirement: Rango UTC y ventana común de observación

El sistema SHALL aceptar `--start-date` y `--end-date` como fechas ISO estrictas `YYYY-MM-DD`, inclusivas e independientes de la zona local o de sesión. SHALL convertirlas a medianoches UTC con fin exclusivo en el día siguiente. SHALL calcular la ventana común como `[min(time), max(time) + 5 minutos)` sobre todas las lecturas, independientemente de filtros de sede o sensor. Cada extremo omitido SHALL usar el extremo correspondiente de esa ventana. El rango efectivo SHALL ser la intersección con la ventana común. Sin lecturas globales o con intersección vacía SHALL devolver una consulta vacía. SHALL asumir que los sensores seleccionados estaban activos durante la ventana común, sin inferir otro calendario de `installed_at`.

#### Scenario: Rango por defecto del seed
- **WHEN** no se indican fechas sobre el seed fijo
- **THEN** se considera desde `2026-08-01T00:00:00Z` hasta `2026-10-31T00:00:00Z` exclusivo, también si se filtra un sensor

#### Scenario: Rango exterior al historial
- **WHEN** se consulta noviembre de 2026 sobre el seed fijo
- **THEN** no se inventan huecos después de octubre y se devuelve una consulta vacía

#### Scenario: Un único límite de fechas
- **WHEN** solo se indica `--start-date 2026-09-01` o solo `--end-date 2026-09-30`
- **THEN** se utiliza respectivamente el fin global o el inicio global para el límite omitido

#### Scenario: Zona de sesión diferente de UTC
- **WHEN** se consulta una fecha con la sesión PostgreSQL en `Europe/Madrid`
- **THEN** los límites y resultados son idénticos a los de una sesión en UTC

### Requirement: Extremos parciales y abiertos

El sistema SHALL consultar el contexto temporal de cada sensor fuera del rango para identificar huecos que lo atraviesen. SHALL intersectar cada hueco con el rango efectivo, mostrar ese inicio y fin y medir su duración recortada. SHALL indicar independientemente `recortado_inicio` y `recortado_fin` cuando el candidato se extienda fuera del rango efectivo, y `abierto_inicio` o `abierto_fin` cuando no exista una lectura previa o posterior del sensor que permita conocer ese extremo real. SHALL conservar los indicadores abiertos aunque el hueco se recorte. Los límites de observación usados para representar un hueco abierto SHALL NOT presentarse como extremos reales demostrados.

#### Scenario: Dos días completos dentro de un hueco cerrado
- **WHEN** se consulta PAT-L01 desde el 2026-10-12 hasta el 2026-10-13 inclusive sobre el seed
- **THEN** se devuelve una fila de `2026-10-12T00:00:00Z` a `2026-10-14T00:00:00Z`, de 2880 minutos, recortada al final, sin recorte inicial y sin extremos abiertos, aunque no haya lecturas dentro del rango

#### Scenario: Hueco iniciado antes de la consulta
- **WHEN** se consulta PAT-L01 solo el 2026-10-14 sobre el seed
- **THEN** se devuelve de `2026-10-14T00:00:00Z` a `2026-10-14T06:00:00Z`, de 360 minutos, recortado al inicio, sin recorte final y sin extremos abiertos

#### Scenario: Umbral tras el recorte
- **WHEN** se consulta PAT-L01 solo el 2026-10-14 con umbral de 360 minutos
- **THEN** no se devuelve el hueco, aunque su duración completa sea de 3240 minutos

#### Scenario: Sensor sin lecturas en toda la ventana
- **WHEN** existe un sensor seleccionado sin ninguna lectura y la ventana global abarca al menos un día
- **THEN** se considera un único hueco en todo el rango efectivo, abierto por ambos extremos, y se devuelve si supera el umbral

#### Scenario: Extremos globales sin lectura de cierre
- **WHEN** un sensor tiene su primera lectura después del inicio global o su última lectura más 5 minutos antes del fin global
- **THEN** se consideran respectivamente un hueco inicial abierto al inicio o uno final abierto al final, con duración limitada a la intersección con el rango efectivo

#### Scenario: Fin exclusivo con lectura de cierre
- **WHEN** un hueco termina en una lectura exactamente a la medianoche del fin exclusivo
- **THEN** esa lectura cierra el hueco sin incluirse en el rango y no marca un recorte final si el candidato termina exactamente en ese límite

#### Scenario: Última lectura normal del dataset
- **WHEN** un sensor tiene lectura en el máximo timestamp global y no presenta otros huecos
- **THEN** sus siguientes 5 minutos esperados no generan un falso hueco al final de la ventana

### Requirement: Filtros combinables y exhaustividad

El sistema SHALL aceptar `--site` como nombre exacto de sede y `--sensor` como código exacto, distinguiendo mayúsculas y combinando todos los filtros por conjunción. SHALL devolver todos y solo los huecos que superen el umbral dentro del rango efectivo para los sensores existentes seleccionados. Un filtro desconocido o incompatible SHALL producir una consulta vacía, sin crear sensores ficticios.

#### Scenario: Todos los huecos del seed
- **WHEN** se ejecuta `gaps` sin opciones sobre el seed fijo
- **THEN** se devuelven exactamente tres filas, ordenadas por inicio: VAL-F01 del `2026-08-20T22:15:00Z` al `2026-08-20T23:05:00Z`, 50 minutos; SAG-P02 del `2026-09-03T08:00:00Z` al `2026-09-03T14:30:00Z`, 390 minutos; PAT-L01 del `2026-10-12T00:00:00Z` al `2026-10-14T06:00:00Z`, 3240 minutos; todas sin recortes ni extremos abiertos

#### Scenario: Filtros coincidentes
- **WHEN** se consulta `gaps --site "Planta Sagunto" --sensor SAG-P02 --start-date 2026-09-03 --end-date 2026-09-03`
- **THEN** solo se devuelve el hueco de SAG-P02 de 390 minutos

#### Scenario: Filtros sin coincidencia
- **WHEN** se combina sede Planta Valencia con SAG-P02, se consulta un sensor desconocido o se indica `sag-p02`
- **THEN** no se devuelve ninguna fila

### Requirement: Salida estable y errores de CLI

El sistema SHALL imprimir una tabla tabulada con columnas `sensor`, `sede`, `inicio_utc`, `fin_utc`, `duracion_minutos`, `recortado_inicio`, `recortado_fin`, `abierto_inicio`, `abierto_fin`, en ese orden. SHALL ordenar por inicio ascendente y código de sensor ascendente, mostrar timestamps ISO UTC con segundos y sufijo `Z`, duración con tres decimales y punto decimal e indicadores como `si` o `no`. Una consulta vacía SHALL imprimir `Sin huecos para los filtros indicados.` y devolver 0. SHALL validar antes de conectar; fechas inválidas o no estrictas, rango invertido, fin cuyo día siguiente no sea representable, umbral negativo o no entero y opciones desconocidas SHALL producir stderr y código 2. Fallos PostgreSQL SHALL producir stderr y código 1, sin tabla parcial. SHALL conservar los contratos de `check` y `daily-summary`.

#### Scenario: Presentación del hueco corto
- **WHEN** se devuelve el hueco completo de VAL-F01
- **THEN** la duración se presenta como `50.000`, los tiempos con sufijo `Z` y los cuatro indicadores como `no`

#### Scenario: Validación sin conexión
- **WHEN** se indica `--threshold-minutes -1`, `--threshold-minutes 1.5`, fecha `2026-02-30`, fecha `20260903`, rango invertido, fin `9999-12-31` u opción desconocida
- **THEN** se devuelve 2 con diagnóstico por stderr sin abrir conexión

#### Scenario: Salida vacía y fallo de base de datos
- **WHEN** la consulta no encuentra huecos o PostgreSQL rechaza la consulta
- **THEN** respectivamente se imprime el mensaje de ausencia con código 0, o se devuelve 1 con diagnóstico por stderr y stdout vacío

#### Scenario: Ayuda y compatibilidad
- **WHEN** se consulta `gaps --help` o se ejecutan los comandos existentes
- **THEN** la ayuda explica filtros, cadencia, umbral y recortes con código 0, y `check` y `daily-summary` mantienen su comportamiento
