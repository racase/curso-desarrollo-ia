## Why

El equipo necesita incorporar las lecturas exportadas del sistema de adquisición,
normalizadas a UTC y sin duplicados, y revisar las filas rechazadas con sus motivos.
Esta propuesta resuelve el [issue 07](../../../docs/issues/07-ingesta-de-lecturas-csv.md)
sin depender de otros issues.

## What Changes

- Añadir `python -m infradata ingestar-csv RUTA --informe RUTA_INFORME` para validar,
  normalizar y cargar lecturas y producir un CSV de rechazos.
- Fijar como contrato el fichero del formador: UTF-8, separador `;`, cabecera
  `codigo_sensor;fecha_hora_local;valor`, fechas `DD/MM/AAAA HH:MM` en
  `Europe/Madrid` y coma decimal.
- Cargar parcialmente los ficheros: incorporar las filas válidas y rechazar las inválidas,
  duplicadas o en conflicto, con identificación de la fila y motivo.
- Identificar una lectura por sensor e instante UTC. Si el valor coincide, rechazar por
  duplicidad; si difiere, rechazar por conflicto sin sobrescribir. Dentro del fichero,
  conservar la primera fila válida de cada clave.
- Rechazar fechas inválidas y horas locales ambiguas o inexistentes por cambios de hora.
- Proponer una restricción única `(sensor_id, time)` para impedir duplicados también
  ante ingestas simultáneas, con aplicación explícita a volúmenes existentes.
- Añadir pruebas, documentación y una migración que no elimine ni corrija datos existentes.

Las cinco decisiones de «Notas» se consultaron antes de crear estos artefactos y el usuario
las aceptó. La autorización de esquema recibida cubre incluir la restricción en la propuesta;
antes de ejecutar su migración se solicitará aprobación para modificar la base de datos.

## Capabilities

### New Capabilities

- `ingesta-de-lecturas-csv`: contrato de entrada, validación, normalización UTC,
  carga parcial, unicidad, informe de rechazos y comportamiento de la CLI.

### Modified Capabilities

Ninguna. Se conservan los contratos de `check`, `resumen-diario` y `huecos`.

## Impact

- Nuevo módulo de ingesta en `src/infradata/` y extensión de `__main__.py`.
- Restricción única en `db/init/01_schema.sql` y script de migración explícita para
  volúmenes existentes; comprobar duplicados antes de aplicarla, sin borrarlos.
- Pruebas unitarias y `integration`, y documentación en `README.md`.
- Sin dependencias nuevas: biblioteca estándar (`csv`, `datetime`, `zoneinfo`) y psycopg.
- La propuesta no ejecuta ingestas ni modifica la base de datos o los CSV originales.
