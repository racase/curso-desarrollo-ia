## Context

El issue 02 amplía `ObrasModel/Obras.pbip`. La exploración de NAVDEMO identificó 10 obras operativas, 514 registros de la tabla a medida `Job Progress Entry` y 50 hitos en la tabla a medida `Job Milestone`. El último movimiento contable es del 31/10/2026. Son referencias de la exploración, que deben comprobarse al aplicar, no constantes de implementación.

`FactPresupuesto` conserva importes por partida, pero pierde el reparto por fecha. `FactAvance` contiene instantáneas mensuales con corte fijo; no permite ampliar el horizonte sin modificar el issue 01. Se conservarán ambas vistas y todos los objetos existentes.

## Goals / Non-Goals

**Objetivos:** fuentes trazables para planificación, mediciones e hitos; comparaciones ponderadas por venta; corte obtenido del ERP; conciliación por obra. La siguiente aplicación se limita a las nuevas vistas y sus evidencias SQL.

**Fuera de alcance:** Gantt, previsión de fin, recalcular planificación, cambiar fórmulas económicas o las vistas 01–05, crear otro modelo o almacenar credenciales. Las fases de modelo, informe y publicación quedan planificadas pero no autorizadas para la aplicación SQL.

## Decisions

### Cuatro vistas nuevas con grano explícito

| Fichero previsto | Vista | Grano y contenido |
| --- | --- | --- |
| `sql/rpt/06_fact_planificacion.sql` | `rpt.FactPlanificacion` | Obra × partida × fecha de planificación, incluyendo grupo con fecha NULL. Venta de líneas 1/2 de tareas de detalle directas, importe total y base elegible fechada separados. |
| `sql/rpt/07_fact_medicion_avance.sql` | `rpt.FactMedicionAvance` | Un registro por `Job Progress Entry.Entry No_`, obra, partida, fecha y avance como fracción. Solo tareas de detalle medibles, sin indirectos. |
| `sql/rpt/08_fact_hito.sql` | `rpt.FactHito` | Obra × código de hito: secuencia, descripción, fecha planificada y fecha real. |
| `sql/rpt/09_dim_corte_datos.sql` | `rpt.DimCorteDatos` | Una fila con clave constante y máximo `Posting Date` válido de la empresa operativa; NULL si no existen movimientos válidos. |

Las claves de obra y partida serán compatibles con `DimObra` y `DimPartida`. Hito tendrá clave compuesta obra/código. Las fechas vacías pasan a NULL, los importes a `decimal(18,2)` y el avance a `decimal(9,6)`. Se conservará la revisión sin fecha para explicar la exclusión: `VentaPlanificada` incluye su importe y `BaseVentaElegible` es cero para esa fila. No se eliminará del presupuesto económico.

Alternativas descartadas: modificar `FactPresupuesto` altera su grano; extender `FactAvance` contraviene la restricción de preservar las vistas del 01; materializar un estado único de hito en SQL impide consultar correctamente cortes históricos. Se exponen fechas y mediciones, dejando los cálculos dependientes del filtro para DAX.

### Avances y corte

La base elegible de cada partida es la venta de líneas 1/2 con fecha válida, sin indirectos. Planificado(t) = venta elegible con fecha ≤ t / venta elegible completa. Real(t) = suma de base elegible por partida × última medición conocida a t / base elegible completa. Ambos consideran todas las partidas elegibles, aunque aún no tengan actividad. No se promedian porcentajes por partida u obra. Con base cero, ambos avances son cero; el índice real/planificado queda vacío cuando el planificado es cero.

El corte efectivo de plazos es el menor entre el último día seleccionado y el corte ERP. Sin movimientos, no se inventará un corte: los indicadores temporales quedarán vacíos. Cada punto mensual usa el cierre del mes limitado al corte efectivo, incluyendo el mes parcial. Las mediciones son acumuladas: antes de la primera se usa cero y después se arrastra la última. Si coinciden fechas se elige el mayor `Entry No_` de forma determinista.

El avance real del nuevo análisis reproduce el criterio económico sin reutilizar su límite fijo de 2026. El denominador abarca toda la planificación elegible, incluida la futura. La desviación es `(real − planificado) × 100` puntos porcentuales. El ranking ordena esa desviación ascendente; los empates por código de obra.

### Hitos a la fecha consultada

Un hito está cumplido al corte solo si su fecha real válida es ≤ corte. Si está cumplido, se clasifica a tiempo cuando real ≤ planificada, y con retraso en caso contrario. Si no está cumplido, está retrasado únicamente cuando planificada < corte; en otro caso está pendiente. Una fecha real posterior al corte se trata como no cumplida en ese corte histórico.

El contador «Hitos retrasados» incluye solo los pendientes vencidos. Los días por hito son `max(0, real − planificada)` si está cumplido, o `max(0, corte − planificada)` si sigue pendiente. No se sumarán demoras solapadas: el detalle mostrará días por hito y el resumen distinguirá número pendiente retrasado y máximo de días pendientes; los cumplidos tarde se mostrarán por separado.

### Extensión posterior del proyecto

Tras autorización específica, importar las cuatro vistas mediante el MCP al modelo existente, reutilizar dimensiones y calendario, y crear medidas en las tablas de hechos. La fecha planificada de hitos podrá relacionarse activamente con calendario y la fecha real de forma inactiva; los cálculos de estado al corte deberán conservar todos los hitos de las obras filtradas, eliminando el filtro temporal sobre la lista de hitos. La curva S y los acumulados deben conservar el denominador completo y evitar que la relación temporal elimine mediciones anteriores.

El informe explicará: «El avance planificado excluye las líneas de presupuesto sin fecha del importe acumulado y de su base total; el presupuesto económico las conserva». Mantendrá los filtros compartidos y añadirá curva S, ranking y detalle de hitos. La aplicación SQL no editará PBIP, TMDL, DAX ni PBIR.

## Risks / Trade-offs

- Dos rutas de avance → comparar el nuevo real con el del issue 01 en su horizonte común, sin cambiar sus fórmulas ni sus vistas.
- Fechas reales futuras respecto de un corte histórico → comprobar estados antes, durante y después del cumplimiento.
- Importe sin fecha oculto → conservar la fila NULL y su importe, excluir su base y explicar la regla en el informe.
- Opciones NAV desconocidas o hitos sin planificación → detener la decisión afectada y preguntar; no asignar estados inventados. Si aparecen porcentajes fuera de 0–100 o claves huérfanas, documentarlos antes de continuar.

## Migration Plan

1. Guardar huellas de los cinco SQL existentes y definiciones de sus vistas; comprobar empresa y datos vigentes con `db/query.sh`.
2. Crear y aplicar únicamente los ficheros 06–09 con `docker compose exec -T db bash db/query.sh -f sql/rpt/NN_nombre.sql`, en estado saludable.
3. Conciliar cada vista contra `dbo` obra a obra y guardar consultas, resultados y diferencias en este cambio. Verificar de nuevo las huellas del issue 01.
4. Dejar pendientes modelo, informe, SQL/DAX, Desktop y publicación hasta una nueva petición.

Las vistas nuevas no sustituyen objetos previos. Si falla una validación, no conectar consumidores y corregir/reaplicar exclusivamente la vista nueva afectada. Cualquier retirada de vistas se decidirá expresamente; no se borran objetos automáticamente.

## Open Questions

No hay decisiones funcionales pendientes que bloqueen las vistas. Antes de publicar habrá que confirmar el destino y acceso al área de trabajo. El tratamiento de nuevos valores NAV o datos anómalos se consultará si aparecen al aplicar.
