-- Referencia independiente NAV para las medidas nuevas, sin consultar vistas rpt.
SET NOCOUNT ON;
DECLARE @ERP date = (SELECT CAST(MAX([Posting Date]) AS date) FROM [dbo].[CONSTRUCTORA DEMO$Job Ledger Entry]
    WHERE [Posting Date] <> CONVERT(datetime,'17530101',112));
DECLARE @Fechas TABLE ([Fecha] date PRIMARY KEY);
WITH [Meses] AS (
    SELECT CONVERT(date,'20250131',112) AS [Fecha]
    UNION ALL SELECT EOMONTH(DATEADD(month,1,[Fecha])) FROM [Meses]
    WHERE EOMONTH(DATEADD(month,1,[Fecha])) <= @ERP
)
INSERT INTO @Fechas SELECT [Fecha] FROM [Meses] WHERE [Fecha] <= @ERP OPTION (MAXRECURSION 0);
INSERT INTO @Fechas SELECT [Fecha] FROM (VALUES (CONVERT(date,'20261015',112)),(CONVERT(date,'20250101',112))) AS [x]([Fecha])
    WHERE [Fecha] <= @ERP AND NOT EXISTS (SELECT 1 FROM @Fechas AS [f] WHERE [f].[Fecha] = [x].[Fecha]);
DECLARE @Grupos TABLE ([Grupo] nvarchar(80), [Obra] nvarchar(20));
INSERT INTO @Grupos
SELECT N'TOTAL', [No_] FROM [dbo].[CONSTRUCTORA DEMO$Job]
UNION ALL SELECT N'OBRA:'+[No_], [No_] FROM [dbo].[CONSTRUCTORA DEMO$Job]
UNION ALL SELECT N'DELEGACION:'+ [Global Dimension 1 Code], [No_] FROM [dbo].[CONSTRUCTORA DEMO$Job]
UNION ALL SELECT N'TIPO:'+ [Global Dimension 2 Code], [No_] FROM [dbo].[CONSTRUCTORA DEMO$Job]
UNION ALL SELECT N'JEFE:'+ [Person Responsible], [No_] FROM [dbo].[CONSTRUCTORA DEMO$Job]
UNION ALL SELECT N'ESTADO:'+ CAST([Status] AS nvarchar(10)), [No_] FROM [dbo].[CONSTRUCTORA DEMO$Job];
DECLARE @Partidas TABLE ([Fecha] date, [Obra] nvarchar(20), [Base] decimal(18,2), [PlanImporte] decimal(18,2),
    [Produccion] decimal(18,2), [SinFecha] decimal(18,2));
INSERT INTO @Partidas
SELECT [f].[Fecha], [t].[Job No_], COALESCE([b].[Base],0), COALESCE([p].[PlanImporte],0),
    CAST(ROUND(COALESCE([b].[Base],0)*COALESCE([m].[Avance],0),2) AS decimal(18,2)), COALESCE([s].[SinFecha],0)
FROM [dbo].[CONSTRUCTORA DEMO$Job Task] AS [t] CROSS JOIN @Fechas AS [f]
OUTER APPLY (
    SELECT CAST(SUM([Line Amount (LCY)]) AS decimal(18,2)) AS [Base]
    FROM [dbo].[CONSTRUCTORA DEMO$Job Planning Line]
    WHERE [Job No_] = [t].[Job No_] AND [Job Task No_] = [t].[Job Task No_] AND [Line Type] IN (1,2)
        AND [Planning Date] <> CONVERT(datetime,'17530101',112)
) AS [b]
OUTER APPLY (
    SELECT CAST(SUM([Line Amount (LCY)]) AS decimal(18,2)) AS [PlanImporte]
    FROM [dbo].[CONSTRUCTORA DEMO$Job Planning Line]
    WHERE [Job No_] = [t].[Job No_] AND [Job Task No_] = [t].[Job Task No_] AND [Line Type] IN (1,2)
        AND [Planning Date] <> CONVERT(datetime,'17530101',112) AND [Planning Date] < DATEADD(day,1,[f].[Fecha])
) AS [p]
OUTER APPLY (
    SELECT CAST(SUM([Line Amount (LCY)]) AS decimal(18,2)) AS [SinFecha]
    FROM [dbo].[CONSTRUCTORA DEMO$Job Planning Line]
    WHERE [Job No_] = [t].[Job No_] AND [Job Task No_] = [t].[Job Task No_] AND [Line Type] IN (1,2)
        AND ([Planning Date] IS NULL OR [Planning Date] = CONVERT(datetime,'17530101',112))
) AS [s]
OUTER APPLY (
    SELECT TOP (1) CAST([Completion _]/100 AS decimal(9,6)) AS [Avance]
    FROM [dbo].[CONSTRUCTORA DEMO$Job Progress Entry]
    WHERE [Job No_] = [t].[Job No_] AND [Job Task No_] = [t].[Job Task No_]
        AND [Measurement Date] <> CONVERT(datetime,'17530101',112) AND [Measurement Date] < DATEADD(day,1,[f].[Fecha])
    ORDER BY [Measurement Date] DESC, [Entry No_] DESC
) AS [m]
WHERE [t].[Job Task Type] = 0 AND [t].[Job Task No_] <> N'10';
DECLARE @Hitos TABLE ([Fecha] date, [Obra] nvarchar(20), [Estado] int, [Dias] int);
-- Estado local: 0 a tiempo, 1 tarde, 2 pendiente, 3 retrasado.
INSERT INTO @Hitos
SELECT [f].[Fecha], [h].[Job No_],
    CASE WHEN [h].[Actual Date] > CONVERT(datetime,'17530101',112) AND [h].[Actual Date] < DATEADD(day,1,[f].[Fecha])
        THEN CASE WHEN CAST([h].[Actual Date] AS date) <= CAST([h].[Planned Date] AS date) THEN 0 ELSE 1 END
        ELSE CASE WHEN CAST([h].[Planned Date] AS date) < [f].[Fecha] THEN 3 ELSE 2 END END,
    CASE WHEN [h].[Planned Date] < [f].[Fecha]
        AND ([h].[Actual Date] IS NULL OR [h].[Actual Date] = CONVERT(datetime,'17530101',112) OR [h].[Actual Date] >= DATEADD(day,1,[f].[Fecha]))
        THEN DATEDIFF(day,[h].[Planned Date],[f].[Fecha]) ELSE 0 END
FROM [dbo].[CONSTRUCTORA DEMO$Job Milestone] AS [h] CROSS JOIN @Fechas AS [f];
WITH [Avance] AS (
    SELECT [p].[Fecha], [g].[Grupo], SUM([p].[Base]) AS [Base], SUM([p].[PlanImporte]) AS [PlanImporte],
        SUM([p].[Produccion]) AS [Produccion], SUM([p].[SinFecha]) AS [SinFecha]
    FROM @Partidas AS [p] JOIN @Grupos AS [g] ON [g].[Obra] = [p].[Obra] GROUP BY [p].[Fecha], [g].[Grupo]
), [Hitos] AS (
    SELECT [h].[Fecha], [g].[Grupo],
        SUM(CASE WHEN [Estado] = 3 THEN 1 ELSE 0 END) AS [Retrasados], MAX([Dias]) AS [MaxDias],
        SUM(CASE WHEN [Estado] = 1 THEN 1 ELSE 0 END) AS [Tarde],
        SUM(CASE WHEN [Estado] = 2 THEN 1 ELSE 0 END) AS [Pendientes],
        SUM(CASE WHEN [Estado] = 0 THEN 1 ELSE 0 END) AS [ATiempo]
    FROM @Hitos AS [h] JOIN @Grupos AS [g] ON [g].[Obra] = [h].[Obra] GROUP BY [h].[Fecha], [g].[Grupo]
)
SELECT [a].[Fecha] AS [Corte], [a].[Grupo], [a].[Base], [a].[PlanImporte], [a].[Produccion],
    CAST([r].[PlanPct] AS decimal(24,12)) AS [PlanPct], CAST([r].[RealPct] AS decimal(24,12)) AS [RealPct],
    CAST(([r].[RealPct]-[r].[PlanPct])*100 AS decimal(24,12)) AS [DesviacionPP],
    CAST(CAST([a].[Produccion] AS decimal(24,2))/NULLIF(CAST([a].[PlanImporte] AS decimal(24,2)),0) AS decimal(24,12)) AS [Indice], [a].[SinFecha],
    [h].[Retrasados], [h].[MaxDias], [h].[Tarde], [h].[Pendientes], [h].[ATiempo]
FROM [Avance] AS [a] JOIN [Hitos] AS [h] ON [h].[Fecha] = [a].[Fecha] AND [h].[Grupo] = [a].[Grupo]
CROSS APPLY (SELECT COALESCE(CAST([a].[PlanImporte] AS decimal(24,2))/NULLIF(CAST([a].[Base] AS decimal(24,2)),0),0) AS [PlanPct],
    COALESCE(CAST([a].[Produccion] AS decimal(24,2))/NULLIF(CAST([a].[Base] AS decimal(24,2)),0),0) AS [RealPct]) AS [r]
ORDER BY [a].[Fecha], [a].[Grupo];
GO
