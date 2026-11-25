-- Instantáneas mensuales, no sumables entre meses. Corte inicial del issue 01.
-- Antes de la primera medición: avance cero. Después: última medición conocida.
CREATE OR ALTER VIEW [rpt].[FactAvance]
AS
WITH [Limites] AS (
    SELECT
        DATEFROMPARTS(YEAR(MIN([e].[Measurement Date])), MONTH(MIN([e].[Measurement Date])), 1) AS [Inicio],
        CONVERT(date, '20261031', 112) AS [Corte]
    FROM [dbo].[CONSTRUCTORA DEMO$Job Progress Entry] AS [e]
    WHERE [e].[Measurement Date] <> CONVERT(datetime, '17530101', 112)
),
[Digitos] AS (
    SELECT [n] FROM (VALUES (0), (1), (2), (3), (4), (5), (6), (7), (8), (9)) AS [d] ([n])
),
[Meses] AS (
    SELECT EOMONTH([l].[Inicio], [u].[n] + 10 * [d].[n] + 100 * [c].[n]) AS [Fecha]
    FROM [Limites] AS [l]
    CROSS JOIN [Digitos] AS [u]
    CROSS JOIN [Digitos] AS [d]
    CROSS JOIN [Digitos] AS [c]
    WHERE [u].[n] + 10 * [d].[n] + 100 * [c].[n] <= DATEDIFF(month, [l].[Inicio], [l].[Corte])
)
SELECT
    [p].[ObraClave],
    [p].[PartidaClave],
    [m].[Fecha],
    [ultima].[Entry No_] AS [MedicionClave],
    CAST([ultima].[Measurement Date] AS date) AS [FechaUltimaMedicion],
    CAST(COALESCE([ultima].[Completion _], 0) / 100 AS decimal(9,6)) AS [Avance]
FROM [rpt].[DimPartida] AS [p]
CROSS JOIN [Meses] AS [m]
OUTER APPLY (
    SELECT TOP (1) [e].[Entry No_], [e].[Measurement Date], [e].[Completion _]
    FROM [dbo].[CONSTRUCTORA DEMO$Job Progress Entry] AS [e]
    WHERE [e].[Job No_] = [p].[ObraClave]
        AND [e].[Job Task No_] = [p].[PartidaCodigo]
        AND [e].[Measurement Date] <> CONVERT(datetime, '17530101', 112)
        AND [e].[Measurement Date] < DATEADD(day, 1, [m].[Fecha])
    ORDER BY [e].[Measurement Date] DESC, [e].[Entry No_] DESC
) AS [ultima]
WHERE [p].[EsIndirecta] = 0;
GO
