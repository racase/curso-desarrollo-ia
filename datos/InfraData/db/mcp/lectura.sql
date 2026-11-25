-- Usuario lectura: lo usa el MCP de base de datos (demo de lenguaje natural).
-- Solo puede leer: la protección está aquí, no en el MCP ni en el prompt.
--
-- No está en db/init: no forma parte del arranque de la base. Se aplica a mano, una vez:
--   docker compose exec -T db psql -U infradata -d infradata < db/mcp/lectura.sql

CREATE ROLE lectura LOGIN PASSWORD 'lectura';

GRANT CONNECT ON DATABASE infradata TO lectura;
GRANT USAGE ON SCHEMA public TO lectura;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO lectura;
-- Tablas y vistas que se creen después (por ejemplo, el resumen precalculado del issue 05)
ALTER DEFAULT PRIVILEGES FOR ROLE infradata IN SCHEMA public GRANT SELECT ON TABLES TO lectura;

-- Toda sesión es de solo lectura y ninguna consulta pasa de 30 segundos
ALTER ROLE lectura SET default_transaction_read_only = on;
ALTER ROLE lectura SET statement_timeout = '30s';
