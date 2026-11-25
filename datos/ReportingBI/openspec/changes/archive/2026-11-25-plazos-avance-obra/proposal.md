## Why

El issue `docs/issues/02-plazos-avance.md` requiere contrastar planificación, mediciones e hitos de NAV para detectar obras con retraso. El informe económico ya comparte obras, partidas y calendario, pero no conserva el reparto temporal del presupuesto ni expone los hitos del partner.

## What Changes

- Preparar vistas nuevas de planificación por fecha, mediciones acumuladas, hitos y corte del ERP, una por fichero en `sql/rpt/`, sin modificar las cinco vistas del issue 01.
- Definir avance planificado y real ponderados por presupuesto de venta de partidas medibles. Excluir del numerador y denominador las líneas sin fecha y explicar la exclusión en el informe.
- Definir desviación en puntos porcentuales, índice real/planificado, curva S y ranking de obras.
- Separar hitos cumplidos a tiempo, cumplidos con retraso, pendientes y retrasados. Solo cuentan como retrasados los pendientes cuya fecha planificada sea anterior al corte; los días históricos de hitos cumplidos se muestran por separado.
- Ampliar posteriormente el mismo modelo e informe, conservando los filtros existentes, y validar SQL contra DAX antes de publicar.

## Capabilities

### New Capabilities

- `plazos-avance-obra`: fuentes SQL, reglas temporales e indicadores de planificación, avance real e hitos del issue 02.

### Modified Capabilities

Ninguna. Se preserva el contrato económico del issue 01 y su corte inicial; el nuevo corte dinámico pertenece al análisis de plazos.

## Impact

Fuentes: `[CONSTRUCTORA DEMO$Job Planning Line]`, `[CONSTRUCTORA DEMO$Job Progress Entry]` y `[CONSTRUCTORA DEMO$Job Milestone]`; las dos últimas son tablas a medida del partner. El corte procede de `[Job Ledger Entry].[Posting Date]`.

La próxima aplicación está autorizada **solo para las vistas SQL nuevas**, su ejecución con `db/query.sh -f` y conciliación NAV obra a obra. Modelo, DAX, visuales y publicación son fases posteriores y requieren una nueva petición. No se modificarán `sql/rpt/01_…` a `05_…` ni objetos del issue 01. No se crearán tablas físicas ni otro modelo.
