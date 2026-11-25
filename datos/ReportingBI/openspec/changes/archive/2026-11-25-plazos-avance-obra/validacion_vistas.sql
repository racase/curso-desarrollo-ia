-- Conciliación independiente: el esperado usa dbo, sin dimensiones rpt.
-- Solo lecturas y variables de tabla de sesión; no crea objetos ni modifica NAV.
SET NOCOUNT ON;
DECLARE @Plan TABLE ([ObraClave] nvarchar(20), [PartidaClave] nvarchar(41), [Fecha] date,
    [VentaPlanificada] decimal(18,2), [BaseVentaElegible] decimal(18,2));
INSERT INTO @Plan
SELECT [l].[Job No_], [l].[Job No_] + N'|' + [l].[Job Task No_],
    CAST(NULLIF([l].[Planning Date], CONVERT(datetime,'17530101',112)) AS date),
    CAST(SUM([l].[Line Amount (LCY)]) AS decimal(18,2)),
    CAST(SUM(CASE WHEN [l].[Planning Date] IS NOT NULL
        AND [l].[Planning Date] <> CONVERT(datetime,'17530101',112)
        THEN [l].[Line Amount (LCY)] ELSE 0 END) AS decimal(18,2))
FROM [dbo].[CONSTRUCTORA DEMO$Job Planning Line] AS [l]
JOIN [dbo].[CONSTRUCTORA DEMO$Job Task] AS [t]
    ON [t].[Job No_] = [l].[Job No_] AND [t].[Job Task No_] = [l].[Job Task No_]
WHERE [t].[Job Task Type] = 0 AND [t].[Job Task No_] <> N'10' AND [l].[Line Type] IN (1,2)
GROUP BY [l].[Job No_], [l].[Job Task No_],
    CAST(NULLIF([l].[Planning Date], CONVERT(datetime,'17530101',112)) AS date);
DECLARE @Medicion TABLE ([MedicionClave] int, [ObraClave] nvarchar(20), [PartidaClave] nvarchar(41), [Fecha] date, [Avance] decimal(9,6));
INSERT INTO @Medicion
SELECT [e].[Entry No_], [e].[Job No_], [e].[Job No_] + N'|' + [e].[Job Task No_],
    CAST(NULLIF([e].[Measurement Date], CONVERT(datetime,'17530101',112)) AS date),
    CAST([e].[Completion _] / 100 AS decimal(9,6))
FROM [dbo].[CONSTRUCTORA DEMO$Job Progress Entry] AS [e]
WHERE EXISTS (SELECT 1 FROM [dbo].[CONSTRUCTORA DEMO$Job Task] AS [t]
    WHERE [t].[Job No_] = [e].[Job No_] AND [t].[Job Task No_] = [e].[Job Task No_]
        AND [t].[Job Task Type] = 0 AND [t].[Job Task No_] <> N'10');
DECLARE @Hito TABLE ([HitoClave] nvarchar(31), [ObraClave] nvarchar(20), [HitoCodigo] nvarchar(10),
    [Secuencia] int, [Hito] nvarchar(50), [FechaPlanificada] date, [FechaReal] date);
INSERT INTO @Hito
SELECT [Job No_] + N'|' + [Code], [Job No_], [Code], [Sequence], [Description],
    CAST(NULLIF([Planned Date], CONVERT(datetime,'17530101',112)) AS date),
    CAST(NULLIF([Actual Date], CONVERT(datetime,'17530101',112)) AS date)
FROM [dbo].[CONSTRUCTORA DEMO$Job Milestone];

DECLARE @Errores TABLE ([Control] nvarchar(60), [Diferencias] int);
INSERT INTO @Errores
SELECT N'Plan_NAV_menos_vista', COUNT(*) FROM (SELECT * FROM @Plan EXCEPT SELECT * FROM [rpt].[FactPlanificacion]) AS [d]
UNION ALL SELECT N'Plan_vista_menos_NAV', COUNT(*) FROM (SELECT * FROM [rpt].[FactPlanificacion] EXCEPT SELECT * FROM @Plan) AS [d]
UNION ALL SELECT N'Mediciones_NAV_menos_vista', COUNT(*) FROM (SELECT * FROM @Medicion EXCEPT SELECT * FROM [rpt].[FactMedicionAvance]) AS [d]
UNION ALL SELECT N'Mediciones_vista_menos_NAV', COUNT(*) FROM (SELECT * FROM [rpt].[FactMedicionAvance] EXCEPT SELECT * FROM @Medicion) AS [d]
UNION ALL SELECT N'Hitos_NAV_menos_vista', COUNT(*) FROM (SELECT * FROM @Hito EXCEPT SELECT * FROM [rpt].[FactHito]) AS [d]
UNION ALL SELECT N'Hitos_vista_menos_NAV', COUNT(*) FROM (SELECT * FROM [rpt].[FactHito] EXCEPT SELECT * FROM @Hito) AS [d];
INSERT INTO @Errores
SELECT N'Plan_clave_duplicada', COUNT(*) FROM (SELECT [ObraClave], [PartidaClave], [Fecha] FROM [rpt].[FactPlanificacion]
    GROUP BY [ObraClave], [PartidaClave], [Fecha] HAVING COUNT(*) <> 1) AS [d]
UNION ALL SELECT N'Mediciones_clave_duplicada', COUNT(*) FROM (SELECT [MedicionClave] FROM [rpt].[FactMedicionAvance]
    GROUP BY [MedicionClave] HAVING COUNT(*) <> 1) AS [d]
UNION ALL SELECT N'Hitos_clave_duplicada', COUNT(*) FROM (SELECT [HitoClave] FROM [rpt].[FactHito]
    GROUP BY [HitoClave] HAVING COUNT(*) <> 1) AS [d];
DECLARE @Corte date = (SELECT MAX(CAST([Posting Date] AS date)) FROM [dbo].[CONSTRUCTORA DEMO$Job Ledger Entry]
    WHERE [Posting Date] <> CONVERT(datetime,'17530101',112));
INSERT INTO @Errores
SELECT N'Corte_NAV_menos_vista', COUNT(*) FROM (SELECT CAST(1 AS int) AS [CorteClave], @Corte AS [FechaCorte]
    EXCEPT SELECT * FROM [rpt].[DimCorteDatos]) AS [d]
UNION ALL SELECT N'Corte_vista_menos_NAV', COUNT(*) FROM (SELECT * FROM [rpt].[DimCorteDatos]
    EXCEPT SELECT CAST(1 AS int), @Corte) AS [d]
UNION ALL SELECT N'Corte_numero_filas', ABS(COUNT(*) - 1) FROM [rpt].[DimCorteDatos];
SELECT * FROM @Errores ORDER BY [Control];
IF EXISTS (SELECT 1 FROM @Errores WHERE [Diferencias] <> 0)
    THROW 51000, N'La conciliacion de filas, claves, fechas o valores no coincide con NAV.', 1;

-- Resumen explícito por cada obra, incluidas las que no tienen mediciones.
WITH [NavPlan] AS (
    SELECT [ObraClave], COUNT(*) AS [Filas], SUM([VentaPlanificada]) AS [Venta], SUM([BaseVentaElegible]) AS [Base]
    FROM @Plan GROUP BY [ObraClave]
), [VistaPlan] AS (
    SELECT [ObraClave], COUNT(*) AS [Filas], SUM([VentaPlanificada]) AS [Venta], SUM([BaseVentaElegible]) AS [Base]
    FROM [rpt].[FactPlanificacion] GROUP BY [ObraClave]
)
SELECT [j].[No_] AS [Obra], COALESCE([n].[Filas],0) AS [FilasNAV], COALESCE([v].[Filas],0) AS [FilasVista],
    COALESCE([n].[Venta],0) AS [VentaNAV], COALESCE([v].[Venta],0) AS [VentaVista],
    COALESCE([v].[Venta],0) - COALESCE([n].[Venta],0) AS [DifVenta],
    COALESCE([n].[Base],0) AS [BaseNAV], COALESCE([v].[Base],0) AS [BaseVista],
    COALESCE([v].[Base],0) - COALESCE([n].[Base],0) AS [DifBase]
FROM [dbo].[CONSTRUCTORA DEMO$Job] AS [j]
LEFT JOIN [NavPlan] AS [n] ON [n].[ObraClave] = [j].[No_]
LEFT JOIN [VistaPlan] AS [v] ON [v].[ObraClave] = [j].[No_]
ORDER BY [j].[No_];
SELECT [j].[No_] AS [Obra],
    (SELECT COUNT(*) FROM @Medicion AS [m] WHERE [m].[ObraClave] = [j].[No_]) AS [MedicionesNAV],
    (SELECT COUNT(*) FROM [rpt].[FactMedicionAvance] AS [m] WHERE [m].[ObraClave] = [j].[No_]) AS [MedicionesVista],
    (SELECT COUNT(*) FROM @Hito AS [h] WHERE [h].[ObraClave] = [j].[No_]) AS [HitosNAV],
    (SELECT COUNT(*) FROM [rpt].[FactHito] AS [h] WHERE [h].[ObraClave] = [j].[No_]) AS [HitosVista],
    CAST(MAX([e].[Posting Date]) AS date) AS [UltimoMovimientoObra], @Corte AS [CorteGlobal]
FROM [dbo].[CONSTRUCTORA DEMO$Job] AS [j]
LEFT JOIN [dbo].[CONSTRUCTORA DEMO$Job Ledger Entry] AS [e] ON [e].[Job No_] = [j].[No_]
GROUP BY [j].[No_] ORDER BY [j].[No_];
SELECT N'TotalNAV' AS [Origen], (SELECT COUNT(*) FROM @Plan) AS [PlanFilas],
    SUM([VentaPlanificada]) AS [Venta], SUM([BaseVentaElegible]) AS [Base],
    (SELECT COUNT(*) FROM @Medicion) AS [Mediciones], (SELECT COUNT(*) FROM @Hito) AS [Hitos] FROM @Plan
UNION ALL
SELECT N'TotalVista', (SELECT COUNT(*) FROM [rpt].[FactPlanificacion]), SUM([VentaPlanificada]), SUM([BaseVentaElegible]),
    (SELECT COUNT(*) FROM [rpt].[FactMedicionAvance]), (SELECT COUNT(*) FROM [rpt].[FactHito]) FROM [rpt].[FactPlanificacion];
SELECT * FROM [rpt].[FactPlanificacion] WHERE [Fecha] IS NULL;
IF (SELECT SUM([VentaPlanificada]) FROM [rpt].[FactPlanificacion] WHERE [Fecha] IS NULL) <> 35000
    OR EXISTS (SELECT 1 FROM [rpt].[FactPlanificacion] WHERE [Fecha] IS NULL AND [BaseVentaElegible] <> 0)
    THROW 51001, N'Revisar la exclusion del presupuesto sin fecha.', 1;
GO
