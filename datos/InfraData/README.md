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

## Detección de huecos

Lista periodos sin lecturas esperadas, con una cadencia fija de 5 minutos:

```bash
uv run python -m infradata gaps
uv run python -m infradata gaps --sensor VAL-F01 --threshold-minutes 0
uv run python -m infradata gaps --site "Planta Sagunto" --sensor SAG-P02
uv run python -m infradata gaps --sensor PAT-L01 --start-date 2026-10-12 --end-date 2026-10-13
uv run python -m infradata gaps --help
```

El hueco comienza en la primera lectura esperada ausente y termina cuando se recibe
la siguiente: se descuentan los 5 minutos normales entre envíos. Por ejemplo, entre
las lecturas de VAL-F01 a las 22:10 y 23:05 del 20 de agosto hay un hueco de
22:15 a 23:05, de **50 minutos**. Las lecturas anómalas también cuentan como envíos.

`--threshold-minutes` acepta enteros no negativos; por defecto es 15. Solo aparecen
duraciones **estrictamente mayores**: con umbral 50 no aparece el hueco de 50 minutos,
con 49 sí. Cero permite listar cualquier hueco positivo.

Sede y sensor son filtros exactos que distinguen mayúsculas y se combinan con las
fechas. Los días inicial y final son inclusivos en UTC; internamente se usa el rango
desde la medianoche inicial hasta la medianoche posterior al día final, exclusiva.
Sin fechas se usa la ventana común de todas las lecturas, independiente del sensor
o sede elegidos: desde el mínimo timestamp hasta el máximo más 5 minutos. En el seed
es `[2026-08-01T00:00:00Z, 2026-10-31T00:00:00Z)`. Un límite omitido usa el extremo
global correspondiente; los límites explícitos se intersectan con esta ventana.
Fuera de ella no se inventan huecos; sin lecturas globales no hay ventana observable.

Se consultan también los vecinos fuera del rango para reconocer huecos que lo
atraviesan. Se muestra su parte dentro del rango y **el umbral se aplica a esa parte**.
PAT-L01 del 12 al 13 de octubre devuelve 2880 minutos aunque no haya lecturas esos
días; solo el día 14 devuelve 360 minutos, que no aparecen con umbral 360.

La salida tabulada contiene `sensor`, `sede`, `inicio_utc`, `fin_utc`, `duracion_minutos`,
`recortado_inicio`, `recortado_fin`, `abierto_inicio`, `abierto_fin`. Se ordena por inicio
y sensor, con tiempos ISO UTC terminados en `Z`, duración con tres decimales e
indicadores `si`/`no`. `recortado_*` indica que el hueco continúa fuera del rango;
`abierto_*` indica que falta una lectura que permita conocer ese extremo real.
Ambas propiedades son independientes y se conservan al recortar. Un sensor existente
sin lecturas produce un único hueco abierto por ambos extremos dentro del rango.

Se supone que todos los sensores estaban activos durante la ventana común; no se
infiere un calendario desde `installed_at`. Un sensor incorporado tarde podría
parecer abierto al inicio. Tampoco se pueden detectar ausencias simultáneas de todos
los sensores anteriores o posteriores al historial global sin otra fuente que defina
la ventana esperada. La cadencia variable y los calendarios de actividad quedan fuera
de este ejercicio.

El conjunto fijo devuelve exactamente estos tres huecos, sin indicadores activos:

| Sensor | Inicio UTC | Fin UTC | Duración (minutos) |
| --- | --- | --- | ---: |
| VAL-F01 | 2026-08-20T22:15:00Z | 2026-08-20T23:05:00Z | 50 |
| SAG-P02 | 2026-09-03T08:00:00Z | 2026-09-03T14:30:00Z | 390 |
| PAT-L01 | 2026-10-12T00:00:00Z | 2026-10-14T06:00:00Z | 3240 |

Sin resultados, incluidos filtros desconocidos o incompatibles, imprime
`Sin huecos para los filtros indicados.` y devuelve 0. Entradas inválidas devuelven 2
antes de conectar; errores PostgreSQL devuelven 1 sin tabla parcial. Todos los cálculos
se realizan en SQL, sin nuevas dependencias ni cambios de esquema o semillas.

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
