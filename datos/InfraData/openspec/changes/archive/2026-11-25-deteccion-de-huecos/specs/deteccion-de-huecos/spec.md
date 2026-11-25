## ADDED Requirements

### Requirement: Duración entre lecturas y umbral estricto

El sistema SHALL medir los huecos completos entre lecturas consecutivas del mismo sensor,
sin descontar la cadencia. SHALL admitir `--umbral-minutos` como entero positivo, con
valor predeterminado 5, y seleccionar solo duraciones estrictamente mayores al umbral.

#### Scenario: Cadencia normal y una lectura ausente
- **GIVEN** lecturas a las 10:00, 10:05 y 10:15 UTC de un mismo sensor
- **WHEN** se consulta con el umbral predeterminado
- **THEN** solo se devuelve el intervalo 10:05–10:15, de diez minutos

#### Scenario: Igualdad con el umbral
- **GIVEN** un hueco de exactamente quince minutos y otro de veinte
- **WHEN** se consulta con `--umbral-minutos 15`
- **THEN** solo se devuelve el de veinte minutos

#### Scenario: Diferencia frente al tiempo de lecturas ausentes
- **GIVEN** lecturas consecutivas a las 10:00 y 10:20 UTC
- **WHEN** se devuelve ese hueco
- **THEN** su duración es veinte minutos, no quince

### Requirement: Exhaustividad sobre las referencias del conjunto fijo

El sistema SHALL devolver exactamente los siguientes tres huecos completos con umbral
de quince minutos sobre el conjunto inicial, sin filtros y sin otras filas:

| Sensor | Última lectura UTC | Siguiente lectura UTC | Duración |
|---|---|---|---|
| VAL-F01 | 2026-08-20 22:10:00 | 2026-08-20 23:05:00 | 00:55:00 |
| SAG-P02 | 2026-09-03 07:55:00 | 2026-09-03 14:30:00 | 06:35:00 |
| PAT-L01 | 2026-10-11 23:55:00 | 2026-10-14 06:00:00 | 54:05:00 |

#### Scenario: Las tres interrupciones verificadas independientemente
- **GIVEN** una base con únicamente el conjunto inicial del curso
- **WHEN** se ejecuta `python -m infradata huecos --umbral-minutos 15`
- **THEN** aparecen exactamente las tres filas de la tabla, en ese orden y con estado completo
- **AND** cada inicio, fin y duración coincide exactamente con la referencia

#### Scenario: Exhaustividad con el umbral predeterminado
- **GIVEN** el conjunto inicial y una referencia independiente calculada con umbral de cinco minutos
- **WHEN** se consulta sin indicar umbral
- **THEN** el conjunto completo de filas coincide con esa referencia, sin faltantes ni adicionales

### Requirement: Filtros combinables y ejecución independiente

El sistema SHALL ofrecer `python -m infradata huecos` con filtros opcionales `--sede`
y `--sensor`, exactos y sensibles a mayúsculas y acentos, combinados con AND.
SHALL consultar directamente sensores y lecturas mediante SQL parametrizado sin depender
del resumen diario, calcular en PostgreSQL y recuperar únicamente resultados finales.
SHALL conservar las lecturas, incluidos valores anómalos, y los comandos existentes.

#### Scenario: Filtro individual por sensor
- **GIVEN** el conjunto inicial y un umbral de quince minutos
- **WHEN** se filtra por `--sensor SAG-P02`
- **THEN** solo se devuelve su hueco de 395 minutos

#### Scenario: Filtro individual por sede
- **GIVEN** el conjunto inicial y un umbral de quince minutos
- **WHEN** se filtra por `--sede "Planta Valencia"`
- **THEN** solo se devuelve el hueco de VAL-F01

#### Scenario: Filtros compatibles e incompatibles
- **WHEN** se combina `--sede "Almacén Paterna" --sensor PAT-L01 --umbral-minutos 15`
- **THEN** solo se devuelve el hueco de PAT-L01
- **AND** cambiar la sede a Planta Valencia produce cero resultados

#### Scenario: Sin coincidencias y compatibilidad
- **WHEN** se consulta un identificador desconocido o un sensor sin huecos calificables
- **THEN** se obtiene una consulta vacía con código 0
- **AND** check y resumen-diario conservan sus salidas y códigos de salida

### Requirement: Rango UTC y contexto fuera de los límites

El sistema SHALL aceptar fechas inclusivas `YYYY-MM-DD`, convertidas en rango `[A, B)`
de días completos UTC. Cada extremo omitido SHALL usar el límite correspondiente del
conjunto del curso: A predeterminado `2026-08-01T00:00:00Z` y B predeterminado
`2026-10-31T00:00:00Z`. SHALL incluir huecos completos cuyo inicio sea menor que B y
cuyo fin sea mayor que A, conservando extremos reales y duración completa para el umbral.
SHALL buscar lecturas anteriores y posteriores fuera del rango cuando existan.

#### Scenario: Rango interior a un hueco sin lecturas dentro
- **GIVEN** el conjunto inicial y umbral quince
- **WHEN** se consulta PAT-L01 del 2026-10-12 al 2026-10-13
- **THEN** se devuelve el hueco completo desde 2026-10-11 23:55 UTC hasta 2026-10-14 06:00 UTC
- **AND** su duración sigue siendo 54:05:00

#### Scenario: Contacto puntual con los límites
- **GIVEN** un rango `[A, B)` y huecos que terminan exactamente en A o empiezan exactamente en B
- **WHEN** se consulta ese rango
- **THEN** esos huecos quedan excluidos por no tener solapamiento de duración positiva

#### Scenario: Fechas parciales y zona horaria
- **WHEN** se indica solo una de las dos fechas
- **THEN** el extremo omitido usa el límite correspondiente del conjunto del curso
- **AND** las mismas fechas producen los mismos resultados con sesiones UTC y Europe/Madrid

### Requirement: Extremos abiertos y duración mínima

El sistema SHALL distinguir los estados completo, abierto al inicio, abierto al final y
abierto en ambos extremos. Un extremo sin lectura delimitadora disponible en todo el
histórico SHALL permanecer desconocido. Para un hueco abierto SHALL calcular únicamente
la duración mínima observable dentro del rango y devolverlo si esa cota supera el umbral.
SHALL considerar los sensores conocidos aunque no tengan lecturas. No SHALL inventar
lecturas ni inferir la duración exacta o la causa de una interrupción abierta.

#### Scenario: Falta la lectura anterior
- **GIVEN** un rango 00:00–01:00 UTC y la primera lectura disponible a las 00:20, sin ninguna anterior
- **WHEN** se consulta con umbral quince
- **THEN** aparece un hueco abierto al inicio con última lectura desconocida, siguiente 00:20 y duración mínima veinte minutos

#### Scenario: Falta la lectura posterior
- **GIVEN** un rango 00:00–01:00 UTC y la última lectura disponible a las 00:40, sin ninguna posterior
- **WHEN** se consulta con umbral quince
- **THEN** aparece un hueco abierto al final con última lectura 00:40, siguiente desconocida y duración mínima veinte minutos

#### Scenario: Sensor conocido sin lecturas en el histórico
- **GIVEN** un sensor del catálogo sin lecturas y un rango de un día UTC
- **WHEN** se consulta con umbral quince
- **THEN** aparece un hueco abierto en ambos extremos con duración mínima 24:00:00

#### Scenario: Cota mínima insuficiente o cero
- **GIVEN** huecos abiertos con duración observable igual o inferior al umbral, o cero
- **WHEN** se consultan esos huecos
- **THEN** se omiten porque la observación no acredita una duración superior al umbral

### Requirement: Salida en castellano y errores

La CLI SHALL mostrar la cabecera
`Sede | Sensor | Última lectura UTC | Siguiente lectura UTC | Duración | Estado`.
SHALL presentar fechas `YYYY-MM-DD HH:MM:SS UTC`, extremos ausentes como `desconocido`
y duraciones `HH:MM:SS` con horas acumuladas. Las duraciones mínimas SHALL llevar `>= `.
SHALL ordenar por inicio observable, sensor y fin observable ascendentes, usando los
límites del rango para los extremos desconocidos al ordenar.
Una consulta vacía SHALL mostrar únicamente
`No hay huecos que superen el umbral para los filtros indicados.` y código 0.
Los errores de argumentos SHALL dar código 2 antes de conectar; los errores de base de
datos SHALL dar código 1 sin salida parcial. Los errores SHALL ir a stderr en castellano,
sin traceback.

#### Scenario: Duraciones largas y cotas explícitas
- **WHEN** se presenta el hueco completo de PAT-L01 o un hueco abierto con cota de veinte minutos
- **THEN** las duraciones se muestran como `54:05:00` y `>= 00:20:00`, respectivamente

#### Scenario: Consulta vacía
- **WHEN** no hay huecos que superen el umbral y cumplan los filtros
- **THEN** aparece solo el mensaje de ausencia de huecos, sin cabecera y con código 0

#### Scenario: Argumentos inválidos
- **WHEN** se indica umbral 0, negativo, decimal o texto, fecha inválida, rango efectivo invertido, opción incompleta o desconocida, o fecha final 9999-12-31
- **THEN** se informa del error con código 2 antes de abrir una conexión

#### Scenario: Fallo de base de datos
- **WHEN** falla la conexión o la consulta
- **THEN** se informa en castellano por stderr con código 1, sin traceback ni resultados parciales
