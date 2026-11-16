---
title: Asignación y seguimiento
labels: [feature, web]
depends_on: [1]
---

## Resumen

Asignar incidencias a técnicos y seguir su evolución mediante cambios de estado válidos y un historial visible en la ficha.

## Problema y oportunidad

Una incidencia registrada necesita un responsable de atención y una evolución comprensible. Sin asignación ni trazabilidad, el equipo puede duplicar esfuerzos y los usuarios desconocen qué ha ocurrido.

## Objetivos

- Identificar al técnico responsable de cada incidencia asignada.
- Mantener una evolución coherente hasta su resolución y cierre.
- Permitir consultar los cambios de estado anteriores.

## Usuarios afectados

- Técnicos: identificar su responsabilidad y actualizar el seguimiento.
- Administradores: coordinar la atención y consultar la evolución.
- Solicitantes: conocer el estado y el recorrido de sus incidencias.

## Alcance

- Asignación de una incidencia a un técnico.
- Cambios de estado sujetos a transiciones válidas.
- Historial de cambios de estado visible en la ficha.
- Aplicación sobre las incidencias del issue 1, manteniendo sus restricciones de visibilidad.

## Fuera de alcance

- Asignación automática o a varios técnicos simultáneamente.
- Comentarios y gestión de usuarios.
- Métricas de atención y notificaciones.

## Requisitos de producto

- El responsable asignado debe tener el rol técnico.
- La ficha permite identificar al técnico asignado, si lo hay.
- Los cambios respetan las transiciones que se acuerden entre abierta, en curso, resuelta y cerrada.
- Una incidencia no puede cerrarse sin pasar por resuelta.
- Los cambios de estado quedan reflejados en un historial consultable desde la ficha.

## Criterios de éxito

- El equipo identifica quién atiende cada incidencia asignada.
- Los usuarios con acceso a la ficha pueden comprender su evolución.
- El seguimiento impide cierres que omitan la resolución.

## Riesgos y preguntas abiertas

- ¿Quién puede asignar, reasignar y cambiar estados?
- ¿Qué transiciones se permiten y se admite reabrir una incidencia?
- ¿Es obligatorio asignar un técnico antes de pasar a en curso?
- ¿Qué información acompaña a cada cambio en el historial?
