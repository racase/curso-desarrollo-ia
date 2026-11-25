-- Lectura y diagnóstico. No crea objetos ni modifica datos.
SET NOCOUNT ON;
SELECT [Name] AS [Empresa] FROM [dbo].[Company];
SELECT [v].[name] AS [Vista01],
    CONVERT(varchar(64), HASHBYTES('SHA2_256', [m].[definition]), 2) AS [SHA256]
FROM [sys].[views] AS [v]
JOIN [sys].[sql_modules] AS [m] ON [m].[object_id] = [v].[object_id]
WHERE [v].[schema_id] = SCHEMA_ID(N'rpt')
    AND [v].[name] IN (N'DimObra', N'DimPartida', N'FactPresupuesto', N'FactMovimiento', N'FactAvance')
ORDER BY [v].[name];
-- Definiciones completas en fragmentos: evita el límite de texto de sqlcmd.
WITH [Numeros] AS (
    SELECT [u].[n] + 10 * [d].[n] AS [n]
    FROM (VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9)) AS [u]([n])
    CROSS JOIN (VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9)) AS [d]([n])
)
SELECT [v].[name] AS [Vista01], [n].[n] AS [Fragmento],
    CAST(SUBSTRING([m].[definition], 1 + 180 * [n].[n], 180) AS nvarchar(180)) AS [Definicion]
FROM [sys].[views] AS [v]
JOIN [sys].[sql_modules] AS [m] ON [m].[object_id] = [v].[object_id]
CROSS JOIN [Numeros] AS [n]
WHERE [v].[schema_id] = SCHEMA_ID(N'rpt')
    AND [v].[name] IN (N'DimObra', N'DimPartida', N'FactPresupuesto', N'FactMovimiento', N'FactAvance')
    AND 180 * [n].[n] < LEN([m].[definition])
ORDER BY [v].[name], [n].[n];
SELECT [t].[name] AS [Tabla], [c].[column_id], [c].[name] AS [Campo],
    TYPE_NAME([c].[user_type_id]) AS [Tipo], [c].[precision], [c].[scale]
FROM [sys].[tables] AS [t]
JOIN [sys].[columns] AS [c] ON [c].[object_id] = [t].[object_id]
WHERE [t].[name] IN (N'CONSTRUCTORA DEMO$Job Planning Line', N'CONSTRUCTORA DEMO$Job Progress Entry', N'CONSTRUCTORA DEMO$Job Milestone')
ORDER BY [t].[name], [c].[column_id];
SELECT [Line Type], COUNT(*) AS [Lineas] FROM [dbo].[CONSTRUCTORA DEMO$Job Planning Line] GROUP BY [Line Type];
SELECT [Job Task Type], COUNT(*) AS [Tareas] FROM [dbo].[CONSTRUCTORA DEMO$Job Task] GROUP BY [Job Task Type];
SELECT CAST(MAX(NULLIF([Posting Date], CONVERT(datetime,'17530101',112))) AS date) AS [CorteERP]
FROM [dbo].[CONSTRUCTORA DEMO$Job Ledger Entry];
SELECT N'Mediciones' AS [Fuente], COUNT(*) AS [Filas],
    SUM(CASE WHEN [Completion _] < 0 OR [Completion _] > 100 THEN 1 ELSE 0 END) AS [PorcentajesInvalidos],
    SUM(CASE WHEN [Measurement Date] IS NULL OR [Measurement Date] = CONVERT(datetime,'17530101',112) THEN 1 ELSE 0 END) AS [FechasVacias]
FROM [dbo].[CONSTRUCTORA DEMO$Job Progress Entry];
SELECT N'Hitos' AS [Fuente], COUNT(*) AS [Filas],
    SUM(CASE WHEN [Planned Date] IS NULL OR [Planned Date] = CONVERT(datetime,'17530101',112) THEN 1 ELSE 0 END) AS [SinPlan],
    SUM(CASE WHEN [Actual Date] IS NULL OR [Actual Date] = CONVERT(datetime,'17530101',112) THEN 1 ELSE 0 END) AS [SinReal]
FROM [dbo].[CONSTRUCTORA DEMO$Job Milestone];
SELECT N'MedicionesHuerfanas' AS [Control], COUNT(*) AS [Incidencias]
FROM [dbo].[CONSTRUCTORA DEMO$Job Progress Entry] AS [e]
LEFT JOIN [dbo].[CONSTRUCTORA DEMO$Job Task] AS [t]
    ON [t].[Job No_] = [e].[Job No_] AND [t].[Job Task No_] = [e].[Job Task No_]
WHERE [t].[Job No_] IS NULL OR [t].[Job Task Type] <> 0 OR [t].[Job Task No_] = N'10';
SELECT N'HitosHuerfanos' AS [Control], COUNT(*) AS [Incidencias]
FROM [dbo].[CONSTRUCTORA DEMO$Job Milestone] AS [h]
LEFT JOIN [dbo].[CONSTRUCTORA DEMO$Job] AS [j] ON [j].[No_] = [h].[Job No_]
WHERE [j].[No_] IS NULL;
SELECT N'PlanificacionHuerfana' AS [Control], COUNT(*) AS [Incidencias]
FROM [dbo].[CONSTRUCTORA DEMO$Job Planning Line] AS [l]
LEFT JOIN [dbo].[CONSTRUCTORA DEMO$Job Task] AS [t]
    ON [t].[Job No_] = [l].[Job No_] AND [t].[Job Task No_] = [l].[Job Task No_]
WHERE [t].[Job No_] IS NULL;
SELECT [Job No_], [Job Task No_], [Line Type], CAST([Line Amount (LCY)] AS decimal(18,2)) AS [VentaSinFecha]
FROM [dbo].[CONSTRUCTORA DEMO$Job Planning Line]
WHERE [Planning Date] IS NULL OR [Planning Date] = CONVERT(datetime,'17530101',112);
GO
