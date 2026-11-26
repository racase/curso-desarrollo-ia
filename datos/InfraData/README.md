# InfraData — demo de datos

Proyecto Python sobre PostgreSQL + TimescaleDB con lecturas de sensores ya cargadas.
Incluye comprobación del dataset y consulta de resúmenes diarios por sensor desde la CLI.

## Requisitos

- Docker Desktop (o Docker dentro de WSL2) o Podman
- Python 3.11 o superior
- [uv](https://docs.astral.sh/uv/) (recomendado) o `pip`

Con Podman, sustituye `docker` por `podman` en todos los comandos de este documento.
Codex y OpenSpec se instalan aparte (ver el `README.md` de la raíz).

## Arranque

```bash
cd datos/InfraData
docker compose up -d          # la primera vez carga los datos: espera unos segundos
uv sync                       # crea .venv e instala dependencias
uv run python -m infradata check
```

Salida esperada:

```
Sensores: 12
Lecturas: 313760
Desde:    2026-08-01 00:00 UTC
Hasta:    2026-10-30 23:55 UTC
```

Sin uv:

```bash
python -m venv .venv
.venv\Scripts\activate        # Windows; en Linux/WSL: source .venv/bin/activate
pip install -e . pytest ruff
python -m infradata check
```

## Comandos habituales

| Para | Comando |
|---|---|
| Tests | `uv run pytest` |
| Lint | `uv run ruff check .` |
| Formato | `uv run ruff format .` |
| Consola SQL | `docker compose exec db psql -U infradata` |
| Recrear la base de datos desde cero | `docker compose down -v` y `docker compose up -d` |

Los tests marcados como `integration` necesitan el contenedor levantado; si no lo está, se omiten.

## Conexión

| Parámetro | Valor |
|---|---|
| Host | `localhost` |
| Puerto | `5433` |
| Base de datos, usuario y contraseña | `infradata` |

Para apuntar a otra base de datos, define la variable de entorno `DATABASE_URL`.

## Resumen diario por sensor

Consulta mínimo, máximo, media y recuento de lecturas por sensor y día UTC:

```bash
uv run python -m infradata daily-summary
uv run python -m infradata daily-summary --sensor VAL-T01 --start-date 2026-08-01 --end-date 2026-08-01
uv run python -m infradata daily-summary --site "Planta Sagunto" --sensor SAG-P02 --start-date 2026-09-03 --end-date 2026-09-03
uv run python -m infradata daily-summary --help
```

Los filtros son opcionales y se combinan: sede y código deben coincidir exactamente,
incluidas las mayúsculas. Las fechas tienen formato `YYYY-MM-DD` y ambos extremos son
inclusivos en UTC. Puedes indicar solo uno; sin fechas se consulta todo el historial.
El día final incluye hasta el instante anterior a la medianoche UTC del día siguiente.

La salida es una tabla separada por tabuladores, ordenada por día y código, con columnas
`dia_utc`, `sensor`, `nombre`, `sede`, `unidad`, `min`, `max`, `media` y `lecturas`.
Los valores se muestran con tres decimales y punto decimal; el recuento es entero.
La media usa exclusivamente las lecturas disponibles: no se rellenan huecos ni se
descartan valores anómalos. Los días sin lecturas se omiten. Si no hay coincidencias,
se muestra `Sin lecturas para los filtros indicados.`, también para sedes o sensores
desconocidos. Todas las agregaciones se realizan en SQL.

Referencias del dataset fijo del curso:

| Sensor | Día UTC | Unidad | Mínimo | Máximo | Media | Lecturas |
| --- | --- | --- | ---: | ---: | ---: | ---: |
| VAL-T01 | 2026-08-01 | °C | 18.600 | 27.460 | 23.013 | 288 |
| SAG-P02 | 2026-09-03 | bar | 6.110 | 7.270 | 6.667 | 210 |
| PAT-L01 | 2026-10-14 | % | 44.270 | 75.770 | 56.854 | 216 |

`PAT-L01` no tiene lecturas los días 12 y 13 de octubre. Para comprobar la salida vacía:

```bash
uv run python -m infradata daily-summary --sensor PAT-L01 --start-date 2026-10-12 --end-date 2026-10-13
```

El comando devuelve 0 al consultar correctamente, incluso sin resultados; 2 para
argumentos o fechas inválidos y 1 ante errores de base de datos. La validación de
argumentos se realiza antes de conectar. No requiere dependencias nuevas ni cambios
de esquema o de las semillas.

## Datos

Dos tablas:

- `sensors`: 12 sensores en 3 sedes (Planta Valencia, Planta Sagunto, Almacén Paterna), de cuatro
  tipos: `temperature`, `pressure`, `flow` y `level`.
- `readings`: hypertable de TimescaleDB con una lectura cada 5 minutos por sensor, del
  1 de agosto al 30 de octubre de 2026 (UTC).

Los datos son sintéticos y los mismos para todos. Contienen, a propósito, **periodos sin lecturas**
y **algunos valores anómalos**: encontrarlos forma parte de los ejercicios.

## Configuración de agentes

| Ruta | Lo usa | Contenido |
|---|---|---|
| `AGENTS.md` | Codex y OpenCode | Instrucciones del proyecto: mapa, comandos, convenciones |
| `.agents/skills/` | Codex y OpenCode | Skills del proyecto (vacío de momento) |
| `.codex/config.toml` | Codex | Ajustes de Codex (sin ajustes activos de momento) |
| `.claude/` | Claude Code | `CLAUDE.md`, que importa los `AGENTS.md`, y `settings.json` |
| `.opencode/opencode.json` | OpenCode | Ajustes de OpenCode |

El curso se hace con Codex. Ábrelo desde esta carpeta y, la primera vez, acepta confiar en
ella: si no, Codex ignora `.codex/`.

## Estructura

```
datos/InfraData/
├── AGENTS.md           instrucciones para los agentes
├── .agents/skills/     skills del proyecto
├── .codex/             ajustes de Codex
├── .claude/            ajustes de Claude Code
├── .opencode/          ajustes de OpenCode
├── db/init/            SQL que se ejecuta al crear la base de datos (esquema y datos)
├── src/infradata/      código del proyecto
│   ├── config.py       configuración (DATABASE_URL)
│   ├── db.py           conexión
│   ├── analytics.py    agregación diaria mediante SQL
│   └── __main__.py     comandos `check` y `daily-summary`
├── tests/
├── docker-compose.yml
└── pyproject.toml
```
