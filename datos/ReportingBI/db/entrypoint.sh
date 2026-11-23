#!/usr/bin/env bash
# Arranca SQL Server y, la primera vez, crea la base NAVDEMO con los scripts de db/init/.
# SQL Server no tiene una carpeta de scripts de inicio como PostgreSQL: lo hacemos aquí.
set -euo pipefail

SQLCMD=/opt/mssql-tools18/bin/sqlcmd

/opt/mssql/bin/sqlservr &
pid=$!
trap 'kill -TERM "$pid"; wait "$pid"' TERM INT

sa() { "$SQLCMD" -C -S localhost -U sa -P "$MSSQL_SA_PASSWORD" -b "$@"; }

echo "Esperando a que SQL Server acepte conexiones..."
for _ in $(seq 1 90); do
  if sa -Q "SELECT 1" -o /dev/null 2>/dev/null; then break; fi
  sleep 2
done

# La base está lista si existe y el usuario reporting (lo crea el último script) también.
ready=$(sa -h -1 -W -Q "SET NOCOUNT ON; SELECT CASE WHEN DB_ID(N'NAVDEMO') IS NOT NULL AND EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'reporting') THEN 1 ELSE 0 END")

if [[ "$ready" != "1" ]]; then
  for f in /project/db/init/*.sql; do
    echo "Ejecutando $(basename "$f")..."
    sa -i "$f" -v REPORTING_PASSWORD="$REPORTING_PASSWORD" -o /dev/null
  done
  echo "Base de datos NAVDEMO creada."
else
  echo "La base de datos NAVDEMO ya existe."
fi

wait "$pid"
