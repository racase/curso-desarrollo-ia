## Context

Issue02 pide listar interrupciones de envío por sensor. El seed genera lecturas cada 5 minutos y elimina tres bloques. La CLI existente usa argparse, filtros de fechas inclusivas en UTC, salida tabulada y códigos 0/1/2; `analytics.py` consulta PostgreSQL mediante conexiones administradas por contexto. Solo existe la capacidad `daily-sensor-summary`, que no cambia.

Este diseño es necesario para fijar la semántica de duración y extremos antes de implementar. Las referencias numéricas proceden de leer `db/init/02_seed.sql`, no de una consulta a la base de datos en esta fase.

## Goals / Non-Goals

**Goals:** detectar todos los huecos, incluidos periodos enteramente vacíos; medir tiempo esperado ausente; distinguir recortes del rango y extremos abiertos; ejecutar detección y cálculos en SQL; conservar compatibilidad con los comandos existentes.

**Non-Goals:** modificar esquema o seed, rellenar datos, explicar averías, generar alertas o informes, inferir cadencias por sensor o soportar calendarios de actividad diferentes.

## Decisions

### Duración y umbral

Cadencia fija de 5 minutos. Entre lecturas consecutivas distintas `p` y `n`, el hueco es `[p + 5 minutos, n)` solo si ese inicio es anterior al fin. Su duración es `n - p - 5 minutos`. Se descuentan los 5 minutos normales: VAL-F01 representa 50 minutos de datos ausentes, frente a los 55 minutos entre envíos.

La alternativa de usar todo el intervalo entre envíos requiere menos hipótesis, pero no coincide con los bloques eliminados. No se estimará la frecuencia desde los datos: podría quedar sesgada por los propios huecos.

`--threshold-minutes` será un entero no negativo, por defecto 15. Se incluirán solo duraciones estrictamente mayores. Cero permite listar cualquier hueco de duración positiva. No se redondeará antes de comparar; la duración presentada tendrá tres decimales.

### Ventana común de observación

Se calculará en SQL, sobre todas las lecturas y antes de aplicar filtros de sede o sensor, la ventana `[min(time), max(time) + 5 minutos)`. En el seed es `[2026-08-01T00:00:00Z, 2026-10-31T00:00:00Z)`. Esto evita fechas hardcodeadas y admite que una ingesta posterior extienda la ventana.

Los límites omitidos usarán la ventana común. Las fechas explícitas serán días inclusivos, convertidos a `[medianoche inicial UTC, medianoche posterior al día final UTC)`. Se intersectará ese rango con la ventana común; una intersección vacía no produce huecos. Sin lecturas globales, tampoco hay ventana ni resultados.

No se utilizarán mínimos y máximos propios de cada sensor: ocultarían ausencias en los extremos. Hipótesis documentada: todos los sensores seleccionados debían estar activos durante la ventana común; no se deduce un calendario de `installed_at`. Los huecos anteriores o posteriores a la ventana global no son observables y se excluyen.

### Extremos y recorte

Para cada sensor existente seleccionado, se considerará su historial, incluidas lecturas fuera del rango efectivo. Los huecos interiores se construyen antes del filtro temporal. Se añaden candidatos desde el inicio global hasta la primera lectura del sensor y desde su última lectura más 5 minutos hasta el fin global. Sin lecturas del sensor, se usa toda la ventana común como un único candidato abierto por ambos extremos.

Cada candidato se intersectará con el rango efectivo y se descartará si queda vacío. La duración y el umbral se calcularán sobre esa intersección. Cuatro indicadores independientes describen la fila:

- `recortado_inicio`: el inicio del candidato es anterior al inicio efectivo.
- `recortado_fin`: el fin del candidato es posterior al fin efectivo.
- `abierto_inicio`: falta una lectura previa del sensor que permita conocer el inicio real.
- `abierto_fin`: falta una lectura posterior del sensor que cierre el periodo.

Los límites globales utilizados como candidatos abiertos son límites observados, no fechas de avería demostradas. Los indicadores abiertos se conservan al recortar. Una lectura exactamente en el fin exclusivo no pertenece al rango, pero puede cerrar el hueco. La última lectura normal cubre su intervalo esperado de 5 minutos: no crea un falso hueco al final global.

Se descarta omitir los extremos porque perdería días enteros sin datos; también se descarta tratar el límite de consulta como evidencia de comienzo o recuperación.

### Consulta e integración

Añadir una función `reading_gaps` y un resultado tipado en `analytics.py`; fechas y umbral se validarán antes de conectar. Una consulta SQL calculará ventana, sensores seleccionados, timestamps distintos, vecinos mediante funciones de ventana por sensor, candidatos extremos, intersecciones, duración, filtros y orden. La deduplicación temporal evita candidatos falsos si existen timestamps repetidos, permitidos por el esquema actual.

Para este dataset, priorizar una consulta clara del historial de los sensores seleccionados; no filtrar fechas antes de calcular vecinos. Si se optimiza después, deberán recuperarse vecinos fuera del rango sin cambiar los resultados. Recuperar solo filas finales, nunca lecturas crudas para calcular huecos en Python. Parametrizar sede, sensor, fechas y umbral; reutilizar el índice por sensor y tiempo. Una sola sentencia mantiene coherentes la ventana y los resultados.

Añadir `gaps` a argparse sin alterar el contrato de `daily-summary` o `check`. Salida tabulada determinista con sensor, sede, inicio y fin UTC, duración e indicadores; errores de PostgreSQL sin salida parcial.

## Risks / Trade-offs

- Cadencia fija → documentar la hipótesis del curso; frecuencias variables necesitarían otro cambio.
- Actividad común inferida del historial → un sensor dado de alta tarde puede parecer abierto al inicio; no usar este resultado como diagnóstico automático de avería.
- Ausencia simultánea de todos los sensores en los extremos globales → no puede detectarse sin una ventana de observación externa; declarar esta limitación.
- Ingesta que amplíe el historial → los valores por defecto cambian con el dataset; las pruebas del seed deben ejecutarse sobre el conjunto fijo.
- Recorte que reduce un hueco por debajo del umbral → documentar que se mide la ausencia dentro de la consulta; cubrirlo en pruebas.
- SQL de bordes más complejo → pruebas de integración transaccionales con sensores de prueba y rollback, sin migraciones ni cambios persistentes del seed.

## Migration Plan

No requiere migración ni dependencias nuevas. Implementar consulta, CLI, pruebas y documentación en las tareas siguientes. Para verificar SQL, iniciar previamente PostgreSQL con `docker compose up -d`. Revertir el cambio de aplicación elimina el comando nuevo sin modificar datos.

## Open Questions

Ninguna bloqueante para el alcance del curso. La actividad común y la ventana global inferida son supuestos de esta propuesta; un calendario operativo explícito queda fuera de alcance.
