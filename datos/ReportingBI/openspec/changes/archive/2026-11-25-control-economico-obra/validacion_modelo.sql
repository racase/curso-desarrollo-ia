-- Referencia independiente para DAX. Solo tablas dbo de la empresa operativa.
-- Cierres de enero de 2025 a octubre de 2026; incluye partidas, obras y total.
SET NOCOUNT ON;
WITH [Cortes] AS (
    SELECT CONVERT(date, '20250131', 112) AS [Corte]
    UNION ALL
    SELECT EOMONTH(DATEADD(month, 1, [Corte])) FROM [Cortes] WHERE [Corte] < '20261031'
), [Presupuesto] AS (
    SELECT [t].[Job No_] AS [Obra], [t].[Job No_] + N'|' + [t].[Job Task No_] AS [Partida],
        [t].[Job Task No_] AS [Codigo],
        CAST(SUM(CASE WHEN [l].[Line Type] IN (0,2) THEN [l].[Total Cost (LCY)] ELSE 0 END) AS decimal(18,2)) AS [PptoCoste],
        CAST(SUM(CASE WHEN [l].[Line Type] IN (1,2) THEN [l].[Line Amount (LCY)] ELSE 0 END) AS decimal(18,2)) AS [PptoVenta],
        CAST(SUM(CASE WHEN [l].[Line Type] IN (1,2) AND [l].[Planning Date] > '17530101' AND [t].[Job Task No_] <> N'10'
            THEN [l].[Line Amount (LCY)] ELSE 0 END) AS decimal(18,2)) AS [BaseVenta]
    FROM [dbo].[CONSTRUCTORA DEMO$Job Task] AS [t]
    LEFT JOIN [dbo].[CONSTRUCTORA DEMO$Job Planning Line] AS [l]
        ON [l].[Job No_] = [t].[Job No_] AND [l].[Job Task No_] = [t].[Job Task No_]
    WHERE [t].[Job Task Type] = 0
    GROUP BY [t].[Job No_], [t].[Job Task No_]
), [Movimiento] AS (
    SELECT [c].[Corte], [e].[Job No_] + N'|' + [e].[Job Task No_] AS [Partida],
        CAST(SUM(CASE WHEN [e].[Entry Type] = 0 THEN [e].[Total Cost (LCY)] ELSE 0 END) AS decimal(18,2)) AS [CosteReal],
        CAST(-SUM(CASE WHEN [e].[Entry Type] = 1 THEN [e].[Line Amount (LCY)] ELSE 0 END) AS decimal(18,2)) AS [Certificado],
        CAST(SUM(CASE WHEN [e].[Entry Type] = 0 AND [e].[Posting Date] >= DATEFROMPARTS(YEAR([c].[Corte]), MONTH([c].[Corte]),1)
            THEN [e].[Total Cost (LCY)] ELSE 0 END) AS decimal(18,2)) AS [CosteMensual],
        CAST(-SUM(CASE WHEN [e].[Entry Type] = 1 AND [e].[Posting Date] >= DATEFROMPARTS(YEAR([c].[Corte]), MONTH([c].[Corte]),1)
            THEN [e].[Line Amount (LCY)] ELSE 0 END) AS decimal(18,2)) AS [CertMensual]
    FROM [Cortes] AS [c]
    JOIN [dbo].[CONSTRUCTORA DEMO$Job Ledger Entry] AS [e] ON [e].[Posting Date] < DATEADD(day,1,[c].[Corte])
    GROUP BY [c].[Corte], [e].[Job No_], [e].[Job Task No_]
), [Mediciones] AS (
    SELECT [c].[Corte], [p].*, CAST(COALESCE([a].[Completion _],0)/100 AS decimal(9,6)) AS [Avance]
    FROM [Cortes] AS [c] CROSS JOIN [Presupuesto] AS [p]
    OUTER APPLY (
        SELECT TOP (1) [e].[Completion _]
        FROM [dbo].[CONSTRUCTORA DEMO$Job Progress Entry] AS [e]
        WHERE [e].[Job No_] = [p].[Obra] AND [e].[Job Task No_] = [p].[Codigo]
            AND [e].[Measurement Date] > '17530101' AND [e].[Measurement Date] < DATEADD(day,1,[c].[Corte])
        ORDER BY [e].[Measurement Date] DESC, [e].[Entry No_] DESC
    ) AS [a]
), [Producciones] AS (
    SELECT *, CAST(ROUND([BaseVenta]*[Avance],2) AS decimal(18,2)) AS [Produccion] FROM [Mediciones]
), [Globales] AS (
    SELECT [Corte], [Obra], COALESCE(CAST(SUM([Produccion]) AS decimal(28,10)) / NULLIF(SUM([BaseVenta]),0),0) AS [Global]
    FROM [Producciones] GROUP BY [Corte], [Obra]
), [Previstos] AS (
    SELECT [p].*, [g].[Global], CAST(ROUND([p].[PptoCoste] * CASE WHEN [p].[Codigo] = N'10' THEN [g].[Global] ELSE [p].[Avance] END,2) AS decimal(18,2)) AS [Previsto],
        [p].[Produccion] - LAG([p].[Produccion],1,0) OVER (PARTITION BY [p].[Partida] ORDER BY [p].[Corte]) AS [ProdMensual]
    FROM [Producciones] AS [p] JOIN [Globales] AS [g] ON [g].[Corte] = [p].[Corte] AND [g].[Obra] = [p].[Obra]
), [Datos] AS (
    SELECT [p].*, COALESCE([m].[CosteReal],0) AS [CosteReal], COALESCE([m].[Certificado],0) AS [Certificado],
        COALESCE([m].[CosteMensual],0) AS [CosteMensual], COALESCE([m].[CertMensual],0) AS [CertMensual]
    FROM [Previstos] AS [p] LEFT JOIN [Movimiento] AS [m] ON [m].[Corte] = [p].[Corte] AND [m].[Partida] = [p].[Partida]
), [Agregados] AS (
    SELECT [Corte], CASE WHEN GROUPING([Obra])=1 THEN 'TOTAL' WHEN GROUPING([Partida])=1 THEN 'OBRA' ELSE 'PARTIDA' END AS [Nivel],
        COALESCE([Obra],'*') AS [Obra], COALESCE([Partida],'*') AS [Partida],
        SUM([PptoCoste]) AS [PptoCoste], SUM([PptoVenta]) AS [PptoVenta], SUM([BaseVenta]) AS [BaseVenta],
        SUM([CosteReal]) AS [CosteReal], SUM([Certificado]) AS [Certificado], SUM([Produccion]) AS [Produccion], SUM([Previsto]) AS [Previsto],
        SUM(CASE WHEN [Codigo] <> N'10' THEN [Previsto] ELSE 0 END) AS [Directo],
        SUM(CASE WHEN [Codigo] = N'10' THEN [Previsto] ELSE 0 END) AS [Indirecto],
        SUM([Produccion]-[Certificado]) AS [Pendiente], SUM([PptoVenta]-[PptoCoste]) AS [MargenPrevisto],
        SUM([Certificado]-[CosteReal]) AS [MargenReal], SUM([CosteReal]-[Previsto]) AS [Desviacion],
        SUM([CosteMensual]) AS [CosteMensual], SUM([CertMensual]) AS [CertMensual], SUM([ProdMensual]) AS [ProdMensual],
        CASE WHEN GROUPING([Obra])=1 THEN COALESCE(CAST(SUM([Produccion]) AS decimal(28,10))/NULLIF(SUM([BaseVenta]),0),0)
            ELSE MAX([Global]) END AS [AvanceGlobal]
    FROM [Datos] GROUP BY [Corte], GROUPING SETS (([Obra],[Partida]),([Obra]),())
)
SELECT CONVERT(char(10),[Corte],23) AS [Corte], [Nivel], [Obra], [Partida],
    [PptoCoste], [PptoVenta], [CosteReal], [Certificado], [Produccion], [Previsto], [Directo], [Indirecto],
    [Pendiente], [MargenPrevisto], [MargenReal], [Desviacion], [CosteMensual], [CertMensual], [ProdMensual], [BaseVenta],
    CAST([AvanceGlobal] AS decimal(18,10)) AS [AvanceGlobal],
    CAST(CAST([MargenPrevisto] AS decimal(28,10))/NULLIF(CAST([PptoVenta] AS decimal(28,10)),0) AS decimal(18,10)) AS [MargenPrevistoPct],
    CAST(CAST([MargenReal] AS decimal(28,10))/NULLIF(CAST([Certificado] AS decimal(28,10)),0) AS decimal(18,10)) AS [MargenRealPct],
    CAST(CAST([Desviacion] AS decimal(28,10))/NULLIF(CAST([Previsto] AS decimal(28,10)),0) AS decimal(18,10)) AS [DesviacionPct],
    CASE WHEN [Previsto]=0 AND [CosteReal]>0 THEN 1 WHEN [Previsto]>0 AND [Desviacion]*100>[Previsto]*5 THEN 1 ELSE 0 END AS [Alerta]
FROM [Agregados] ORDER BY [Corte], [Nivel], [Obra], [Partida];
