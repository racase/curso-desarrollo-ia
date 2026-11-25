# Spec Delta

## Purpose

Comparar la planificación temporal de venta, las mediciones reales y los hitos de las obras de CONSTRUCTORA DEMO para detectar retrasos con indicadores trazables y conciliados contra NAV.

## ADDED Requirements

### Requirement: Fuentes SQL independientes y trazables

El sistema MUST exponer planificación, mediciones, hitos y corte del ERP mediante cuatro vistas nuevas en `rpt`, una por fichero `sql/rpt/06_…` a `09_…`, sin modificar los ficheros ni las definiciones de las cinco vistas del issue 01. MUST usar exclusivamente la empresa operativa `CONSTRUCTORA DEMO`, conservar claves compatibles con las dimensiones existentes y consultar/aplicar SQL exclusivamente mediante `db/query.sh`, por fichero o entrada estándar. Los despliegues MUST usar `db/query.sh -f`.

#### Scenario: Aplicación limitada a vistas nuevas
- **WHEN** se aplica la fase SQL del cambio
- **THEN** se crean o actualizan únicamente `FactPlanificacion`, `FactMedicionAvance`, `FactHito` y `DimCorteDatos`, se guardan evidencias NAV por obra y las vistas del issue 01 conservan sus definiciones.

### Requirement: Planificación por fecha y exclusión de presupuesto sin fecha

`FactPlanificacion` MUST conservar el grano obra, partida y fecha, sumando la venta de líneas tipo 1/2 de tareas de detalle directas. MUST convertir `1753-01-01` a NULL y conservar separadamente `VentaPlanificada` y `BaseVentaElegible`: la primera conserva la venta sin fecha y la segunda vale cero para esas filas. Los importes MUST ser `decimal(18,2)`.

El avance planificado MUST dividir la venta elegible fechada hasta el corte entre la venta elegible de todo el horizonte de las partidas seleccionadas, excluyendo indirectos y presupuesto sin fecha de ambos términos. Con denominador cero MUST ser cero. El informe MUST explicar la exclusión y aclarar que el presupuesto económico conserva esos importes.

#### Scenario: Revisión sin fecha
- **WHEN** se consulta la revisión de `OB-25004`, partida `06`
- **THEN** su venta de 35.000 € se conserva con fecha vacía y base elegible cero, sin incrementar el acumulado ni el denominador del avance planificado.

#### Scenario: Planificación futura en el denominador
- **WHEN** una obra tiene 100.000 € elegibles previstos hasta el corte y 300.000 € después
- **THEN** su avance planificado es 25 %, sin eliminar los importes futuros del denominador.

### Requirement: Mediciones acumuladas y avance real ponderado

`FactMedicionAvance` MUST conservar cada registro de la tabla a medida `Job Progress Entry` con su identificador, obra, partida, fecha y `Completion _` convertido de escala 0–100 a fracción `decimal(9,6)`. MUST limitarse a tareas de detalle directas y convertir las fechas vacías a NULL. El cálculo MUST usar la última medición válida de cada partida hasta el corte, desempatar por mayor `Entry No_`, usar cero antes de la primera medición y conservar la última cuando no haya nuevas.

El avance real MUST dividir la suma de base de venta elegible por partida multiplicada por su última medición entre la base elegible completa. MUST usar la misma base que el planificado; no MUST sumar mediciones acumuladas ni promediar porcentajes sin ponderar. Con base cero MUST devolver cero.

#### Scenario: Medición acumulada y arrastre
- **WHEN** una partida registra 20 % y después 50 %, sin mediciones posteriores
- **THEN** aporta 50 % a los cortes posteriores, nunca 70 %.

#### Scenario: Conjunto ponderado y obra sin empezar
- **WHEN** dos partidas tienen bases de 100.000 € y 300.000 €, con avances 100 % y 0 %
- **THEN** el avance conjunto es 25 %; una obra sin ninguna medición conserva avance real cero.

### Requirement: Corte ERP y consultas históricas

`DimCorteDatos` MUST devolver una fila con el máximo `Job Ledger Entry.Posting Date` válido de la empresa operativa, convertido a fecha; sin movimientos válidos MUST devolver corte NULL. El análisis de plazos MUST usar el menor entre el final seleccionado y ese corte, sin una fecha máxima fija. Sin corte ERP MUST mostrar los indicadores temporales vacíos. La curva mensual MUST usar el cierre de cada mes limitado al corte efectivo y admitir un último mes parcial.

#### Scenario: Corte de la exploración y nuevos movimientos
- **WHEN** el máximo movimiento sigue siendo 31/10/2026 y se selecciona una fecha posterior
- **THEN** el corte efectivo es 31/10/2026; un movimiento posterior válido amplía el corte disponible tras actualizar sin editar SQL ni DAX.

### Requirement: Hitos a la fecha de corte

`FactHito` MUST conservar un registro por obra y código de `Job Milestone`, con secuencia, descripción y fechas planificada y real. MUST transformar fechas vacías en NULL. Un hito MUST considerarse cumplido solo si su fecha real válida es menor o igual al corte. Si está cumplido, MUST clasificarse «Cumplido a tiempo» si real ≤ planificada y «Cumplido con retraso» en caso contrario. Si no está cumplido, MUST ser «Retrasado» solo si planificada < corte y «Pendiente» en otro caso.

El contador de hitos retrasados MUST incluir exclusivamente pendientes vencidos. Los días por hito MUST ser el máximo entre cero y la diferencia en días desde la fecha planificada hasta la real si está cumplido, o hasta el corte en otro caso. El resumen MUST mostrar separadamente el máximo de días de hitos pendientes retrasados, cero si no hay ninguno, sin sumar demoras solapadas ni mezclarlas con las de hitos cumplidos.

#### Scenario: Vencimiento estricto
- **WHEN** un hito sin fecha real tiene fecha planificada igual al corte
- **THEN** aparece pendiente con cero días de retraso y solo pasa a retrasado en un corte posterior.

#### Scenario: Cumplimiento tardío separado
- **WHEN** un hito se cumple diez días después de lo previsto y antes del corte
- **THEN** muestra «Cumplido con retraso» y diez días, pero no incrementa el contador de pendientes retrasados.

#### Scenario: Fecha real posterior al corte histórico
- **WHEN** la fecha planificada ya pasó al corte consultado pero la fecha real está después de ese corte
- **THEN** el hito aparece retrasado con días hasta el corte, sin anticipar su cumplimiento.

#### Scenario: Obra futura
- **WHEN** una obra no tiene mediciones y todos sus hitos están planificados después del corte
- **THEN** su avance real es cero y sus hitos están pendientes, sin contarse como retrasados.

### Requirement: Indicadores y presentación compartida

El sistema MUST calcular desviación como `(real − planificado) × 100` en puntos porcentuales e índice de plazo como real/planificado, vacío cuando planificado sea cero. MUST recalcular los conjuntos a partir de importes ponderados, sin medias de porcentajes por obra. El informe MUST incluir curva S mensual, ranking ascendente de desviación con desempate por código y detalle de hitos con días individuales. MUST ampliar el mismo proyecto PBIP y mantener los filtros de obra, delegación, tipo, jefe y estado del informe económico, sin cambiar sus indicadores.

#### Scenario: Retraso visible
- **WHEN** una obra tiene avance real del 60 % y planificado del 80 %
- **THEN** muestra desviación −20 puntos, índice 0,75 y línea real por debajo de la planificada.

### Requirement: Conciliación y entrega por fases

Cada vista MUST conciliarse contra consultas independientes sobre tablas `dbo` de NAV obra a obra, incluyendo obras sin actividad, claves, fechas, importes y mediciones. Las diferencias monetarias MUST ser cero al céntimo; mediciones MUST coincidir a la precisión publicada y fechas/recuentos exactamente. MUST documentarse corte, SQL, resultados y excepciones en el cambio.

La siguiente aplicación MUST limitarse a las vistas nuevas y estas evidencias; no MUST modificar modelo ni informe. En una fase posterior autorizada, el modelo MUST editarse mediante MCP de Power BI y las medidas MUST contrastarse SQL/DAX con diferencia cero en importes y precisión común de porcentajes. El cierre completo del issue MUST incluir apertura, actualización, filtros, explicación de exclusiones y publicación por la persona en el área de trabajo de la demo, sin guardar credenciales.

#### Scenario: Fase SQL finalizada
- **WHEN** las cuatro vistas nuevas están aplicadas y conciliadas
- **THEN** las tareas SQL pueden marcarse completas y las de modelo, informe y publicación permanecen pendientes, sin declarar terminado el informe del issue 02.
