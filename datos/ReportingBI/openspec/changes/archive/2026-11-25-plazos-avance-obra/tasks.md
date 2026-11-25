## 1. Preparación SQL y protección del issue 01

Esta planificación cubre el issue 02 completo. La próxima aplicación se limita a los grupos 1–3; los grupos 4–6 requieren una nueva petición. No marcar estos últimos como completados al terminar las vistas.

- [x] 1.1 Comprobar estado saludable, empresa operativa en `[Company]`, esquemas y opciones utilizadas mediante `db/query.sh`; registrar corte y anomalías concretas en planificación, mediciones e hitos.
- [x] 1.2 Guardar huellas de `sql/rpt/01_…` a `05_…` y definiciones de las cinco vistas del issue 01 para comprobar su preservación al finalizar.

## 2. Vistas nuevas, una por fichero

- [x] 2.1 Crear `06_fact_planificacion.sql` con `rpt.FactPlanificacion`, grano obra/partida/fecha y venta total y base elegible separadas; conservar la revisión sin fecha con base cero. Aplicar con `db/query.sh -f` y conciliar importes por obra y fecha contra NAV.
- [x] 2.2 Crear `07_fact_medicion_avance.sql` con `rpt.FactMedicionAvance`, mediciones acumuladas identificables y avance en fracción. Aplicar con `db/query.sh -f` y comparar registros, claves, fechas y porcentajes contra NAV por obra.
- [x] 2.3 Crear `08_fact_hito.sql` con `rpt.FactHito`, claves obra/código, secuencia, descripción y fechas normalizadas. Aplicar con `db/query.sh -f` y comparar cada hito y recuentos por obra contra NAV.
- [x] 2.4 Crear `09_dim_corte_datos.sql` con `rpt.DimCorteDatos`, una fila y máximo movimiento válido sin fecha fija. Aplicar con `db/query.sh -f` y contrastar el corte con NAV y los máximos por obra.

## 3. Conciliación SQL y entrega de esta fase

- [x] 3.1 Guardar consultas independientes a `dbo` y comparaciones con las vistas en `validacion_vistas.sql`; registrar resultados por las 10 obras y total en `validacion.md`, con diferencias monetarias cero y claves, fechas y mediciones coincidentes.
- [x] 3.2 Contrastar usando las vistas frente a NAV el avance planificado y real en cierres mensuales, conservando denominador completo y última medición; comprobar exclusión de 35.000 € sin fecha, obra futura y concordancia con el avance del 01 en su horizonte común.
- [x] 3.3 Verificar mediante consultas de validación los cuatro estados de hito, días individuales, contador solo pendiente vencido, vencimiento igual al corte y cumplimiento posterior a un corte histórico, sin crear objetos de prueba en NAV.
- [x] 3.4 Confirmar huellas y definiciones del issue 01 intactas; entregar la conciliación SQL dejando explícitamente pendientes modelo, informe y publicación.

## 4. Modelo compartido — fase posterior

- [x] 4.1 Mediante MCP de Power BI, importar las nuevas vistas al modelo activo, reutilizar dimensiones y calendario y configurar relaciones sin alterar los indicadores económicos ni su corte inicial.
- [x] 4.2 Crear medidas descritas en español para corte dinámico, avances ponderados, desviación en puntos e índice vacío con planificado cero, respetando filtros de obra y horizonte completo del denominador.
- [x] 4.3 Crear medidas de estados y días de hitos a corte histórico, contador pendiente retrasado y máximo de días pendientes, separando cumplidos tarde y evitando filtros temporales que oculten hitos.

## 5. Informe y validación integrada — fase posterior

- [x] 5.1 Añadir al mismo informe curva S mensual, ranking de obras y detalle de hitos; mantener filtros compartidos y explicar visiblemente la exclusión del presupuesto sin fecha.
- [x] 5.2 Comparar SQL independiente frente a DAX ejecutado por MCP obra a obra, total y filtros relevantes en cortes mensuales e históricos; guardar diferencias cero a la precisión acordada y comprobar que el issue 01 mantiene sus resultados.
- [x] 5.3 Validar apertura y actualización en Desktop, sincronización de filtros, fechas vacías, mes parcial, índice sin denominador y casos de hitos futuros y tardíos; guardar evidencias visuales.

## 6. Publicación y cierre — fase posterior

- [ ] 6.1 Confirmar destino y registrar publicación y comprobación por la persona en el área de trabajo de la demo, sin persistir credenciales.
- [x] 6.2 Verificar todos los criterios del issue 02 y preparar el archivo del cambio con evidencias completas, sin declarar publicación ni validaciones no realizadas.

## Cierre autorizado

El 25/11/2026 el usuario confirma la validación local y solicita archivar y hacer commit de todo ReportingBI. La tarea 6.1 permanece pendiente: no se ha confirmado publicación. Archivo autorizado con esta excepción; evidencias y límites en `validacion_informe.md`.
