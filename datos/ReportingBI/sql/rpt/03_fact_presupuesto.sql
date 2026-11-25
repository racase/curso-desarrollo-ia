-- Presupuesto completo por obra/partida. La revisión sin fecha se conserva por separado.
-- Line Type: 0 = presupuesto, 1 = facturable, 2 = presupuesto y facturable.
CREATE OR ALTER VIEW [rpt].[FactPresupuesto]
AS
SELECT
    [p].[ObraClave],
    [p].[PartidaClave],
    CAST(COALESCE(SUM(CASE WHEN [l].[Line Type] IN (0, 2)
        THEN [l].[Total Cost (LCY)] ELSE 0 END), 0) AS decimal(18,2)) AS [PresupuestoCoste],
    CAST(COALESCE(SUM(CASE WHEN [l].[Line Type] IN (1, 2)
        THEN [l].[Line Amount (LCY)] ELSE 0 END), 0) AS decimal(18,2)) AS [PresupuestoVenta],
    CAST(COALESCE(SUM(CASE WHEN [l].[Line Type] IN (1, 2)
        AND [l].[Planning Date] <> CONVERT(datetime, '17530101', 112)
        AND [p].[EsIndirecta] = 0
        THEN [l].[Line Amount (LCY)] ELSE 0 END), 0) AS decimal(18,2)) AS [BaseVentaProduccion],
    CAST(COALESCE(SUM(CASE WHEN [l].[Line Type] IN (0, 2)
        AND [l].[Planning Date] = CONVERT(datetime, '17530101', 112)
        THEN [l].[Total Cost (LCY)] ELSE 0 END), 0) AS decimal(18,2)) AS [PresupuestoCosteSinFecha],
    CAST(COALESCE(SUM(CASE WHEN [l].[Line Type] IN (1, 2)
        AND [l].[Planning Date] = CONVERT(datetime, '17530101', 112)
        THEN [l].[Line Amount (LCY)] ELSE 0 END), 0) AS decimal(18,2)) AS [PresupuestoVentaSinFecha],
    MIN(CAST(NULLIF([l].[Planning Date], CONVERT(datetime, '17530101', 112)) AS date)) AS [PrimeraFechaPlanificada],
    MAX(CAST(NULLIF([l].[Planning Date], CONVERT(datetime, '17530101', 112)) AS date)) AS [UltimaFechaPlanificada]
FROM [rpt].[DimPartida] AS [p]
LEFT JOIN [dbo].[CONSTRUCTORA DEMO$Job Planning Line] AS [l]
    ON [l].[Job No_] = [p].[ObraClave]
    AND [l].[Job Task No_] = [p].[PartidaCodigo]
GROUP BY [p].[ObraClave], [p].[PartidaClave];
GO
