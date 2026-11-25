#!/usr/bin/env bash
# Ejecuta SQL contra NAVDEMO con el usuario reporting (lee dbo, solo puede crear vistas en rpt).
#
# Desde datos/ReportingBI/ (con Podman, «podman compose» en lugar de «docker compose»):
#
#   Un fichero:          docker compose exec -T db bash db/query.sh -f sql/rpt/01_dim_obra.sql
#   SQL por la entrada:  ... | docker compose exec -T db bash db/query.sh
#   Consulta corta:      docker compose exec -T db bash db/query.sh 'SELECT [Name] FROM [Company]'
#
# Para cualquier SQL con comillas, usa la entrada estándar o un fichero: al pasar por
# docker compose en Windows, las comillas de los argumentos se pierden.
set -euo pipefail

args=(-C -S localhost -U reporting -P "$REPORTING_PASSWORD" -d NAVDEMO -b -W -s "|" -I)
sqlcmd=/opt/mssql-tools18/bin/sqlcmd

if [[ "${1:-}" == "-f" ]]; then
  exec "$sqlcmd" "${args[@]}" -i "${2:?Falta la ruta del fichero .sql}"
elif [[ $# -gt 0 ]]; then
  exec "$sqlcmd" "${args[@]}" -Q "$*"
else
  tmp=$(mktemp)
  trap 'rm -f "$tmp"' EXIT
  cat > "$tmp"
  "$sqlcmd" "${args[@]}" -i "$tmp"
fi
