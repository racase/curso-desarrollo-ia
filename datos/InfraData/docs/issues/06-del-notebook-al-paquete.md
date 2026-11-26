---
title: Del notebook al paquete
labels: [feature, datos]
depends_on: []
---

## Contexto
El equipo de datos dispone de `notebooks/exploracion.ipynb`, que calcula la media móvil y la tendencia semanal de temperatura por sede cargando todas las lecturas en memoria. Ese cálculo todavía no es una funcionalidad reutilizable del paquete y debe conservar sus resultados al trasladarse.

## Historia de usuario
Como analista de datos, quiero reutilizar desde el paquete los cálculos de media móvil y tendencia semanal de temperatura por sede, para mantener el análisis del notebook sin duplicar su lógica ni cargar todas las lecturas en memoria.

## Alcance
- Incorporar al paquete ambos cálculos como funcionalidad reutilizable, con agregaciones realizadas en la base de datos.
- Mantener los resultados del notebook original sobre el conjunto fijo del curso.
- Adaptar el notebook para usar esa funcionalidad en lugar de su propio cálculo.
- Incorporar tests de equivalencia; marcar como `integration` los que necesiten la base de datos.
- Funcionalidad independiente de los demás issues.

## Fuera de alcance
- Métricas nuevas, análisis de otros tipos de sensor o cambios en la definición de los cálculos.
- API, informes y rediseño del notebook ajeno a esta adaptación.

## Criterios de aceptación
1. Dado el notebook original y el conjunto fijo del curso, cuando se calcula la media móvil de temperatura por sede desde el paquete con los mismos parámetros, entonces coincide con la referencia original: <valor a obtener del conjunto de datos>.
2. Dado el mismo conjunto y las mismas condiciones del notebook original, cuando se calcula la tendencia semanal por sede desde el paquete, entonces coincide con la referencia original: <valor a obtener del conjunto de datos>.
3. Dado el notebook adaptado y el conjunto fijo, cuando se ejecuta el análisis, entonces utiliza la funcionalidad del paquete, conserva los resultados originales y no realiza su propio cálculo de agregación.
4. Dada la funcionalidad reutilizable y el conjunto fijo, cuando se verifica su ejecución, entonces las agregaciones se realizan en la base de datos sin cargar todas las lecturas en memoria.
5. Dadas las referencias del notebook original, cuando se ejecutan los tests de equivalencia, entonces comprueban ambos cálculos y los tests que requieren la base de datos están marcados como `integration`.

## Notas
- El notebook lo aporta el formador; conservar sus resultados de referencia antes de modificarlo durante la implementación.
- Precisar en la especificación la ventana móvil, la definición de tendencia y semana, el tratamiento de huecos y los criterios de equivalencia numérica. Respetar el cómputo de días en UTC; aclarar cualquier discrepancia con el original antes de programar.
- `AGENTS.md` pide justificar cualquier dependencia nueva, como Jupyter o pandas; decidir si estas dependencias van en un grupo aparte.
- Si trasladar ambos cálculos y adaptar el notebook excede un único cambio, revisarlo con el equipo antes de implementar.
