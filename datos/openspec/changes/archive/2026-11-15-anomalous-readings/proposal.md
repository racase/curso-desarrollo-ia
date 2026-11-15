# Proposal

## Why

El conjunto contiene valores sospechosos que hoy no se pueden localizar de forma sistemática. El equipo necesita clasificarlos por motivo para revisar la calidad de los datos.

## What Changes

- Identificar valores fuera del rango posible, tramos de valor fijo y saltos bruscos.
- Permitir consultar las lecturas afectadas por sede y sensor desde la CLI.
- Fijar límites verificables para cada tipo de sensor.

## Capabilities

### New Capabilities

- `anomalous-readings`: clasificación de lecturas sospechosas por sensor y motivo.

### Modified Capabilities

Ninguna.

## Impact

Nueva consulta, comando y pruebas. No modifica lecturas ni esquema.
