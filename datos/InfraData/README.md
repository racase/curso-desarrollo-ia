# InfraData — demo de datos

Proyecto Python mínimo sobre PostgreSQL + TimescaleDB con lecturas de sensores ya cargadas.
Incluye una consulta de resumen diario por sensor, desarrollada con OpenSpec y Codex.

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

## Resumen diario por sensor

```bash
uv run python -m infradata resumen-diario
uv run python -m infradata resumen-diario --sede "Planta Valencia"
uv run python -m infradata resumen-diario --sensor VAL-T01 --desde 2026-08-01 --hasta 2026-08-01
uv run python -m infradata resumen-diario --sede "Planta Sagunto" --sensor SAG-P02 --desde 2026-09-03 --hasta 2026-09-03
```

Ejemplo del último comando con el conjunto inicial:

```text
Sede | Sensor | Día UTC | Unidad | Mínimo | Máximo | Media | Lecturas | Esperadas
Planta Sagunto | SAG-P02 | 2026-09-03 | bar | 6.11 | 7.27 | 6.67 | 210 | 288
```

Los filtros son opcionales y se combinan. Sede y código de sensor deben coincidir exactamente,
incluyendo mayúsculas y acentos. Las fechas usan `YYYY-MM-DD`; ambos extremos se incluyen
como días completos **UTC**, con independencia del horario local. Se puede indicar solo
`--desde` o solo `--hasta`, y sin fechas se consulta todo el histórico.

La base de datos calcula mínimo, máximo, media y recuento; la salida se ordena por día y
código de sensor. Los valores se presentan con dos decimales y punto decimal, sin convertir
la unidad del sensor. `Lecturas` muestra las observaciones disponibles y `Esperadas` siempre
muestra **288** (una lectura cada cinco minutos durante un día UTC). Un recuento menor
permite reconocer un día incompleto: la media usa solo las lecturas reales, por lo que debe
compararse con cautela frente a la de un día completo. No se rellenan huecos ni se excluyen
valores anómalos.

Los días sin lecturas se omiten. Si no hay resultados, incluido un sensor desconocido o
incompatible con la sede, se muestra `No hay lecturas para los filtros indicados.` y se
termina con código 0. Los argumentos inválidos devuelven código 2 antes de conectar y
los fallos de base de datos devuelven código 1. Usa `resumen-diario --help` para ver las opciones.

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
│   ├── analytics.py    consulta SQL del resumen diario
│   └── __main__.py     comandos `check` y `resumen-diario`
├── tests/
├── docker-compose.yml
└── pyproject.toml
```
