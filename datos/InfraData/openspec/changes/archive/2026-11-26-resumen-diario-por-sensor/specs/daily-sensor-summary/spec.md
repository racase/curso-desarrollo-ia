## Purpose

Permitir a los analistas comparar el comportamiento diario de los sensores mediante una consulta de CLI con mínimo, máximo, media y recuento, filtros combinables y días delimitados en UTC.

## ADDED Requirements

### Requirement: Resumen diario por sensor

El sistema SHALL ofrecer `python -m infradata daily-summary`, calculando en SQL mínimo, máximo, media aritmética y número de lecturas disponibles por sensor y día UTC. Cada fila SHALL incluir código y nombre del sensor, sede y unidad. El cálculo SHALL incluir todas las lecturas, también las anómalas, sin interpolación ni sustitución de datos ausentes.

#### Scenario: Día completo de referencia del curso
- **WHEN** se ejecuta `daily-summary --sensor VAL-T01 --start-date 2026-08-01 --end-date 2026-08-01` sobre el dataset fijo
- **THEN** se devuelve una fila para `VAL-T01`, `Temperatura horno 1`, `Planta Valencia`, unidad `°C`, con mínimo `18.600`, máximo `27.460`, media `23.013` y recuento `288`

#### Scenario: Día incompleto de referencia del curso
- **WHEN** se consulta `SAG-P02` para el día 2026-09-03
- **THEN** se devuelve una fila con unidad `bar`, mínimo `6.110`, máximo `7.270`, media `6.667` y recuento `210`, calculados exclusivamente sobre las lecturas existentes

#### Scenario: Conservación de valores anómalos
- **WHEN** se consulta `VAL-T01` para el día 2026-09-18 del dataset fijo
- **THEN** el máximo mostrado es `96.400` y esas lecturas participan en la media y el recuento

### Requirement: Filtros opcionales combinables

El sistema SHALL aceptar `--site` como nombre exacto de sede, `--sensor` como código exacto del sensor y `--start-date` y `--end-date` como fechas ISO `YYYY-MM-DD`. Sede y sensor SHALL distinguir mayúsculas. Todos los filtros presentes SHALL combinarse por conjunción; los ausentes SHALL dejar su dimensión sin restringir. Ambos extremos de fechas SHALL ser inclusivos; un extremo omitido SHALL dejar ese lado sin límite.

#### Scenario: Combinación coincidente
- **WHEN** se ejecuta `daily-summary --site "Planta Sagunto" --sensor SAG-P02 --start-date 2026-09-03 --end-date 2026-09-03`
- **THEN** se devuelve únicamente el resumen de `SAG-P02` del 2026-09-03 con las cifras de referencia

#### Scenario: Sede y sensor incompatibles
- **WHEN** se combina `--site "Planta Valencia"` con `--sensor SAG-P02`
- **THEN** no se devuelve ningún resumen y se informa de la ausencia de lecturas con código de salida 0

#### Scenario: Sin filtros
- **WHEN** se ejecuta `daily-summary` sin opciones
- **THEN** se muestran todos los pares sensor y día con lecturas disponibles

#### Scenario: Un único límite temporal
- **WHEN** solo se indica `--start-date 2026-09-03` o solo `--end-date 2026-09-03`
- **THEN** se incluyen respectivamente los días desde el 3 de septiembre inclusive o hasta ese día inclusive, sin restringir el extremo omitido

### Requirement: Días y límites independientes de la zona de sesión

El sistema SHALL asignar cada lectura al día de su timestamp convertido a UTC, independientemente de la zona local o de sesión PostgreSQL. El filtro SHALL incluir la medianoche UTC inicial y excluir la medianoche UTC posterior al día final.

#### Scenario: Frontera de día con zona de sesión distinta de UTC
- **WHEN** un sensor de prueba tiene lecturas de valor 10 a `2026-09-03T23:55:00Z`, valor 20 a `2026-09-04T00:00:00Z` y valor 30 a `2026-09-05T00:00:00Z`, la sesión usa `Europe/Madrid` y se consulta del 3 al 4 de septiembre
- **THEN** se muestran dos filas de recuento 1: el día 3 con mínimo, máximo y media `10.000`, y el día 4 con `20.000`; la lectura del día 5 queda excluida

### Requirement: Días sin lecturas y salida vacía

El sistema SHALL omitir los pares sensor y día sin lecturas, sin producir valores cero ni nulos artificiales. Cuando no existan filas SHALL imprimir `Sin lecturas para los filtros indicados.` por stdout y devolver 0. Esto SHALL aplicarse también a sedes y sensores desconocidos.

#### Scenario: Días totalmente vacíos seguidos de un día parcial
- **WHEN** se consulta `PAT-L01` del 2026-10-12 al 2026-10-14 sobre el dataset fijo
- **THEN** se devuelve únicamente el 2026-10-14 con unidad `%`, recuento `216`, mínimo `44.270`, máximo `75.770` y media `56.854`

#### Scenario: Consulta sin coincidencias
- **WHEN** se consulta `PAT-L01` solo el 2026-10-12 o un código de sensor inexistente
- **THEN** se imprime el mensaje de ausencia de lecturas y se devuelve 0

### Requirement: Salida legible y determinista

Cuando existan filas el sistema SHALL imprimir una tabla con columnas `dia_utc`, `sensor`, `nombre`, `sede`, `unidad`, `min`, `max`, `media`, `lecturas`, en ese orden y separadas por tabuladores. Las filas SHALL ordenarse por día ascendente y código de sensor ascendente. Las fechas SHALL usar `YYYY-MM-DD`; mínimo, máximo y media SHALL mostrarse con tres decimales, redondeo de formato numérico estándar de Python y punto decimal; el recuento SHALL ser entero. El redondeo SHALL afectar solo a la presentación.

#### Scenario: Orden y formato estables
- **WHEN** una consulta devuelve varios sensores y días
- **THEN** se imprime una única cabecera, seguida de filas ordenadas por día y código, con tres decimales incluso para valores enteros y sin separador decimal dependiente de la configuración regional

### Requirement: Validación y compatibilidad de la CLI

El sistema SHALL validar argumentos antes de abrir conexión. Argumentos desconocidos, fechas inválidas, inicio posterior al fin o un fin cuyo límite exclusivo no sea representable SHALL producir diagnóstico de uso por stderr y código 2. Los errores de PostgreSQL SHALL producir diagnóstico por stderr y código 1, sin una tabla de éxito parcial. La ayuda SHALL mostrar el nuevo comando y sus filtros y devolver 0. El comando `check` SHALL conservar su comportamiento.

#### Scenario: Entrada inválida
- **WHEN** se indica `--start-date 2026-02-30`, un rango invertido, `--end-date 9999-12-31` o una opción desconocida
- **THEN** se devuelve 2 con explicación por stderr y sin consultar la base de datos

#### Scenario: Fallo de conexión o consulta
- **WHEN** PostgreSQL no está accesible o rechaza la consulta
- **THEN** se devuelve 1 y un mensaje de error por stderr, sin tabla parcial

#### Scenario: Ayuda y comando existente
- **WHEN** se solicita `daily-summary --help` o se ejecuta `check`
- **THEN** la ayuda describe los filtros y termina con 0, o `check` conserva su resumen y sus códigos de salida anteriores, respectivamente
