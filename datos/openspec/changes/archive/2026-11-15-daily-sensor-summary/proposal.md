# Proposal

## Why

InfraData ya almacena lecturas, pero no permite consultar su comportamiento diario. El equipo necesita un resumen verificable por sensor y sede desde la línea de comandos.

## What Changes

- Añadir una consulta diaria con mínimo, máximo, media, recuento y unidad por sensor.
- Permitir filtros combinables por sede, sensor y fechas UTC.
- Mostrar los resultados desde el comando del proyecto.

## Capabilities

### New Capabilities

- `daily-sensor-summary`: consulta diaria de las lecturas por sensor.

### Modified Capabilities

Ninguna.

## Impact

Nuevo módulo de consultas, comando y pruebas. No cambia el esquema ni añade dependencias.
