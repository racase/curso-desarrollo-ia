-- Clasificación al corte: NAV frente a fechas publicadas en FactHito.
-- Las clasificaciones son consultas de prueba; no forman parte de las vistas.
SET NOCOUNT ON;
DECLARE @Corte date = (SELECT [FechaCorte] FROM [rpt].[DimCorteDatos]);
DECLARE @Cortes TABLE ([Fecha] date PRIMARY KEY);
DECLARE @Inicio date = (SELECT MIN(CAST([Planning Date] AS date))
    FROM [dbo].[CONSTRUCTORA DEMO$Job Planning Line] WHERE [Planning Date] > CONVERT(datetime,'17530101',112));
WITH [Meses] AS (
    SELECT DATEFROMPARTS(YEAR(@Inicio), MONTH(@Inicio), 1) AS [Mes]
    UNION ALL SELECT DATEADD(month,1,[Mes]) FROM [Meses] WHERE DATEADD(month,1,[Mes]) <= @Corte
)
INSERT INTO @Cortes SELECT CASE WHEN EOMONTH([Mes]) > @Corte THEN @Corte ELSE EOMONTH([Mes]) END
FROM [Meses] WHERE @Corte IS NOT NULL OPTION (MAXRECURSION 0);
DECLARE @NAV TABLE ([Corte] date, [Obra] nvarchar(20), [Codigo] nvarchar(10), [Estado] nvarchar(30), [Dias] int);
INSERT INTO @NAV
SELECT [c].[Fecha], [h].[Job No_], [h].[Code],
    CASE WHEN [h].[Actual Date] > CONVERT(datetime,'17530101',112)
        AND [h].[Actual Date] < DATEADD(day,1,[c].[Fecha])
        THEN CASE WHEN CAST([h].[Actual Date] AS date) <= CAST([h].[Planned Date] AS date)
            THEN N'Cumplido a tiempo' ELSE N'Cumplido con retraso' END
        ELSE CASE WHEN CAST([h].[Planned Date] AS date) < [c].[Fecha]
            THEN N'Retrasado' ELSE N'Pendiente' END END,
    CASE WHEN DATEDIFF(day,[h].[Planned Date],
        CASE WHEN [h].[Actual Date] > CONVERT(datetime,'17530101',112)
            AND [h].[Actual Date] < DATEADD(day,1,[c].[Fecha]) THEN [h].[Actual Date] ELSE [c].[Fecha] END) > 0
        THEN DATEDIFF(day,[h].[Planned Date],
        CASE WHEN [h].[Actual Date] > CONVERT(datetime,'17530101',112)
            AND [h].[Actual Date] < DATEADD(day,1,[c].[Fecha]) THEN [h].[Actual Date] ELSE [c].[Fecha] END)
        ELSE 0 END
FROM [dbo].[CONSTRUCTORA DEMO$Job Milestone] AS [h] CROSS JOIN @Cortes AS [c];
DECLARE @Vista TABLE ([Corte] date, [Obra] nvarchar(20), [Codigo] nvarchar(10), [Estado] nvarchar(30), [Dias] int);
INSERT INTO @Vista
SELECT [c].[Fecha], [h].[ObraClave], [h].[HitoCodigo],
    CASE WHEN [h].[FechaReal] <= [c].[Fecha] AND [h].[FechaReal] <= [h].[FechaPlanificada] THEN N'Cumplido a tiempo'
        WHEN [h].[FechaReal] <= [c].[Fecha] THEN N'Cumplido con retraso'
        WHEN [h].[FechaPlanificada] < [c].[Fecha] THEN N'Retrasado' ELSE N'Pendiente' END,
    CASE WHEN [fin].[FechaFin] <= [h].[FechaPlanificada] THEN 0 ELSE DATEDIFF(day,[h].[FechaPlanificada],[fin].[FechaFin]) END
FROM [rpt].[FactHito] AS [h] CROSS JOIN @Cortes AS [c]
CROSS APPLY (SELECT CASE WHEN [h].[FechaReal] <= [c].[Fecha] THEN [h].[FechaReal] ELSE [c].[Fecha] END AS [FechaFin]) AS [fin];
SELECT N'NAV_menos_vista' AS [Control], COUNT(*) AS [Diferencias] FROM (SELECT * FROM @NAV EXCEPT SELECT * FROM @Vista) AS [d]
UNION ALL SELECT N'Vista_menos_NAV', COUNT(*) FROM (SELECT * FROM @Vista EXCEPT SELECT * FROM @NAV) AS [d];
IF EXISTS (SELECT * FROM @NAV EXCEPT SELECT * FROM @Vista)
    OR EXISTS (SELECT * FROM @Vista EXCEPT SELECT * FROM @NAV)
    THROW 51005, N'Los estados o dias de hitos difieren de NAV.', 1;
SELECT COUNT(*) AS [HitoCortes], COUNT(DISTINCT [Corte]) AS [Cierres] FROM @Vista;
SELECT [Estado], COUNT(*) AS [Hitos] FROM @Vista WHERE [Corte] = @Corte GROUP BY [Estado] ORDER BY [Estado];
SELECT [Obra], SUM(CASE WHEN [Estado] = N'Retrasado' THEN 1 ELSE 0 END) AS [PendientesRetrasados],
    MAX(CASE WHEN [Estado] = N'Retrasado' THEN [Dias] ELSE 0 END) AS [MaxDiasPendientes],
    SUM(CASE WHEN [Estado] = N'Cumplido con retraso' THEN 1 ELSE 0 END) AS [CumplidosTarde],
    MAX(CASE WHEN [Estado] = N'Cumplido con retraso' THEN [Dias] ELSE 0 END) AS [MaxDiasCumplidosTarde]
FROM @Vista WHERE [Corte] = @Corte GROUP BY [Obra] ORDER BY [Obra];
SELECT * FROM @Vista WHERE [Corte] = @Corte AND [Obra] = N'OB-25006' ORDER BY [Codigo];
SELECT COUNT(*) AS [HitosAunNoCumplidosEnCortesHistoricos]
FROM [rpt].[FactHito] AS [h] JOIN @Vista AS [v]
    ON [v].[Obra] = [h].[ObraClave] AND [v].[Codigo] = [h].[HitoCodigo]
WHERE [h].[FechaReal] > [v].[Corte] AND [v].[Estado] IN (N'Pendiente',N'Retrasado');
-- Referencia individual para contrastar Estado de hito y Días de retraso del hito en DAX.
SELECT [Corte], [Obra], [Codigo], [Estado], [Dias] FROM @NAV ORDER BY [Corte], [Obra], [Codigo];
IF EXISTS (SELECT 1 FROM @Vista WHERE [Obra] = N'OB-26003' AND [Estado] <> N'Pendiente')
    THROW 51006, N'La obra futura no debe tener hitos retrasados en este horizonte.', 1;
-- Fronteras explícitas: solo filas de sesión, sin introducir datos en NAV.
DECLARE @Casos TABLE ([Caso] nvarchar(40), [Plan] date, [Real] date, [Corte] date, [Esperado] nvarchar(30), [DiasEsperados] int);
INSERT INTO @Casos VALUES
    (N'Cumplido_a_tiempo','20261010','20261009','20261031',N'Cumplido a tiempo',0),
    (N'Cumplido_tarde','20261010','20261020','20261031',N'Cumplido con retraso',10),
    (N'Vence_hoy','20261031',NULL,'20261031',N'Pendiente',0),
    (N'Vencio_ayer','20261030',NULL,'20261031',N'Retrasado',1),
    (N'Real_posterior_al_corte','20261001','20261110','20261031',N'Retrasado',30),
    (N'Futuro','20261101',NULL,'20261031',N'Pendiente',0),
    (N'Cumplido_en_el_corte','20261030','20261031','20261031',N'Cumplido con retraso',1);
DECLARE @Resultados TABLE ([Caso] nvarchar(40), [Estado] nvarchar(30), [Dias] int, [Esperado] nvarchar(30), [DiasEsperados] int);
INSERT INTO @Resultados
SELECT [Caso],
    CASE WHEN [Real] <= [Corte] AND [Real] <= [Plan] THEN N'Cumplido a tiempo'
        WHEN [Real] <= [Corte] THEN N'Cumplido con retraso'
        WHEN [Plan] < [Corte] THEN N'Retrasado' ELSE N'Pendiente' END,
    CASE WHEN [fin].[FechaFin] <= [Plan] THEN 0 ELSE DATEDIFF(day,[Plan],[fin].[FechaFin]) END,
    [Esperado], [DiasEsperados]
FROM @Casos CROSS APPLY (SELECT CASE WHEN [Real] <= [Corte] THEN [Real] ELSE [Corte] END AS [FechaFin]) AS [fin];
SELECT * FROM @Resultados;
IF EXISTS (SELECT 1 FROM @Resultados WHERE [Estado] <> [Esperado] OR [Dias] <> [DiasEsperados])
    THROW 51007, N'Fallo en las fronteras de fechas de hitos.', 1;
GO
