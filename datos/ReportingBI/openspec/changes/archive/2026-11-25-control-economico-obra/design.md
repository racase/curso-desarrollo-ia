## Context

La exploración del issue 01 identificó dos empresas: la operativa tiene 10 obras y la de pruebas una obra de formación. No hay todavía vistas de reporting ni proyecto PBIP. Este diseño es necesario por el cambio conjunto de SQL, modelo, cálculos temporales y validación; desarrolla la [propuesta](proposal.md) y la [especificación](specs/control-economico-obra/spec.md).

Fuentes comprobadas mediante `db/query.sh`:

| Tabla NAV (prefijo `CONSTRUCTORA DEMO$`) | Campos relevantes | Grano |
|---|---|---|
| `Job` | `No_`, `Description`, `Status`, `Person Responsible`, dimensiones globales 1 y 2 | Obra |
| `Job Task` | `Job No_`, `Job Task No_`, `Job Task Type`, `Description` | Obra y tarea |
| `Job Planning Line` | Obra, tarea, `Line No_`, `Line Type`, `Planning Date`, `Total Cost (LCY)`, `Line Amount (LCY)` | Línea de planificación |
| `Job Ledger Entry` | `Entry No_`, obra, tarea, `Entry Type`, `Type`, `Posting Date`, importes | Movimiento |
| `Job Progress Entry` | `Entry No_`, obra, tarea, `Measurement Date`, `Completion _` | Medición acumulada |
| `Dimension Value` | `Dimension Code`, `Code`, `Name` | Dimensión y código |
| `Resource` | `No_`, `Name` | Recurso; resuelve el jefe de obra |

Los datos reales llegan al 31/10/2026. Existen 789 líneas de planificación, 2.777 movimientos y 514 mediciones. No se detectaron mediciones duplicadas por obra, partida y fecha. Los significados utilizados se contrastaron con `tools/generate_seed.py`: estados 0/2/3 = Planificación/Abierta/Terminada; consumo/venta = 0/1; tareas 0/3/4 = detalle/cabecera/total. No se traducirán otras opciones sin confirmación.

## Goals / Non-Goals

**Objetivos:** indicadores reproducibles por obra y partida, indirectos previstos con avance global ponderado por venta, alertas estrictas superiores al 5 %, navegación coherente y conciliación SQL/DAX.

**Fuera de alcance:** issue 02, modificaciones de origen o del generador, previsiones a fin de obra, seguridad por filas, refresco programado y edición automática de visuales PBIR.

## Decisions

### 1. Separar hechos y preservar las obras sin actividad

Crear una vista por fichero con `CREATE OR ALTER VIEW [rpt].[Nombre]`:

| Fichero previsto | Vista y propósito |
|---|---|
| `sql/rpt/01_dim_obra.sql` | `DimObra`: obra, descripción, estado, delegación, tipo y responsable con etiquetas |
| `sql/rpt/02_dim_partida.sql` | `DimPartida`: clave compuesta explícita obra/tarea, solo tareas de detalle |
| `sql/rpt/03_fact_presupuesto.sql` | `FactPresupuesto`: importes por obra y partida; presupuesto completo y base de venta elegible para producción separados |
| `sql/rpt/04_fact_movimiento.sql` | `FactMovimiento`: coste y certificado agregados por obra, partida y fecha, con signo de negocio |
| `sql/rpt/05_fact_avance.sql` | `FactAvance`: avance por obra, partida y cierre mensual, hasta el corte |

`DimPartida` tendrá clave estable `Obra|Partida`, validando ausencia del separador en ambos códigos. Las claves presentes en hechos se contrastarán con dimensiones. Se conservará explícitamente `ObraClave` en los hechos. Los cálculos no unirán líneas de presupuesto con movimientos y mediciones sin agregarlas: esa alternativa multiplicaría filas e importes.

La delegación se resuelve con dimensión global 1 y `Dimension Code = DELEGACION`; tipo con dimensión global 2 y `TIPOOBRA`; jefe con `Person Responsible = Resource.No_`. Se usa la cabecera actual de obra como fuente común de filtros; no se modela historial de cambios de asignación.

### 2. Estrella con filtros unidireccionales y conexión parametrizada

El modelo usará relaciones 1:N desde `DimObra` y `DimPartida` a los hechos, sin relación entre ambas dimensiones, y `DimFecha` hacia movimientos y cierres de avance. Esto evita rutas ambiguas. `DimFecha`, marcada como calendario, cubrirá los años completos de los datos y del presupuesto; los indicadores ejecutados se limitarán al corte inicial.

Las consultas M usarán parámetros `Servidor` y `BaseDatos`, inicialmente `127.0.0.1,14330` y `NAVDEMO`. Crear tablas, relaciones, calendario, parámetros y medidas mediante el MCP. La persona acepta la licencia. Las medidas pertenecerán a sus hechos: presupuestos en `FactPresupuesto`, ejecución y márgenes en `FactMovimiento`, producción, avance y desviación en `FactAvance`; sin tabla de medidas independiente. Sus descripciones referenciarán el requisito concreto de la spec.

### 3. Mantener presupuestos completos y mediciones acumuladas

El presupuesto no se reduce a las fechas seleccionadas. SQL distinguirá importes con fecha válida de la revisión sin fecha de `OB-25004/06`. Esta última suma 28.000 € de coste y 35.000 € de venta al presupuesto completo; se excluyen los 35.000 € de la base de producción, conforme al criterio del generador. Se propone incluir los 28.000 € en el coste directo previsto proporcional al avance por pertenecer al presupuesto de coste aprobado; se deja identificado como supuesto de revisión.

`FactAvance` completará todos los cierres mensuales y partidas medibles, arrastrando la última medición conocida con fecha menor o igual al cierre; antes de la primera, cero. Las partidas medibles son las directas `01` a `09`, incluyendo las no iniciadas en el denominador del avance global. La partida `10` es indirecta. Una selección de fecha intermedia recuperará la última medición disponible hasta esa fecha, sin anticipar el cierre siguiente. No se sumarán las instantáneas de avance al agregar meses.

Las medidas acumuladas usarán el último día seleccionado, limitado al corte. Los flujos de coste y certificado mensuales suman movimientos del mes; producción mensual resta los acumulados al cierre actual y anterior. Calcular el avance sumando porcentajes o sumando instantáneas mensuales daría resultados incorrectos.

### 4. Aplicar el avance global por obra a los indirectos

Decisión confirmada por la persona:

`Avance global obra = Producción acumulada obra / Base de venta elegible obra`.

`Coste previsto directo = Presupuesto coste partida × Avance medido partida`.

`Coste previsto indirecto = Presupuesto indirectos obra × Avance global obra`.

DAX retirará solo el filtro de partida para obtener el avance global y conservará obra, corte y filtros de negocio. Si la obra se identifica únicamente por una fila de `DimPartida`, se trasladará su clave explícitamente al contexto de obra antes de retirar ese filtro. Los totales iterarán por obra y partida. Se descartan la media simple de avances y el avance propio de indirectos: las partidas tienen pesos económicos distintos y los indirectos no tienen mediciones.

### 5. Ratios, alertas y redondeo

El margen real confirmado es certificado menos coste real; el previsto es venta presupuestada menos coste presupuestado. Se propone expresar sus porcentajes sobre certificado y venta presupuestada, respectivamente. El pendiente conserva su signo y no equivale a deuda pendiente de cobro: esta última requeriría datos de cobros ajenos al alcance.

Desviación: `(Coste real − Coste previsto) / Coste previsto`. La alerta compara el valor sin redondeo de presentación con `0,05`, usando `>` y no `>=` ni valor absoluto. Si el previsto es cero, usar división segura y porcentaje vacío; con coste positivo, emitir «Coste sin avance». Con ambos costes cero no hay alerta.

Producir importes de negocio en `decimal(18,2)`; redondear producción y coste previsto por obra/partida antes de sumar, con redondeo comercial idéntico en SQL y DAX. Usar tipo decimal fijo en el modelo para evitar errores binarios en la frontera del 5 %. Los ratios se calculan después de agregar importes. Formatos de medidas: `#,0.00 €` y `0.0 %`, con cultura española.

### 6. Verificar contra NAV y entregar visuales a la persona

Cada vista se aplicará exclusivamente mediante `db/query.sh` y se contrastará con NAV por obra. DAX mediante MCP deberá coincidir con consultas independientes sobre las tablas de origen, no únicamente con las vistas. La evidencia futura recogerá valores SQL, valores DAX, diferencia, fecha y filtros; no se considera realizada durante la planificación.

La persona compone resumen, detalle con formato condicional basado en la medida de alerta y evolución mensual. Se comprobarán filtros, ordenación, pérdidas, casos sin actividad y publicación en el servicio. No se editará `Obras.Report/` desde el agente sin solicitud expresa.

## Risks / Trade-offs

- [Revisión sin fecha y sin historial de vigencia] → Separar bases presupuestarias y productivas, documentar el supuesto de coste previsto y no inventar su mes de aprobación.
- [Selección aislada de indirectos altera el denominador] → Obtener avance por obra retirando filtros de partida y probarlo también en totales de varias obras.
- [Mediciones terminadas sin filas posteriores] → Arrastrar la última medición; validar producción mensual cero tras terminar.
- [Diferencias por precisión o filtros] → Redondeo por partida, tipos fijos, ratios agregados y contraste SQL/DAX con cortes y filtros iguales.
- [Desktop sobrescribe cambios externos] → Usar MCP; solicitar cierre de Desktop antes de cualquier edición directa de TMDL.
- [Permisos o licencia de publicación no disponibles] → Mantener abierta la tarea de publicación hasta que la persona aporte evidencia; no aceptar licencias ni guardar credenciales.

## Migration Plan

1. Implementar y conciliar las cinco vistas con `reporting`, sin alterar `dbo` ni `db/init/`.
2. Crear el PBIP y modelo mediante MCP, cargar datos y validar medidas contra NAV.
3. La persona monta visuales, abre y actualiza Desktop y publica la versión validada.
4. Si falla la validación, no publicar; restaurar definiciones previas de vistas/modelo bajo control de versiones y actualizar de nuevo. No borrar el volumen SQL ni regenerar datos para corregir indicadores.

## Open Questions

- Confirmar en la revisión los supuestos de porcentajes de margen y de coste previsto de la revisión sin fecha; las tres decisiones consultadas (ponderación por venta, margen real y alerta sin avance) ya están confirmadas.
- Confirmar el área de trabajo de la demo y acceso de publicación al llegar a esa tarea.
- Las opciones no utilizadas por los indicadores (`Blocked`, `Dimension Value Type`) permanecen sin traducción funcional; si se incorporan al informe, preguntar su significado antes.
