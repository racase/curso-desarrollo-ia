# InfraData — demo de datos

Proyecto Python sobre PostgreSQL + TimescaleDB con lecturas de sensores ya cargadas.
Incluye comprobación del dataset, resúmenes diarios, detección de huecos e ingesta CSV desde la CLI.

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
├── db/migrations/      migraciones explícitas de bases existentes
├── src/infradata/      código del proyecto
│   ├── config.py       configuración (DATABASE_URL)
│   ├── db.py           conexión
│   ├── analytics.py    agregación diaria y huecos mediante SQL
│   ├── ingestion.py    validación y carga CSV idempotente
│   └── __main__.py     comandos `check`, `daily-summary`, `gaps` e `ingest-csv`
├── tests/
├── docker-compose.yml
└── pyproject.toml
```

## Ingesta de lecturas CSV

```bash
uv run python -m infradata ingest-csv entrada.csv --rejects rechazos.csv
```

`DATABASE_URL` selecciona la base de destino. El informe debe ser un fichero nuevo,
con directorio padre existente, distinto del origen; nunca se sobrescribe un informe.
La ingesta no crea sensores ni modifica el esquema automáticamente.

Contrato: UTF-8 (BOM opcional), separador `;`, comillas CSV estándar, LF o CRLF,
y cabecera exacta en este orden:

```csv
codigo_sensor;fecha_hora_local;valor
VAL-T01;31/10/2026 01:05;23,31
```

Cada registro tiene tres campos. Se recortan espacios exteriores para validar,
conservando los originales en los rechazos. El código distingue mayúsculas y debe
existir. La fecha es estrictamente `DD/MM/AAAA HH:MM`, en `Europe/Madrid`, sin segundos
ni offset. El valor admite signo y coma decimal (`-2,50`, `96,40` o `12`), sin miles,
punto decimal, exponentes ni valores no finitos. No se filtran anomalías.
El entorno debe disponer de los datos de zona `Europe/Madrid`; si faltan, se informa
un error antes de escribir. No se añaden dependencias Python.

Se convierten los tiempos a UTC. Las fechas inválidas y las horas ambiguas o
inexistentes de los cambios horarios se rechazan, sin elegir un offset arbitrario.
Se cargan las filas válidas aunque otras se rechacen. Una lectura se identifica por
**sensor e instante UTC**, con independencia del valor: prevalece la lectura existente;
dentro del fichero, la primera fila válida. Repetir la carga no duplica lecturas.
Una fila inválida no reserva la clave. Primero se comprueba estructura, sensor,
fecha/zona y valor; después duplicados internos y finalmente duplicados en la base.

El informe es CSV UTF-8 con `;` y columnas:
`fila;codigo_sensor;fecha_hora_local;valor;registro_original;motivo;detalle`.
`fila` es la línea física inicial del registro (cabecera en línea 1), también para
registros multilínea. Se conservan campos y texto original; se informa un motivo por
fila, en orden de entrada, y la primera fila válida para duplicados internos.
Los motivos son `estructura_invalida`, `sensor_desconocido`, `fecha_invalida`,
`hora_ambigua`, `hora_inexistente`, `valor_invalido`, `duplicado_fichero` y
`duplicado_base`. Sin rechazos se escribe solo la cabecera.

La CLI muestra procesadas, insertadas y rechazadas. Códigos de salida:

| Código | Resultado |
| --- | --- |
| 0 | Carga confirmada sin rechazos, incluido un fichero con solo cabecera |
| 3 | Carga confirmada con rechazos, incluida una repetición sin nuevas inserciones |
| 2 | Argumentos o contrato global del CSV inválidos |
| 1 | Error operativo (conexión, SQL, ficheros, zona o falta de unicidad) |

La cabecera, codificación o sintaxis CSV irrecuperables invalidan toda la operación.
Un error operativo antes del commit revierte las inserciones de esa ejecución.
Cualquier informe de una operación fallida es incompleto y no acredita una carga.
Una desconexión durante el commit puede dejar el resultado desconocido: el diagnóstico
lo indica; se puede reintentar con otro informe gracias a la idempotencia.

### Unicidad y migración

La ingesta necesita un índice único en `(sensor_id, time)`. El esquema inicial lo
incluye; un volumen existente necesita la migración explícita de
`db/migrations/01_readings_unique.sql`. **Antes de ejecutar DDL se requiere aprobación
del usuario**, también para el entorno de pruebas. El script bloquea escrituras durante
la comprobación y creación, verifica índices homónimos y se detiene si encuentra
duplicados históricos, conservándolos todos. No elimina ni corrige datos. Cambiar
`db/init/01_schema.sql` no migra volúmenes existentes.

Después de aprobación, con copia de seguridad y el destino revisado, el operador puede
aplicar el script con `psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -f db/migrations/01_readings_unique.sql`.
Para volver a la versión anterior de la CLI se conservan lecturas e índice; retirar
el índice necesita otra aprobación y elimina la garantía de futuras ingestas.

### Pruebas de ingesta aisladas

Se usa una base independiente `infradata_ingestion_test` dentro del mismo servidor
Docker local del curso (`infradata-db`, puerto **5433**), sin otro contenedor ni
modificaciones de lecturas en la base `infradata`. Tras aprobar su inicialización:

```bash
docker exec infradata-db createdb -U infradata infradata_ingestion_test
docker exec -i infradata-db psql -U infradata -d infradata_ingestion_test -v ON_ERROR_STOP=1 < db/init/01_schema.sql
docker exec -i infradata-db psql -U infradata -d infradata_ingestion_test -v ON_ERROR_STOP=1 < db/init/02_seed.sql
export INGESTION_TEST_DATABASE_URL='postgresql://infradata:infradata@localhost:5433/infradata_ingestion_test'
export INGESTION_TEST_SCHEMA_APPROVED=1
uv run pytest tests/test_ingestion_integration.py -v
```

El indicador `INGESTION_TEST_SCHEMA_APPROVED=1` registra la aprobación para el DDL de
la prueba de migración; no sustituye a pedirla previamente. Las pruebas usan sensores
propios y limpian solo sus filas; la prueba del CSV real se revierte al terminar.
La prueba de migración usa un esquema desechable exclusivamente en la base aislada.
La base local observada ya tiene un índice único compatible (`readings_sensor_time_unique`);
la CLI lo reconoce por su definición y no necesita migrarla. Sin URL explícita se omiten las pruebas. Se bloquean bases con el nombre de la original,
incluso cambiando usuario, contraseña, alias o puerto (comprobación conservadora).
No se imprime la URL en diagnósticos propios.

Para comprobar las regresiones de consultas sobre la copia limpia, en otra ejecución:

```bash
DATABASE_URL="$INGESTION_TEST_DATABASE_URL" uv run pytest tests/test_db.py tests/test_daily_summary_integration.py tests/test_gaps_integration.py -v
uv run pytest -m "not integration"
uv run ruff check .
uv run ruff format --check .
```

No uses `docker compose down -v` del curso: elimina su volumen original. La ingesta
real amplía el periodo del conjunto; ejecuta los ejercicios de referencia sobre el
seed original o una copia limpia.
