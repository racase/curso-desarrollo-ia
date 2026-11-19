---
title: Asignación y seguimiento
labels: [feature, web]
depends_on: [1]
---

## Resumen
Permitir asignar incidencias a técnicos y seguir su evolución mediante cambios de estado válidos y un historial visible.

## Problema y oportunidad
Una vez registradas las incidencias, hace falta conocer quién las atiende y cómo avanzan. Sin esa información, el equipo pierde claridad sobre la responsabilidad y los solicitantes sobre la situación de su problema.

## Objetivos
- Hacer visible el técnico encargado de atender una incidencia.
- Mantener una evolución coherente hasta la resolución y el cierre.
- Conservar una referencia comprensible de los cambios de estado.

## Usuarios afectados
- Técnicos: conocer la asignación y reflejar el avance de la atención.
- Administradores: consultar la responsabilidad y evolución de las incidencias.
- Solicitantes: conocer el progreso de sus solicitudes.

## Alcance
- Asignación de una incidencia a un técnico.
- Cambios entre los estados definidos en el issue 1 sujetos a transiciones válidas.
- Historial de cambios de estado visible en la ficha.

## Fuera de alcance
- Asignación automática y reparto de carga.
- Historial de cambios distintos del estado.
- Nuevos estados o prioridades.

## Requisitos de producto
- El usuario asignado debe tener el rol de técnico.
- Solo se permiten transiciones de estado consideradas válidas para el ciclo de atención.
- Una incidencia no puede cerrarse sin haber sido resuelta.
- Los cambios de estado deben quedar reflejados en el historial de la ficha.
- La consulta de la ficha y del historial respeta la visibilidad del issue 1.

## Criterios de éxito
- El equipo puede identificar al técnico asignado y entender el avance de una incidencia.
- El historial permite reconstruir su evolución de estados.
- El cierre conserva la distinción entre resolver un problema y darlo por cerrado.

## Riesgos y preguntas abiertas
- ¿Qué roles pueden asignar técnicos y realizar cada cambio de estado?
- ¿Cuál es el mapa completo de transiciones válidas? ¿Se permite reabrir o retroceder?
- ¿Puede una incidencia permanecer sin asignación o cambiar de técnico?
- ¿Qué información debe acompañar a cada cambio en el historial?
