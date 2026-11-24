# Proposal

## Why

Dirección revisa cada mes la situación económica de las obras con Excel exportados a mano
desde Navision, con criterios distintos según el jefe de obra y días de retraso. Necesita un
informe único de Power BI, conectado a la base del ERP, que diga en qué obras y capítulos se
pierde dinero y qué obra ejecutada falta por certificar (issue
`docs/issues/01-control-economico.md`).

## What Changes

- Vistas de reporting en el esquema `rpt` (`sql/rpt/`) que traducen las tablas NAV de la
  empresa `CONSTRUCTORA DEMO` a un modelo en estrella: obras, capítulos, presupuesto,
  movimientos reales (coste y certificación) y producción mensual según el avance medido.
- Definiciones de negocio escritas en la spec, que dirección usará a partir de ahora:
  producción, coste previsto para la obra producida, desviación de coste, obra pendiente de
  certificar y margen previsto y real.
- Modelo semántico `Obras.SemanticModel` construido sobre las vistas `rpt`, con parámetros
  `Servidor` y `BaseDatos`, tabla de calendario propia, relaciones y medidas en español con
  formato español.
- Informe `Obras.Report` con tres páginas: resumen, detalle obra × capítulo y evolución
  mensual, con filtros por delegación, tipo de obra, jefe de obra y estado. Los visuales los
  monta la persona en Power BI Desktop; el cambio fija qué debe mostrar cada página.
- Prueba de cada medida: su valor en DAX coincide con el calculado en SQL sobre las tablas
  NAV, obra a obra.
- Publicación en el área de trabajo de la demo.
- **BREAKING**: sustituye el proyecto de prueba del MCP que hay hoy en `Obras.pbip` (tabla
  `CONSTRUCTORA DEMO$Job` leída de `dbo` con el servidor escrito en la consulta, tablas de
  fechas automáticas y páginas de prueba).

## Capabilities

### New Capabilities

- `modelo-obras`: base común de los informes de obra: conexión parametrizada al ERP, obras y
  sus atributos (delegación, tipo, jefe de obra, estado), capítulos, calendario, fecha de
  corte y filtros compartidos. El issue 02 la ampliará.
- `control-economico`: indicadores económicos por obra y capítulo (presupuesto, coste real,
  producción, certificado, pendiente de certificar, márgenes y desviación de coste), sus
  definiciones y las páginas del informe que los muestran.

### Modified Capabilities

Ninguna: es el primer cambio del proyecto.

## Impact

- Base `NAVDEMO`: solo se crean vistas en `rpt`. Las tablas NAV no se modifican.
- `sql/rpt/`: ficheros nuevos, una vista por fichero.
- `Obras.SemanticModel/` y `Obras.Report/`: se rehacen. Se pierde el contenido de prueba
  actual.
- Servicio Power BI: se publica un modelo e informe en el área de trabajo de la demo, en modo
  importación y sin refresco programado.
- Fuera de alcance, como dice el issue: plazos e hitos (issue 02), previsiones a fin de obra,
  seguridad por filas y refresco programado.
