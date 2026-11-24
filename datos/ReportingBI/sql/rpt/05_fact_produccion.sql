-- Producción y coste previsto por obra, capítulo y mes, como incrementos mensuales.
--
-- Para cada obra y capítulo, de la primera medición de la obra a la fecha de corte:
--   avance       = % de la última medición hasta el fin de mes (0 si no hay)
--   producción   = presupuesto de venta × avance, acumulada y redondeada a céntimos
--   coste prev.  = presupuesto de coste × avance; en capítulos sin presupuesto de venta
--                  (costes indirectos), × avance global de la obra
--                  (producción acumulada de la obra / presupuesto de venta de la obra)
-- Cada fila guarda lo que aumentan los acumulados respecto al mes anterior, así la suma de
-- todas las filas es el valor a la fecha de corte. Solo se guardan meses con cambios.
-- Fecha: último día del mes, o la fecha de corte si el mes no ha terminado.
CREATE OR ALTER VIEW [rpt].[FactProduccion] AS
WITH corte AS (
    SELECT CAST(MAX([Posting Date]) AS date) AS Corte
    FROM [dbo].[CONSTRUCTORA DEMO$Job Ledger Entry]
),
n AS (  -- 0..119 meses
    SELECT d.i * 10 + u.i AS i
    FROM (VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9)) u(i)
    CROSS JOIN (VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9),(10),(11)) d(i)
),
meses AS (
    SELECT e.[Job No_] AS ObraId, EOMONTH(DATEADD(MONTH, n.i, MIN(e.[Measurement Date]))) AS FinMes
    FROM [dbo].[CONSTRUCTORA DEMO$Job Progress Entry] e
    CROSS JOIN n
    GROUP BY e.[Job No_], n.i
    HAVING EOMONTH(DATEADD(MONTH, n.i, MIN(e.[Measurement Date]))) <= EOMONTH((SELECT Corte FROM corte))
),
cap AS (
    SELECT p.[Job No_] AS ObraId, p.[Job Task No_] AS CapituloId,
           SUM(p.[Total Cost (LCY)]) AS PresupuestoCoste,
           SUM(CASE WHEN p.[Line Type] IN (1, 2) THEN p.[Line Amount (LCY)] ELSE 0 END) AS PresupuestoVenta
    FROM [dbo].[CONSTRUCTORA DEMO$Job Planning Line] p
    JOIN [dbo].[CONSTRUCTORA DEMO$Job Task] t
      ON t.[Job No_] = p.[Job No_] AND t.[Job Task No_] = p.[Job Task No_] AND t.[Job Task Type] = 0
    GROUP BY p.[Job No_], p.[Job Task No_]
),
acum AS (
    SELECT c.ObraId, c.CapituloId, m.FinMes, c.PresupuestoCoste, c.PresupuestoVenta,
           ISNULL(a.AvancePct, 0) AS AvancePct,
           CAST(ROUND(c.PresupuestoVenta * ISNULL(a.AvancePct, 0) / 100, 2) AS decimal(18, 2)) AS ProduccionAcum
    FROM cap c
    JOIN meses m ON m.ObraId = c.ObraId
    OUTER APPLY (
        SELECT TOP 1 e.[Completion _] AS AvancePct
        FROM [dbo].[CONSTRUCTORA DEMO$Job Progress Entry] e
        WHERE e.[Job No_] = c.ObraId AND e.[Job Task No_] = c.CapituloId
          AND CAST(e.[Measurement Date] AS date) <= m.FinMes
          AND CAST(e.[Measurement Date] AS date) <= (SELECT Corte FROM corte)
        ORDER BY e.[Measurement Date] DESC, e.[Entry No_] DESC
    ) a
),
obra AS (
    SELECT ObraId, FinMes, SUM(ProduccionAcum) AS Produccion, SUM(PresupuestoVenta) AS PresupuestoVenta
    FROM acum GROUP BY ObraId, FinMes
),
acum2 AS (
    SELECT a.ObraId, a.CapituloId, a.FinMes, a.ProduccionAcum,
           CAST(CASE WHEN a.PresupuestoVenta <> 0
                     THEN ROUND(a.PresupuestoCoste * a.AvancePct / 100, 2)
                     ELSE ROUND(a.PresupuestoCoste * ISNULL(o.Produccion / NULLIF(o.PresupuestoVenta, 0), 0), 2)
                END AS decimal(18, 2)) AS CostePrevistoAcum
    FROM acum a
    JOIN obra o ON o.ObraId = a.ObraId AND o.FinMes = a.FinMes
),
inc AS (
    SELECT ObraId, CapituloId, FinMes,
           ProduccionAcum - LAG(ProduccionAcum, 1, 0) OVER (PARTITION BY ObraId, CapituloId ORDER BY FinMes) AS ProduccionMes,
           CostePrevistoAcum - LAG(CostePrevistoAcum, 1, 0) OVER (PARTITION BY ObraId, CapituloId ORDER BY FinMes) AS CostePrevistoMes
    FROM acum2
)
SELECT
    i.ObraId,
    i.CapituloId,
    CASE WHEN i.FinMes > c.Corte THEN c.Corte ELSE i.FinMes END AS Fecha,
    CAST(i.ProduccionMes AS decimal(18, 2))    AS ProduccionMes,
    CAST(i.CostePrevistoMes AS decimal(18, 2)) AS CostePrevistoMes
FROM inc i
CROSS JOIN corte c
WHERE i.ProduccionMes <> 0 OR i.CostePrevistoMes <> 0;
