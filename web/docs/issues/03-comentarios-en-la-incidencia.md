---
title: Comentarios en la incidencia
labels: [feature, web]
depends_on: [1]
---

## Resumen

Permitir que los usuarios añadan comentarios en la ficha de una incidencia, identificando su autor y fecha.

## Problema y oportunidad

La descripción inicial de un problema puede necesitar aclaraciones. Reunir la conversación en la incidencia ayuda a conservar el contexto para solicitantes y personal de soporte.

## Objetivos

- Mantener las aclaraciones junto a la incidencia correspondiente.
- Hacer reconocible quién aportó cada comentario y cuándo.
- Facilitar el intercambio de información entre los usuarios con acceso.

## Usuarios afectados

- Solicitantes: aportar aclaraciones y consultar respuestas en sus incidencias.
- Técnicos y administradores: añadir y consultar información durante la atención.

## Alcance

- Alta y consulta de comentarios desde la ficha.
- Visualización del autor y la fecha de cada comentario.
- Uso sobre las incidencias del issue 1, sin requerir asignación previa.

## Fuera de alcance

- Adjuntos, menciones y notificaciones.
- Conversaciones separadas o comentarios privados.
- Cambios de estado mediante comentarios.

## Requisitos de producto

- Cada comentario pertenece a una incidencia e identifica a su autor y fecha.
- Solo los usuarios con acceso a la incidencia pueden consultar o añadir comentarios en ella.
- Los comentarios pueden leerse desde la ficha en un orden comprensible.
- Añadir comentarios mantiene las restricciones de visibilidad definidas en el issue 1.

## Criterios de éxito

- La ficha reúne las aclaraciones necesarias para comprender la conversación.
- Los participantes distinguen las aportaciones por autor y fecha.
- Los comentarios no exponen información de incidencias ajenas a un solicitante.

## Riesgos y preguntas abiertas

- ¿Se permite editar o eliminar comentarios y con qué permisos?
- ¿Qué longitud y formato admite el contenido?
- ¿Qué orden de lectura resulta más útil para los participantes?
- ¿Cómo se identifica al autor si posteriormente se elimina su usuario?
