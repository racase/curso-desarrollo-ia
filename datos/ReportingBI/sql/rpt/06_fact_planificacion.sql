-- Planificación de venta por fecha. La revisión sin fecha se conserva, con base cero.
-- Tipos 1/2 = venta; detalle directo = Job Task Type 0, excepto partida 10.
CREATE OR ALTER VIEW [rpt].[FactPlanificacion]
AS
SELECT
    [p].[ObraClave],
    [p].[PartidaClave],
    CAST(NULLIF([l].[Planning Date], CONVERT(datetime, '17530101', 112)) AS date) AS [Fecha],
    CAST(SUM([l].[Line Amount (LCY)]) AS decimal(18,2)) AS [VentaPlanificada],
    CAST(SUM(CASE WHEN [l].[Planning Date] IS NOT NULL
        AND [l].[Planning Date] <> CONVERT(datetime, '17530101', 112)
        THEN [l].[Line Amount (LCY)] ELSE 0 END) AS decimal(18,2)) AS [BaseVentaElegible]
FROM [dbo].[CONSTRUCTORA DEMO$Job Planning Line] AS [l]
JOIN [rpt].[DimPartida] AS [p]
    ON [p].[ObraClave] = [l].[Job No_]
    AND [p].[PartidaCodigo] = [l].[Job Task No_]
WHERE [l].[Line Type] IN (1, 2) AND [p].[EsIndirecta] = 0
GROUP BY [p].[ObraClave], [p].[PartidaClave],
    CAST(NULLIF([l].[Planning Date], CONVERT(datetime, '17530101', 112)) AS date);
GO
