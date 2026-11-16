---
title: Alta de incidencias
labels: [feature, web]
depends_on: []
---

## Resumen

Permitir registrar y consultar incidencias en el panel de InfraTicket, con una visión adaptada al rol del usuario.

## Problema y oportunidad

El panel actual permite iniciar sesión, pero no registrar necesidades de soporte. Solicitantes y personal técnico necesitan un lugar común donde identificar los problemas y su situación.

## Objetivos

- Centralizar la información básica de las incidencias.
- Facilitar la consulta por estado y prioridad.
- Limitar la información de cada solicitante a sus propias incidencias.

## Usuarios afectados

- Solicitantes: registrar problemas y consultar sus incidencias.
- Técnicos y administradores: consultar todas las incidencias para organizar la atención.

## Alcance

- Alta mediante un formulario en el panel.
- Incidencias con título, descripción, estado, prioridad y solicitante.
- Listado con filtros por estado y prioridad.
- Visibilidad según el rol del usuario.

## Fuera de alcance

- Asignación a técnicos e historial de estados.
- Comentarios, sedes, activos y widget de recuento.
- Gestión de usuarios.

## Requisitos de producto

- Los estados disponibles son abierta, en curso, resuelta y cerrada.
- Las prioridades disponibles son baja, media, alta y crítica.
- Cada incidencia identifica a su solicitante.
- Un solicitante solo puede consultar sus propias incidencias, también al acceder directamente a una ficha.
- Técnicos y administradores pueden consultar todas las incidencias.
- Los filtros permiten acotar el listado por estado y prioridad.

## Criterios de éxito

- Los usuarios pueden registrar problemas con información suficiente para identificarlos.
- El equipo puede localizar las incidencias de un estado o prioridad concretos.
- Los solicitantes acceden únicamente a sus incidencias.

## Riesgos y preguntas abiertas

- ¿Qué campos son obligatorios y qué límites de contenido necesitan?
- ¿El alta establece siempre el estado abierta y qué prioridad se propone inicialmente?
- ¿Técnicos y administradores pueden registrar incidencias en nombre de otra persona?
- ¿Quién puede modificar la información después del alta? Las transiciones de estado se concretarán en el issue 2.
