---
title: Resumen diario precalculado
labels: [feature, datos]
depends_on: [1]
---

## Contexto
El issue 1 proporcionará un resumen diario calculado a partir de las lecturas. El equipo de datos necesita consultarlo sin recalcular todas las lecturas cada vez y mantener los resultados al día al llegar lecturas nuevas.

## Historia de usuario
Como analista de datos, quiero consultar un resumen diario precalculado y actualizado, para reutilizar los mismos resultados sin recalcular todas las lecturas en cada consulta.

## Alcance
- Ofrecer los mismos resultados, unidades, días UTC y filtros que el issue 1.
- Evitar recalcular sobre todas las lecturas en cada consulta.
- Mantener el resumen al día cuando lleguen lecturas nuevas, dentro del retraso que se acuerde.
- Incluir el cambio necesario en la base de datos, sujeto a aprobación previa.

## Fuera de alcance
- Nuevas métricas, filtros, informes o mecanismos de recepción de lecturas.
- Cambios en el significado del resumen diario.

## Criterios de aceptación
1. Dado el conjunto fijo del curso, cuando se comparan los resúmenes precalculados con los del issue 1 para los mismos sensores y días, entonces mínimo, máximo, media, recuento y unidad coinciden exactamente.
2. Dadas las mismas combinaciones de sede, sensor y fechas del conjunto fijo, cuando se consulta el resumen precalculado, entonces conserva los filtros y límites de día UTC del issue 1.
3. Dada una prueba controlada de llegada de lecturas que utiliza datos del conjunto fijo, cuando transcurre el retraso máximo acordado, entonces el resumen coincide exactamente con el cálculo del issue 1 sobre las lecturas disponibles.
4. Dado el resumen ya precalculado para el conjunto fijo, cuando se repite una consulta sin lecturas nuevas, entonces se obtienen los mismos resultados y la verificación de ejecución confirma que no se recalcula sobre todas las lecturas.

## Notas
- Depende del issue 1 e implica un cambio en la base de datos.
- El `AGENTS.md` pide preguntar antes de cambiar el esquema de la base de datos; solicitar aprobación y esperar la respuesta antes de modificarlo.
- Decidir con qué retraso se admite que el resumen refleje las lecturas nuevas y cómo comprobar ese compromiso.
- Precisar en la especificación cómo demostrar la equivalencia exacta y preparar la prueba de nuevas lecturas sin alterar permanentemente el conjunto fijo.
