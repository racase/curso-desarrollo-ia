---
title: Sedes y activos
labels: [feature, web]
depends_on: [1]
---

## Resumen
Incorporar sedes y activos para vincular cada incidencia con el activo afectado y conocer su ubicación.

## Problema y oportunidad
La información de una incidencia necesita situar el problema en un activo concreto. Un conjunto común de sedes y activos evita referencias ambiguas y ayuda al equipo a entender dónde debe intervenir.

## Objetivos
- Identificar el activo afectado y la sede a la que pertenece.
- Dar un contexto compartido a solicitantes, técnicos y administradores.

## Usuarios afectados
- Solicitantes: indicar el activo al registrar una incidencia.
- Técnicos y administradores: reconocer el activo y su sede al consultar el problema.

## Alcance
- Un conjunto de exactamente 6 sedes con 5 activos por sede: 30 activos en total.
- Vinculación de cada incidencia a un activo.
- Adaptación del alta y la consulta de incidencias del issue 1 para incluir ese vínculo y su contexto de sede.

## Fuera de alcance
- Gestión completa de inventario, mantenimiento o ciclo de vida de los activos.
- Ampliación del número de sedes o activos.
- Nuevas restricciones de acceso por sede.

## Requisitos de producto
- Deben existir exactamente 6 sedes y exactamente 5 activos asociados a cada una, sumando 30 activos.
- Cada activo pertenece a una sede y debe poder distinguirse de los demás.
- Cada incidencia queda vinculada a un activo; su sede se reconoce a partir de ese activo.
- Esta capacidad modifica el alta y la consulta del issue 1 y mantiene su visibilidad por rol.

## Criterios de éxito
- Los usuarios pueden identificar sin ambigüedad el activo y la sede de una incidencia.
- Las incidencias incorporan ese contexto sobre un conjunto de 6 sedes y 30 activos, con 5 por sede.

## Riesgos y preguntas abiertas
- ¿Cuáles son los nombres de las sedes y los activos y qué información permite distinguirlos?
- ¿Cómo se vincularán a un activo las incidencias creadas antes de incorporar esta capacidad?
- ¿Quién podrá corregir el activo vinculado a una incidencia y en qué circunstancias?
