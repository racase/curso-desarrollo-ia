---
title: Gestión de usuarios
labels: [feature, web]
depends_on: []
---

## Resumen
Dar a los administradores la capacidad de gestionar usuarios y sus roles desde el panel, partiendo del acceso y los usuarios de prueba ya existentes.

## Problema y oportunidad
Actualmente no hay gestión de usuarios en el panel. La administración necesita incorporar personas y mantener sus datos y roles sin depender de los usuarios de prueba iniciales.

## Objetivos
- Facilitar el mantenimiento de los usuarios desde el panel.
- Mantener la asignación de roles bajo control de los administradores.

## Usuarios afectados
- Administradores: consultar, crear, editar y eliminar usuarios y asignarles un rol.
- Técnicos y solicitantes: disponer de una cuenta con el rol adecuado, sin acceso a la gestión de usuarios.

## Alcance
- Listado, alta, edición y eliminación de usuarios en el panel.
- Asignación de los roles administrador, técnico o solicitante.
- Acceso exclusivo para administradores a estas operaciones.

## Fuera de alcance
- Registro público, invitaciones e importaciones masivas.
- Nuevos roles o permisos configurables.
- Cambios en el inicio de sesión existente.

## Requisitos de producto
- Solo los administradores pueden acceder a la gestión de usuarios y realizar sus operaciones.
- Cada usuario tiene uno de los tres roles existentes: administrador, técnico o solicitante.
- El administrador puede consultar el listado, dar de alta usuarios, editar sus datos y rol y eliminarlos.
- Esta capacidad no depende de la existencia de incidencias.

## Criterios de éxito
- Un administrador puede mantener los usuarios y sus roles desde el panel.
- Técnicos y solicitantes no pueden acceder a estas operaciones.

## Riesgos y preguntas abiertas
- ¿Qué datos y credenciales debe proporcionar el administrador al crear o editar una cuenta?
- ¿Qué restricciones deben aplicarse a la eliminación propia o del último administrador?
- Cuando existan incidencias o comentarios, ¿cómo se tratará la eliminación de usuarios vinculados a ellos?
