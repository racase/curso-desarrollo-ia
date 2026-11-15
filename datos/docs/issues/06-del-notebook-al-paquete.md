---
title: Del notebook al paquete
labels: [feature, datos]
depends_on: []
---

## Contexto
El equipo de datos tiene un notebook de exploración que calcula la media móvil y la tendencia semanal de la temperatura por sede cargando todas las lecturas en memoria. Llevar ese cálculo al paquete permitirá reutilizarlo y verificar sus resultados con tests.

## Historia de usuario
Como analista de datos, quiero reutilizar desde el paquete los cálculos de temperatura por sede, para obtener resultados consistentes sin mantener una copia de la lógica en el notebook.

## Alcance
- Incorporar al paquete el cálculo reutilizable de la media móvil y la tendencia semanal de temperatura por sede.
- Realizar la agregación en la base de datos, sin cargar todas las lecturas en memoria.
- Añadir tests que comprueben los resultados.
- Adaptar `notebooks/exploracion.ipynb` para que utilice la funcionalidad del paquete en lugar de su cálculo propio.
- Mantener la coincidencia de resultados con el notebook original.

## Fuera de alcance
- Añadir cálculos para otros tipos de sensor.
- Crear informes, gráficos nuevos o una API.
- Cambiar los criterios de cálculo del notebook original.

## Criterios de aceptación
1. Dadas las lecturas de temperatura del curso, cuando se obtienen la media móvil y la tendencia semanal por sede desde el paquete, entonces los resultados coinciden con los del notebook original para las mismas sedes y fechas.
2. Dado el notebook adaptado, cuando se ejecuta con los datos del curso, entonces usa la funcionalidad del paquete y muestra los resultados sin mantener una implementación propia del cálculo.
3. Dadas las lecturas del curso, cuando se ejecuta el cálculo, entonces la agregación se realiza en la base de datos sin cargar todas las lecturas en memoria.
4. Dados los tests del cálculo, cuando se ejecutan, entonces verifican la media móvil y la tendencia semanal por sede, incluida su coincidencia con la referencia del notebook.

## Notas
- El notebook `notebooks/exploracion.ipynb` lo aporta el formador; debe estar disponible antes de especificar y comprobar la equivalencia.
- `AGENTS.md` pide justificar cualquier dependencia nueva, como Jupyter o pandas. Conviene decidir si esas dependencias van en un grupo aparte.
- La especificación debe recoger la ventana de la media móvil y la definición de tendencia semanal usadas por el notebook original. Si la adaptación completa resulta demasiado grande para un solo cambio, habrá que revisar el alcance antes de implementarla.
