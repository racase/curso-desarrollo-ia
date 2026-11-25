-- Solo lectura. Ejecutar con db/query.sh -f desde la raíz del proyecto.
-- EXCEPT en ambos sentidos comprueba todas las columnas contra NAV, no solo totales.
SET NOCOUNT ON;
DECLARE @Fallos int;

WITH [Esperado] AS (
    SELECT [j].[No_] AS [ObraClave], [j].[Description] AS [Obra], [j].[Status] AS [EstadoCodigo],
        CASE [j].[Status] WHEN 0 THEN N'Planificación' WHEN 2 THEN N'Abierta' WHEN 3 THEN N'Terminada' END AS [Estado],
        [j].[Global Dimension 1 Code] AS [DelegacionCodigo], [d].[Name] AS [Delegacion],
        [j].[Global Dimension 2 Code] AS [TipoObraCodigo], [t].[Name] AS [TipoObra],
        [j].[Person Responsible] AS [JefeObraCodigo], [r].[Name] AS [JefeObra],
        [j].[Bill-to Customer No_] AS [ClienteCodigo],
        CAST(NULLIF([j].[Creation Date], '17530101') AS date) AS [FechaCreacion],
        CAST(NULLIF([j].[Starting Date], '17530101') AS date) AS [FechaInicio],
        CAST(NULLIF([j].[Ending Date], '17530101') AS date) AS [FechaFinPlanificada]
    FROM [dbo].[CONSTRUCTORA DEMO$Job] AS [j]
    LEFT JOIN [dbo].[CONSTRUCTORA DEMO$Dimension Value] AS [d]
        ON [d].[Dimension Code] = N'DELEGACION' AND [d].[Code] = [j].[Global Dimension 1 Code]
    LEFT JOIN [dbo].[CONSTRUCTORA DEMO$Dimension Value] AS [t]
        ON [t].[Dimension Code] = N'TIPOOBRA' AND [t].[Code] = [j].[Global Dimension 2 Code]
    LEFT JOIN [dbo].[CONSTRUCTORA DEMO$Resource] AS [r] ON [r].[No_] = [j].[Person Responsible]
), [Faltantes] AS (
    SELECT * FROM [Esperado] EXCEPT SELECT * FROM [rpt].[DimObra]
), [Sobrantes] AS (
    SELECT * FROM [rpt].[DimObra] EXCEPT SELECT * FROM [Esperado]
)
SELECT @Fallos = (SELECT COUNT(*) FROM [Faltantes]) + (SELECT COUNT(*) FROM [Sobrantes]);
SELECT N'DimObra: comparación completa' AS [Prueba], @Fallos AS [Fallos];
IF @Fallos <> 0 THROW 51001, N'DimObra no coincide con NAV.', 1;

WITH [Esperado] AS (
    SELECT CAST([Job No_] + N'|' + [Job Task No_] AS nvarchar(41)) AS [PartidaClave],
        [Job No_] AS [ObraClave], [Job Task No_] AS [PartidaCodigo], [Description] AS [Partida],
        CASE WHEN [Job Task No_] = N'10' THEN N'Indirecto' ELSE N'Directo' END AS [TipoCoste],
        CAST(CASE WHEN [Job Task No_] = N'10' THEN 1 ELSE 0 END AS bit) AS [EsIndirecta]
    FROM [dbo].[CONSTRUCTORA DEMO$Job Task] WHERE [Job Task Type] = 0
), [Faltantes] AS (
    SELECT * FROM [Esperado] EXCEPT SELECT * FROM [rpt].[DimPartida]
), [Sobrantes] AS (
    SELECT * FROM [rpt].[DimPartida] EXCEPT SELECT * FROM [Esperado]
)
SELECT @Fallos = (SELECT COUNT(*) FROM [Faltantes]) + (SELECT COUNT(*) FROM [Sobrantes]);
SELECT N'DimPartida: comparación completa' AS [Prueba], @Fallos AS [Fallos];
IF @Fallos <> 0 THROW 51002, N'DimPartida no coincide con NAV.', 1;

WITH [Esperado] AS (
    SELECT [t].[Job No_] AS [ObraClave],
        CAST([t].[Job No_] + N'|' + [t].[Job Task No_] AS nvarchar(41)) AS [PartidaClave],
        CAST(SUM(CASE WHEN [l].[Line Type] IN (0, 2) THEN [l].[Total Cost (LCY)] ELSE 0 END) AS decimal(18,2)) AS [PresupuestoCoste],
        CAST(SUM(CASE WHEN [l].[Line Type] IN (1, 2) THEN [l].[Line Amount (LCY)] ELSE 0 END) AS decimal(18,2)) AS [PresupuestoVenta],
        CAST(SUM(CASE WHEN [l].[Line Type] IN (1, 2) AND [l].[Planning Date] > '17530101' AND [t].[Job Task No_] <> N'10'
            THEN [l].[Line Amount (LCY)] ELSE 0 END) AS decimal(18,2)) AS [BaseVentaProduccion],
        CAST(SUM(CASE WHEN [l].[Line Type] IN (0, 2) AND [l].[Planning Date] = '17530101'
            THEN [l].[Total Cost (LCY)] ELSE 0 END) AS decimal(18,2)) AS [PresupuestoCosteSinFecha],
        CAST(SUM(CASE WHEN [l].[Line Type] IN (1, 2) AND [l].[Planning Date] = '17530101'
            THEN [l].[Line Amount (LCY)] ELSE 0 END) AS decimal(18,2)) AS [PresupuestoVentaSinFecha],
        MIN(CAST(NULLIF([l].[Planning Date], '17530101') AS date)) AS [PrimeraFechaPlanificada],
        MAX(CAST(NULLIF([l].[Planning Date], '17530101') AS date)) AS [UltimaFechaPlanificada]
    FROM [dbo].[CONSTRUCTORA DEMO$Job Task] AS [t]
    LEFT JOIN [dbo].[CONSTRUCTORA DEMO$Job Planning Line] AS [l]
        ON [l].[Job No_] = [t].[Job No_] AND [l].[Job Task No_] = [t].[Job Task No_]
    WHERE [t].[Job Task Type] = 0
    GROUP BY [t].[Job No_], [t].[Job Task No_]
), [Faltantes] AS (
    SELECT * FROM [Esperado] EXCEPT SELECT * FROM [rpt].[FactPresupuesto]
), [Sobrantes] AS (
    SELECT * FROM [rpt].[FactPresupuesto] EXCEPT SELECT * FROM [Esperado]
)
SELECT @Fallos = (SELECT COUNT(*) FROM [Faltantes]) + (SELECT COUNT(*) FROM [Sobrantes]);
SELECT N'FactPresupuesto: comparación completa' AS [Prueba], @Fallos AS [Fallos];
IF @Fallos <> 0 THROW 51003, N'FactPresupuesto no coincide con NAV.', 1;

WITH [Esperado] AS (
    SELECT [Job No_] AS [ObraClave], CAST([Job No_] + N'|' + [Job Task No_] AS nvarchar(41)) AS [PartidaClave],
        CAST(NULLIF([Posting Date], '17530101') AS date) AS [Fecha],
        CAST(SUM(CASE WHEN [Entry Type] = 0 THEN [Total Cost (LCY)] ELSE 0 END) AS decimal(18,2)) AS [CosteReal],
        CAST(SUM(CASE WHEN [Entry Type] = 1 THEN -[Line Amount (LCY)] ELSE 0 END) AS decimal(18,2)) AS [Certificado],
        COUNT(*) AS [NumeroMovimientos]
    FROM [dbo].[CONSTRUCTORA DEMO$Job Ledger Entry]
    WHERE [Entry Type] IN (0, 1)
    GROUP BY [Job No_], [Job Task No_], CAST(NULLIF([Posting Date], '17530101') AS date)
), [Faltantes] AS (
    SELECT * FROM [Esperado] EXCEPT SELECT * FROM [rpt].[FactMovimiento]
), [Sobrantes] AS (
    SELECT * FROM [rpt].[FactMovimiento] EXCEPT SELECT * FROM [Esperado]
)
SELECT @Fallos = (SELECT COUNT(*) FROM [Faltantes]) + (SELECT COUNT(*) FROM [Sobrantes]);
SELECT N'FactMovimiento: comparación completa' AS [Prueba], @Fallos AS [Fallos];
IF @Fallos <> 0 THROW 51004, N'FactMovimiento no coincide con NAV.', 1;

-- Cierres y elección de mediciones independientes: CTE recursiva y ROW_NUMBER,
-- en lugar del calendario por dígitos y TOP/OUTER APPLY de la vista.
WITH [Cierres] AS (
    SELECT EOMONTH(MIN([Measurement Date])) AS [Fecha]
    FROM [dbo].[CONSTRUCTORA DEMO$Job Progress Entry] WHERE [Measurement Date] > '17530101'
    UNION ALL
    SELECT EOMONTH(DATEADD(month, 1, [Fecha])) FROM [Cierres] WHERE [Fecha] < '20261031'
), [Candidatos] AS (
    SELECT [t].[Job No_] AS [ObraClave], CAST([t].[Job No_] + N'|' + [t].[Job Task No_] AS nvarchar(41)) AS [PartidaClave],
        [c].[Fecha], [e].[Entry No_] AS [MedicionClave], CAST([e].[Measurement Date] AS date) AS [FechaUltimaMedicion],
        CAST(COALESCE([e].[Completion _], 0) * CAST(0.01 AS decimal(3,2)) AS decimal(9,6)) AS [Avance],
        ROW_NUMBER() OVER (PARTITION BY [t].[Job No_], [t].[Job Task No_], [c].[Fecha]
            ORDER BY [e].[Measurement Date] DESC, [e].[Entry No_] DESC) AS [Orden]
    FROM [dbo].[CONSTRUCTORA DEMO$Job Task] AS [t]
    CROSS JOIN [Cierres] AS [c]
    LEFT JOIN [dbo].[CONSTRUCTORA DEMO$Job Progress Entry] AS [e]
        ON [e].[Job No_] = [t].[Job No_] AND [e].[Job Task No_] = [t].[Job Task No_]
        AND [e].[Measurement Date] > '17530101' AND CAST([e].[Measurement Date] AS date) <= [c].[Fecha]
    WHERE [t].[Job Task Type] = 0 AND [t].[Job Task No_] <> N'10'
), [Esperado] AS (
    SELECT [ObraClave], [PartidaClave], [Fecha], [MedicionClave], [FechaUltimaMedicion], [Avance]
    FROM [Candidatos] WHERE [Orden] = 1
), [Faltantes] AS (
    SELECT * FROM [Esperado] EXCEPT SELECT * FROM [rpt].[FactAvance]
), [Sobrantes] AS (
    SELECT * FROM [rpt].[FactAvance] EXCEPT SELECT * FROM [Esperado]
)
SELECT @Fallos = (SELECT COUNT(*) FROM [Faltantes]) + (SELECT COUNT(*) FROM [Sobrantes]);
SELECT N'FactAvance: todos los cierres contra NAV' AS [Prueba], @Fallos AS [Fallos];
IF @Fallos <> 0 THROW 51005, N'FactAvance no coincide con NAV.', 1;

-- Comprobar unicidad, huérfanos, opciones no reconocidas y fechas vacías.
WITH [ClavesDuplicadas] AS (
    SELECT N'DimObra' AS [Vista] FROM [rpt].[DimObra] GROUP BY [ObraClave] HAVING COUNT(*) > 1
    UNION ALL SELECT N'DimPartida' FROM [rpt].[DimPartida] GROUP BY [PartidaClave] HAVING COUNT(*) > 1
    UNION ALL SELECT N'FactPresupuesto' FROM [rpt].[FactPresupuesto] GROUP BY [ObraClave], [PartidaClave] HAVING COUNT(*) > 1
    UNION ALL SELECT N'FactMovimiento' FROM [rpt].[FactMovimiento] GROUP BY [ObraClave], [PartidaClave], [Fecha] HAVING COUNT(*) > 1
    UNION ALL SELECT N'FactAvance' FROM [rpt].[FactAvance] GROUP BY [ObraClave], [PartidaClave], [Fecha] HAVING COUNT(*) > 1
), [Hechos] AS (
    SELECT [ObraClave], [PartidaClave] FROM [rpt].[FactPresupuesto]
    UNION ALL SELECT [ObraClave], [PartidaClave] FROM [rpt].[FactMovimiento]
    UNION ALL SELECT [ObraClave], [PartidaClave] FROM [rpt].[FactAvance]
)
SELECT @Fallos = (SELECT COUNT(*) FROM [ClavesDuplicadas]) + (
    SELECT COUNT(*) FROM [Hechos] AS [h]
    LEFT JOIN [rpt].[DimObra] AS [o] ON [o].[ObraClave] = [h].[ObraClave]
    LEFT JOIN [rpt].[DimPartida] AS [p] ON [p].[PartidaClave] = [h].[PartidaClave] AND [p].[ObraClave] = [h].[ObraClave]
    WHERE [o].[ObraClave] IS NULL OR [p].[PartidaClave] IS NULL
) + (SELECT COUNT(*) FROM [dbo].[CONSTRUCTORA DEMO$Job Task] WHERE [Job No_] LIKE '%|%' OR [Job Task No_] LIKE '%|%')
  + (SELECT COUNT(*) FROM [rpt].[DimObra] WHERE [Estado] IS NULL OR [Delegacion] IS NULL OR [TipoObra] IS NULL OR [JefeObra] IS NULL)
  + (SELECT COUNT(*) FROM [dbo].[CONSTRUCTORA DEMO$Job Planning Line] WHERE [Line Type] NOT IN (0, 1, 2))
  + (SELECT COUNT(*) FROM [dbo].[CONSTRUCTORA DEMO$Job Ledger Entry] WHERE [Entry Type] NOT IN (0, 1))
  + (SELECT COUNT(*) FROM [dbo].[CONSTRUCTORA DEMO$Job Progress Entry] WHERE [Completion _] < 0 OR [Completion _] > 100 OR CAST([Measurement Date] AS date) <> EOMONTH([Measurement Date]))
  + (SELECT COUNT(*) FROM [dbo].[CONSTRUCTORA DEMO$Job Task] WHERE [Job Task Type] NOT IN (0, 3, 4))
  + (SELECT COUNT(*) FROM (SELECT [Job No_], [Job Task No_], [Measurement Date]
        FROM [dbo].[CONSTRUCTORA DEMO$Job Progress Entry]
        GROUP BY [Job No_], [Job Task No_], [Measurement Date] HAVING COUNT(*) > 1) AS [DuplicadosMedicion])
  + (SELECT COUNT(*) FROM [rpt].[FactMovimiento] WHERE [Fecha] = '17530101')
  + (SELECT COUNT(*) FROM [rpt].[FactPresupuesto] WHERE [PrimeraFechaPlanificada] = '17530101' OR [UltimaFechaPlanificada] = '17530101');
SELECT N'Claves, integridad, opciones y fechas' AS [Prueba], @Fallos AS [Fallos];
IF @Fallos <> 0 THROW 51006, N'Error de integridad o dato sin interpretar.', 1;

-- Tabla de importes: comparar cada obra sin depender de joins entre hechos.
WITH [PresupuestoNAV] AS (
    SELECT [Job No_] AS [ObraClave], CAST(SUM([Total Cost (LCY)]) AS decimal(18,2)) AS [Coste],
        CAST(SUM([Line Amount (LCY)]) AS decimal(18,2)) AS [Venta]
    FROM [dbo].[CONSTRUCTORA DEMO$Job Planning Line] GROUP BY [Job No_]
), [MovimientoNAV] AS (
    SELECT [Job No_] AS [ObraClave],
        CAST(SUM(CASE WHEN [Entry Type] = 0 THEN [Total Cost (LCY)] ELSE 0 END) AS decimal(18,2)) AS [Coste],
        CAST(-SUM(CASE WHEN [Entry Type] = 1 THEN [Line Amount (LCY)] ELSE 0 END) AS decimal(18,2)) AS [Certificado]
    FROM [dbo].[CONSTRUCTORA DEMO$Job Ledger Entry] WHERE [Posting Date] < '20261101' GROUP BY [Job No_]
), [PresupuestoVista] AS (
    SELECT [ObraClave], SUM([PresupuestoCoste]) AS [Coste], SUM([PresupuestoVenta]) AS [Venta]
    FROM [rpt].[FactPresupuesto] GROUP BY [ObraClave]
), [MovimientoVista] AS (
    SELECT [ObraClave], SUM([CosteReal]) AS [Coste], SUM([Certificado]) AS [Certificado]
    FROM [rpt].[FactMovimiento] WHERE [Fecha] <= '20261031' GROUP BY [ObraClave]
)
SELECT [j].[No_] AS [Obra], [n].[Coste] AS [PptoCosteNAV], [p].[Coste] AS [PptoCosteVista], [p].[Coste] - [n].[Coste] AS [DifPptoCoste],
    [n].[Venta] AS [PptoVentaNAV], [p].[Venta] AS [PptoVentaVista], [p].[Venta] - [n].[Venta] AS [DifPptoVenta],
    COALESCE([m].[Coste], 0) AS [CosteRealNAV], COALESCE([v].[Coste], 0) AS [CosteRealVista], COALESCE([v].[Coste], 0) - COALESCE([m].[Coste], 0) AS [DifCosteReal],
    COALESCE([m].[Certificado], 0) AS [CertificadoNAV], COALESCE([v].[Certificado], 0) AS [CertificadoVista], COALESCE([v].[Certificado], 0) - COALESCE([m].[Certificado], 0) AS [DifCertificado]
FROM [dbo].[CONSTRUCTORA DEMO$Job] AS [j]
LEFT JOIN [PresupuestoNAV] AS [n] ON [n].[ObraClave] = [j].[No_]
LEFT JOIN [MovimientoNAV] AS [m] ON [m].[ObraClave] = [j].[No_]
LEFT JOIN [PresupuestoVista] AS [p] ON [p].[ObraClave] = [j].[No_]
LEFT JOIN [MovimientoVista] AS [v] ON [v].[ObraClave] = [j].[No_]
ORDER BY [j].[No_];

SELECT [o].[ObraClave] AS [Obra], COUNT(DISTINCT [p].[PartidaClave]) AS [Partidas],
    COUNT([a].[PartidaClave]) AS [Instantaneas], COUNT(DISTINCT [a].[Fecha]) AS [Cierres],
    SUM(CASE WHEN [a].[Fecha] = '20261031' THEN [a].[Avance] ELSE 0 END) AS [SumaAvancePartidasAlCorte]
FROM [rpt].[DimObra] AS [o]
LEFT JOIN [rpt].[DimPartida] AS [p] ON [p].[ObraClave] = [o].[ObraClave]
LEFT JOIN [rpt].[FactAvance] AS [a] ON [a].[PartidaClave] = [p].[PartidaClave]
GROUP BY [o].[ObraClave] ORDER BY [o].[ObraClave];

SELECT SUM([PresupuestoCoste]) AS [PresupuestoCoste], SUM([PresupuestoVenta]) AS [PresupuestoVenta],
    SUM([BaseVentaProduccion]) AS [BaseVentaProduccion], SUM([PresupuestoCosteSinFecha]) AS [CosteSinFecha],
    SUM([PresupuestoVentaSinFecha]) AS [VentaSinFecha]
FROM [rpt].[FactPresupuesto];
SELECT COUNT(*) AS [FilasMovimiento], SUM([NumeroMovimientos]) AS [MovimientosNAV],
    SUM([CosteReal]) AS [CosteReal], SUM([Certificado]) AS [Certificado]
FROM [rpt].[FactMovimiento] WHERE [Fecha] <= '20261031';

-- Casos de la demo: ausencia de actividad y persistencia de una partida terminada.
IF EXISTS (SELECT 1 FROM [rpt].[FactAvance] WHERE [ObraClave] = N'OB-26003' AND [Avance] <> 0)
    THROW 51007, N'La obra sin iniciar tiene avance.', 1;
IF NOT EXISTS (SELECT 1 FROM [rpt].[FactAvance] WHERE [PartidaClave] = N'OB-25001|01'
    AND [Fecha] = '20261031' AND [FechaUltimaMedicion] = '20250331' AND [Avance] = 1)
    THROW 51008, N'No se conserva la medición de una partida terminada.', 1;
IF NOT EXISTS (SELECT 1 FROM [rpt].[FactPresupuesto] WHERE [PartidaClave] = N'OB-25004|06'
    AND [PresupuestoCosteSinFecha] = 28000 AND [PresupuestoVentaSinFecha] = 35000
    AND [PresupuestoVenta] - [BaseVentaProduccion] = 35000)
    THROW 51009, N'Tratamiento incorrecto de la revisión sin fecha.', 1;
SELECT N'Casos sin actividad, arrastre y revisión sin fecha' AS [Prueba], 0 AS [Fallos];
