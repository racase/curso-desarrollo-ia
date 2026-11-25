-- Empresa operativa confirmada en Company. Estados contrastados con el generador.
CREATE OR ALTER VIEW [rpt].[DimObra]
AS
SELECT
    [j].[No_] AS [ObraClave],
    [j].[Description] AS [Obra],
    [j].[Status] AS [EstadoCodigo],
    CASE [j].[Status]
        WHEN 0 THEN N'Planificación'
        WHEN 2 THEN N'Abierta'
        WHEN 3 THEN N'Terminada'
    END AS [Estado],
    [j].[Global Dimension 1 Code] AS [DelegacionCodigo],
    [d].[Name] AS [Delegacion],
    [j].[Global Dimension 2 Code] AS [TipoObraCodigo],
    [t].[Name] AS [TipoObra],
    [j].[Person Responsible] AS [JefeObraCodigo],
    [r].[Name] AS [JefeObra],
    [j].[Bill-to Customer No_] AS [ClienteCodigo],
    CAST(NULLIF([j].[Creation Date], CONVERT(datetime, '17530101', 112)) AS date) AS [FechaCreacion],
    CAST(NULLIF([j].[Starting Date], CONVERT(datetime, '17530101', 112)) AS date) AS [FechaInicio],
    CAST(NULLIF([j].[Ending Date], CONVERT(datetime, '17530101', 112)) AS date) AS [FechaFinPlanificada]
FROM [dbo].[CONSTRUCTORA DEMO$Job] AS [j]
LEFT JOIN [dbo].[CONSTRUCTORA DEMO$Dimension Value] AS [d]
    ON [d].[Dimension Code] = N'DELEGACION'
    AND [d].[Code] = [j].[Global Dimension 1 Code]
LEFT JOIN [dbo].[CONSTRUCTORA DEMO$Dimension Value] AS [t]
    ON [t].[Dimension Code] = N'TIPOOBRA'
    AND [t].[Code] = [j].[Global Dimension 2 Code]
LEFT JOIN [dbo].[CONSTRUCTORA DEMO$Resource] AS [r]
    ON [r].[No_] = [j].[Person Responsible];
GO
