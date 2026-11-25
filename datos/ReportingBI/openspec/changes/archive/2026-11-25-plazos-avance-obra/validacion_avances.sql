-- Avances por partida y cierre: tablas NAV frente a vistas nuevas y arrastre del 01.
SET NOCOUNT ON;
DECLARE @Corte date = (SELECT [FechaCorte] FROM [rpt].[DimCorteDatos]);
DECLARE @Inicio date = (SELECT MIN(CAST([Planning Date] AS date))
    FROM [dbo].[CONSTRUCTORA DEMO$Job Planning Line] WHERE [Planning Date] > CONVERT(datetime,'17530101',112));
DECLARE @Cortes TABLE ([Fecha] date PRIMARY KEY);
WITH [Meses] AS (
    SELECT DATEFROMPARTS(YEAR(@Inicio), MONTH(@Inicio), 1) AS [InicioMes]
    UNION ALL SELECT DATEADD(month, 1, [InicioMes]) FROM [Meses]
    WHERE DATEADD(month, 1, [InicioMes]) <= @Corte
)
INSERT INTO @Cortes
SELECT CASE WHEN EOMONTH([InicioMes]) > @Corte THEN @Corte ELSE EOMONTH([InicioMes]) END
FROM [Meses] WHERE @Corte IS NOT NULL OPTION (MAXRECURSION 0);
DECLARE @Comparacion TABLE (
    [Fecha] date, [Obra] nvarchar(20), [Partida] nvarchar(20),
    [BaseNAV] decimal(18,2), [BaseVista] decimal(18,2),
    [PlanNAV] decimal(18,2), [PlanVista] decimal(18,2),
    [AvanceNAV] decimal(9,6), [AvanceVista] decimal(9,6), [Avance01] decimal(9,6),
    [ProduccionNAV] decimal(18,2), [ProduccionVista] decimal(18,2), [Produccion01] decimal(18,2)
);
INSERT INTO @Comparacion
SELECT [c].[Fecha], [t].[Job No_], [t].[Job Task No_],
    COALESCE([n].[Base],0), COALESCE([v].[Base],0), COALESCE([np].[Plan],0), COALESCE([vp].[Plan],0),
    COALESCE([e].[Avance],0), COALESCE([m].[Avance],0), COALESCE([a].[Avance],0),
    CAST(ROUND(COALESCE([n].[Base],0) * COALESCE([e].[Avance],0),2) AS decimal(18,2)),
    CAST(ROUND(COALESCE([v].[Base],0) * COALESCE([m].[Avance],0),2) AS decimal(18,2)),
    CAST(ROUND(COALESCE([p].[BaseVentaProduccion],0) * COALESCE([a].[Avance],0),2) AS decimal(18,2))
FROM [dbo].[CONSTRUCTORA DEMO$Job Task] AS [t]
CROSS JOIN @Cortes AS [c]
OUTER APPLY (
    SELECT CAST(SUM([l].[Line Amount (LCY)]) AS decimal(18,2)) AS [Base]
    FROM [dbo].[CONSTRUCTORA DEMO$Job Planning Line] AS [l]
    WHERE [l].[Job No_] = [t].[Job No_] AND [l].[Job Task No_] = [t].[Job Task No_]
        AND [l].[Line Type] IN (1,2) AND [l].[Planning Date] <> CONVERT(datetime,'17530101',112)
) AS [n]
OUTER APPLY (
    SELECT CAST(SUM([l].[Line Amount (LCY)]) AS decimal(18,2)) AS [Plan]
    FROM [dbo].[CONSTRUCTORA DEMO$Job Planning Line] AS [l]
    WHERE [l].[Job No_] = [t].[Job No_] AND [l].[Job Task No_] = [t].[Job Task No_]
        AND [l].[Line Type] IN (1,2) AND [l].[Planning Date] <> CONVERT(datetime,'17530101',112)
        AND [l].[Planning Date] < DATEADD(day,1,[c].[Fecha])
) AS [np]
OUTER APPLY (
    SELECT SUM([f].[BaseVentaElegible]) AS [Base]
    FROM [rpt].[FactPlanificacion] AS [f]
    WHERE [f].[ObraClave] = [t].[Job No_] AND [f].[PartidaClave] = [t].[Job No_] + N'|' + [t].[Job Task No_]
) AS [v]
OUTER APPLY (
    SELECT SUM([f].[BaseVentaElegible]) AS [Plan]
    FROM [rpt].[FactPlanificacion] AS [f]
    WHERE [f].[ObraClave] = [t].[Job No_] AND [f].[PartidaClave] = [t].[Job No_] + N'|' + [t].[Job Task No_]
        AND [f].[Fecha] <= [c].[Fecha]
) AS [vp]
OUTER APPLY (
    SELECT TOP (1) CAST([x].[Completion _] / 100 AS decimal(9,6)) AS [Avance]
    FROM [dbo].[CONSTRUCTORA DEMO$Job Progress Entry] AS [x]
    WHERE [x].[Job No_] = [t].[Job No_] AND [x].[Job Task No_] = [t].[Job Task No_]
        AND [x].[Measurement Date] <> CONVERT(datetime,'17530101',112)
        AND [x].[Measurement Date] < DATEADD(day,1,[c].[Fecha])
    ORDER BY [x].[Measurement Date] DESC, [x].[Entry No_] DESC
) AS [e]
OUTER APPLY (
    SELECT TOP (1) [f].[Avance] FROM [rpt].[FactMedicionAvance] AS [f]
    WHERE [f].[PartidaClave] = [t].[Job No_] + N'|' + [t].[Job Task No_]
        AND [f].[Fecha] <= [c].[Fecha]
    ORDER BY [f].[Fecha] DESC, [f].[MedicionClave] DESC
) AS [m]
OUTER APPLY (
    SELECT TOP (1) [f].[Avance] FROM [rpt].[FactAvance] AS [f]
    WHERE [f].[PartidaClave] = [t].[Job No_] + N'|' + [t].[Job Task No_]
        AND [f].[Fecha] = [c].[Fecha]
) AS [a]
LEFT JOIN [rpt].[FactPresupuesto] AS [p]
    ON [p].[PartidaClave] = [t].[Job No_] + N'|' + [t].[Job Task No_]
WHERE [t].[Job Task Type] = 0 AND [t].[Job Task No_] <> N'10';

SELECT COUNT(*) AS [PartidaCortes], COUNT(DISTINCT [Fecha]) AS [Cierres], COUNT(DISTINCT [Obra]) AS [Obras],
    MAX(ABS([BaseNAV]-[BaseVista])) AS [MaxDifBase], MAX(ABS([PlanNAV]-[PlanVista])) AS [MaxDifPlan],
    MAX(ABS([AvanceNAV]-[AvanceVista])) AS [MaxDifMedicion],
    MAX(ABS([ProduccionNAV]-[ProduccionVista])) AS [MaxDifProduccion]
FROM @Comparacion;
IF EXISTS (SELECT 1 FROM @Comparacion WHERE [BaseNAV] <> [BaseVista] OR [PlanNAV] <> [PlanVista]
    OR [AvanceNAV] <> [AvanceVista] OR [ProduccionNAV] <> [ProduccionVista])
    THROW 51002, N'Los avances de las nuevas vistas difieren de NAV.', 1;
-- Contrato histórico del 01: comprobar solo cierres presentes en esa vista.
SELECT COUNT(*) AS [Comparaciones01], MAX(ABS([x].[AvanceNAV]-[x].[Avance01])) AS [MaxDifAvance01],
    MAX(ABS([x].[ProduccionNAV]-[x].[Produccion01])) AS [MaxDifProduccion01]
FROM @Comparacion AS [x] WHERE EXISTS (SELECT 1 FROM [rpt].[FactAvance] AS [a] WHERE [a].[Fecha] = [x].[Fecha]);
IF EXISTS (SELECT 1 FROM @Comparacion AS [x]
    WHERE EXISTS (SELECT 1 FROM [rpt].[FactAvance] AS [a] WHERE [a].[Fecha] = [x].[Fecha])
    AND ([x].[AvanceNAV] <> [x].[Avance01] OR [x].[ProduccionNAV] <> [x].[Produccion01]))
    THROW 51003, N'El avance real no concuerda con el horizonte comun del issue 01.', 1;
-- Cada cociente usa sumas ponderadas; producción redondeada por partida como el 01.
SELECT [Fecha], COALESCE([Obra],N'TOTAL') AS [Obra], SUM([BaseNAV]) AS [BaseNAV], SUM([BaseVista]) AS [BaseVista],
    SUM([PlanNAV]) AS [PlanNAV], SUM([PlanVista]) AS [PlanVista],
    SUM([ProduccionNAV]) AS [ProduccionNAV], SUM([ProduccionVista]) AS [ProduccionVista],
    CAST(COALESCE(CAST(SUM([PlanVista]) AS decimal(24,2)) / NULLIF(CAST(SUM([BaseVista]) AS decimal(24,2)),0),0) AS decimal(18,10)) AS [Planificado],
    CAST(COALESCE(CAST(SUM([ProduccionVista]) AS decimal(24,2)) / NULLIF(CAST(SUM([BaseVista]) AS decimal(24,2)),0),0) AS decimal(18,10)) AS [Real]
FROM @Comparacion GROUP BY GROUPING SETS (([Fecha],[Obra]),([Fecha])) ORDER BY [Fecha],[Obra];
IF EXISTS (SELECT 1 FROM @Comparacion WHERE [Obra] = N'OB-26003' AND ([ProduccionVista] <> 0 OR [PlanVista] <> 0))
    THROW 51004, N'La obra futura no debe tener avance al corte actual.', 1;
SELECT N'Presupuesto_sin_fecha' AS [Control], SUM([VentaPlanificada]) AS [VentaExcluida],
    SUM([BaseVentaElegible]) AS [BaseExcluida]
FROM [rpt].[FactPlanificacion] WHERE [Fecha] IS NULL;
GO
