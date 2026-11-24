-- Presupuesto por obra y capítulo de imputación, sin fecha (el presupuesto se muestra
-- siempre completo). Incluye las líneas sin fecha de planificación.
-- Line Type (opción estándar de NAV): 0 Presupuesto (solo coste), 1 Facturable, 2 Ambos.
CREATE OR ALTER VIEW [rpt].[FactPresupuesto] AS
SELECT
    p.[Job No_]                                          AS ObraId,
    p.[Job Task No_]                                     AS CapituloId,
    CAST(SUM(p.[Total Cost (LCY)]) AS decimal(18, 2))    AS PresupuestoCoste,
    CAST(SUM(CASE WHEN p.[Line Type] IN (1, 2) THEN p.[Line Amount (LCY)] ELSE 0 END)
         AS decimal(18, 2))                              AS PresupuestoVenta
FROM [dbo].[CONSTRUCTORA DEMO$Job Planning Line] p
JOIN [dbo].[CONSTRUCTORA DEMO$Job Task] t
  ON t.[Job No_] = p.[Job No_] AND t.[Job Task No_] = p.[Job Task No_] AND t.[Job Task Type] = 0
GROUP BY p.[Job No_], p.[Job Task No_];
