# InfraData — demo de datos

Proyecto Python mínimo sobre PostgreSQL + TimescaleDB con lecturas de sensores ya cargadas.
Incluye consultas de resumen diario y detección de huecos por sensor, desarrolladas con
OpenSpec y Codex.

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

## Detección de huecos

```bash
uv run python -m infradata huecos
uv run python -m infradata huecos --umbral-minutos 15
uv run python -m infradata huecos --sede "Planta Sagunto" --sensor SAG-P02
uv run python -m infradata huecos --sensor PAT-L01 --desde 2026-10-12 --hasta 2026-10-13
```

El umbral es un entero positivo en minutos, **5 por defecto**, y se compara de forma
estricta: un intervalo igual al umbral se excluye. La duración de un hueco completo es
el tiempo entre la última lectura y la siguiente, sin descontar los cinco minutos de
cadencia. Por ejemplo, lecturas a las 10:00 y 10:20 delimitan veinte minutos entre
lecturas, aunque falten tres lecturas esperadas, equivalentes a quince minutos.

Los filtros por sede y sensor son exactos, distinguen mayúsculas y acentos y se combinan.
Las fechas incluyen días completos UTC. Cada extremo omitido usa el límite correspondiente
del conjunto del curso: **2026-08-01** y **2026-10-30**. Estos límites son fijos; para datos
posteriores hay que indicar fechas explícitas. Un rango efectivo invertido es un error.

Se seleccionan los huecos que se solapan con el rango, buscando también las lecturas
anteriores y posteriores fuera de él. Los huecos completos conservan su inicio, fin y
duración total: el último ejemplo muestra el intervalo desde el 11 de octubre a las
23:55 UTC hasta el 14 a las 06:00 UTC, con duración `54:05:00`. Un hueco que solo toca
un límite del rango queda excluido.

Si no existe una lectura delimitadora en todo el histórico disponible, el extremo aparece
como `desconocido`. El estado indica `abierto al inicio`, `abierto al final` o
`abierto en ambos extremos`; la duración, presentada como `>= HH:MM:SS`, es únicamente
la cota mínima observable dentro del rango. Solo se muestra si esa cota supera el umbral;
una cota menor no demuestra que la interrupción real sea corta. Se incluyen sensores
conocidos sin lecturas cuando el rango observado supera el umbral. La ausencia de datos
no demuestra una avería ni permite deducir cuándo empezó.

La salida incluye sede, sensor, última y siguiente lectura UTC, duración y estado. Las
horas se acumulan sin reiniciar a las 24; los resultados se ordenan por inicio observable,
sensor y fin observable. El cálculo se realiza en SQL, sin rellenar huecos ni excluir
valores anómalos. Con el conjunto inicial y umbral de quince minutos aparecen exactamente
estos huecos completos:

| Sensor | Última lectura UTC | Siguiente lectura UTC | Duración |
|---|---|---|---|
| VAL-F01 | 2026-08-20 22:10 | 2026-08-20 23:05 | 00:55:00 |
| SAG-P02 | 2026-09-03 07:55 | 2026-09-03 14:30 | 06:35:00 |
| PAT-L01 | 2026-10-11 23:55 | 2026-10-14 06:00 | 54:05:00 |

Sin resultados se muestra `No hay huecos que superen el umbral para los filtros indicados.`
y se termina con código 0. Los argumentos inválidos devuelven código 2 antes de conectar;
los fallos de base de datos devuelven código 1. Consulta `huecos --help` para ver las opciones.

## Ingesta de lecturas CSV

```bash
uv run python -m infradata ingestar-csv data/incoming/lecturas_2026-10-31_2026-11-06.csv --informe data/rechazos_lecturas.csv
```

El destino del informe es obligatorio, su carpeta debe existir y el archivo no debe existir.
Se conserva el CSV original, también si el destino es un enlace al origen. Usa
`ingestar-csv --help` para consultar la sintaxis.

El formato del formador es UTF-8, separador `;`, comillas dobles CSV y finales LF o CRLF:

```csv
codigo_sensor;fecha_hora_local;valor
VAL-T01;31/10/2026 01:00;23,12
```

Cada registro tiene tres campos. Los códigos distinguen mayúsculas; se quitan espacios
exteriores para validar. Las fechas usan exactamente `DD/MM/AAAA HH:MM` en `Europe/Madrid`,
sin segundos ni desplazamiento. Los valores admiten signo y coma decimal opcionales,
sin miles, punto decimal ni notación científica. Se rechazan vacíos, no numéricos y
no finitos, pero se conservan valores válidos negativos, ceros y anomalías.

La conversión usa las reglas IANA, no un desplazamiento fijo: `31/10/2026 01:00`
equivale a `2026-10-31T00:00:00Z`. Se rechazan fechas inválidas y horas locales ambiguas
(por ejemplo, `25/10/2026 02:30`) o inexistentes (`29/03/2026 02:30`). El sistema debe
tener datos IANA instalados; en Linux los proporciona el paquete del sistema `tzdata`.
No se han añadido dependencias de Python.

La carga es parcial: se incorporan juntas las filas válidas y se informa cada rechazo.
Sensor e instante UTC identifican una lectura. La primera fila con campos válidos de una
clave prevalece dentro del fichero: mismo valor numérico es duplicado y distinto valor es
conflicto. Las lecturas ya almacenadas nunca se sobrescriben; cargar el fichero otra vez
incorpora cero lecturas nuevas. Los sensores desconocidos no se dan de alta.

El informe UTF-8 usa `;` y cabecera:

```csv
fila;codigo_sensor;fecha_hora_local;valor;registro_original;motivo
```

La fila es la línea física inicial del registro, con la cabecera en línea 1. Se conservan
campos y registro originales, escapados como CSV, incluso con campos adicionales o saltos
de línea. Un informe sin rechazos contiene solo la cabecera. Si hay varios fallos se
informa el primero por este orden: número de campos, sensor, fecha, valor, duplicidad.
Los motivos son `numero_de_campos_invalido`, `sensor_desconocido`, `fecha_invalida`,
`hora_ambigua`, `hora_inexistente`, `valor_vacio`, `valor_no_numerico`, `valor_no_finito`,
`duplicado_en_csv`, `conflicto_en_csv`, `ya_cargada` y `conflicto_con_existente`.

Al completar se muestra `Filas: N | Incorporadas: I | Rechazadas: R` y la ruta del informe,
con `N = I + R`. Código 0 significa carga completada, también con rechazos o cero filas;
2 indica uso, rutas o formato incorrectos; 1 indica un fallo operativo. Una cabecera,
codificación o sintaxis CSV ilegible aborta todo el fichero. Un fallo SQL o de archivos
antes del commit revierte las inserciones.

El informe se prepara en un temporal antes del commit y se publica después, sin
sobrescribir destinos. Si falla la publicación tras confirmar, se devuelve 1 y se indica
que la carga está confirmada junto con la ruta del temporal recuperable. Copia ese archivo
a un destino nuevo y comprueba los recuentos. Si falla la conexión durante el commit,
el resultado puede ser desconocido: verifica las lecturas o reintenta con otro informe;
la restricción única impide duplicarlas. No se garantiza una transacción conjunta entre
PostgreSQL y el sistema de archivos.

### Migración de volúmenes existentes

Las instalaciones nuevas incluyen `readings_sensor_time_unique`, una restricción
`UNIQUE (sensor_id, time)` compatible con la hypertable. La CLI exige esa restricción y
no modifica el esquema automáticamente. Los scripts de `db/init/` solo se ejecutan al
crear el volumen; para un volumen existente hay que aplicar la migración explícita,
**previa aprobación del usuario** según `AGENTS.md`:

```bash
docker compose exec -T db psql -U infradata -d infradata -v ON_ERROR_STOP=1 < db/migrations/01_readings_unique.sql
```

El script bloquea escrituras mientras comprueba duplicados y crea la restricción; elige
una ventana adecuada. Si hay duplicados, aborta sin borrar ni fusionar datos. Su diagnóstico
se puede ampliar con esta consulta, realizada en SQL:

```sql
SELECT sensor_id, time, count(*)
FROM readings
GROUP BY sensor_id, time
HAVING count(*) > 1;
```

El saneamiento requiere una decisión explícita antes de continuar. La migración puede
repetirse tras aplicarse correctamente. Revertir el código no requiere retirar la restricción
ni borrar lecturas importadas. Si fuera necesario retirar la restricción, solicitar otra
aprobación antes de ejecutar `ALTER TABLE readings DROP CONSTRAINT readings_sensor_time_unique`;
esto deshabilitaría la ingesta hasta volver a aplicar la migración.

Las pruebas de ingesta `integration` crean y eliminan exclusivamente una base efímera
`infradata_test_ingestion_<uuid>` en el servidor configurado por `DATABASE_URL`, con
TimescaleDB; necesitan permisos de creación de base y extensión. No importan el CSV del
formador a la base local ni modifican sus lecturas.

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
│   ├── analytics.py    consultas SQL de resumen diario y detección de huecos
│   ├── ingestion.py    validación, carga CSV e informe de rechazos
│   └── __main__.py     comandos `check`, `resumen-diario`, `huecos` e `ingestar-csv`
├── tests/
├── docker-compose.yml
└── pyproject.toml
```
