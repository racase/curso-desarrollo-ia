## Why

Dirección necesita comparar las obras y partidas con criterios económicos únicos y cifras conciliadas con NAV, en lugar de exportaciones manuales. El [issue 01](../../../../docs/issues/01-control-economico.md) requiere detectar pérdidas, sobrecostes y producción pendiente de certificar a 31/10/2026.

## What Changes

- Preparar vistas de reporting de la empresa `CONSTRUCTORA DEMO` y un modelo semántico en estrella con presupuesto, coste real, producción, certificado, pendiente de certificar y márgenes.
- Valorar el coste previsto directo con el avance medido de cada partida y los indirectos con el avance global de la obra, ponderado por presupuesto de venta y excluyendo la revisión sin fecha.
- Calcular margen real como certificado menos coste real. Destacar partidas cuya desviación desfavorable sea estrictamente superior al 5 %; mostrar «Coste sin avance» con porcentaje vacío cuando exista coste real positivo y coste previsto cero.
- Preparar medidas para resumen, detalle por obra y partida, evolución mensual y filtros de delegación, tipo, responsable y estado; la persona compone los visuales en Desktop y publica en el área de la demo.
- Conciliar SQL sobre NAV frente a DAX, obra a obra, y conservar las evidencias en este cambio.

## Capabilities

### New Capabilities

- `control-economico-obra`: definiciones económicas, fechas, filtros, alertas y validación del informe del issue 01.

### Modified Capabilities

Ninguna: no existen especificaciones principales previas.

## Impact

- Futuras vistas en `sql/rpt/`, proyecto `Obras.pbip` y modelo `Obras.SemanticModel/`, mediante el MCP de Power BI. No se modifican los datos NAV ni `db/init/`.
- Informe `Obras.Report/` montado por la persona en Power BI Desktop; el agente no editará PBIR sin petición expresa. Publicación pendiente de acceso y licencia de la persona.
- SQL exclusivamente mediante `db/query.sh` con el usuario `reporting`; conexión parametrizada y sin credenciales en ficheros.
- Fuera de alcance: issue 02, previsiones a fin de obra, seguridad por filas y refresco programado. Esta propuesta solo crea planificación; no aplica vistas ni modifica el modelo o el informe.
