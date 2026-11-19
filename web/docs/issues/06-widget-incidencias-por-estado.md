---
title: Incidencias por estado
labels: [feature, web]
depends_on: [1]
---

## Resumen
Mostrar en el panel un widget con el número de incidencias abiertas por estado para ofrecer una visión rápida de la atención pendiente.

## Problema y oportunidad
El listado permite consultar incidencias individuales, pero obliga a revisar el detalle para hacerse una idea del volumen pendiente. Un resumen por estado facilita reconocer la situación general de un vistazo.

## Objetivos
- Facilitar la lectura rápida del volumen de incidencias abiertas y su distribución por estado.
- Mantener esa visión alineada con las incidencias que cada usuario puede consultar.

## Usuarios afectados
- Solicitantes: conocer la situación conjunta de sus propias incidencias.
- Técnicos y administradores: conocer el volumen global de incidencias por atender.

## Alcance
- Widget en el panel con recuentos de incidencias abiertas agrupados por estado.
- Identificación clara del estado al que corresponde cada cifra.

## Fuera de alcance
- Gráficos de evolución, comparaciones temporales y métricas adicionales.
- Informes, exportaciones y nuevas acciones sobre las incidencias.

## Requisitos de producto
- Los recuentos utilizan los estados definidos en el issue 1.
- El widget respeta la misma visibilidad: los solicitantes ven cifras de sus incidencias; técnicos y administradores, del conjunto.
- Las cifras deben corresponder al mismo universo de incidencias que representa el resumen.
- El significado de «incidencias abiertas» debe concretarse antes de implementar para que las cifras sean interpretables.

## Criterios de éxito
- Los usuarios pueden reconocer de un vistazo el volumen pendiente y su distribución por estado.
- Las cifras son comprensibles y coherentes con las incidencias accesibles para cada rol.

## Riesgos y preguntas abiertas
- «Abierta» ya es un estado: ¿«abiertas por estado» incluye todas las no cerradas, solo abierta y en curso u otro conjunto de los estados existentes?
- ¿Deben aparecer los estados sin incidencias con valor cero?
- ¿Con qué actualización de las cifras se considera útil el resumen?
