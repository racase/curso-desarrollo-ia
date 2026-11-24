-- Cálculo de referencia del control económico (cambio informe-control-economico).
--
-- Calcula los indicadores directamente sobre las tablas NAV de CONSTRUCTORA DEMO, sin usar
-- las vistas rpt, para comparar con las medidas DAX del modelo. Las definiciones son las de
-- openspec/changes/informe-control-economico/specs/control-economico/spec.md y el redondeo
-- el de design.md: producción y coste previsto se redondean a céntimos por obra y capítulo.
--
-- Uso: podman compose exec -T db bash db/query.sh -f sql/test/control_economico.sql
--
-- Resultados:
--   1. Códigos de capítulo con más de un nombre (debe salir vacío).
--   2. Indicadores por obra.
--   3. Indicadores por obra y capítulo.

SET NOCOUNT ON;

DECLARE @Corte date = (SELECT CONVERT(date, MAX([Posting Date])) FROM [CONSTRUCTORA DEMO$Job Ledger Entry]);

-- 1. Un solo nombre por código de capítulo
SELECT [Job Task No_] AS CodigoCapitulo, COUNT(DISTINCT [Description]) AS Nombres
FROM [CONSTRUCTORA DEMO$Job Task]
WHERE [Job Task Type] = 0
GROUP BY [Job Task No_]
HAVING COUNT(DISTINCT [Description]) > 1;

-- Base obra × capítulo de imputación
SELECT
    t.[Job No_] AS ObraId,
    t.[Job Task No_] AS CapituloId,
    ISNULL(p.PresupuestoCoste, 0) AS PresupuestoCoste,
    ISNULL(p.PresupuestoVenta, 0) AS PresupuestoVenta,
    ISNULL(m.CosteReal, 0) AS CosteReal,
    ISNULL(m.Certificado, 0) AS Certificado,
    ISNULL(a.AvancePct, 0) AS AvancePct
INTO #cap
FROM [CONSTRUCTORA DEMO$Job Task] t
LEFT JOIN (
    SELECT [Job No_], [Job Task No_],
           SUM([Total Cost (LCY)]) AS PresupuestoCoste,
           SUM(CASE WHEN [Line Type] IN (1, 2) THEN [Line Amount (LCY)] ELSE 0 END) AS PresupuestoVenta
    FROM [CONSTRUCTORA DEMO$Job Planning Line]
    GROUP BY [Job No_], [Job Task No_]
) p ON p.[Job No_] = t.[Job No_] AND p.[Job Task No_] = t.[Job Task No_]
LEFT JOIN (
    SELECT [Job No_], [Job Task No_],
           SUM(CASE WHEN [Entry Type] = 0 THEN [Total Cost (LCY)] ELSE 0 END) AS CosteReal,
           -SUM(CASE WHEN [Entry Type] = 1 THEN [Line Amount (LCY)] ELSE 0 END) AS Certificado
    FROM [CONSTRUCTORA DEMO$Job Ledger Entry]
    WHERE CONVERT(date, [Posting Date]) <= @Corte
    GROUP BY [Job No_], [Job Task No_]
) m ON m.[Job No_] = t.[Job No_] AND m.[Job Task No_] = t.[Job Task No_]
OUTER APPLY (
    SELECT TOP 1 e.[Completion _] AS AvancePct
    FROM [CONSTRUCTORA DEMO$Job Progress Entry] e
    WHERE e.[Job No_] = t.[Job No_] AND e.[Job Task No_] = t.[Job Task No_]
      AND CONVERT(date, e.[Measurement Date]) <= @Corte
    ORDER BY e.[Measurement Date] DESC, e.[Entry No_] DESC
) a
WHERE t.[Job Task Type] = 0;

-- Producción por capítulo (redondeada a céntimos)
ALTER TABLE #cap ADD Produccion decimal(18, 2) NULL, CostePrevisto decimal(18, 2) NULL;
UPDATE #cap SET Produccion = ROUND(PresupuestoVenta * AvancePct / 100, 2);

-- Coste previsto: por el avance del capítulo, o por el avance global de la obra en los
-- capítulos sin presupuesto de venta (costes indirectos)
UPDATE c SET CostePrevisto =
    CASE WHEN c.PresupuestoVenta <> 0
         THEN ROUND(c.PresupuestoCoste * c.AvancePct / 100, 2)
         ELSE ROUND(c.PresupuestoCoste * ISNULL(o.Produccion / NULLIF(o.PresupuestoVenta, 0), 0), 2)
    END
FROM #cap c
JOIN (SELECT ObraId, SUM(Produccion) AS Produccion, SUM(PresupuestoVenta) AS PresupuestoVenta
      FROM #cap GROUP BY ObraId) o ON o.ObraId = c.ObraId;

-- 2. Indicadores por obra
SELECT
    j.[No_] AS ObraId,
    CAST(SUM(c.PresupuestoCoste) AS decimal(18, 2)) AS PresupuestoCoste,
    CAST(SUM(c.PresupuestoVenta) AS decimal(18, 2)) AS PresupuestoVenta,
    CAST(SUM(c.CosteReal) AS decimal(18, 2)) AS CosteReal,
    CAST(SUM(c.Certificado) AS decimal(18, 2)) AS Certificado,
    SUM(c.Produccion) AS Produccion,
    SUM(c.CostePrevisto) AS CostePrevisto,
    SUM(c.Produccion) - CAST(SUM(c.Certificado) AS decimal(18, 2)) AS PendienteCertificar,
    CAST(SUM(c.CosteReal) AS decimal(18, 2)) - SUM(c.CostePrevisto) AS DesviacionCoste,
    CAST((SUM(c.CosteReal) - SUM(c.CostePrevisto)) / NULLIF(SUM(c.CostePrevisto), 0) * 100 AS decimal(9, 2)) AS DesviacionPct,
    CAST(SUM(c.PresupuestoVenta) - SUM(c.PresupuestoCoste) AS decimal(18, 2)) AS MargenPrevisto,
    SUM(c.Produccion) - CAST(SUM(c.CosteReal) AS decimal(18, 2)) AS MargenReal,
    CAST((SUM(c.Produccion) - SUM(c.CosteReal)) / NULLIF(SUM(c.Produccion), 0) * 100 AS decimal(9, 2)) AS MargenRealPct
FROM [CONSTRUCTORA DEMO$Job] j
LEFT JOIN #cap c ON c.ObraId = j.[No_]
GROUP BY j.[No_]
ORDER BY j.[No_];

-- 3. Indicadores por obra y capítulo
SELECT
    ObraId, CapituloId,
    CAST(PresupuestoCoste AS decimal(18, 2)) AS PresupuestoCoste,
    CAST(PresupuestoVenta AS decimal(18, 2)) AS PresupuestoVenta,
    CAST(CosteReal AS decimal(18, 2)) AS CosteReal,
    Produccion,
    CostePrevisto,
    CAST(CosteReal AS decimal(18, 2)) - CostePrevisto AS DesviacionCoste,
    CAST((CosteReal - CostePrevisto) / NULLIF(CostePrevisto, 0) * 100 AS decimal(9, 2)) AS DesviacionPct
FROM #cap
ORDER BY ObraId, CapituloId;

DROP TABLE #cap;
