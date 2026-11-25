-- Usuario lectura: lo usa el MCP de base de datos (demo de lenguaje natural).
-- Solo lee NAVDEMO (tablas de NAV y vistas rpt): no crea vistas ni modifica nada.
--
-- No está en db/init: no forma parte del arranque de la base. Se aplica a mano, una vez,
-- con la contraseña que usará el MCP (variable MSSQL_PASSWORD de Codex):
--   docker compose exec -T db bash -c '/opt/mssql-tools18/bin/sqlcmd -C -S localhost -U sa \
--     -P "$MSSQL_SA_PASSWORD" -b -v LECTURA_PASSWORD=Lectura#2026 -i db/mcp/lectura.sql'
SET NOCOUNT ON;
GO
USE [master];
GO
IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'lectura')
    CREATE LOGIN [lectura] WITH PASSWORD = N'$(LECTURA_PASSWORD)',
        DEFAULT_DATABASE = [NAVDEMO], CHECK_POLICY = OFF;
GO
USE [NAVDEMO];
GO
IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name = N'lectura')
    CREATE USER [lectura] FOR LOGIN [lectura];
ALTER ROLE [db_datareader] ADD MEMBER [lectura];
DENY INSERT, UPDATE, DELETE, ALTER, EXECUTE ON DATABASE::[NAVDEMO] TO [lectura];
GO
