## Why

Mantenimiento necesita localizar interrupciones en el envío de lecturas por sensor.
El resumen diario permite reconocer días incompletos, pero no delimita las interrupciones.
Esta propuesta resuelve el [issue 02](../../../../docs/issues/02-deteccion-de-huecos.md)
de forma independiente del resumen diario.

## What Changes

- Añadir `python -m infradata huecos`, con umbral configurable mediante
  `--umbral-minutos` y filtros opcionales combinables por sede, sensor y fechas UTC.
- Medir cada hueco completo desde la última lectura hasta la siguiente, con umbral
  predeterminado de 5 minutos y comparación estricta: duración mayor que el umbral.
- Buscar las lecturas que delimitan los huecos aunque estén fuera del rango consultado;
  conservar los extremos y la duración completos cuando el hueco se solape con el rango.
- Distinguir huecos completos de abiertos al inicio, al final o en ambos extremos;
  para estos últimos mostrar límites desconocidos y una duración mínima observable.
- Incorporar como referencias de aceptación los tres huecos obtenidos con la consulta
  independiente aportada por el usuario, usando un umbral explícito de 15 minutos.
- Añadir pruebas y documentación del comportamiento, sin rellenar ni alterar lecturas.

Quedan fuera las causas, anomalías, alertas, exportación, estimación del tiempo de avería
y cálculo del número de lecturas ausentes.

## Capabilities

### New Capabilities

- `deteccion-de-huecos`: consulta reutilizable y CLI para localizar intervalos sin lecturas,
  filtros, semántica del umbral, límites UTC, extremos abiertos y referencias verificables.

### Modified Capabilities

Ninguna. `check` y `resumen-diario` conservan sus contratos.

## Impact

- Extender `src/infradata/analytics.py` y `src/infradata/__main__.py`.
- Añadir pruebas unitarias y `integration`, y documentar el comando en `README.md`.
- Consultar las tablas y los índices existentes mediante SQL parametrizado.
- Sin dependencias nuevas, migraciones ni cambios en el esquema de base de datos.
- Implementación y validación registradas en [validacion.md](validacion.md).
