# Proposal

## Why

Los periodos sin lecturas están en los datos, pero el equipo no dispone de una consulta para localizarlos y medirlos.

## What Changes

- Listar los intervalos entre lecturas consecutivas que superan un umbral de minutos.
- Permitir filtrar por sede y sensor y consultar el resultado por CLI.

## Capabilities

### New Capabilities

- `reading-gaps`: identificación de interrupciones entre lecturas de un sensor.

### Modified Capabilities

Ninguna.

## Impact

Nuevo módulo de consulta, comando y pruebas. No modifica datos ni esquema.
