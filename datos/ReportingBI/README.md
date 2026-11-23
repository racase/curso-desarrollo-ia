# ReportingBI — informes de Power BI sobre Navision

Demo de informes de Power BI de principio a fin, desde un issue funcional hasta el informe
publicado, con un agente y OpenSpec. Los datos están en la base de datos de un ERP Navision
(NAV clásico) simulado, de una constructora ficticia: `CONSTRUCTORA DEMO`.

```
issue → proposal OpenSpec → vistas SQL → modelo semántico (MCP de Power BI) → informe → prueba → publicación
```

El guion paso a paso está en [`docs/DEMO_POWERBI.md`](docs/DEMO_POWERBI.md) y los dos issues
de partida, en [`docs/issues/`](docs/issues/).

## Requisitos

- **Windows** con **Power BI Desktop**: el informe y el MCP de Power BI solo funcionan en Windows.
- **Docker Desktop o Podman** con compose. SQL Server necesita **al menos 2 GB de memoria
  libres** para el contenedor: con Podman, amplía la máquina con
  `podman machine stop`, `podman machine set --memory 4096` y `podman machine start`.
- **Node.js 22** o superior, para el MCP de Power BI (`npx`).
- Para publicar: una **licencia de Power BI** (Pro o superior) y un área de trabajo.
- Python 3.10 o superior solo si quieres regenerar los datos.

## Arranque

```bash
cd datos/ReportingBI
docker compose up -d        # con Podman: podman compose up -d
docker compose ps           # espera a que el estado sea "healthy" (la primera vez, 1–2 minutos)
```

La primera vez el contenedor crea la base de datos `NAVDEMO` y carga los datos. Para empezar
de cero: `docker compose down -v` y `docker compose up -d`.

## Conexión

| Parámetro | Valor |
|---|---|
| Servidor (Power BI, SSMS, Azure Data Studio) | `127.0.0.1,14330` |
| Base de datos | `NAVDEMO` |
| Usuario y contraseña | `reporting` / `Informes#2026` |

`reporting` puede leer todas las tablas y solo puede crear vistas en el esquema `rpt`. Las
contraseñas son de la demo y se cambian con un fichero `.env` (ver `.env.example`).

Usa la IP `127.0.0.1` y no `localhost`: Podman sobre WSL publica el puerto solo en IPv4 y
`localhost` puede resolverse como IPv6.

En Power BI Desktop: **Obtener datos → SQL Server**, servidor `127.0.0.1,14330`, base de
datos `NAVDEMO`, modo **Importar**, autenticación **Base de datos**. El contenedor usa un
certificado autofirmado: si Desktop avisa de que la conexión no está cifrada, acepta
conectar sin cifrar.

Desde la línea de comandos (lo que usa el agente), el SQL va por la entrada estándar o en un
fichero:

```bash
docker compose exec -T db bash db/query.sh <<'SQL'
SELECT [Name] FROM [Company];
SQL
docker compose exec -T db bash db/query.sh -f sql/rpt/01_dim_obra.sql
```

Con Podman, cambia `docker compose` por `podman compose`.

## Datos

Una base con el aspecto de la de un Navision: tablas `[EMPRESA$Tabla]`, opciones guardadas
como enteros, fechas vacías como `1753-01-01` y sin valores NULL.

| Tabla | Contenido |
|---|---|
| `Company` | Empresas de la base de datos |
| `...$Job` | Obras |
| `...$Job Task` | Capítulos de cada obra |
| `...$Job Planning Line` | Presupuesto, repartido en el tiempo |
| `...$Job Ledger Entry` | Movimientos reales: consumos y certificaciones |
| `...$Customer`, `...$Resource`, `...$Dimension Value` | Clientes, recursos y dimensiones |

Hay también tablas a medida que añadió el partner: encontrarlas forma parte del ejercicio.

Los datos son ficticios, los mismos para todos, con fecha de corte 31/10/2026. Contienen
a propósito **obras con problemas**: descubrirlas es el objetivo de los informes. Si cambias
`tools/generate_seed.py`, vuelve a ejecutarlo (`python tools/generate_seed.py`) y recrea la
base de datos.

## Configuración de agentes

Abre el agente desde esta carpeta.

| Ruta | Lo usa | Contenido |
|---|---|---|
| `AGENTS.md` | Codex y OpenCode | Instrucciones del proyecto: base de datos, NAV, vistas, Power BI |
| `.agents/skills/` | Codex y OpenCode | Skills del proyecto (vacío de momento) |
| `.codex/config.toml` | Codex | MCP de Power BI |
| `.mcp.json` | Claude Code | MCP de Power BI |
| `.claude/` | Claude Code | `CLAUDE.md`, que importa los `AGENTS.md`, y `settings.json` |
| `.opencode/opencode.json` | OpenCode | MCP de Power BI |

El MCP es el oficial de Microsoft, [`@microsoft/powerbi-modeling-mcp`](https://github.com/microsoft/powerbi-modeling-mcp):
trabaja con el modelo semántico (tablas, relaciones, medidas y consultas DAX), no con los
visuales del informe. La primera vez pide aceptar su licencia.

## Estructura

```
datos/ReportingBI/
├── AGENTS.md               instrucciones para los agentes
├── docker-compose.yml      SQL Server 2022 (puerto 14330)
├── db/
│   ├── entrypoint.sh       arranque: crea la base de datos la primera vez
│   ├── query.sh            ejecuta SQL con el usuario reporting
│   └── init/               esquema, datos y permisos (generados, no se editan)
├── docs/
│   ├── issues/             issues funcionales de partida
│   └── DEMO_POWERBI.md     guion de la demo
├── sql/rpt/                vistas de reporting (se crean en la demo)
└── tools/generate_seed.py  generador de los datos
```
