# Design

## Datos del mapa

`course_completion` consulta la lista completa de sensores y llama a `daily_summaries`, que lee el agregado continuo del issue 1. `completion_grid` construye los 91 días UTC del conjunto y las 12 filas, con 0 % cuando falta un resumen. Cada porcentaje es el recuento dividido por 288 lecturas esperadas, multiplicado por 100. El valor exacto aparece al pasar el cursor, pues 91 etiquetas visibles no caben en el ancho de un gráfico.

## Serie y ejecución

El cuaderno `.py` usa celdas `# %%` para VS Code. Un selector `ipywidgets` ofrece los sensores existentes y, al cambiarlo, una consulta SQL parametrizada obtiene solo la serie de ese sensor dentro del intervalo del curso. `Scattergl` permite ampliar mediante el zoom de Plotly. Cada consulta abre y cierra su propia conexión para que el selector siga funcionando tras ejecutar todas las celdas.

## Verificación

Pruebas unitarias cubren calendario, ceros y consulta parametrizada. Pruebas de integración comprueban el día parcial y el día totalmente vacío del conjunto fijo, y que todos los sensores tengan serie. La visualización en VS Code requiere una comprobación manual con las extensiones instaladas.
