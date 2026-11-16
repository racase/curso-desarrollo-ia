---
title: Sedes y activos
labels: [feature, web]
depends_on: [1]
---

## Resumen

Incorporar sedes y activos para contextualizar cada incidencia mediante su vinculación a un activo.

## Problema y oportunidad

Una incidencia descrita únicamente con texto puede dejar dudas sobre el elemento afectado y su ubicación. Identificar el activo y su sede ayuda a comprender dónde se requiere atención.

## Objetivos

- Identificar el activo al que corresponde cada incidencia.
- Situar los activos en el contexto de una sede.
- Reducir la ambigüedad al registrar y consultar problemas.

## Usuarios afectados

- Solicitantes: identificar el activo afectado al comunicar un problema.
- Técnicos y administradores: consultar el activo y su sede para contextualizar la atención.
- Los permisos para mantener sedes y activos quedan pendientes de definición.

## Alcance

- Incorporación de sedes y activos a InfraTicket.
- Relación entre activos y sedes.
- Vinculación de cada incidencia a un activo.
- Adaptación del alta y la consulta de incidencias del issue 1 para mostrar este contexto.

## Fuera de alcance

- Mantenimiento preventivo y gestión de compras.
- Descubrimiento automático de activos.
- Nuevas reglas de visibilidad por sede.

## Requisitos de producto

- Las sedes y los activos deben poder distinguirse de forma comprensible para los usuarios.
- Debe poder identificarse la sede de un activo.
- El alta de incidencias permite vincular el activo afectado y la consulta muestra esa vinculación.
- La nueva relación conserva las restricciones de acceso del issue 1.
- Debe acordarse cómo adaptar las incidencias existentes que todavía no tengan activo asociado.

## Criterios de éxito

- Los usuarios identifican el elemento afectado sin depender exclusivamente de la descripción libre.
- El equipo puede reconocer el contexto de sede de una incidencia.
- La incorporación de activos permite seguir consultando las incidencias existentes.

## Riesgos y preguntas abiertas

- ¿Qué información mínima distingue una sede y un activo?
- ¿Quién puede mantener sedes y activos y qué operaciones necesita?
- ¿La vinculación es obligatoria en todas las incidencias? ¿Cómo se tratan las anteriores?
- ¿Qué ocurre si un activo cambia de sede o deja de estar disponible?
- El alcance puede crecer hacia un inventario completo; antes de implementar conviene concretar el mantenimiento mínimo necesario.
