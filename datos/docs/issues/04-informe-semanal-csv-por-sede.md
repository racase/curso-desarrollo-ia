---
title: Informe semanal CSV por sede
labels: [feature, datos]
depends_on: [1]
---

## Contexto
El resumen diario permite revisar sensores, pero los responsables de sede necesitan compartir una semana completa en un fichero. Un CSV compatible con Excel en español facilita esa revisión fuera del proyecto.

## Historia de usuario
Como responsable de sede, quiero exportar el resumen semanal de mis sensores a CSV, para abrirlo y compartirlo en Excel.

Como analista de datos, quiero un formato de exportación estable, para verificar y reutilizar el informe.

## Alcance
- Exportar a CSV el resumen de cada sensor de una sede para una semana solicitada.
- Incluir los resultados del resumen diario del issue 1 correspondientes a esa sede y semana.
- Fijar y documentar columnas, separador, representación decimal y codificación del fichero.
- Producir un fichero que se abra correctamente en Excel en español.

## Fuera de alcance
- Exportar varias sedes o varias semanas en un solo fichero.
- Crear hojas de cálculo o gráficos.
- Añadir métricas distintas de las del resumen diario.

## Criterios de aceptación
1. Dada una sede y una semana del conjunto de datos, cuando se exporta el informe, entonces el fichero incluye únicamente los resúmenes de los sensores de esa sede y los días de esa semana.
2. Dado el fichero exportado, cuando se comprueba su contenido, entonces las columnas, el separador, los decimales y la codificación coinciden con el formato fijado en la especificación.
3. Dado el fichero exportado, cuando se abre en Excel en español, entonces las columnas y los valores decimales se reconocen correctamente.
4. Dado un resumen diario del informe, cuando se compara con el resultado del issue 1 para el mismo sensor y día UTC, entonces sus valores coinciden.

## Notas
- Excel en español espera punto y coma como separador y coma decimal.
- La especificación debe fijar el inicio y el fin de la semana, los encabezados y la forma de representar días sin lecturas.
