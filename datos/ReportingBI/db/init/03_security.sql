-- Usuario reporting: lo usan el agente y Power BI.
-- Puede leer las tablas de NAV (dbo) pero no modificarlas, y solo puede crear vistas en rpt.
SET NOCOUNT ON;
GO
USE [master];
GO
IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'reporting')
    CREATE LOGIN [reporting] WITH PASSWORD = N'$(REPORTING_PASSWORD)',
        DEFAULT_DATABASE = [NAVDEMO], CHECK_POLICY = OFF;
GO
USE [NAVDEMO];
GO
CREATE USER [reporting] FOR LOGIN [reporting];
ALTER ROLE [db_datareader] ADD MEMBER [reporting];
GRANT CREATE VIEW TO [reporting];
GRANT ALTER ON SCHEMA::[rpt] TO [reporting];
DENY INSERT, UPDATE, DELETE, ALTER ON SCHEMA::[dbo] TO [reporting];
GRANT SHOWPLAN TO [reporting];
GO
