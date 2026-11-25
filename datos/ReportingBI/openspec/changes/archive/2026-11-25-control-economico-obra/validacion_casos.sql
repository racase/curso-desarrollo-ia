SET NOCOUNT ON;
SELECT [No_] AS [Obra], [Global Dimension 1 Code] AS [Delegacion],
    [Global Dimension 2 Code] AS [Tipo], [Person Responsible] AS [Jefe], [Status] AS [Estado]
FROM [dbo].[CONSTRUCTORA DEMO$Job] ORDER BY [No_];

SELECT [c].[Caso], [c].[Real], [c].[Previsto],
    CAST(([c].[Real]-[c].[Previsto])/NULLIF([c].[Previsto],0) AS decimal(18,10)) AS [Porcentaje],
    CASE WHEN [c].[Previsto]=0 AND [c].[Real]>0 THEN N'Coste sin avance'
        WHEN ([c].[Real]-[c].[Previsto])*100>[c].[Previsto]*5 THEN N'Desviación > 5 %' END AS [Estado],
    CASE WHEN [c].[Previsto]=0 AND [c].[Real]>0 THEN 1
        WHEN [c].[Previsto]>0 AND ([c].[Real]-[c].[Previsto])*100>[c].[Previsto]*5 THEN 1 ELSE 0 END AS [Alerta]
FROM (VALUES (N'5 % exacto',CAST(1050 AS decimal(18,2)),CAST(1000 AS decimal(18,2))),
    (N'Superior al 5 %',1050.01,1000), (N'Coste sin avance',100,0), (N'Sin coste ni avance',0,0)) AS [c] ([Caso],[Real],[Previsto]);

SELECT CAST(100000.0/400000 AS decimal(18,10)) AS [Avance],
    CAST(ROUND(20000*(100000.0/400000),2) AS decimal(18,2)) AS [Indirecto];
