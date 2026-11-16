# InfraData — demo de datos

Proyecto Python de analítica sobre PostgreSQL + TimescaleDB con lecturas de sensores ya cargadas.
Incluye resúmenes diarios, detección de huecos y anomalías, y exportación semanal.

## Requisitos

- Docker Desktop (o Docker dentro de WSL2) o Podman
- Python 3.11 o superior
- [uv](https://docs.astral.sh/uv/) (recomendado) o `pip`

Con Podman, sustituye `docker` por `podman` en todos los comandos de este documento.
Codex y OpenSpec se instalan aparte (ver el `README.md` de la raíz).

## Arranque

```bash
cd datos
docker compose up -d          # la primera vez carga los datos: espera unos segundos
uv sync                       # crea .venv e instala dependencias
uv run python -m infradata check
uv run python -m infradata migrate  # instala el resumen diario precalculado
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
python -m infradata migrate
```

## Comandos habituales

| Para | Comando |
|---|---|
| Tests | `uv run pytest` |
| Lint | `uv run ruff check .` |
| Formato | `uv run ruff format .` |
| Instalar o refrescar resumen | `uv run python -m infradata migrate` |
| Resumen diario | `uv run python -m infradata summary --site "Planta Valencia" --from-date 2026-08-01 --to-date 2026-08-07` |
| Huecos de lecturas | `uv run python -m infradata gaps --minutes 5 --site "Planta Sagunto"` |
| Valores anómalos | `uv run python -m infradata anomalies --sensor VAL-T01` |
| CSV semanal | `uv run python -m infradata weekly-csv --site "Planta Valencia" --week 2026-08-03 --output valencia.csv` |
| Consola SQL | `docker compose exec db psql -U infradata` |
| Recrear la base de datos desde cero | `docker compose down -v` y `docker compose up -d` |

Los tests marcados como `integration` necesitan el contenedor levantado; si no lo está, se omiten.

## Cuaderno de exploración visual (opcional)

Instala las dependencias del cuaderno con `uv sync --group notebook` y ejecuta
`uv run python -m infradata migrate` con la base de datos levantada. En VS Code,
instala las extensiones **Python** y **Jupyter**, abre
`notebooks/visual_exploration.py`, selecciona el intérprete `datos/.venv` y pulsa
**Run Below** en la primera celda `# %%` (o ejecuta las celdas en orden).
Los gráficos aparecen en la ventana interactiva de VS Code. El selector permite
ver cualquiera de los 12 sensores; arrastra sobre su serie para aplicar zoom.

El mapa muestra del 1 de agosto al 30 de octubre de 2026 en UTC. Cada celda
contiene `lecturas recibidas / 288 × 100`; los días sin lecturas aparecen como
0 %. El porcentaje exacto se ve al pasar el cursor por la celda. Usa el resumen
diario precalculado. La serie lee las lecturas originales solo del sensor elegido.

El grupo opcional `notebook` añade `ipykernel` para ejecutar celdas en VS Code,
`ipywidgets` para seleccionar el sensor y `plotly` para el mapa y el zoom. El
paquete principal no necesita estas dependencias para la CLI.

## Analítica

- `summary` muestra mínimo, máximo, media, número de lecturas y unidad por sensor y día UTC. Los filtros `--site`, `--sensor`, `--from-date` y `--to-date` se pueden combinar; las fechas límite se incluyen. Los días sin lecturas no aparecen y los incompletos muestran el recuento real.
- `migrate` crea el agregado continuo que usa `summary` y `weekly-csv` sin recrear el volumen ni tocar `db/init/`. Puede ejecutarse de nuevo. Los días cerrados se refrescan automáticamente cada minuto y pueden tardar hasta dos minutos en reflejar lecturas históricas en condiciones normales; el día UTC abierto incorpora lecturas recientes inmediatamente.
- `gaps` muestra los intervalos entre lecturas consecutivas que superan `--minutes` (5 por defecto), con inicio y fin UTC. Acepta `--site` y `--sensor`. No infiere huecos anteriores a la primera lectura ni posteriores a la última.
- `anomalies` muestra lecturas sospechosas y sus motivos, con filtros `--site` y `--sensor`. Los rangos y umbrales están fijados en [la especificación](openspec/specs/anomalous-readings/spec.md); una lectura puede tener varios motivos.
- `weekly-csv` exporta los resúmenes diarios de una sede para una semana de lunes a domingo UTC. `--week` debe ser la fecha del lunes. El CSV usa UTF-8 con BOM, punto y coma, coma decimal, dos decimales y saltos de línea CRLF; no sobrescribe ficheros existentes.

Los issues 1 a 5 del [backlog](docs/issues/README.md) están implementados. El [parte de incidencias](docs/issues/07-parte-de-incidencias.md) es el ejercicio principal pendiente para integrar esas funciones; el cuaderno visual (issue 8) es una ampliación opcional ya implementada. La migración del notebook (issue 6) queda pendiente hasta que el formador aporte `notebooks/exploracion.ipynb`; sin él no es posible verificar la equivalencia de resultados.

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
datos/
├── AGENTS.md           instrucciones para los agentes
├── .agents/skills/     skills del proyecto
├── .codex/             ajustes de Codex
├── .claude/            ajustes de Claude Code
├── .opencode/          ajustes de OpenCode
├── db/init/            SQL que se ejecuta al crear la base de datos (esquema y datos)
├── src/infradata/      código del proyecto
│   ├── config.py       configuración (DATABASE_URL)
│   ├── db.py           conexión
│   └── __main__.py     comando `check`
├── tests/
├── docker-compose.yml
└── pyproject.toml
```
