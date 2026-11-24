-- Capítulos de imputación (Job Task Type = 0), uno por código. Los totalizadores
-- (tipos 3 y 4: «00» y «99») quedan fuera para no contar dos veces.
-- Coste indirecto: capítulo con presupuesto de coste y sin ninguna línea facturable en
-- ninguna obra.
CREATE OR ALTER VIEW [rpt].[DimCapitulo] AS
SELECT
    t.[Job Task No_]                                        AS CapituloId,
    CAST(t.[Job Task No_] + N' · ' + MIN(t.[Description]) AS nvarchar(120)) AS Capitulo,
    CAST(CASE WHEN EXISTS (
            SELECT 1 FROM [dbo].[CONSTRUCTORA DEMO$Job Planning Line] p
            WHERE p.[Job Task No_] = t.[Job Task No_] AND p.[Line Type] IN (1, 2))
         THEN 0 ELSE 1 END AS bit)                          AS EsCosteIndirecto
FROM [dbo].[CONSTRUCTORA DEMO$Job Task] t
WHERE t.[Job Task Type] = 0
GROUP BY t.[Job Task No_];
