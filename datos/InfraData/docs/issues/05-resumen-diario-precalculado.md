---
title: Resumen diario precalculado
labels: [feature, datos]
depends_on: [1]
---

## Contexto
El resumen del issue 1 debe evitar recalcular todas las lecturas en cada consulta. El equipo de datos necesita conservar los mismos resultados y filtros mientras el resumen se mantiene al día con las lecturas nuevas.

## Historia de usuario
Como analista de datos, quiero consultar un resumen diario precalculado y actualizado, para obtener los mismos resultados sin repetir el cálculo completo en cada consulta.

## Alcance
- Precalcular el resumen diario del issue 1 y mantenerlo actualizado cuando lleguen lecturas nuevas.
- Conservar exactamente los resultados, unidades, días UTC y filtros del issue 1.
- Evitar el recálculo sobre todas las lecturas en cada consulta.
- Cambio en la base de datos, sujeto a aprobación previa del esquema.

## Fuera de alcance
- Nuevas métricas, filtros o interfaces.
- Importación de lecturas, informes y detección de anomalías o huecos.

## Criterios de aceptación
1. Dado el conjunto fijo del curso, cuando se compara el resumen precalculado con el del issue 1 para los mismos sensores, sedes y fechas, entonces todos los resultados y unidades coinciden exactamente, incluidos los días incompletos.
2. Dado el conjunto fijo y una incorporación controlada de lecturas de ese mismo conjunto reservadas para la comprobación, cuando transcurre el retraso máximo acordado, entonces el resumen refleja esas lecturas y coincide exactamente con el cálculo del issue 1.
3. Dado un resumen ya actualizado, cuando se repite una consulta sin nuevas lecturas, entonces se obtiene el mismo resultado sin recalcular sobre todas las lecturas, según la evidencia de verificación acordada.

## Notas
- `AGENTS.md` pide preguntar antes de cambiar el esquema de la base de datos; obtener aprobación antes de realizar ese cambio.
- Decidir con qué retraso máximo se admite que el resumen refleje las lecturas nuevas y cómo se verificará que no se repite el cálculo completo.
- Definir la comprobación de actualización usando el conjunto fijo en un entorno de pruebas, sin alterar los datos originales del curso.
