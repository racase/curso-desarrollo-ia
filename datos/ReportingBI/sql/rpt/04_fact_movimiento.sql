-- Movimientos reales por obra, capítulo y día.
-- Entry Type (opción estándar de NAV): 0 Consumo (coste), 1 Venta (certificación, que NAV
-- guarda con importe negativo: aquí se pasa a positivo).
CREATE OR ALTER VIEW [rpt].[FactMovimiento] AS
SELECT
    e.[Job No_]                                          AS ObraId,
    e.[Job Task No_]                                     AS CapituloId,
    CAST(e.[Posting Date] AS date)                       AS Fecha,
    CAST(SUM(CASE WHEN e.[Entry Type] = 0 THEN e.[Total Cost (LCY)] ELSE 0 END)
         AS decimal(18, 2))                              AS CosteReal,
    CAST(-SUM(CASE WHEN e.[Entry Type] = 1 THEN e.[Line Amount (LCY)] ELSE 0 END)
         AS decimal(18, 2))                              AS Certificado
FROM [dbo].[CONSTRUCTORA DEMO$Job Ledger Entry] e
JOIN [dbo].[CONSTRUCTORA DEMO$Job Task] t
  ON t.[Job No_] = e.[Job No_] AND t.[Job Task No_] = e.[Job Task No_] AND t.[Job Task Type] = 0
GROUP BY e.[Job No_], e.[Job Task No_], CAST(e.[Posting Date] AS date);
