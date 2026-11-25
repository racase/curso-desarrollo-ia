-- Corte de plazos global de la empresa operativa; no depende del reloj ni del filtro de obra.
-- Una sola fila, con FechaCorte NULL si no hay movimientos válidos.
CREATE OR ALTER VIEW [rpt].[DimCorteDatos]
AS
SELECT
    CAST(1 AS int) AS [CorteClave],
    CAST(MAX(NULLIF([Posting Date], CONVERT(datetime, '17530101', 112))) AS date) AS [FechaCorte]
FROM [dbo].[CONSTRUCTORA DEMO$Job Ledger Entry];
GO
