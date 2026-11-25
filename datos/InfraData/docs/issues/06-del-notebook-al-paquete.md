---
title: Del notebook al paquete
labels: [feature, datos]
depends_on: []
---

## Contexto
El equipo de datos dispone de `notebooks/exploracion.ipynb`, que calcula la media móvil y la tendencia semanal de la temperatura por sede cargando todas las lecturas en memoria. Ese cálculo necesita convertirse en una funcionalidad reutilizable del paquete, con agregación en la base de datos y resultados equivalentes al notebook original.

## Historia de usuario
Como analista de datos, quiero reutilizar desde el paquete los cálculos del notebook, para mantener una única definición comprobada de la media móvil y la tendencia semanal por sede.

## Alcance
- Incorporar al paquete el cálculo reutilizable de la media móvil y la tendencia semanal de temperatura por sede.
- Realizar la agregación en la base de datos sin cargar todas las lecturas en memoria.
- Mantener los resultados del notebook original sobre el conjunto fijo.
- Dejar el notebook usando esa funcionalidad en lugar de su propio cálculo.
- Añadir pruebas de equivalencia; marcar como `integration` las que necesitan la base de datos.
- Entregar el cambio sin depender de otros issues.

## Fuera de alcance
- Otras métricas, tipos de sensor, informes, API o cambios de presentación del notebook.
- Cambiar la definición del cálculo original.

## Criterios de aceptación
1. Dado el notebook original y el conjunto fijo, cuando se comparan sus resultados con los del paquete para cada sede, entonces la media móvil coincide con la referencia: <valor a obtener del conjunto de datos>.
2. Dado el mismo conjunto fijo, cuando se compara la tendencia semanal por sede del paquete con la del notebook original, entonces coincide con la referencia: <valor a obtener del conjunto de datos>.
3. Dado el notebook adaptado y el conjunto fijo, cuando se ejecuta, entonces utiliza la funcionalidad del paquete y conserva los resultados originales sin mantener un cálculo propio duplicado.
4. Dada la funcionalidad sobre el conjunto fijo, cuando se verifica su ejecución, entonces la agregación se realiza en la base de datos sin cargar todas las lecturas en memoria.
5. Dadas las pruebas añadidas, cuando se ejecutan, entonces verifican la equivalencia con el original y todas las que necesitan la base de datos están marcadas como `integration`.

## Notas
- El notebook lo aporta el formador; conservar una referencia de sus resultados antes de modificarlo.
- El `AGENTS.md` pide justificar cualquier dependencia nueva, como Jupyter o pandas; decidir si esas dependencias van en un grupo aparte.
- Documentar en la especificación las ventanas, límites semanales UTC, tratamiento de huecos y precisión del cálculo original antes de trasladarlo al paquete.
- Si reproducir el cálculo original entra en conflicto con contar los días en UTC, resolverlo en la especificación antes de programar.
