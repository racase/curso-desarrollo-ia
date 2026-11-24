-- Obras de CONSTRUCTORA DEMO con sus atributos de negocio.
-- Status (opción estándar de NAV): 0 Planificación, 1 Oferta, 2 Abierta, 3 Terminada.
CREATE OR ALTER VIEW [rpt].[DimObra] AS
SELECT
    j.[No_]                                            AS ObraId,
    CAST(j.[No_] + N' · ' + j.[Description] AS nvarchar(200)) AS Obra,
    j.[Description]                                    AS Descripcion,
    COALESCE(NULLIF(c.[Name], N''), N'(sin asignar)')  AS Cliente,
    COALESCE(NULLIF(d1.[Name], N''), N'(sin asignar)') AS Delegacion,
    COALESCE(NULLIF(d2.[Name], N''), N'(sin asignar)') AS TipoObra,
    COALESCE(NULLIF(r.[Name], N''), N'(sin asignar)')  AS JefeObra,
    CASE j.[Status]
        WHEN 0 THEN N'Planificación'
        WHEN 1 THEN N'Oferta'
        WHEN 2 THEN N'Abierta'
        WHEN 3 THEN N'Terminada'
    END                                                AS Estado,
    j.[Status]                                         AS EstadoOrden,
    CAST(NULLIF(j.[Starting Date], '1753-01-01') AS date) AS FechaInicio,
    CAST(NULLIF(j.[Ending Date], '1753-01-01') AS date)   AS FechaFin
FROM [dbo].[CONSTRUCTORA DEMO$Job] j
LEFT JOIN [dbo].[CONSTRUCTORA DEMO$Customer] c ON c.[No_] = j.[Bill-to Customer No_]
LEFT JOIN [dbo].[CONSTRUCTORA DEMO$Dimension Value] d1
       ON d1.[Dimension Code] = N'DELEGACION' AND d1.[Code] = j.[Global Dimension 1 Code]
LEFT JOIN [dbo].[CONSTRUCTORA DEMO$Dimension Value] d2
       ON d2.[Dimension Code] = N'TIPOOBRA' AND d2.[Code] = j.[Global Dimension 2 Code]
LEFT JOIN [dbo].[CONSTRUCTORA DEMO$Resource] r ON r.[No_] = j.[Person Responsible];
