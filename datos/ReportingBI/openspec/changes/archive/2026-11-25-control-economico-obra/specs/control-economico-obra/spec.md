## Purpose

Definir el control económico de las obras de CONSTRUCTORA DEMO con indicadores conciliados contra NAV, análisis por partida y evolución mensual, para detectar pérdidas, sobrecostes y producción pendiente de certificar.

## ADDED Requirements

### Requirement: Empresa, grano y opciones de NAV

El sistema MUST usar exclusivamente `[CONSTRUCTORA DEMO$…]`, identificando cada partida por obra y código de tarea. MUST conservar las 10 obras, incluida la que no tiene actividad, y excluir cabeceras y totales de la suma de partidas. Las opciones traducidas MUST tener significado documentado; cualquier valor desconocido se consultará antes de asignarle una etiqueta.

#### Scenario: Empresa de pruebas y filas de total
- **WHEN** se consulta el conjunto de obras sin filtros
- **THEN** aparecen las 10 obras operativas, no aparece `PRUEBA-01` y las tareas `00` y `99` no duplican los importes de las partidas `01` a `10`.

### Requirement: Presupuestos y revisión sin fecha

El presupuesto de coste MUST sumar `[Job Planning Line].[Total Cost (LCY)]` de líneas de presupuesto (tipos 0 y 2); el de venta MUST sumar `[Line Amount (LCY)]` de tipos facturables (1 y 2). Ambos son presupuestos completos, independientes del periodo de ejecución seleccionado. La fecha `1753-01-01` MUST convertirse a vacío sin eliminar sus importes del presupuesto total. La revisión sin fecha MUST excluirse de la base de venta para producción y avance global.

#### Scenario: Costes indirectos presupuestados
- **WHEN** se calcula el presupuesto de coste de una obra
- **THEN** se incluyen las líneas tipo 0 de indirectos aunque no tengan venta.

#### Scenario: Revisión pendiente de fecha
- **WHEN** se consulta `OB-25004`
- **THEN** su presupuesto incluye los 28.000 € de coste y 35.000 € de venta de la revisión de la partida `06`, pero esos 35.000 € no incrementan la base de producción ni la del avance global.

### Requirement: Coste real y certificado

El coste real MUST sumar `[Job Ledger Entry].[Total Cost (LCY)]` para `[Entry Type] = 0`. El certificado MUST ser el negativo de la suma de `[Line Amount (LCY)]` para `[Entry Type] = 1`. El análisis acumulado MUST incluir movimientos hasta la fecha de corte; la evolución mensual MUST asignarlos a su `[Posting Date]`.

#### Scenario: Certificación con signo NAV
- **WHEN** una partida contiene un movimiento de certificación por −1.000 €
- **THEN** aporta 1.000 € al certificado de negocio.

### Requirement: Producción acumulada y mensual

El sistema MUST obtener el último `[Completion _]` de `[Job Progress Entry]` por obra y partida con `[Measurement Date]` menor o igual al corte, convertirlo de escala 0–100 a fracción y usar cero si aún no hay medición. No MUST sumar porcentajes históricos. La producción acumulada MUST sumar el presupuesto de venta de cada partida medible, excluyendo revisiones sin fecha, multiplicado por su avance. La producción mensual MUST ser la diferencia entre acumulados al cierre actual y al cierre anterior, conservando la última medición aunque no haya medición nueva en el mes.

#### Scenario: Medición acumulada
- **WHEN** una partida de venta 100.000 € tiene mediciones de 20 % y 50 % hasta el corte
- **THEN** su producción acumulada es 50.000 €, no 70.000 €.

#### Scenario: Persistencia tras terminar
- **WHEN** una partida alcanza el 100 % y no vuelve a registrar mediciones
- **THEN** conserva su producción acumulada en cierres posteriores y aporta producción mensual cero.

### Requirement: Coste previsto directo e indirecto

El coste previsto de una partida directa MUST ser su presupuesto completo de coste multiplicado por su último avance medido. El avance global de cada obra MUST ser su producción acumulada dividida entre su presupuesto de venta elegible para producción, excluyendo la revisión sin fecha. El denominador MUST incluir todas las partidas medibles, incluso las aún no iniciadas, cuyo avance es cero. Con denominador cero, el avance global MUST ser cero.

El coste previsto de la partida `10` (indirectos) MUST ser su presupuesto de coste multiplicado por el avance global de su propia obra. Dicho avance MUST mantenerse al filtrar una partida y conservar el filtro de obra y el corte temporal. En conjuntos de obras, el coste previsto MUST sumar los resultados por obra y partida, sin aplicar un único avance medio a todos los indirectos.

#### Scenario: Indirectos ponderados por venta
- **WHEN** una obra tiene dos partidas con bases de venta de 100.000 € y 300.000 €, avances del 100 % y 0 %, y presupuesto indirecto de 20.000 €
- **THEN** su avance global es 25 % y su coste indirecto previsto es 5.000 €, también al consultar solo la partida `10`.

### Requirement: Desviación y resaltado estricto

La desviación en euros MUST ser coste real menos coste previsto. Si el coste previsto es positivo, el porcentaje MUST ser desviación en euros dividida entre coste previsto. Se MUST destacar una partida solo cuando este porcentaje, antes del redondeo de presentación, sea estrictamente mayor que 0,05. Un valor igual al 5 % y las desviaciones favorables no activan esta alerta. Si el coste previsto es cero, el porcentaje MUST quedar vacío; con coste real positivo MUST mostrarse una alerta diferenciada «Coste sin avance».

#### Scenario: Frontera del cinco por ciento
- **WHEN** el coste previsto es 1.000 € y el real es 1.050 € o 1.050,01 €
- **THEN** 1.050 € no activa el resaltado por desviación y 1.050,01 € sí lo activa.

#### Scenario: Coste sin avance
- **WHEN** el coste previsto es cero y el coste real es 100 €
- **THEN** la desviación es 100 €, su porcentaje está vacío y la partida aparece destacada con «Coste sin avance».

### Requirement: Pendiente de certificar y márgenes

El pendiente MUST ser producción menos certificado, sin recortar saldos negativos. El margen previsto MUST ser presupuesto de venta menos presupuesto de coste; su porcentaje MUST dividirse entre presupuesto de venta. El margen real MUST ser certificado menos coste real; su porcentaje MUST dividirse entre certificado. Con denominador cero, los porcentajes MUST quedar vacíos. Los totales MUST recalcular porcentajes a partir de importes agregados, sin promediar porcentajes de filas.

#### Scenario: Obra terminada con pérdidas
- **WHEN** `OB-25002` tiene certificado de 2.400.000 € y coste real de 2.525.524,14 € al corte
- **THEN** su margen real es −125.524,14 € y aparece en negativo y destacado.

#### Scenario: Obra sin actividad
- **WHEN** se consulta `OB-26003` al corte 31/10/2026
- **THEN** conserva presupuesto de coste 801.000 € y de venta 900.000 €, con coste, producción y certificado cero, margen real porcentual vacío y sin alerta de sobrecoste.

### Requirement: Informe, filtros y fechas

El informe MUST ofrecer resumen de indicadores y tabla por obra, detalle obra × partida con alertas y evolución mensual de coste, producción y certificado. MUST permitir ordenar por pendiente de certificar y filtrar coherentemente por delegación, tipo de obra, jefe de obra y estado. Los importes MUST presentarse en euros con formato español. El corte inicial MUST ser 31/10/2026; los indicadores acumulados usarán el último día seleccionado hasta ese corte. Los flujos mensuales se distinguirán de los acumulados en sus etiquetas.

#### Scenario: Filtro de delegación
- **WHEN** se selecciona la delegación NORTE
- **THEN** todas las páginas e indicadores muestran exclusivamente las obras de esa delegación y el detalle mantiene el avance global propio de cada obra.

### Requirement: Conciliación y publicación

Cada importe MUST coincidir al céntimo entre SQL sobre las tablas NAV y DAX ejecutado mediante MCP, obra a obra y en total. Producción y coste previsto MUST redondearse a céntimos por obra y partida antes de agregarse, con el mismo criterio en SQL y DAX. La evidencia MUST guardarse en el cambio con corte, fórmula, filtros, SQL, DAX, resultados y diferencia cero. El proyecto MUST abrirse y actualizarse sin errores en Desktop y publicarse por la persona en el área de la demo, sin credenciales en ningún fichero.

#### Scenario: Validación antes de cerrar el cambio
- **WHEN** se da por terminado el informe
- **THEN** existen comparaciones SQL/DAX con diferencia cero, comprobaciones del umbral del 5 %, de los indirectos y de las excepciones, y evidencia de apertura, actualización y publicación.
