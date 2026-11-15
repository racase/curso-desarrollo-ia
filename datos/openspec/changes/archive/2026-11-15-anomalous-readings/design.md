# Design

## Context

Los sensores tienen tipos y unidades conocidos. No existe información sobre si una instalación estaba parada; solo se pueden aplicar reglas de calidad de datos.

## Goals / Non-Goals

**Goals:** aplicar las tres reglas de forma reproducible sin cargar todas las lecturas en memoria.

**Non-Goals:** diagnosticar fallos físicos o corregir valores.

## Decisions

- Presión de 0 bar está dentro del rango posible. Podrá señalarse por persistencia o salto, pero no se afirmará que sea imposible sin contexto operativo.
- La persistencia compara valores exactos y se reinicia al cambiar el valor o al superar la cadencia de cinco minutos. El tramo mínimo son cuatro lecturas consecutivas.
- Los saltos se atribuyen a la lectura nueva. Se comparan solo lecturas separadas cinco minutos para no confundir un periodo sin datos con un cambio brusco.
- Las funciones de ventana y la clasificación se ejecutan en SQL; Python recibe solo las lecturas señaladas. Los límites se pasan como parámetros.

## Risks / Trade-offs

- Los límites son reglas iniciales del curso y pueden producir falsos positivos → mantenerlos explícitos en la especificación y mostrar el motivo, sin diagnóstico automático.
