# Design

## Context

Motivación y alcance en `proposal.md`; requisitos en `specs/modelo-obras` y
`specs/control-economico`.

Lo observado al explorar `NAVDEMO` (usuario `reporting`, 24/11/2026):

- Empresas (`[Company]`): `CONSTRUCTORA DEMO` (la real: 10 obras, 2.777 movimientos, 789
  líneas de presupuesto, 514 mediciones) y `CONSTRUCTORA DEMO PRUEBAS` (1 obra y 3
  movimientos basura).
- Integridad: todos los movimientos, líneas de presupuesto y mediciones apuntan a un capítulo
  de imputación existente. Ningún coste cae en un capítulo sin presupuesto.
- Fecha de corte: el último `Posting Date` es el 31/10/2026.

| Tabla NAV | Campos usados | Lo que significan los enteros (opciones estándar de NAV, coherentes con los datos) |
|---|---|---|
| `Job` | `No_`, `Description`, `Bill-to Customer No_`, `Starting Date`, `Ending Date`, `Status`, `Person Responsible`, `Global Dimension 1 Code`, `Global Dimension 2 Code` | `Status`: 0 Planificación, 1 Oferta, 2 Abierta, 3 Terminada. Dimensión 1 = `DELEGACION`, dimensión 2 = `TIPOOBRA` |
| `Dimension Value` | `Dimension Code`, `Code`, `Name` | Nombres de delegación y tipo de obra |
| `Resource` | `No_`, `Name` | `Person Responsible` apunta a recursos `JO01`–`JO04` (jefes de obra) |
| `Customer` | `No_`, `Name` | Cliente de la obra |
| `Job Task` | `Job No_`, `Job Task No_`, `Description`, `Job Task Type` | 0 imputación (`01`–`10`), 3 inicio de total (`00`), 4 fin de total (`99`, `Totaling` = `01..10`). Los mismos 10 capítulos, con el mismo nombre, en todas las obras |
| `Job Planning Line` | `Line Type`, `Total Cost (LCY)`, `Line Amount (LCY)`, `Planning Date` | `Line Type`: 0 Presupuesto (solo coste), 1 Facturable, 2 Ambos. No hay líneas tipo 1. Las 147 líneas tipo 0 son el capítulo `10` *Costes indirectos*, sin precio de venta. Una línea tiene `Planning Date` = `1753-01-01` |
| `Job Ledger Entry` | `Entry Type`, `Posting Date`, `Total Cost (LCY)`, `Line Amount (LCY)` | `Entry Type`: 0 Consumo (coste, siempre positivo), 1 Venta (certificación, `Line Amount` siempre negativo) |
| `Job Progress Entry` (a medida, 50000) | `Job No_`, `Job Task No_`, `Measurement Date`, `Completion _` | Porcentaje acumulado de 0 a 100, medido el último día de cada mes. No baja nunca y no hay dos mediciones el mismo día. El capítulo `10` no tiene mediciones |

El `Obras.pbip` actual es la prueba del MCP: una tabla leída de `dbo` con el servidor escrito
en la consulta, tablas de fechas automáticas activas y dos páginas de prueba.

## Goals / Non-Goals

**Goals:**
- Que todo el cálculo pesado (signos, opciones, avance acumulado, producción mensual) quede
  en vistas SQL comprobables con una consulta, y que DAX solo sume y divida.
- Que todas las medidas sean aditivas por obra, capítulo y mes, para que cuadren al céntimo
  en cualquier corte.
- Dejar el modelo listo para que el issue 02 añada tablas sin tocar las existentes.

**Non-Goals:**
- Granularidad diaria de la producción: el avance se mide a final de mes, así que la
  producción se da por meses.
- Modo DirectQuery o refresco en el servicio.

## Decisions

### 1. Vistas de reporting

| Fichero | Vista | Grano | Columnas |
|---|---|---|---|
| `01_dim_obra.sql` | `rpt.DimObra` | obra | `ObraId`, `Obra` (código + descripción), `Descripcion`, `Cliente`, `Delegacion`, `TipoObra`, `JefeObra`, `Estado`, `EstadoOrden`, `FechaInicio`, `FechaFin` |
| `02_dim_capitulo.sql` | `rpt.DimCapitulo` | código de capítulo | `CapituloId`, `Capitulo` (código + nombre), `EsCosteIndirecto` |
| `03_fact_presupuesto.sql` | `rpt.FactPresupuesto` | obra × capítulo | `ObraId`, `CapituloId`, `PresupuestoCoste`, `PresupuestoVenta` |
| `04_fact_movimiento.sql` | `rpt.FactMovimiento` | obra × capítulo × día | `ObraId`, `CapituloId`, `Fecha`, `CosteReal`, `Certificado` |
| `05_fact_produccion.sql` | `rpt.FactProduccion` | obra × capítulo × fin de mes | `ObraId`, `CapituloId`, `Fecha`, `ProduccionMes`, `CostePrevistoMes` |

Todas filtran `CONSTRUCTORA DEMO` (el prefijo de tabla), solo capítulos `Job Task Type` = 0,
pasan `1753-01-01` a NULL, los textos vacíos a `'(sin asignar)'` y los importes a
`decimal(18,2)` con el signo de negocio (certificado = `-[Line Amount (LCY)]`).

- **`DimCapitulo` por código y no por obra × capítulo.** Los 10 códigos tienen el mismo nombre
  en todas las obras, así que una dimensión de 10 filas permite comparar capítulos entre
  obras y deja un modelo en estrella simple (`ObraId` y `CapituloId` en cada hecho).
  Alternativa descartada: clave compuesta obra|capítulo, que duplica la obra en dos
  dimensiones.
- **`FactPresupuesto` sin fecha.** El presupuesto se muestra siempre completo (spec,
  *Indicadores por periodo*). La planificación en el tiempo es del issue 02, que añadirá su
  propio hecho a partir de `Planning Date`.
- **`FactProduccion` guarda incrementos, no acumulados.** Para cada obra, capítulo y fin de
  mes, desde la primera medición de la obra hasta la fecha de corte, se toma el avance de la
  última medición hasta ese día (0 si no hay), se calcula el acumulado redondeado a céntimos
  y se guarda la diferencia con el mes anterior. Así `Producción` es una suma simple, el
  total de los meses es el valor a la fecha de corte y no hay medidas semiaditivas.
  Alternativa descartada: guardar el avance acumulado y usar `LASTNONBLANK` en DAX, que
  complica cada medida y la prueba.
- **Costes indirectos en SQL.** En los capítulos sin presupuesto de venta, el coste previsto
  acumulado es `PresupuestoCoste × (producción acumulada de la obra / presupuesto de venta de
  la obra)`, calculado sobre el mismo grano de meses (decisión de dirección del 24/11/2026).
- **Redondeo.** Se redondea el acumulado de cada obra × capítulo × mes, y el incremento es la
  resta de dos acumulados redondeados. La suma de incrementos da exactamente el acumulado
  redondeado, y la prueba SQL usa el mismo criterio.

### 2. Modelo semántico

- Importación desde las vistas `rpt` con `Sql.Database(Servidor, BaseDatos)`. Los parámetros
  `Servidor` (`127.0.0.1,14330`) y `BaseDatos` (`NAVDEMO`) son de Power Query. Las
  credenciales las guarda Power BI Desktop en el equipo, no en el proyecto.
- Tabla `Calendario` calculada en DAX, del 01/01/2025 al 31/12/2027 (cubre presupuesto,
  movimientos y mediciones), con `Fecha`, `Año`, `MesNumero`, `Mes` y `AñoMes`, marcada como
  tabla de fechas. Se desactivan las tablas de fechas automáticas.
- Relaciones de uno a varios, en una dirección: `DimObra` y `DimCapitulo` con los tres
  hechos, y `Calendario` con `FactMovimiento` y `FactProduccion`. `FactPresupuesto` no se
  relaciona con `Calendario`.

### 3. Medidas

Cada una con descripción tomada de la spec, en la tabla de hechos de la que sale.

| Tabla | Medida | Definición | Formato |
|---|---|---|---|
| `FactPresupuesto` | Presupuesto de coste | `SUM(PresupuestoCoste)` | `#,0.00 €` |
| | Presupuesto de venta | `SUM(PresupuestoVenta)` | `#,0.00 €` |
| | Margen previsto | venta − coste | `#,0.00 €` |
| | Margen previsto % | `DIVIDE(margen previsto, presupuesto de venta)` | `0.0 %` |
| `FactMovimiento` | Coste real | `SUM(CosteReal)` | `#,0.00 €` |
| | Certificado | `SUM(Certificado)` | `#,0.00 €` |
| | Fecha de corte | `MAX(Fecha)` sin ningún filtro | `dd/mm/yyyy` |
| `FactProduccion` | Producción | `SUM(ProduccionMes)` | `#,0.00 €` |
| | Coste previsto | `SUM(CostePrevistoMes)` | `#,0.00 €` |
| | Pendiente de certificar | producción − certificado | `#,0.00 €` |
| | Desviación de coste | coste real − coste previsto | `#,0.00 €` |
| | Desviación de coste % | `DIVIDE(desviación, coste previsto)` | `0.0 %` |
| | Margen real | producción − coste real | `#,0.00 €` |
| | Margen real % | `DIVIDE(margen real, producción)` | `0.0 %` |
| | Avance de obra % | `DIVIDE(producción, presupuesto de venta)` | `0.0 %` |
| | Color desviación | `"#C62828"` si desviación % > 5 %, si no en blanco | texto |
| | Color margen real | `"#C62828"` si margen real < 0, si no en blanco | texto |

- Los porcentajes de una obra o del total son cociente de sumas, nunca media de porcentajes.
- Las reglas de color quedan en medidas para que se puedan probar con DAX. La persona las usa
  como formato condicional por valor de campo.

### 4. Informe

Lo monta la persona en Power BI Desktop. La lista de visuales está en `tasks.md`. Se borran
las páginas de prueba actuales.

### 5. Prueba

Un script `sql/test/control_economico.sql` calcula cada indicador por obra **directamente
sobre las tablas NAV**, sin usar las vistas `rpt`, para que la prueba sea independiente de la
implementación. El agente ejecuta la misma consulta en DAX con el MCP y deja la tabla de
comparación obra a obra en `prueba.md`, dentro de este cambio. Diferencia exigida: 0,00 €.

## Risks / Trade-offs

- [Un capítulo con el mismo código y distinto nombre en otra obra] → `DimCapitulo` toma un
  nombre por código. La vista comprueba que hay un solo nombre por código y la prueba lo
  verifica.
- [Mediciones a mitad de mes] → Hoy todas son de fin de mes. La producción de una medición a
  mitad de mes se asignaría a su fin de mes. Se anota en la descripción de `Producción`.
- [Editar TMDL con Power BI Desktop abierto] → Desktop sobrescribe los cambios externos. Todo
  el modelo se hace con el MCP contra Desktop abierto. Si hay que tocar TMDL a mano, antes se
  pide cerrar Desktop.
- [Las tablas de fechas automáticas reaparecen] → Se desactivan en las opciones del fichero
  antes de cargar las vistas.
- [La línea de presupuesto sin fecha] → No afecta a este cambio, porque el presupuesto no se
  filtra por fecha. El issue 02 tendrá que decidir qué hace con ella.

## Migration Plan

1. Aplicar las vistas en orden (`01` a `05`) con `db/query.sh -f`.
2. Rehacer `Obras.pbip` sobre las vistas y quitar la tabla y las páginas de prueba.
3. Publicar desde Power BI Desktop en el área de trabajo de la demo.

Marcha atrás: volver al commit anterior del proyecto y borrar las vistas con
`DROP VIEW [rpt].[...]`. Las tablas NAV no se tocan.

## Open Questions

- Nombre exacto del área de trabajo de la demo en el servicio: se confirma al publicar.
