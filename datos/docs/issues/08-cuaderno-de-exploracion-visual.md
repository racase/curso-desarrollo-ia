---
title: Cuaderno de exploración visual
labels: [feature, datos]
depends_on: [1]
---

## Contexto
Los resúmenes diarios permiten consultar cifras, pero algunos participantes prefieren explorar visualmente los días incompletos y la evolución de cada sensor. Este cuaderno es una ampliación opcional que se ejecuta en VS Code, fuera del recorrido principal del curso.

## Historia de usuario
Como analista de datos, quiero explorar la completitud diaria y la serie temporal de cualquier sensor, para reconocer patrones y periodos que merecen revisión.

Como responsable de mantenimiento, quiero ampliar la serie de un sensor, para examinar sus lecturas en un intervalo concreto.

## Alcance
- Un cuaderno guardado como fichero de Python con celdas, que se abre y ejecuta celda a celda en VS Code y muestra los gráficos dentro del editor.
- Un mapa de calor de completitud con un sensor por fila y un día UTC por columna para el periodo del conjunto de datos. Cada celda muestra el porcentaje de lecturas recibidas respecto de las esperadas.
- El cálculo de completitud utiliza el resumen diario del issue 1, sin volver a calcularlo a partir de todas las lecturas.
- Una serie temporal interactiva que permite elegir cualquiera de los sensores y ampliar una zona mediante zoom.

## Fuera de alcance
- Detectar o superponer huecos y valores anómalos; los issues 2 y 3 los tratan por separado.
- Añadir una aplicación web o una API.
- Calcular la media móvil o la tendencia semanal del otro notebook del equipo.

## Criterios de aceptación
1. Dado el proyecto preparado en VS Code, cuando se abre el fichero de Python con celdas y se ejecuta entero, entonces todas las celdas terminan sin errores y los dos gráficos aparecen dentro del editor.
2. Dados los sensores y días del conjunto fijo del curso, cuando se observa el mapa de calor, entonces aparece una fila por sensor y una columna por día UTC, y cada celda muestra el porcentaje de lecturas recibidas frente a las esperadas.
3. Dado cualquiera de los sensores del curso, cuando se selecciona en el cuaderno y se amplía una zona de su serie temporal, entonces el gráfico permite ver las lecturas del sensor en esa zona con el zoom aplicado.

## Notas
- Actividad opcional: no es requisito para completar el parte de incidencias del issue 7.
- `AGENTS.md` pide justificar cualquier dependencia nueva. Conviene colocar las dependencias del cuaderno en un grupo aparte para no cargarlas al resto del proyecto.
- Para abrir y ejecutar el cuaderno en VS Code hacen falta las extensiones de Python y Jupyter.
- La especificación debe fijar cómo se presentan los días sin lecturas y el porcentaje de completitud, manteniendo los días en UTC y la cadencia esperada de una lectura cada cinco minutos.
