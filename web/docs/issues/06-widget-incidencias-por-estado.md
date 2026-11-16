---
title: Widget de incidencias por estado
labels: [feature, web]
depends_on: [1]
---

## Resumen

Mostrar en el panel un widget con el número de incidencias abiertas, agrupadas por estado, para facilitar una lectura rápida de la situación.

## Problema y oportunidad

El listado de incidencias permite consultar casos concretos, pero obliga a revisar o filtrar información para conocer el volumen pendiente. Un recuento por estado facilita una primera orientación al entrar al panel.

## Objetivos

- Dar una visión rápida del volumen de incidencias abiertas.
- Distinguir cómo se distribuye ese volumen entre los estados pertinentes.
- Mantener la misma visibilidad por usuario que en el listado.

## Usuarios afectados

- Solicitantes: consultar el resumen de sus propias incidencias.
- Técnicos y administradores: consultar el resumen de todas las incidencias.

## Alcance

- Widget en el panel con recuentos agrupados por estado.
- Recuentos basados en las incidencias del issue 1.
- Presentación comprensible cuando no haya incidencias.

## Fuera de alcance

- Tendencias históricas, tiempos de resolución y comparaciones entre periodos.
- Informes exportables y métricas por técnico, prioridad o sede.
- Nuevas operaciones sobre incidencias desde el widget.

## Requisitos de producto

- El widget identifica el estado al que corresponde cada recuento.
- Solo cuenta incidencias visibles para el usuario: las propias para solicitantes y todas para técnicos y administradores.
- Los recuentos son coherentes con el conjunto de incidencias y estados incluidos en el resumen.
- La ausencia de incidencias se representa sin ambigüedad.

## Criterios de éxito

- El usuario reconoce el volumen y la distribución por estado de las incidencias incluidas de un vistazo.
- El resumen coincide con la información accesible en el listado.
- Los recuentos no revelan incidencias ajenas a un solicitante.

## Riesgos y preguntas abiertas

- ¿«Abiertas» significa únicamente el estado abierta o todas las incidencias todavía no cerradas? Debe aclararse antes de decidir qué estados mostrar.
- ¿Las resueltas siguen formando parte del trabajo pendiente hasta su cierre?
- ¿Cuándo deben actualizarse los recuentos para que el resumen sea suficientemente reciente?
