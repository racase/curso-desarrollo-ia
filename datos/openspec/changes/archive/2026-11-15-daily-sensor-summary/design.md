# Design

## Context

La conexión existente usa psycopg y las lecturas están en una hypertable. Los días y las fechas se interpretan en UTC.

## Goals / Non-Goals

**Goals:** calcular las métricas en SQL y exponerlas mediante una consulta reutilizable y un comando.

**Non-Goals:** precalcular resultados o rellenar lecturas ausentes.

## Decisions

- Agrupar en la base de datos por días UTC con `time_bucket`; trasladar a Python solo las filas agregadas. Se evita cargar todas las lecturas.
- Usar filtros opcionales como fragmentos SQL fijos con parámetros `%s`; esto mantiene las consultas parametrizadas sin exigir una combinación de consulta por cada filtro.
- Usar un rango temporal semiabierto internamente para que el último día solicitado quede incluido por completo.
- Mostrar valores con dos decimales y recuentos enteros. Los días sin lecturas no generan fila y el comando indica cuando la consulta completa está vacía.

## Risks / Trade-offs

- Las medias de días incompletos pueden sesgar la comparación → mostrar siempre el recuento.
