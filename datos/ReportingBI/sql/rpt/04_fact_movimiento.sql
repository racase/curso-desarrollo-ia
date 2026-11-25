-- Consumos (Entry Type 0) positivos; certificaciones (1) con signo de negocio.
CREATE OR ALTER VIEW [rpt].[FactMovimiento]
AS
SELECT
    [e].[Job No_] AS [ObraClave],
    CAST([e].[Job No_] + N'|' + [e].[Job Task No_] AS nvarchar(41)) AS [PartidaClave],
    CAST(NULLIF([e].[Posting Date], CONVERT(datetime, '17530101', 112)) AS date) AS [Fecha],
    CAST(SUM(CASE WHEN [e].[Entry Type] = 0
        THEN [e].[Total Cost (LCY)] ELSE 0 END) AS decimal(18,2)) AS [CosteReal],
    CAST(-SUM(CASE WHEN [e].[Entry Type] = 1
        THEN [e].[Line Amount (LCY)] ELSE 0 END) AS decimal(18,2)) AS [Certificado],
    COUNT(*) AS [NumeroMovimientos]
FROM [dbo].[CONSTRUCTORA DEMO$Job Ledger Entry] AS [e]
WHERE [e].[Entry Type] IN (0, 1)
GROUP BY
    [e].[Job No_],
    [e].[Job Task No_],
    CAST(NULLIF([e].[Posting Date], CONVERT(datetime, '17530101', 112)) AS date);
GO
