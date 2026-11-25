-- Hitos del partner. Estado y días se calculan respecto del corte consultado.
-- Una fecha real futura respecto de un corte histórico no implica cumplimiento a ese corte.
CREATE OR ALTER VIEW [rpt].[FactHito]
AS
SELECT
    CAST([h].[Job No_] + N'|' + [h].[Code] AS nvarchar(31)) AS [HitoClave],
    [h].[Job No_] AS [ObraClave],
    [h].[Code] AS [HitoCodigo],
    [h].[Sequence] AS [Secuencia],
    [h].[Description] AS [Hito],
    CAST(NULLIF([h].[Planned Date], CONVERT(datetime, '17530101', 112)) AS date) AS [FechaPlanificada],
    CAST(NULLIF([h].[Actual Date], CONVERT(datetime, '17530101', 112)) AS date) AS [FechaReal]
FROM [dbo].[CONSTRUCTORA DEMO$Job Milestone] AS [h];
GO
