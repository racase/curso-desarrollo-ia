# Proposal

## Why

El resumen diario permite consultar cifras, pero durante el curso resulta más fácil reconocer días incompletos y explorar la evolución de un sensor en gráficos dentro de VS Code.

## What Changes

- Añadir un cuaderno Python con celdas `# %%`, ejecutable en la ventana interactiva de VS Code.
- Mostrar un mapa de completitud desde el resumen diario precalculado y una serie interactiva del sensor elegido.
- Añadir dependencias opcionales, instrucciones de uso y pruebas de preparación de datos.

## Capabilities

### New Capabilities

- `visual-exploration`: mapa de completitud y serie temporal seleccionable.

### Modified Capabilities

Ninguna.

## Impact

Solo afecta a `datos/`. Se añaden `ipykernel` (ejecución de celdas), `ipywidgets` (selector) y `plotly` (gráficos y zoom) en el grupo opcional `notebook`. No cambia el esquema ni los datos de ejemplo.
