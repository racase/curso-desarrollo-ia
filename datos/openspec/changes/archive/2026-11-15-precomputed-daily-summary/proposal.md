# Proposal

## Why

La consulta diaria agrupa todas las lecturas cada vez que se ejecuta. El equipo necesita conservar el mismo resultado al consultar periodos históricos con un coste de lectura estable.

## What Changes

- Añadir un resumen diario precalculado en la base de datos.
- Mantenerlo actualizado con las lecturas nuevas, incluidas las insertadas en días históricos.
- Usar ese resumen en la consulta existente y ofrecer una instalación reproducible del cambio de esquema.

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `daily-sensor-summary`: añade precálculo, frescura y equivalencia con la consulta original.

## Impact

Cambia el esquema de la base de datos mediante una migración separada de `db/init/`, la consulta diaria, pruebas y documentación. No se modifican los datos de ejemplo ni se añaden dependencias.
