# Spec Delta

## Purpose

Indicadores económicos de cada obra y capítulo, con las definiciones que usa dirección, y
las páginas del informe que permiten ver dónde se pierde dinero y qué obra ejecutada falta
por certificar.

## ADDED Requirements

### Requirement: Presupuesto de coste y de venta
El presupuesto de coste MUST ser la suma del coste de todas las líneas de presupuesto de la obra o capítulo, incluidas las que solo tienen coste y las que no tienen fecha de planificación. El presupuesto de venta MUST ser la suma del importe de venta de las líneas facturables.

#### Scenario: Capítulo solo de coste
- **WHEN** un capítulo tiene presupuesto de coste pero ninguna línea facturable, como los costes indirectos
- **THEN** muestra su presupuesto de coste y un presupuesto de venta de 0 €

#### Scenario: Línea sin fecha
- **WHEN** una línea de presupuesto no tiene fecha de planificación
- **THEN** su importe cuenta en el presupuesto de su obra y capítulo

### Requirement: Coste real
El coste real MUST ser la suma del coste de los movimientos de consumo registrados en la obra o capítulo en el periodo filtrado. Sin filtro de fecha, MUST incluir todos los movimientos hasta la fecha de corte.

#### Scenario: Coste de una obra
- **WHEN** se mira el coste real de una obra sin filtro de fecha
- **THEN** coincide al céntimo con la suma del coste de sus movimientos de consumo en el ERP

#### Scenario: Coste de un mes
- **WHEN** se filtra un mes
- **THEN** el coste real es solo el de los movimientos de ese mes

### Requirement: Indicadores por periodo
Los indicadores de flujo (coste real, certificado, producción y coste previsto, y los que se calculan a partir de ellos) MUST referirse al periodo filtrado; sin filtro de fecha, MUST referirse a todo lo registrado hasta la fecha de corte. El presupuesto MUST mostrarse siempre completo, sin filtrarse por fecha.

#### Scenario: Presupuesto con filtro de mes
- **WHEN** se filtra un mes
- **THEN** el presupuesto de coste y de venta de cada obra sigue siendo el total de la obra

### Requirement: Certificado
El certificado MUST ser la suma de lo facturado al cliente en la obra o capítulo, en positivo, aunque el ERP lo guarde con signo negativo.

#### Scenario: Signo de negocio
- **WHEN** una obra tiene certificaciones registradas
- **THEN** su certificado aparece como importe positivo igual, en valor absoluto, al registrado en el ERP

### Requirement: Avance de un capítulo
El avance de un capítulo a una fecha MUST ser el porcentaje de la última medición de avance registrada en esa fecha o antes. Un capítulo sin mediciones MUST tener un avance del 0 %.

#### Scenario: Varias mediciones
- **WHEN** un capítulo tiene mediciones a final de cada mes
- **THEN** su avance a una fecha es el de la última medición hasta esa fecha, no la suma ni la media

### Requirement: Producción
La producción de un capítulo MUST ser su presupuesto de venta multiplicado por su avance. La producción de una obra MUST ser la suma de la de sus capítulos. La producción de un mes MUST ser lo que aumenta la producción acumulada en ese mes.

#### Scenario: Capítulo terminado
- **WHEN** un capítulo tiene un avance del 100 %
- **THEN** su producción es igual a su presupuesto de venta

#### Scenario: Producción mensual
- **WHEN** se mira la evolución mensual de una obra
- **THEN** la suma de la producción de todos sus meses es igual a su producción a la fecha de corte

### Requirement: Obra pendiente de certificar
La obra pendiente de certificar MUST ser la producción menos el certificado. Un valor negativo MUST mostrarse tal cual, porque indica obra certificada por delante de la ejecutada.

#### Scenario: Obra ejecutada sin certificar
- **WHEN** una obra tiene más producción que certificado
- **THEN** su importe pendiente de certificar es positivo y la tabla por obra se puede ordenar por él

### Requirement: Coste previsto para la obra producida
El coste previsto para lo producido de un capítulo con presupuesto de venta MUST ser su presupuesto de coste multiplicado por su avance. En un capítulo sin presupuesto de venta, como los costes indirectos, MUST ser su presupuesto de coste multiplicado por el avance global de la obra, que es la producción de la obra entre su presupuesto de venta.

#### Scenario: Costes indirectos
- **WHEN** una obra lleva producido el 40 % de su presupuesto de venta
- **THEN** el coste previsto de su capítulo de costes indirectos es el 40 % del presupuesto de coste de ese capítulo

### Requirement: Desviación de coste
La desviación de coste MUST ser el coste real menos el coste previsto para lo producido. Un valor positivo MUST indicar sobrecoste. La desviación en porcentaje MUST ser la desviación entre el coste previsto y MUST quedar en blanco cuando el coste previsto es 0.

#### Scenario: Capítulo con sobrecoste
- **WHEN** un capítulo cuesta más de lo previsto para lo que lleva producido
- **THEN** su desviación de coste es positiva y se ve también en porcentaje

#### Scenario: Sin producción
- **WHEN** un capítulo no tiene avance
- **THEN** su desviación en porcentaje aparece en blanco, sin error

### Requirement: Margen previsto
El margen previsto MUST ser el presupuesto de venta menos el presupuesto de coste. El margen previsto en porcentaje MUST calcularse sobre el presupuesto de venta y MUST quedar en blanco cuando este es 0.

#### Scenario: Margen previsto de una obra
- **WHEN** una obra tiene presupuesto de venta y de coste
- **THEN** su margen previsto es la diferencia y su porcentaje se calcula sobre el presupuesto de venta

### Requirement: Margen real
El margen real MUST ser la producción menos el coste real. El margen real en porcentaje MUST calcularse sobre la producción y MUST quedar en blanco cuando la producción es 0.

#### Scenario: Obra terminada con pérdidas
- **WHEN** una obra terminada ha costado más de lo que ha producido
- **THEN** su margen real es negativo y aparece destacado en la tabla por obra

### Requirement: Obras sin empezar
Una obra sin movimientos ni mediciones MUST mostrarse con su presupuesto y sin coste real, producción ni certificado, y ningún indicador MUST dar error.

#### Scenario: Obra en planificación
- **WHEN** una obra todavía no ha empezado
- **THEN** aparece con su presupuesto de coste y de venta, y los indicadores que dependen de la producción quedan en blanco o a 0 sin error

### Requirement: Página resumen
El informe MUST tener una página resumen con los totales de presupuesto de coste, presupuesto de venta, coste real, producción, certificado, pendiente de certificar y margen real, y una tabla con una fila por obra y esos indicadores, los márgenes y la desviación de coste.

#### Scenario: Totales sin filtros
- **WHEN** se abre la página resumen sin filtros
- **THEN** los totales coinciden con los del ERP a la fecha de corte y con la suma de la tabla por obra

### Requirement: Página de detalle obra × capítulo
El informe MUST tener una página con una fila por obra y capítulo que muestre presupuesto, coste real, producción, coste previsto y desviación de coste en importe y en porcentaje. Los capítulos con una desviación de coste mayor del 5 % MUST aparecer destacados en rojo.

#### Scenario: Capítulo destacado
- **WHEN** un capítulo tiene una desviación de coste del 8 %
- **THEN** aparece destacado en rojo en la página de detalle

#### Scenario: Desviación pequeña
- **WHEN** un capítulo tiene una desviación de coste del 3 %
- **THEN** no aparece destacado

### Requirement: Página de evolución mensual
El informe MUST tener una página con la evolución por mes del coste real, la producción y el certificado, para el conjunto de obras o para las obras filtradas.

#### Scenario: Evolución de una obra
- **WHEN** se filtra la página por una obra
- **THEN** el gráfico muestra para cada mes su coste real, su producción y su certificado

### Requirement: Cuadre con el ERP
Cada indicador MUST coincidir al céntimo, obra a obra, con la misma cifra calculada directamente sobre las tablas del ERP con estas definiciones.

#### Scenario: Comprobación obra a obra
- **WHEN** se compara cualquier indicador del informe con el cálculo sobre el ERP para cada obra
- **THEN** la diferencia es 0,00 € en todas las obras

### Requirement: Publicación
El informe MUST estar publicado en el área de trabajo de la demo del servicio Power BI.

#### Scenario: Informe en el servicio
- **WHEN** se abre el área de trabajo de la demo en el servicio Power BI
- **THEN** el informe aparece y muestra los mismos totales que en Power BI Desktop
