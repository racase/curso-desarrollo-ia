# Guía del repositorio

## Estructura del proyecto

InfraData es un proyecto didáctico con Python 3.11+, PostgreSQL y TimescaleDB para datos de sensores. El código está en `src/infradata/`: `config.py` lee la configuración, `db.py` gestiona conexiones y `__main__.py` implementa la CLI. Las pruebas están en `tests/`. Los scripts de `db/init/` inicializan volúmenes nuevos por orden de nombre. Usa `notebooks/` para exploración y `data/incoming/` para CSV de entrada. Consulta los ejercicios en `docs/PROMPT_BACKLOG.md`.

## Comandos de desarrollo y validación

Ejecuta desde esta carpeta:

- `uv sync`: crea el entorno e instala las dependencias.
- `docker compose up -d`: inicia PostgreSQL/TimescaleDB e inicializa un volumen nuevo.
- `uv run python -m infradata check`: muestra sensores, lecturas y fechas.
- `uv run pytest`: ejecuta todas las pruebas; las de integración se omiten si la base de datos no responde.
- `uv run pytest -m "not integration"`: ejecuta las pruebas sin base de datos.
- `uv run pytest -m integration`: ejecuta las pruebas de integración con la base de datos iniciada.
- `uv run ruff check .`: comprueba estilo e importaciones.
- `uv run ruff format .`: aplica formato.
- `uv build`: genera los paquetes con Hatchling.

## Estilo y convenciones

Usa cuatro espacios, líneas de hasta 100 caracteres y formato Ruff. Emplea `snake_case` para funciones, variables y módulos; `UPPER_SNAKE_CASE` para constantes. Mantén las anotaciones de tipos y docstrings breves. Usa `connect()` como gestor de contexto, parametriza los valores SQL y escribe los mensajes de la CLI en castellano.

## Reglas del proyecto

- Los días se cuentan en UTC: usa límites de día y agrupaciones diarias en UTC.
- Pregunta al usuario y espera su aprobación antes de cambiar el esquema de la base de datos.
- Justifica cualquier dependencia nueva: explica su necesidad y las alternativas consideradas.
- Realiza las agregaciones en la base de datos mediante SQL; no cargues las lecturas en memoria para agregarlas.
- Marca todos los tests que necesitan la base de datos con `integration`.

## Pruebas

Usa pytest, archivos `test_*.py` y funciones `test_*` descriptivas. Emplea `monkeypatch` para configuración y `capsys` para salidas. No hay umbral de cobertura configurado. Comprueba los cambios de comportamiento y conserva las verificaciones de datos iniciales e hypertables.

## Commits y solicitudes de cambio

El historial usa mensajes breves en castellano, como `Subir notebook y csv`, sin prefijo obligatorio. Mantén cada commit centrado en un cambio. Describe las modificaciones, enlaza incidencias pertinentes e indica las validaciones realizadas en cada solicitud de cambio.

## Configuración y datos

Configura la conexión mediante `DATABASE_URL`; no publiques credenciales reales. El puerto local es `5433`. `docker compose down -v` elimina los datos almacenados. Conserva los huecos y anomalías intencionados de las lecturas salvo indicación del ejercicio.
