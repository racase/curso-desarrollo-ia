---
title: Resumen diario precalculado
labels: [feature, datos]
depends_on: [1]
---

## Contexto
El resumen diario del issue 1 puede exigir volver a recorrer las lecturas en cada consulta. El equipo de datos necesita que siga disponible conforme lleguen lecturas nuevas, sin cambiar los resultados que ve el usuario.

## Historia de usuario
Como analista de datos, quiero consultar un resumen diario mantenido al día, para obtener los mismos resultados sin recalcular todas las lecturas en cada consulta.

## Alcance
- Precalcular los resúmenes diarios definidos en el issue 1.
- Mantenerlos actualizados cuando lleguen lecturas nuevas.
- Conservar los filtros, las unidades y los resultados observables del issue 1.
- Incluir el cambio necesario en la base de datos.

## Fuera de alcance
- Cambiar las métricas o la interfaz de consulta del issue 1.
- Añadir informes o detección de anomalías.
- Alterar el conjunto fijo de datos del curso.

## Criterios de aceptación
1. Dadas las lecturas del curso, cuando se consulta cualquier resumen diario precalculado, entonces sus métricas, recuento y unidad coinciden exactamente con los del issue 1 para el mismo sensor y día UTC.
2. Dada una lectura nueva, cuando se alcanza el plazo de actualización acordado, entonces el resumen del día y sensor afectados refleja esa lectura y coincide con el resultado calculado a partir de las lecturas disponibles.
3. Dados filtros de sede, sensor y fechas, cuando se consulta el resumen precalculado, entonces aparecen los mismos resultados que con el issue 1.
4. Dadas consultas repetidas, cuando se obtienen los resúmenes, entonces no se recalculan todas las lecturas en cada consulta.

## Notas
- `AGENTS.md` pide preguntar antes de cambiar el esquema de la base de datos; ese cambio necesita autorización antes de implementarse.
- La especificación debe decidir con qué retraso se admite que el resumen refleje las lecturas nuevas y cómo se comprueba la coincidencia exacta de los resultados.
