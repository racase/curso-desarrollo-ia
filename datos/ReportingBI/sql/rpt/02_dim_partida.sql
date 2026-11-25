-- Tipo 0 = detalle; tipos 3/4 = cabecera/total. La partida 10 recoge indirectos.
CREATE OR ALTER VIEW [rpt].[DimPartida]
AS
SELECT
    CAST([t].[Job No_] + N'|' + [t].[Job Task No_] AS nvarchar(41)) AS [PartidaClave],
    [t].[Job No_] AS [ObraClave],
    [t].[Job Task No_] AS [PartidaCodigo],
    [t].[Description] AS [Partida],
    CASE WHEN [t].[Job Task No_] = N'10' THEN N'Indirecto' ELSE N'Directo' END AS [TipoCoste],
    CAST(CASE WHEN [t].[Job Task No_] = N'10' THEN 1 ELSE 0 END AS bit) AS [EsIndirecta]
FROM [dbo].[CONSTRUCTORA DEMO$Job Task] AS [t]
WHERE [t].[Job Task Type] = 0;
GO
