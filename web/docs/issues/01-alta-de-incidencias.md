---
title: Alta de incidencias
labels: [feature, web]
depends_on: []
---

## Resumen
Permitir registrar y consultar incidencias en el panel de InfraTicket, con una visión de las solicitudes acorde a cada rol.

## Problema y oportunidad
El panel actual permite iniciar sesión, pero no comunicar ni consultar incidencias. Solicitantes y personal técnico necesitan un lugar común para conocer qué problemas requieren atención.

## Objetivos
- Facilitar la comunicación de problemas con información suficiente para atenderlos.
- Permitir localizar incidencias según su situación y prioridad.
- Mantener la visibilidad de cada solicitante limitada a sus propias incidencias.

## Usuarios afectados
- Solicitantes: registrar y consultar sus incidencias.
- Técnicos y administradores: consultar el conjunto de incidencias para conocer la demanda.

## Alcance
- Formulario de alta de incidencias en el panel.
- Listado con título, descripción, estado, prioridad y solicitante disponibles para consulta.
- Filtros por estado y prioridad.
- Visibilidad diferenciada según el rol existente.

## Fuera de alcance
- Asignación, transiciones de estado e historial, tratados en el issue 2.
- Comentarios, sedes, activos y widget, tratados en otros issues.

## Requisitos de producto
- Cada incidencia debe contar con título, descripción, estado, prioridad y solicitante identificado.
- Los estados son abierta, en curso, resuelta y cerrada.
- Las prioridades son baja, media, alta y crítica.
- Un solicitante solo puede ver sus incidencias; técnicos y administradores pueden ver todas.
- Los filtros deben operar sobre las incidencias que el usuario tiene permitido consultar.

## Criterios de éxito
- Los solicitantes pueden registrar problemas y encontrar sus solicitudes en el panel.
- Técnicos y administradores pueden localizar las incidencias relevantes por estado y prioridad.
- La consulta respeta la separación entre solicitantes.

## Riesgos y preguntas abiertas
- ¿Qué información mínima hace útil el título y la descripción?
- ¿Qué estado y prioridad se proponen al crear una incidencia y quién decide la prioridad?
- ¿Técnicos y administradores pueden dar de alta incidencias en nombre de un solicitante?
