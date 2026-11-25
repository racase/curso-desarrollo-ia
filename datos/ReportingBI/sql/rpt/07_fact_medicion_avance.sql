-- Mediciones acumuladas del partner. No sumar avances entre fechas.
-- El consumidor elige la última fecha válida hasta el corte y desempata por MedicionClave.
CREATE OR ALTER VIEW [rpt].[FactMedicionAvance]
AS
SELECT
    [e].[Entry No_] AS [MedicionClave],
    [p].[ObraClave],
    [p].[PartidaClave],
    CAST(NULLIF([e].[Measurement Date], CONVERT(datetime, '17530101', 112)) AS date) AS [Fecha],
    CAST([e].[Completion _] / 100 AS decimal(9,6)) AS [Avance]
FROM [dbo].[CONSTRUCTORA DEMO$Job Progress Entry] AS [e]
JOIN [rpt].[DimPartida] AS [p]
    ON [p].[ObraClave] = [e].[Job No_]
    AND [p].[PartidaCodigo] = [e].[Job Task No_]
WHERE [p].[EsIndirecta] = 0;
GO
