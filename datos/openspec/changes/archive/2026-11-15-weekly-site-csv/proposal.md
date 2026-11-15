# Proposal

## Why

Los responsables de sede necesitan revisar y compartir fuera de la CLI los resúmenes de una semana.

## What Changes

- Exportar a CSV los resúmenes diarios de una sede y una semana.
- Fijar un formato compatible con Excel en español.

## Capabilities

### New Capabilities

- `weekly-site-csv`: exportación semanal de resúmenes de sensores por sede.

### Modified Capabilities

Ninguna.

## Impact

Nuevo módulo de exportación, comando y pruebas. Reutiliza el resumen diario sin cambiar la base de datos ni añadir dependencias.
